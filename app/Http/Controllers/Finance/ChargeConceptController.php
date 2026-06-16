<?php

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\ChargeConceptStatus;
use App\Enums\Finance\ChargeConceptType;
use App\Enums\Finance\OfficialFeeLinkStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreChargeConceptRequest;
use App\Http\Requests\Finance\UpdateChargeConceptRequest;
use App\Models\ChargeConcept;
use App\Models\ChargeConceptOfficialLink;
use App\Models\OfficialFeeConcept;
use App\Models\OfficialFeeSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChargeConceptController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ChargeConcept::class);

        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();
        $fiscalYear = (int) $request->integer('fiscal_year', now()->year);

        $concepts = ChargeConcept::query()
            ->with([
                'officialLinks' => fn ($query) => $query
                    ->where('fiscal_year', $fiscalYear)
                    ->with('officialFeeConcept.schedule'),
            ])
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ChargeConcept $concept): array => [
                'id' => $concept->id,
                'name' => $concept->name,
                'description' => $concept->description,
                'amount_pesos' => $concept->amount_pesos,
                'type' => $concept->type->value,
                'allows_quantity' => $concept->allows_quantity,
                'status' => $concept->status->value,
                'internal_key' => $concept->internal_key,
                'official_link' => $this->officialLinkData($concept, $fiscalYear),
                'can' => [
                    'update' => $request->user()?->can('update', $concept) === true,
                ],
            ]);

        return Inertia::render('finance/concepts/index', [
            'concepts' => $concepts,
            'filters' => [
                'type' => $type,
                'status' => $status,
                'fiscal_year' => $fiscalYear,
            ],
            'options' => [
                'types' => array_map(fn (ChargeConceptType $type): string => $type->value, ChargeConceptType::cases()),
                'statuses' => array_map(fn (ChargeConceptStatus $status): string => $status->value, ChargeConceptStatus::cases()),
                'official_link_statuses' => array_map(fn (OfficialFeeLinkStatus $status): string => $status->value, OfficialFeeLinkStatus::cases()),
            ],
            'can' => [
                'create' => $request->user()?->can('create', ChargeConcept::class) === true,
            ],
            'official' => [
                'fiscal_year' => $fiscalYear,
                'schedules' => OfficialFeeSchedule::query()
                    ->orderByDesc('fiscal_year')
                    ->get()
                    ->map(fn (OfficialFeeSchedule $schedule): array => [
                        'id' => $schedule->id,
                        'fiscal_year' => $schedule->fiscal_year,
                        'source_name' => $schedule->source_name,
                        'published_on' => $schedule->published_on?->toDateString(),
                    ])
                    ->all(),
                'concepts' => OfficialFeeConcept::query()
                    ->whereHas('schedule', fn ($query) => $query->where('fiscal_year', $fiscalYear))
                    ->with('schedule')
                    ->orderBy('code')
                    ->get()
                    ->map(fn (OfficialFeeConcept $concept): array => [
                        'id' => $concept->id,
                        'code' => $concept->code,
                        'name' => $concept->name,
                        'amount_pesos' => $concept->amount_pesos,
                        'source_name' => $concept->schedule->source_name,
                        'published_on' => $concept->schedule->published_on?->toDateString(),
                        'label' => "{$concept->code} - {$concept->name}",
                    ])
                    ->all(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function officialLinkData(ChargeConcept $concept, int $fiscalYear): array
    {
        /** @var ChargeConceptOfficialLink|null $link */
        $link = $concept->officialLinks->firstWhere('fiscal_year', $fiscalYear);
        $status = $link?->status ?? OfficialFeeLinkStatus::PendingReview;

        return [
            'id' => $link?->id,
            'fiscal_year' => $fiscalYear,
            'status' => $status->value,
            'label' => match ($status) {
                OfficialFeeLinkStatus::Linked => $link?->officialFeeConcept
                    ? "{$link->officialFeeConcept->code} - {$link->officialFeeConcept->name}"
                    : 'Enlace oficial incompleto',
                OfficialFeeLinkStatus::NotApplicable => 'No aplica DOF',
                OfficialFeeLinkStatus::PendingReview => 'Pendiente de revisión',
            },
            'official_fee_concept_id' => $link?->official_fee_concept_id,
            'notes' => $link?->notes,
        ];
    }

    public function store(StoreChargeConceptRequest $request): RedirectResponse
    {
        ChargeConcept::create($request->validated());

        return to_route('finance.charge-concepts.index');
    }

    public function update(UpdateChargeConceptRequest $request, ChargeConcept $chargeConcept): RedirectResponse
    {
        $chargeConcept->update($request->validated());

        return to_route('finance.charge-concepts.index');
    }
}
