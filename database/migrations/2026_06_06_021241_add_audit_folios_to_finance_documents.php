<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('finance_folio_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('sequence_key');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['sequence_key', 'year']);
        });

        Schema::table('payment_procedures', function (Blueprint $table) {
            $table->string('folio')->nullable()->after('id');
            $table->unique('folio');
        });

        $this->backfillProcedures();
        $this->backfillReceipts();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_procedures', function (Blueprint $table) {
            $table->dropUnique(['folio']);
            $table->dropColumn('folio');
        });

        Schema::dropIfExists('finance_folio_sequences');
    }

    private function backfillProcedures(): void
    {
        $this->backfill(
            table: 'payment_procedures',
            sequenceKey: 'procedure',
            prefix: 'CREN-T',
            dateColumn: 'created_at',
            type: null,
        );
    }

    private function backfillReceipts(): void
    {
        $this->backfill(
            table: 'receipts',
            sequenceKey: 'receipt_internal',
            prefix: 'CREN-I',
            dateColumn: 'issued_at',
            type: 'internal',
        );

        $this->backfill(
            table: 'receipts',
            sequenceKey: 'receipt_external',
            prefix: 'CREN',
            dateColumn: 'issued_at',
            type: 'external',
        );
    }

    private function backfill(string $table, string $sequenceKey, string $prefix, string $dateColumn, ?string $type): void
    {
        $query = DB::table($table)
            ->select(['id', $dateColumn])
            ->orderBy($dateColumn)
            ->orderBy('id');

        if ($type !== null) {
            $query->where('type', $type);
        }

        $nextNumbers = [];

        foreach ($query->get() as $row) {
            $year = Carbon::parse($row->{$dateColumn} ?? now(), 'UTC')
                ->setTimezone(config('finance.timezone'))
                ->year;

            $nextNumbers[$year] ??= 1;

            DB::table($table)
                ->where('id', $row->id)
                ->update([
                    'folio' => sprintf('%s-%d-%04d', $prefix, $year, $nextNumbers[$year]),
                ]);

            $nextNumbers[$year]++;
        }

        foreach ($nextNumbers as $year => $nextNumber) {
            DB::table('finance_folio_sequences')->insert([
                'sequence_key' => $sequenceKey,
                'year' => $year,
                'next_number' => $nextNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
