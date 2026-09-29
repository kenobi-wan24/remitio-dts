<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retainer vs walk-in / one-time clients (as described in the company profile).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('is_retainer')->default(false)->after('client_type');
            $table->index('is_retainer');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['is_retainer']);
            $table->dropColumn('is_retainer');
        });
    }
};
