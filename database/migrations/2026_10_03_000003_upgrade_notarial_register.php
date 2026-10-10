<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 11C: the Notarial Register follows the register book's columns
 * (Rules on Notarial Practice): which notary, date notarized, type of act,
 * principals with evidence of identity, witnesses — plus the notary's commission details.
 * Principals and witnesses are COPIED into the entry so the register keeps what was recorded that day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('notarial_commission_no', 60)->nullable()->after('is_active');
            $table->date('commission_expires_on')->nullable()->after('notarial_commission_no');
            $table->string('notary_office_address')->nullable()->after('commission_expires_on');
        });

        Schema::table('notarial_entries', function (Blueprint $table) {
            $table->foreignId('notary_id')->nullable()->after('document_id')->constrained('users')->nullOnDelete();
            $table->date('notarized_on')->nullable()->after('series');
            $table->string('act_type', 30)->nullable()->after('notarized_on');
            $table->json('principals')->nullable()->after('act_type');
            $table->json('witnesses')->nullable()->after('principals');
        });

        // Existing entries: notary = whoever recorded it if a lawyer, else the first lawyer; date = date recorded
        $firstLawyer = DB::table('users')->where('role', 'admin')->orderBy('id')->value('id');
        DB::table('notarial_entries')->orderBy('id')->each(function ($entry) use ($firstLawyer) {
            $recorderIsLawyer = $entry->recorded_by
                && DB::table('users')->where('id', $entry->recorded_by)->where('role', 'admin')->exists();

            DB::table('notarial_entries')->where('id', $entry->id)->update([
                'notary_id' => $recorderIsLawyer ? $entry->recorded_by : $firstLawyer,
                'notarized_on' => substr((string) $entry->created_at, 0, 10),
            ]);
        });

        // Each notary keeps their own book: Doc. No. is unique per notary + series + book
        Schema::table('notarial_entries', function (Blueprint $table) {
            $table->dropUnique('notarial_entries_register_unique');
            $table->unique(['notary_id', 'series', 'book_no', 'doc_no'], 'notarial_entries_notary_register_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notarial_entries', function (Blueprint $table) {
            $table->dropUnique('notarial_entries_notary_register_unique');
            $table->unique(['series', 'book_no', 'doc_no'], 'notarial_entries_register_unique');
            $table->dropConstrainedForeignId('notary_id');
            $table->dropColumn(['notarized_on', 'act_type', 'principals', 'witnesses']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notarial_commission_no', 'commission_expires_on', 'notary_office_address']);
        });
    }
};
