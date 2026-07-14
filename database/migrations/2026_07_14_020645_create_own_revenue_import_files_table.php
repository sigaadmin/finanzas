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
        Schema::create('own_revenue_import_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_import_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('format')->nullable();
            $table->string('detected_format')->nullable();
            $table->unsignedSmallInteger('detected_year')->nullable();
            $table->string('original_name');
            $table->string('storage_disk')->default('local');
            $table->string('storage_path');
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->unsignedInteger('version_number');
            $table->string('status')->default('uploaded');
            $table->unsignedTinyInteger('detection_confidence')->nullable();
            $table->json('detection_evidence')->nullable();
            $table->timestamp('budget_updated_at_at_analysis')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('replaced_by_file_id')->nullable()->constrained('own_revenue_import_files')->nullOnDelete();
            $table->timestamps();
            $table->unique(['own_revenue_budget_id', 'format', 'version_number'], 'own_rev_file_version_unique');
            $table->index(['own_revenue_budget_id', 'format', 'status'], 'own_rev_file_active_index');
            $table->index(['own_revenue_budget_id', 'sha256'], 'own_rev_file_hash_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_import_files');
    }
};
