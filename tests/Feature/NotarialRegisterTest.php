<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\NotarialEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotarialRegisterTest extends TestCase
{
    use RefreshDatabase;

    private function entry(array $overrides = []): array
    {
        return ['doc_no' => 45, 'page_no' => 9, 'book_no' => 'iii', 'series' => 2026, ...$overrides];
    }

    public function test_an_entry_can_be_added_once_the_document_is_approved_and_does_not_change_its_status(): void
    {
        $staff = User::factory()->create();
        $document = Document::factory()->create(['status' => DocumentStatus::Approved]);

        $this->actingAs($staff)->post(route('notarial.store', $document), $this->entry())->assertSessionHas('success');

        $entry = NotarialEntry::firstOrFail();
        $this->assertSame('III', $entry->book_no, 'book number is stored in capitals');
        $this->assertSame('Doc. No. 45; Page No. 9; Book No. III; Series of 2026', $document->fresh()->notarial_reference);
        $this->assertSame(DocumentStatus::Approved, $document->fresh()->status);
    }

    public function test_an_entry_cannot_be_added_before_approval(): void
    {
        $staff = User::factory()->create();
        $document = Document::factory()->create(['status' => DocumentStatus::ForReview]);

        $this->actingAs($staff)->post(route('notarial.store', $document), $this->entry())->assertSessionHas('error');
        $this->assertDatabaseCount('notarial_entries', 0);
    }

    public function test_the_same_doc_no_cannot_repeat_in_a_book_and_series(): void
    {
        $staff = User::factory()->create();
        $first = Document::factory()->create(['status' => DocumentStatus::Approved]);
        $second = Document::factory()->create(['status' => DocumentStatus::Approved]);

        $this->actingAs($staff)->post(route('notarial.store', $first), $this->entry());
        $this->actingAs($staff)->post(route('notarial.store', $second), $this->entry())->assertSessionHasErrors('doc_no');

        $this->actingAs($staff)->post(route('notarial.store', $second), $this->entry(['series' => 2025]))->assertSessionHasNoErrors();
    }

    public function test_only_administrators_can_correct_an_entry(): void
    {
        $staff = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $document = Document::factory()->create(['status' => DocumentStatus::Approved]);
        $this->actingAs($staff)->post(route('notarial.store', $document), $this->entry());
        $entry = NotarialEntry::firstOrFail();

        $this->actingAs($staff)->put(route('notarial.update', $entry), $this->entry(['page_no' => 10]))->assertForbidden();
        $this->actingAs($admin)->put(route('notarial.update', $entry), $this->entry(['page_no' => 10]))->assertSessionHasNoErrors();

        $this->assertSame(10, $entry->fresh()->page_no);
    }

    public function test_the_register_lists_and_finds_entries(): void
    {
        $staff = User::factory()->create();
        $document = Document::factory()->create(['status' => DocumentStatus::Approved]);
        $this->actingAs($staff)->post(route('notarial.store', $document), $this->entry());

        $this->actingAs($staff)->get(route('notarial.index', ['doc_no' => 45, 'series' => 2026]))
            ->assertOk()
            ->assertSee($document->tracking_code);

        $this->actingAs($staff)->get(route('documents.index', ['n_doc' => 45]))
            ->assertOk()
            ->assertSee($document->tracking_code);
    }
}
