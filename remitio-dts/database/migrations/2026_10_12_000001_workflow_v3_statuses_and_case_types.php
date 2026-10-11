<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Revision 2 — Phase 1.
 *
 *  1. Statuses: For Signature + Signed are merged into "Signature & Sealed" (signed_sealed),
 *     and the order becomes ... Approved → Finalized → Signature & Sealed → Released.
 *     - Documents that were For Signature, Signed or Finalized (old meaning: sealed & complete)
 *       become Signature & Sealed.
 *     - Old history rows that said for_signature / signed are updated too. History rows that
 *       say "finalized" are left alone (they are still true: the document was finalized).
 *  2. "Consultation" is not a case, so existing consultation cases become "Other".
 *
 * Labels only: received / for_drafting / for_review / revision_required keep their values.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('documents')
            ->whereIn('status', ['for_signature', 'signed', 'finalized'])
            ->update(['status' => 'signed_sealed']);

        foreach (['from_status', 'to_status'] as $column) {
            DB::table('document_movements')
                ->whereIn($column, ['for_signature', 'signed'])
                ->update([$column => 'signed_sealed']);
        }

        DB::table('legal_cases')->where('case_type', 'consultation')->update(['case_type' => 'other']);
    }

    public function down(): void
    {
        // Back to the old workflow: the new "Finalized" (awaiting signature) ≈ old For Signature,
        // and Signature & Sealed ≈ old Finalized (sealed and complete).
        DB::table('documents')->where('status', 'finalized')->update(['status' => 'for_signature']);
        DB::table('documents')->where('status', 'signed_sealed')->update(['status' => 'finalized']);

        foreach (['from_status', 'to_status'] as $column) {
            DB::table('document_movements')->where($column, 'finalized')->update([$column => 'for_signature']);
            DB::table('document_movements')->where($column, 'signed_sealed')->update([$column => 'finalized']);
        }
        // Consultation cases that became "Other" cannot be told apart again.
    }
};
