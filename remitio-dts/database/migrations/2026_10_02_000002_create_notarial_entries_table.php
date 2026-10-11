<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Notarial Register becomes its own record, linked to the notarized document.
 * Same four fields as before (Doc. No., Page No., Book No., Series); the firm's
 * physical register book is the reference for any fields added later.
 * Existing notarial details on documents are moved into entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notarial_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('doc_no');
            $table->unsignedInteger('page_no');
            $table->string('book_no', 10);
            $table->unsignedSmallInteger('series');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['series', 'book_no', 'doc_no'], 'notarial_entries_register_unique');
            $table->index(['series', 'book_no', 'page_no']);
        });

        DB::table('documents')
            ->whereNotNull('notarial_doc_no')
            ->whereNotNull('notarial_series')
            ->orderBy('id')
            ->each(function ($doc) {
                DB::table('notarial_entries')->insertOrIgnore([
                    'document_id' => $doc->id,
                    'doc_no' => $doc->notarial_doc_no,
                    'page_no' => $doc->notarial_page_no ?? 1,
                    'book_no' => $doc->notarial_book_no ?? 'I',
                    'series' => $doc->notarial_series,
                    'recorded_by' => $doc->created_by,
                    'created_at' => $doc->updated_at ?? now(),
                    'updated_at' => $doc->updated_at ?? now(),
                ]);
            });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_notarial_index');
            $table->dropColumn(['notarial_doc_no', 'notarial_page_no', 'notarial_book_no', 'notarial_series']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedInteger('notarial_doc_no')->nullable()->after('physical_location');
            $table->unsignedInteger('notarial_page_no')->nullable()->after('notarial_doc_no');
            $table->string('notarial_book_no', 10)->nullable()->after('notarial_page_no');
            $table->unsignedSmallInteger('notarial_series')->nullable()->after('notarial_book_no');
            $table->index(['notarial_series', 'notarial_book_no', 'notarial_page_no', 'notarial_doc_no'], 'documents_notarial_index');
        });

        DB::table('notarial_entries')->orderBy('id')->each(function ($entry) {
            DB::table('documents')->where('id', $entry->document_id)->update([
                'notarial_doc_no' => $entry->doc_no,
                'notarial_page_no' => $entry->page_no,
                'notarial_book_no' => $entry->book_no,
                'notarial_series' => $entry->series,
            ]);
        });

        Schema::dropIfExists('notarial_entries');
    }
};
