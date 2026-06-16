<?php

namespace App\Services\Finance;

use App\Enums\Finance\ReceiptType;
use App\Models\FinanceFolioSequence;
use App\Models\PaymentProcedure;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FolioService
{
    public function procedureFolio(): string
    {
        return $this->sequential(
            sequenceKey: 'procedure',
            prefix: 'CREN-T',
            model: PaymentProcedure::class,
        );
    }

    public function transactionFolio(): string
    {
        return $this->unique('TX', PaymentTransaction::class);
    }

    public function receiptFolio(ReceiptType $type): string
    {
        return match ($type) {
            ReceiptType::Internal => $this->sequential(
                sequenceKey: 'receipt_internal',
                prefix: 'CREN-I',
                model: Receipt::class,
            ),
            ReceiptType::External => $this->sequential(
                sequenceKey: 'receipt_external',
                prefix: 'CREN',
                model: Receipt::class,
            ),
        };
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function sequential(string $sequenceKey, string $prefix, string $model): string
    {
        return DB::transaction(function () use ($sequenceKey, $prefix, $model): string {
            $year = now(config('finance.timezone'))->year;
            $sequence = FinanceFolioSequence::query()
                ->where('sequence_key', $sequenceKey)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = FinanceFolioSequence::query()->create([
                    'sequence_key' => $sequenceKey,
                    'year' => $year,
                    'next_number' => 1,
                ]);
            }

            do {
                $folio = sprintf('%s-%d-%04d', $prefix, $year, $sequence->next_number);

                $sequence->increment('next_number');
                $sequence->refresh();
            } while ($model::query()->where('folio', $folio)->exists());

            return $folio;
        });
    }

    /**
     * @param  class-string<PaymentTransaction|Receipt>  $model
     */
    private function unique(string $prefix, string $model): string
    {
        do {
            $folio = $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while ($model::query()->where('folio', $folio)->exists());

        return $folio;
    }
}
