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
        Schema::create('own_revenue_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_import_file_id')->constrained()->cascadeOnDelete();
            $table->string('sheet_name');
            $table->unsignedInteger('row_number');
            $table->string('row_kind');
            $table->char('row_hash', 64);
            $table->json('source_payload');
            $table->json('normalized_payload')->nullable();
            $table->timestamps();
            $table->unique(['own_revenue_import_file_id', 'sheet_name', 'row_number'], 'own_rev_source_row_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_import_rows');
    }
};
