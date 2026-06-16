<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\UpdateChargeConceptOfficialLinkRequest;
use App\Models\ChargeConcept;
use App\Models\ChargeConceptOfficialLink;
use Illuminate\Http\RedirectResponse;

class ChargeConceptOfficialLinkController extends Controller
{
    public function update(UpdateChargeConceptOfficialLinkRequest $request, ChargeConcept $chargeConcept): RedirectResponse
    {
        $validated = $request->validated();

        ChargeConceptOfficialLink::query()->updateOrCreate(
            [
                'charge_concept_id' => $chargeConcept->id,
                'fiscal_year' => $validated['fiscal_year'],
            ],
            [
                'status' => $validated['status'],
                'official_fee_concept_id' => $validated['official_fee_concept_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ],
        );

        return to_route('finance.charge-concepts.index');
    }
}
