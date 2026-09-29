<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Version control for files (paper: Problem 2 — wrong versions / duplicates).
 * All versions of the same file share one version_group_id (= id of version 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_attachments', function (Blueprint $table) {
            $table->unsignedBigInteger('version_group_id')->nullable()->after('document_id');
            $table->unsignedInteger('version')->default(1)->after('version_group_id');
            $table->boolean('is_final')->default(false)->after('version');
            $table->string('version_notes')->nullable()->after('is_final');

            $table->index('version_group_id');
        });

        // Existing files become version 1 of their own group
        DB::table('document_attachments')
            ->whereNull('version_group_id')
            ->update(['version_group_id' => DB::raw('id')]);
    }

    public function down(): void
    {
        Schema::table('document_attachments', function (Blueprint $table) {
            $table->dropIndex(['version_group_id']);
            $table->dropColumn(['version_group_id', 'version', 'is_final', 'version_notes']);
        });
    }
};
