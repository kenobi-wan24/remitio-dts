<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_code', 20)->unique();            // RR-2026-0001 (internal)
            $table->string('docket_number', 50)->nullable();      // court / agency case no.
            $table->string('title');
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('case_type', 30);
            $table->string('court_or_venue')->nullable();
            $table->foreignId('handling_attorney_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->date('date_opened');
            $table->date('date_closed')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('case_type');
            $table->index('docket_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_cases');
    }
};
