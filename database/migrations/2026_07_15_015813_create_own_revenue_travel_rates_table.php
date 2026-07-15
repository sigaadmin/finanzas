<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('own_revenue_travel_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->string('position');
            $table->string('normalized_position');
            $table->unsignedTinyInteger('food_zone');
            $table->unsignedTinyInteger('lodging_zone');
            $table->decimal('per_diem_uma', 12, 4);
            $table->decimal('lodging_uma', 12, 4);
            $table->boolean('is_fallback')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['own_revenue_budget_id', 'normalized_position', 'food_zone', 'lodging_zone'],
                'own_rev_rate_budget_position_zones_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('own_revenue_travel_rates');
    }
};
