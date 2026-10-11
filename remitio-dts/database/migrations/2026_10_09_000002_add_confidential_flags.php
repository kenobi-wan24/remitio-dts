<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revision 1 (R3) — confidential cases and documents.
 * A confidential record is visible only to the Administrator and the people assigned to it.
 * (A document is also treated as confidential when its case is.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_cases', function (Blueprint $table) {
            $table->boolean('is_confidential')->default(false)->after('status');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_confidential')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('documents', fn (Blueprint $table) => $table->dropColumn('is_confidential'));
        Schema::table('legal_cases', fn (Blueprint $table) => $table->dropColumn('is_confidential'));
    }
};
