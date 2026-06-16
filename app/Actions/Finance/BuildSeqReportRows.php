<?php

namespace App\Actions\Finance;

use App\Enums\Finance\ReceiptType;
use App\Models\Receipt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BuildSeqReportRows
{
    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(array $filters): Collection
    {
        $query = Receipt::query()
            ->with(['paymentProcedure.studentSnapshot', 'paymentProcedureItem', 'seqDeposit'])
            ->where('type', ReceiptType::External);

        if (! empty($filters['from'])) {
            $query->where('issued_at', '>=', CarbonImmutable::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('issued_at', '<=', CarbonImmutable::parse($filters['to'])->endOfDay());
        }

        return $query
            ->orderBy('issued_at')
            ->get()
            ->map(fn (Receipt $receipt): array => [
                'id' => $receipt->id,
                'folio' => $receipt->folio,
                'issued_at' => $receipt->issued_at?->toISOString(),
                'student_name' => $receipt->paymentProcedure->studentSnapshot->name,
                'grade' => $receipt->paymentProcedure->studentSnapshot->grade,
                'group' => $receipt->paymentProcedure->studentSnapshot->group,
                'concept_name' => $receipt->paymentProcedureItem?->concept_name,
                'status' => $receipt->status->value,
                'total_pesos' => $receipt->total_pesos,
                'amount_in_words' => $receipt->amount_in_words,
                'seq_deposit' => $receipt->seqDeposit ? [
                    'deposit_date' => $receipt->seqDeposit->deposit_date?->toDateString(),
                    'bank_transaction_folio' => $receipt->seqDeposit->bank_transaction_folio,
                    'deposit_type' => $receipt->seqDeposit->deposit_type,
                    'deposit_concept' => $receipt->seqDeposit->deposit_concept,
                    'amount_pesos' => $receipt->seqDeposit->amount_pesos,
                ] : null,
            ])
            ->values();
    }
}
