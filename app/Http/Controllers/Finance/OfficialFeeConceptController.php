<?php

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\OfficialFeeScheduleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreOfficialFeeConceptRequest;
use App\Models\OfficialFeeConcept;
use App\Models\OfficialFeeSchedule;
use Illuminate\Http\RedirectResponse;

class OfficialFeeConceptController extends Controller
{
    public function store(StoreOfficialFeeConceptRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $schedule = OfficialFeeSchedule::query()->updateOrCreate(
            ['fiscal_year' => $validated['fiscal_year']],
            [
                'source_name' => $validated['source_name'],
                'source_url' => $validated['source_url'] ?? null,
                'published_on' => $validated['published_on'] ?? null,
                'status' => OfficialFeeScheduleStatus::tryFrom($validated['schedule_status'] ?? '')
                    ?? OfficialFeeScheduleStatus::Active,
            ],
        );

        OfficialFeeConcept::query()->updateOrCreate(
            [
                'official_fee_schedule_id' => $schedule->id,
                'code' => $validated['code'],
            ],
            [
                'name' => $validated['name'],
                'amount_pesos' => $validated['amount_pesos'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ],
        );

        return to_route('finance.charge-concepts.index');
    }
}
