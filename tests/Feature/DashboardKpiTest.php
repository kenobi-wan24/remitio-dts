<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Revision 1 (R1) — professor's critique items 1 and 3:
 * professional wording, no repeated sections, and KPIs that reconcile.
 */
class DashboardKpiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_numbers_reconcile_in_process_plus_completed_equals_all_documents(): void
    {
        $user = User::factory()->create();
        Document::factory(5)->create(['status' => DocumentStatus::ForDrafting]);
        Document::factory(3)->create(['status' => DocumentStatus::ForReview]);
        Document::factory(2)->create(['status' => DocumentStatus::Released]);
        Document::factory(1)->create(['status' => DocumentStatus::Archived]);
        Document::factory()->create(['status' => DocumentStatus::ForReview])->delete(); // removed entries are not counted

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $stats = $response->viewData('stats');
        $chart = $response->viewData('statusChart');

        $this->assertSame(8, $stats['in_process']);
        $this->assertSame(3, $stats['awaiting_review']);
        $this->assertSame($stats['in_process'], array_sum(array_column($chart['rows'], 'count')), 'the bars add up to the KPI');
        $this->assertSame(3, $chart['completed']);
        $this->assertSame(11, $chart['total']);
        $this->assertSame(Document::count(), $chart['in_process'] + $chart['completed']);
        $this->assertNotContains(DocumentStatus::Released, array_column($chart['rows'], 'status'), 'no separate Released bar');
        $this->assertNotContains(DocumentStatus::Archived, array_column($chart['rows'], 'status'), 'no separate Archived bar');
    }

    public function test_professional_wording_is_used_and_not_repeated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Waiting for Me')
            ->assertSee('Pending My Action')
            ->assertSee('My Action Queue')
            ->assertSee('Not yet released or archived');
    }

    public function test_the_action_queue_groups_my_documents_by_the_action_they_need(): void
    {
        $lawyer = User::factory()->admin()->create();
        $secretary = User::factory()->create();
        Document::factory(2)->create(['status' => DocumentStatus::ForReview, 'current_holder_id' => $lawyer->id]);
        Document::factory()->create(['status' => DocumentStatus::ForSignature, 'current_holder_id' => $lawyer->id]);
        Document::factory()->create(['status' => DocumentStatus::ForDrafting, 'current_holder_id' => $secretary->id]);
        Document::factory()->create(['status' => DocumentStatus::Released, 'current_holder_id' => $lawyer->id]); // completed: not a task

        $response = $this->actingAs($lawyer)->get(route('dashboard', ['queue' => 'action']))->assertOk();
        $queue = collect($response->viewData('actionQueue'));

        $this->assertSame(3, $response->viewData('stats')['pending_mine']);
        $this->assertSame(['Awaiting Review / Approval', 'Pending Signature'], $queue->pluck('label')->all());
        $this->assertSame([2, 1], $queue->pluck('count')->all());
        $response->assertDontSee('Drafting in Progress', false); // the secretary's work is not in the lawyer's queue
    }

    public function test_dashboard_links_open_matching_document_lists(): void
    {
        $user = User::factory()->create();
        Document::factory(4)->create(['status' => DocumentStatus::Approved, 'current_holder_id' => $user->id]);
        Document::factory(2)->create(['status' => DocumentStatus::Released]);

        $this->assertSame(4, $this->actingAs($user)->get(route('documents.index', ['show' => 'in_process']))->viewData('documents')->total());
        $this->assertSame(2, $this->actingAs($user)->get(route('documents.index', ['show' => 'completed']))->viewData('documents')->total());
        $this->assertSame(4, $this->actingAs($user)->get(route('documents.index', ['holder' => 'me', 'show' => 'in_process']))->viewData('documents')->total());
    }

    public function test_deleted_records_replace_the_trash_wording(): void
    {
        $admin = User::factory()->admin()->create();
        Document::factory()->create()->delete();

        $this->actingAs($admin)->get(route('documents.index'))->assertSee('Removed entries')->assertDontSee('>Trash<', false);
        $this->actingAs($admin)->get(route('documents.index', ['view' => 'deleted']))->assertOk()->assertSee('Removed Documents');
        $this->assertSame(1, $this->actingAs($admin)->get(route('documents.index', ['view' => 'trash']))->viewData('documents')->total(), 'old links still work');
    }
}
