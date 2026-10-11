<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail: every hand-off / status change of a document.
 * Rows here are never edited or deleted by the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('action', 30);
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->string('location')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('acted_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('acted_at');
            $table->timestamps();

            $table->index(['document_id', 'acted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_movements');
    }
};
