<?php

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\ReceiptStatus;
use App\Enums\Finance\ReceiptType;
use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Services\Finance\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Receipt::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:internal,external'],
            'status' => ['nullable', 'string', 'in:issued,reprinted,cancelled'],
        ]);

        $receipts = Receipt::query()
            ->with(['paymentProcedure.studentSnapshot', 'paymentProcedureItem'])
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('folio', 'like', "%{$search}%")
                        ->orWhereHas('paymentProcedure.studentSnapshot', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('matricula', 'like', "%{$search}%")
                                ->orWhere('siga_student_id', 'like', "%{$search}%");
                        })
                        ->orWhereHas('paymentProcedureItem', function ($query) use ($search): void {
                            $query->where('concept_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('issued_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Receipt $receipt): array => [
                'id' => $receipt->id,
                'folio' => $receipt->folio,
                'type' => $receipt->type->value,
                'status' => $receipt->status->value,
                'total_pesos' => $receipt->total_pesos,
                'issued_at' => $receipt->issued_at?->toISOString(),
                'student_name' => $receipt->paymentProcedure->studentSnapshot->name,
                'concept_name' => $receipt->paymentProcedureItem?->concept_name,
            ]);

        return Inertia::render('finance/receipts/index', [
            'filters' => [
                'search' => $filters['search'] ?? null,
                'type' => $filters['type'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
            'receipts' => $receipts,
            'options' => [
                'types' => array_map(fn (ReceiptType $type): string => $type->value, ReceiptType::cases()),
                'statuses' => array_map(fn (ReceiptStatus $status): string => $status->value, ReceiptStatus::cases()),
            ],
        ]);
    }

    public function show(Request $request, Receipt $receipt, QrCode $qrCode): Response
    {
        Gate::authorize('view', $receipt);

        $receipt->load([
            'cancellation.cancelledBy',
            'paymentProcedure.studentSnapshot',
            'paymentProcedureItem',
            'paymentTransaction',
        ]);

        $validationUrl = route('finance.receipts.validate', $receipt->validation_token);

        return Inertia::render('finance/receipts/show', [
            'receipt' => $this->receiptDetail($request, $receipt, $validationUrl) + [
                'qr_svg' => $qrCode->svg($validationUrl),
            ],
        ]);
    }

    public function print(Request $request, Receipt $receipt, QrCode $qrCode): Response
    {
        Gate::authorize('view', $receipt);

        $receipt->load([
            'paymentProcedure.items',
            'paymentProcedure.studentSnapshot',
            'paymentProcedureItem',
            'paymentTransaction',
        ]);

        $validationUrl = route('finance.receipts.validate', $receipt->validation_token);
        $detail = $this->receiptDetail($request, $receipt, $validationUrl) + [
            'qr_svg' => $qrCode->svg($validationUrl),
        ];

        if ($receipt->type === ReceiptType::External) {
            return Inertia::render('finance/receipts/print-external-seq', [
                'receipt' => $detail,
            ]);
        }

        return Inertia::render('finance/receipts/print-internal', [
            'receipt' => $detail + [
                'items' => $receipt->paymentProcedure->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'concept_name' => $item->concept_name,
                    'concept_type' => $item->concept_type->value,
                    'quantity' => $item->quantity,
                    'unit_amount_pesos' => $item->unit_amount_pesos,
                    'subtotal_pesos' => $item->subtotal_pesos,
                ])->values(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptDetail(Request $request, Receipt $receipt, string $validationUrl): array
    {
        return [
            'id' => $receipt->id,
            'folio' => $receipt->folio,
            'type' => $receipt->type->value,
            'status' => $receipt->status->value,
            'total_pesos' => $receipt->total_pesos,
            'amount_in_words' => $receipt->amount_in_words,
            'issued_at' => $receipt->issued_at?->toISOString(),
            'validation_url' => $validationUrl,
            'can_cancel' => $request->user()->can('cancel', $receipt),
            'procedure_id' => $receipt->payment_procedure_id,
            'procedure_folio' => $receipt->paymentProcedure->folio,
            'transaction_folio' => $receipt->paymentTransaction->folio,
            'student' => [
                'name' => $receipt->paymentProcedure->studentSnapshot->name,
                'grade' => $receipt->paymentProcedure->studentSnapshot->grade,
                'group' => $receipt->paymentProcedure->studentSnapshot->group,
                'program' => $receipt->paymentProcedure->studentSnapshot->program,
                'matricula' => $receipt->paymentProcedure->studentSnapshot->matricula,
            ],
            'item' => $receipt->paymentProcedureItem ? [
                'concept_name' => $receipt->paymentProcedureItem->concept_name,
                'official_fee_code' => $receipt->paymentProcedureItem->official_fee_code,
                'official_fee_name' => $receipt->paymentProcedureItem->official_fee_name,
                'subtotal_pesos' => $receipt->paymentProcedureItem->subtotal_pesos,
            ] : null,
            'cancellation' => $receipt->cancellation ? [
                'reason' => $receipt->cancellation->reason,
                'cancelled_at' => $receipt->cancellation->cancelled_at?->toISOString(),
                'cancelled_by' => $receipt->cancellation->cancelledBy->name,
            ] : null,
        ];
    }
}
