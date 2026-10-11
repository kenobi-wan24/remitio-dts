<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow v2:
 *  - old statuses → new statuses (in_review → for_review, filed → finalized)
 *  - "received by" on releases
 *  - due date removed (the firm doesn't work with due dates)
 */
return new class extends Migration
{
    private const MAP = ['in_review' => 'for_review', 'filed' => 'finalized'];

    public function up(): void
    {
        $this->remap(self::MAP);

        Schema::table('document_movements', function (Blueprint $table) {
            $table->string('received_by')->nullable()->after('remarks');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['due_date']);
            $table->dropColumn('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('date_received');
            $table->index('due_date');
        });

        Schema::table('document_movements', function (Blueprint $table) {
            $table->dropColumn('received_by');
        });

        // New statuses fall back to the closest old one
        $this->remap([
            'for_review' => 'in_review', 'for_drafting' => 'in_review', 'revision_required' => 'in_review',
            'approved' => 'for_signature', 'signed' => 'for_signature', 'finalized' => 'filed',
        ]);
    }

    private function remap(array $map): void
    {
        foreach ($map as $old => $new) {
            DB::table('documents')->where('status', $old)->update(['status' => $new]);
            DB::table('document_movements')->where('from_status', $old)->update(['from_status' => $new]);
            DB::table('document_movements')->where('to_status', $old)->update(['to_status' => $new]);
        }
    }
};
