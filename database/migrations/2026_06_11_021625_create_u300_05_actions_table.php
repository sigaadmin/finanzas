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
        Schema::create('u300_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('u300_goal_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20);
            $table->text('name');
            $table->text('justification')->nullable();
            $table->unsignedBigInteger('requested_total_cents')->default(0);
            $table->unsignedBigInteger('approved_total_cents')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['u300_goal_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('u300_actions');
    }
};
