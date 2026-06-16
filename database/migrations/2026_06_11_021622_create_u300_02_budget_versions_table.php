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
        Schema::create('u300_budget_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('u300_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind');
            $table->string('name');
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->timestamps();

            $table->index(['u300_program_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('u300_budget_versions');
    }
};
