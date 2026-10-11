<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_code', 20)->unique();        // DOC-2026-00001
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('legal_case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('received');
            $table->foreignId('current_holder_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('physical_location')->nullable();      // cabinet / drawer / folder
            $table->date('date_received');
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
