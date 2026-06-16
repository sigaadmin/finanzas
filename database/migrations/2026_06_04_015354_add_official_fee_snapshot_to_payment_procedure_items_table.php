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
        Schema::table('payment_procedure_items', function (Blueprint $table) {
            $table->foreignId('official_fee_concept_id')->nullable()->after('charge_concept_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('official_fee_fiscal_year')->nullable()->after('concept_type');
            $table->string('official_fee_link_status')->default('pending_review')->after('official_fee_fiscal_year');
            $table->string('official_fee_code')->nullable()->after('official_fee_link_status');
            $table->string('official_fee_name')->nullable()->after('official_fee_code');
            $table->unsignedInteger('official_fee_amount_pesos')->nullable()->after('official_fee_name');

            $table->index(['official_fee_fiscal_year', 'official_fee_link_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_procedure_items', function (Blueprint $table) {
            $table->dropIndex(['official_fee_fiscal_year', 'official_fee_link_status']);
            $table->dropConstrainedForeignId('official_fee_concept_id');
            $table->dropColumn([
                'official_fee_fiscal_year',
                'official_fee_link_status',
                'official_fee_code',
                'official_fee_name',
                'official_fee_amount_pesos',
            ]);
        });
    }
};
