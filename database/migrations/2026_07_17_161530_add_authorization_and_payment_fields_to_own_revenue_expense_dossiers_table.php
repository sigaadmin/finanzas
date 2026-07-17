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
            $table->string('finance_authorization_reference')->nullable()->after('payment_request_reference');
            $table->foreignId('finance_authorized_by')->nullable()->after('payment_requested_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('finance_authorized_at')->nullable()->after('finance_authorized_by');
            $table->string('budget_office_authorization_reference')->nullable()->after('finance_authorization_reference');
            $table->foreignId('budget_office_authorized_by')->nullable()->after('finance_authorized_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('budget_office_authorized_at')->nullable()->after('budget_office_authorized_by');
            $table->string('payment_reference')->nullable()->after('budget_office_authorization_reference');
            $table->foreignId('paid_by')->nullable()->after('budget_office_authorized_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable()->after('paid_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('own_revenue_expense_dossiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by');
            $table->dropConstrainedForeignId('budget_office_authorized_by');
            $table->dropConstrainedForeignId('finance_authorized_by');
            $table->dropColumn([
                'finance_authorization_reference',
                'finance_authorized_at',
                'budget_office_authorization_reference',
                'budget_office_authorized_at',
                'payment_reference',
                'paid_at',
            ]);
        });
    }
};
