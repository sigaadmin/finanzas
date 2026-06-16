<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\CreatePaymentProcedure;
use App\Enums\Finance\ChargeConceptStatus;
use App\Enums\Finance\PaymentProcedureStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePaymentProcedureRequest;
use App\Http\Requests\Finance\UpdatePaymentProcedureRequest;
use App\Models\ChargeConcept;
use App\Models\PaymentProcedure;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentProcedureController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PaymentProcedure::class);

        $filters = [
            'procedure_type' => $request->string('procedure_type')->toString(),
            'date' => $request->string('date')->toString(),
            'student_name' => $request->string('student_name')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $procedures = PaymentProcedure::query()
            ->with('studentSnapshot')
            ->when($filters['procedure_type'] !== '', fn ($query) => $query
                ->whereHas('items', fn ($query) => $query
                    ->where('charge_concept_id', $filters['procedure_type'])))
            ->when($filters['date'] !== '', function ($query) use ($filters) {
                $startOfLocalDay = CarbonImmutable::createFromFormat(
                    'Y-m-d',
                    $filters['date'],
                    config('finance.timezone')
                )->startOfDay();

                $query->whereBetween('created_at', [
                    $startOfLocalDay->utc(),
                    $startOfLocalDay->endOfDay()->utc(),
                ]);
            })
            ->when($filters['student_name'] !== '', fn ($query) => $query
                ->whereHas('studentSnapshot', fn ($query) => $query
                    ->where('name', 'like', '%'.$filters['student_name'].'%')))
            ->when($filters['status'] !== '', fn ($query) => $query
                ->where('status', $filters['status']))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PaymentProcedure $procedure): array => [
                'id' => $procedure->id,
                'folio' => $procedure->folio,
                'student_name' => $procedure->studentSnapshot->name,
                'status' => $procedure->status->value,
                'total_pesos' => $procedure->total_pesos,
                'created_at' => $procedure->created_at?->toISOString(),
            ]);

        return Inertia::render('finance/procedures/index', [
            'procedures' => $procedures,
            'filters' => $filters,
            'filter_options' => [
                'procedure_types' => ChargeConcept::query()
                    ->where('status', ChargeConceptStatus::Active)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (ChargeConcept $concept): array => [
                        'id' => $concept->id,
                        'name' => $concept->name,
                    ]),
                'statuses' => collect(PaymentProcedureStatus::cases())
                    ->map(fn (PaymentProcedureStatus $status): array => [
                        'value' => $status->value,
                        'label' => $status->value,
                    ]),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', PaymentProcedure::class);

        $concepts = ChargeConcept::query()
            ->where('status', ChargeConceptStatus::Active)
            ->orderBy('name')
            ->get()
            ->map(fn (ChargeConcept $concept): array => [
                'id' => $concept->id,
                'name' => $concept->name,
                'type' => $concept->type->value,
                'allows_quantity' => $concept->allows_quantity,
                'amount_pesos' => $concept->amount_pesos,
            ]);

        return Inertia::render('finance/procedures/create', [
            'concepts' => $concepts,
        ]);
    }

    public function store(StorePaymentProcedureRequest $request, CreatePaymentProcedure $createPaymentProcedure): RedirectResponse
    {
        $procedure = $createPaymentProcedure->handle(
            createdBy: $request->user(),
            student: $request->validated('student'),
            items: $request->paymentItems(),
        );

        return to_route('finance.payment-procedures.show', $procedure);
    }

    public function show(PaymentProcedure $paymentProcedure): Response
    {
        Gate::authorize('view', $paymentProcedure);

        $paymentProcedure->load(['studentSnapshot', 'items', 'receipts.seqDeposit']);

        return Inertia::render('finance/procedures/show', [
            'procedure' => [
                'id' => $paymentProcedure->id,
                'folio' => $paymentProcedure->folio,
                'status' => $paymentProcedure->status->value,
                'total_pesos' => $paymentProcedure->total_pesos,
                'can_register_payment' => $paymentProcedure->status === PaymentProcedureStatus::PendingPayment
                    && Gate::allows('update', $paymentProcedure),
                'student' => [
                    'name' => $paymentProcedure->studentSnapshot->name,
                    'grade' => $paymentProcedure->studentSnapshot->grade,
                    'group' => $paymentProcedure->studentSnapshot->group,
                ],
                'items' => $paymentProcedure->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'concept_name' => $item->concept_name,
                    'concept_type' => $item->concept_type->value,
                    'quantity' => $item->quantity,
                    'unit_amount_pesos' => $item->unit_amount_pesos,
                    'subtotal_pesos' => $item->subtotal_pesos,
                ]),
                'receipts' => $paymentProcedure->receipts->map(fn ($receipt): array => [
                    'id' => $receipt->id,
                    'folio' => $receipt->folio,
                    'type' => $receipt->type->value,
                    'status' => $receipt->status->value,
                    'total_pesos' => $receipt->total_pesos,
                    'can_register_seq_deposit' => Gate::allows('registerSeqDeposit', $receipt),
                    'seq_deposit' => $receipt->seqDeposit ? [
                        'id' => $receipt->seqDeposit->id,
                        'deposit_date' => $receipt->seqDeposit->deposit_date?->toDateString(),
                        'bank_transaction_folio' => $receipt->seqDeposit->bank_transaction_folio,
                        'deposit_type' => $receipt->seqDeposit->deposit_type,
                        'deposit_concept' => $receipt->seqDeposit->deposit_concept,
                        'amount_pesos' => $receipt->seqDeposit->amount_pesos,
                    ] : null,
                ]),
            ],
        ]);
    }

    public function update(UpdatePaymentProcedureRequest $request, PaymentProcedure $paymentProcedure): RedirectResponse
    {
        return to_route('finance.payment-procedures.show', $paymentProcedure);
    }
}
