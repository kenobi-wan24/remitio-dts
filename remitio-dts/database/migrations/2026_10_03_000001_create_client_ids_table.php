<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 11C: a client's valid government IDs (one or more), with an optional scanned copy.
 * Used as "competent evidence of identity" in notarial entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('id_type', 60);          // e.g. Driver's License, PhilSys National ID
            $table->string('id_number', 60);
            $table->string('scan_path')->nullable(); // private storage
            $table->string('scan_name')->nullable();
            $table->string('scan_mime', 100)->nullable();
            $table->unsignedBigInteger('scan_size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_ids');
    }
};
