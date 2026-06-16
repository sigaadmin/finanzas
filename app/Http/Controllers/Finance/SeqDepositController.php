<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreSeqDepositRequest;
use App\Models\Receipt;
use Illuminate\Http\RedirectResponse;

class SeqDepositController extends Controller
{
    public function store(StoreSeqDepositRequest $request, Receipt $receipt): RedirectResponse
    {
        $receipt->seqDeposit()->create([
            ...$request->validated(),
            'registered_by' => $request->user()->id,
        ]);

        return to_route('finance.payment-procedures.show', $receipt->paymentProcedure);
    }
}
