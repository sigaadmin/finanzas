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
        Schema::table('own_revenue_expense_dossiers', function (Blueprint $table) {
            $table->string('purchase_reference')->nullable()->after('external_reference');
            $table->foreignId('purchase_started_by')->nullable()->after('sufficiency_confirmed_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('purchase_started_at')->nullable()->after('purchase_started_by');
            $table->string('payment_request_reference')->nullable()->after('purchase_reference');
            $table->foreignId('payment_requested_by')->nullable()->after('purchase_started_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('payment_requested_at')->nullable()->after('payment_requested_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('own_revenue_expense_dossiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_requested_by');
            $table->dropConstrainedForeignId('purchase_started_by');
            $table->dropColumn([
                'purchase_reference',
                'purchase_started_at',
                'payment_request_reference',
                'payment_requested_at',
            ]);
        });
    }
};
