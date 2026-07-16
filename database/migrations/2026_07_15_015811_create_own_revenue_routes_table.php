<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('own_revenue_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
            $table->string('origin');
            $table->string('normalized_origin');
            $table->string('destination');
            $table->string('normalized_destination');
            $table->decimal('one_way_kilometers', 12, 4);
            $table->decimal('additional_kilometers', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['own_revenue_budget_id', 'normalized_origin', 'normalized_destination'],
                'own_rev_route_budget_points_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('own_revenue_routes');
    }
};
