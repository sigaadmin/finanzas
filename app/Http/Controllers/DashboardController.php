<?php

namespace App\Http\Controllers;

use App\Enums\Finance\PaymentProcedureStatus;
use App\Enums\Finance\ReceiptStatus;
use App\Enums\Finance\ReceiptType;
use App\Models\PaymentProcedure;
use App\Models\Receipt;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return Inertia::render('dashboard', [
            'metrics' => [
                'pending_procedures' => PaymentProcedure::query()
                    ->where('status', PaymentProcedureStatus::PendingPayment)
                    ->count(),
                'paid_today' => PaymentProcedure::query()
                    ->where('status', PaymentProcedureStatus::Paid)
                    ->whereBetween('paid_at', [$todayStart, $todayEnd])
                    ->count(),
                'receipts_issued_today' => Receipt::query()
                    ->where('status', ReceiptStatus::Issued)
                    ->whereBetween('issued_at', [$todayStart, $todayEnd])
                    ->count(),
                'external_receipts_this_month' => Receipt::query()
                    ->where('type', ReceiptType::External)
                    ->where('status', ReceiptStatus::Issued)
                    ->whereBetween('issued_at', [$monthStart, $monthEnd])
                    ->count(),
            ],
        ]);
    }
}
