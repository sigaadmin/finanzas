<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\RegisterPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RegisterPaymentRequest;
use App\Models\PaymentProcedure;
use Illuminate\Http\RedirectResponse;

class PaymentRegistrationController extends Controller
{
    public function store(
        RegisterPaymentRequest $request,
        PaymentProcedure $paymentProcedure,
        RegisterPayment $registerPayment,
    ): RedirectResponse {
        $validated = $request->validated();

        $registerPayment->handle(
            procedure: $paymentProcedure,
            registeredBy: $request->user(),
            paymentMethod: $validated['payment_method'],
            reference: $validated['reference'] ?? null,
        );

        return to_route('finance.payment-procedures.show', $paymentProcedure);
    }
}
