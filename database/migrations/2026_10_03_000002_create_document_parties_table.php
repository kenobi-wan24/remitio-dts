<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 11C: the parties to a document (e.g. Vendor and Vendee). Each party is a client record.
 * Document types say how many parties they need (Agreements and Deeds: 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('role', 60)->nullable();  // e.g. First Party / Vendor
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['document_id', 'client_id']);
        });

        Schema::table('document_types', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_parties')->default(1)->after('description');
        });

        // Confirmed by the firm: agreements and deeds involve at least two parties
        DB::table('document_types')->whereIn('name', ['Contract / Agreement', 'Deed'])->update(['min_parties' => 2]);
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn('min_parties');
        });

        Schema::dropIfExists('document_parties');
    }
};
