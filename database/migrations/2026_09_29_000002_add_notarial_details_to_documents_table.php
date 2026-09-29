<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notarial register reference: "Doc. No. 45; Page No. 9; Book No. III; Series of 2026".
 * Matches how the firm labels its physical bundles, so old notarized documents
 * can be looked up directly (paper: Problem 1 — 3-day retrieval).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedInteger('notarial_doc_no')->nullable()->after('physical_location');
            $table->unsignedInteger('notarial_page_no')->nullable()->after('notarial_doc_no');
            $table->string('notarial_book_no', 10)->nullable()->after('notarial_page_no');
            $table->unsignedSmallInteger('notarial_series')->nullable()->after('notarial_book_no');

            $table->index(
                ['notarial_series', 'notarial_book_no', 'notarial_page_no', 'notarial_doc_no'],
                'documents_notarial_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_notarial_index');
            $table->dropColumn(['notarial_doc_no', 'notarial_page_no', 'notarial_book_no', 'notarial_series']);
        });
    }
};
