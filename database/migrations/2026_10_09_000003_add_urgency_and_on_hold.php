<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revision 1 (R4) — urgency, On Hold, and an optional deadline.
 * The deadline column is only used when DTS_DEADLINES=true (the firm asked to remove due dates;
 * the column lets them turn deadlines back on without a database change).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('urgency', 20)->default('normal')->index()->after('status');
            $table->date('deadline')->nullable()->after('urgency');
            $table->dateTime('on_hold_since')->nullable()->after('deadline');
            $table->string('on_hold_reason')->nullable()->after('on_hold_since');
        });

        Schema::table('legal_cases', function (Blueprint $table) {
            $table->string('urgency', 20)->default('normal')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('legal_cases', fn (Blueprint $table) => $table->dropColumn('urgency'));
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['urgency']);
            $table->dropColumn(['urgency', 'deadline', 'on_hold_since', 'on_hold_reason']);
        });
    }
};
