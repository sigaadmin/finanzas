<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('own_revenue_expense_dossier_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_expense_dossier_id')->constrained()->restrictOnDelete();
            $table->string('stage', 40);
            $table->string('original_name');
            $table->string('storage_disk', 32)->default('local');
            $table->string('storage_path')->unique();
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->index(['own_revenue_expense_dossier_id', 'stage'], 'expense_dossier_document_stage_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_expense_dossier_documents');
    }
};
