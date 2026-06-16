<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->renameMoneyColumn('charge_concepts', 'amount_cents', 'amount_pesos');
        $this->renameMoneyColumn('official_fee_concepts', 'amount_cents', 'amount_pesos');
        $this->renameMoneyColumn('payment_procedure_items', 'unit_amount_cents', 'unit_amount_pesos');
        $this->renameMoneyColumn('payment_procedure_items', 'subtotal_cents', 'subtotal_pesos');
        $this->renameMoneyColumn('payment_procedure_items', 'official_fee_amount_cents', 'official_fee_amount_pesos');
        $this->renameMoneyColumn('payment_procedures', 'total_cents', 'total_pesos');
        $this->renameMoneyColumn('payment_transactions', 'total_cents', 'total_pesos');
        $this->renameMoneyColumn('receipts', 'total_cents', 'total_pesos');
        $this->renameMoneyColumn('seq_report_exports', 'total_cents', 'total_pesos');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->renamePesosColumnToCents('charge_concepts', 'amount_pesos', 'amount_cents');
        $this->renamePesosColumnToCents('official_fee_concepts', 'amount_pesos', 'amount_cents');
        $this->renamePesosColumnToCents('payment_procedure_items', 'unit_amount_pesos', 'unit_amount_cents');
        $this->renamePesosColumnToCents('payment_procedure_items', 'subtotal_pesos', 'subtotal_cents');
        $this->renamePesosColumnToCents('payment_procedure_items', 'official_fee_amount_pesos', 'official_fee_amount_cents');
        $this->renamePesosColumnToCents('payment_procedures', 'total_pesos', 'total_cents');
        $this->renamePesosColumnToCents('payment_transactions', 'total_pesos', 'total_cents');
        $this->renamePesosColumnToCents('receipts', 'total_pesos', 'total_cents');
        $this->renamePesosColumnToCents('seq_report_exports', 'total_pesos', 'total_cents');
    }

    private function renameIfPresent(string $table, string $from, string $to): bool
    {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $from)
            || Schema::hasColumn($table, $to)) {
            return false;
        }

        Schema::table($table, function (Blueprint $table) use ($from, $to): void {
            $table->renameColumn($from, $to);
        });

        return true;
    }

    private function renameMoneyColumn(string $table, string $from, string $to): void
    {
        if (! $this->renameIfPresent($table, $from, $to)) {
            return;
        }

        DB::table($table)->update([
            $to => DB::raw($to.' / 100'),
        ]);
    }

    private function renamePesosColumnToCents(string $table, string $from, string $to): void
    {
        if (! $this->renameIfPresent($table, $from, $to)) {
            return;
        }

        DB::table($table)->update([
            $to => DB::raw($to.' * 100'),
        ]);
    }
};
