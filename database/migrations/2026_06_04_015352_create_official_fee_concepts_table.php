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
        Schema::create('official_fee_concepts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('official_fee_schedule_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedInteger('amount_pesos')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['official_fee_schedule_id', 'code']);
            $table->index(['code', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('official_fee_concepts');
    }
};
