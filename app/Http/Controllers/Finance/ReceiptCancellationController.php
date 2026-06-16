<?php

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\ReceiptStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CancelReceiptRequest;
use App\Models\Receipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ReceiptCancellationController extends Controller
{
    public function __invoke(CancelReceiptRequest $request, Receipt $receipt): RedirectResponse
    {
        DB::transaction(function () use ($request, $receipt): void {
            $receipt = Receipt::query()
                ->whereKey($receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            $receipt->cancellation()->create([
                'cancelled_by' => $request->user()->id,
                'reason' => $request->validated('reason'),
                'cancelled_at' => now(),
            ]);

            $receipt->update([
                'status' => ReceiptStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
        });

        return to_route('finance.receipts.show', $receipt);
    }
}
