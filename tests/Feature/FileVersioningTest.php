<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileVersioningTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj <<>> endobj\ntrailer <<>>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::PDF);
    }

    public function test_the_files_card_adds_separate_files_not_versions(): void
    {
        $staff = User::factory()->create();
        $document = Document::factory()->create();

        $this->actingAs($staff)->post(route('documents.attachments.store', $document), ['attachments' => [$this->pdf('deed.pdf')]])
            ->assertSessionHasNoErrors();
        $first = DocumentAttachment::firstOrFail();

        // even if someone sends "version_of", the Files card only adds a NEW file (v1 of its own group)
        $this->actingAs($staff)->post(route('documents.attachments.store', $document), [
            'attachments' => [$this->pdf('scanned-id.pdf')],
            'version_of' => $first->id,
        ])->assertSessionHasNoErrors();

        $second = DocumentAttachment::latest('id')->firstOrFail();
        $this->assertSame(1, $second->version);
        $this->assertNotSame($first->version_group_id, $second->version_group_id);
        Storage::disk('local')->assertExists($second->path);
        // (versions of the draft are tested in DocumentTrackingTest: Submit → v1, Resubmit → v2)
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        $staff = User::factory()->create();
        $document = Document::factory()->create();

        $this->actingAs($staff)->post(route('documents.attachments.store', $document), [
            'attachments' => [UploadedFile::fake()->create('virus.exe', 10)],
        ])->assertSessionHasErrors();

        $this->assertDatabaseCount('document_attachments', 0);
    }

    public function test_the_separate_mark_final_button_is_gone(): void
    {
        // Workflow v2: the lawyer marks the final version by approving the document
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('attachments.final'));
    }

    public function test_staff_cannot_delete_a_final_version_even_if_they_uploaded_it(): void
    {
        $staff = User::factory()->create();
        $document = Document::factory()->create();
        $this->actingAs($staff)->post(route('documents.attachments.store', $document), ['attachments' => [$this->pdf('deed.pdf')]]);
        $file = DocumentAttachment::firstOrFail();
        $file->update(['is_final' => true]); // as if the lawyer approved this version

        $this->actingAs($staff)->delete(route('attachments.destroy', $file))->assertForbidden();
        $this->assertDatabaseHas('document_attachments', ['id' => $file->id]);
    }

    public function test_files_cannot_be_downloaded_without_signing_in(): void
    {
        $staff = User::factory()->create();
        $document = Document::factory()->create();
        $this->actingAs($staff)->post(route('documents.attachments.store', $document), ['attachments' => [$this->pdf('deed.pdf')]]);
        $file = DocumentAttachment::firstOrFail();

        auth()->logout();
        $this->get(route('attachments.download', $file))->assertRedirect('/login');
    }
}
