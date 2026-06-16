<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptValidationController extends Controller
{
    public function __invoke(string $token): Response
    {
        $receipt = Receipt::query()
            ->with(['paymentProcedure.studentSnapshot'])
            ->where('validation_token', $token)
            ->firstOrFail();

        return Inertia::render('finance/receipts/validate', [
            'receipt' => [
                'folio' => $receipt->folio,
                'type' => $receipt->type->value,
                'status' => $receipt->status->value,
                'total_pesos' => $receipt->total_pesos,
                'amount_in_words' => $receipt->amount_in_words,
                'issued_at' => $receipt->issued_at?->toISOString(),
                'student' => [
                    'name' => $receipt->paymentProcedure->studentSnapshot->name,
                    'grade' => $receipt->paymentProcedure->studentSnapshot->grade,
                    'group' => $receipt->paymentProcedure->studentSnapshot->group,
                ],
            ],
        ]);
    }
}
