<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revision 1 (R2) — Problem 3: where a physical document is while it is OUT of the office
 * (e.g. brought to court or to a client for signing) and when it came back.
 *
 * documents: the current out-of-office state (null = in the office)
 * document_movements: the details of each "Sent out" / "Returned" step (history)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dateTime('out_since')->nullable()->index()->after('physical_location');
            $table->string('out_destination')->nullable()->after('out_since');
            $table->string('out_purpose')->nullable()->after('out_destination');
            $table->string('out_carried_by')->nullable()->after('out_purpose');
            $table->date('out_expected_back')->nullable()->after('out_carried_by');
        });

        Schema::table('document_movements', function (Blueprint $table) {
            $table->string('purpose')->nullable()->after('location');
            $table->string('carried_by')->nullable()->after('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('document_movements', function (Blueprint $table) {
            $table->dropColumn(['purpose', 'carried_by']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['out_since']);
            $table->dropColumn(['out_since', 'out_destination', 'out_purpose', 'out_carried_by', 'out_expected_back']);
        });
    }
};
