<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('own_revenue_travel_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->string('destination');
            $table->string('normalized_destination');
            $table->unsignedTinyInteger('food_zone');
            $table->unsignedTinyInteger('lodging_zone');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['own_revenue_budget_id', 'normalized_destination'], 'own_rev_destination_budget_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('own_revenue_travel_destinations');
    }
};
