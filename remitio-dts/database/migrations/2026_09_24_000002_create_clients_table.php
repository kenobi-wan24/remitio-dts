<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_code', 20)->unique();          // CL-2026-0001
            $table->string('client_type', 20)->default('individual');
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('company_name', 150)->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
            $table->index('company_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
