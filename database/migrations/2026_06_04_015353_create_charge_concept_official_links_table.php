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
        Schema::create('charge_concept_official_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_concept_id')->constrained()->cascadeOnDelete();
            $table->foreignId('official_fee_concept_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('status')->default('pending_review');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['charge_concept_id', 'fiscal_year']);
            $table->index(['fiscal_year', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charge_concept_official_links');
    }
};
