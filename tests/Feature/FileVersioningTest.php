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

    public function test_a_new_upload_can_become_the_next_version_of_a_file(): void
    {
        $staff = User::factory()->create();
        $document = Document::factory()->create();

        $this->actingAs($staff)->post(route('documents.attachments.store', $document), ['attachments' => [$this->pdf('deed.pdf')]])
            ->assertSessionHasNoErrors();
        $v1 = DocumentAttachment::firstOrFail();

        $this->actingAs($staff)->post(route('documents.attachments.store', $document), [
            'attachments' => [$this->pdf('deed-corrected.pdf')],
            'version_of' => $v1->id,
            'version_notes' => 'Corrected page 1',
        ])->assertSessionHasNoErrors();

        $v2 = DocumentAttachment::latest('id')->firstOrFail();
        $this->assertSame(2, $v2->version);
        $this->assertSame($v1->version_group_id, $v2->version_group_id);
        Storage::disk('local')->assertExists($v2->path);
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
