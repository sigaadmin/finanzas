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
        Schema::create('own_revenue_fuel_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_revenue_fuel_fund_id')->constrained()->restrictOnDelete();
            $table->foreignId('own_revenue_proposal_fuel_need_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->date('commission_date');
            $table->text('reason');
            $table->text('route_description');
            $table->string('vehicle_description');
            $table->decimal('kilometers', 12, 4);
            $table->decimal('liters', 12, 4);
            $table->unsignedBigInteger('amount_cents');
            $table->decimal('effective_price_per_liter', 12, 4);
            $table->boolean('is_extraordinary')->default(false);
            $table->text('extraordinary_justification')->nullable();
            $table->unsignedBigInteger('balance_after_cents')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['own_revenue_fuel_fund_id', 'status', 'commission_date'], 'fuel_commission_status_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('own_revenue_fuel_commissions');
    }
};
