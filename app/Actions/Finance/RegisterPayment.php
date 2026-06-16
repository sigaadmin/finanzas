<?php

namespace App\Actions\Finance;

use App\Enums\Finance\PaymentProcedureStatus;
use App\Enums\Finance\PaymentTransactionStatus;
use App\Models\PaymentProcedure;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Finance\FolioService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RegisterPayment
{
    public function __construct(
        private readonly FolioService $folios,
        private readonly GenerateReceiptsForTransaction $generateReceipts,
    ) {}

    public function handle(
        PaymentProcedure $procedure,
        User $registeredBy,
        string $paymentMethod,
        ?string $reference = null,
    ): PaymentTransaction {
        return DB::transaction(function () use ($procedure, $registeredBy, $paymentMethod, $reference): PaymentTransaction {
            $lockedProcedure = PaymentProcedure::query()
                ->whereKey($procedure->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedProcedure->status !== PaymentProcedureStatus::PendingPayment) {
                throw new HttpException(403, 'El trámite no está pendiente de pago.');
            }

            $transaction = PaymentTransaction::query()->create([
                'payment_procedure_id' => $lockedProcedure->id,
                'registered_by' => $registeredBy->id,
                'folio' => $this->folios->transactionFolio(),
                'status' => PaymentTransactionStatus::Paid,
                'total_pesos' => $lockedProcedure->total_pesos,
                'payment_method' => $paymentMethod,
                'reference' => $reference,
                'paid_at' => now(),
            ]);

            $lockedProcedure->update([
                'status' => PaymentProcedureStatus::Paid,
                'paid_at' => $transaction->paid_at,
            ]);

            $this->generateReceipts->handle($transaction);

            return $transaction;
        });
    }
}
