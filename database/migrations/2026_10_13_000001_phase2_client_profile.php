<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revision 2 - Phase 2: Clients.
 *  - Individuals: middle name, suffix, civil status, gender, spouse details
 *  - Companies: representative, business permit, TIN
 *  - Walk-in clients (notarization only)
 * All new columns are nullable so existing client records keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('suffix', 20)->nullable()->after('last_name');
            $table->string('civil_status', 20)->nullable()->after('suffix');
            $table->string('gender', 20)->nullable()->after('civil_status');

            $table->string('spouse_last_name', 100)->nullable()->after('gender');
            $table->string('spouse_first_name', 100)->nullable()->after('spouse_last_name');
            $table->string('spouse_middle_name', 100)->nullable()->after('spouse_first_name');
            $table->string('spouse_contact_number', 30)->nullable()->after('spouse_middle_name');

            $table->string('representative', 150)->nullable()->after('company_name');
            $table->string('business_permit_number', 100)->nullable()->after('representative');
            $table->string('tin', 30)->nullable()->after('business_permit_number');

            // Walk-in client: recorded only so the firm can notarize their document
            $table->boolean('notarization_only')->default(false)->after('is_retainer');
            $table->index('notarization_only');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['notarization_only']);
            $table->dropColumn([
                'middle_name', 'suffix', 'civil_status', 'gender',
                'spouse_last_name', 'spouse_first_name', 'spouse_middle_name', 'spouse_contact_number',
                'representative', 'business_permit_number', 'tin',
                'notarization_only',
            ]);
        });
    }
};
