<?php

namespace Database\Factories\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierDocumentStage;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueExpenseDossierDocument>
 */
class OwnRevenueExpenseDossierDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_expense_dossier_id' => OwnRevenueExpenseDossier::factory(),
            'stage' => OwnRevenueExpenseDossierDocumentStage::PaymentRequest,
            'original_name' => 'factura.pdf',
            'storage_disk' => 'local',
            'storage_path' => fn (): string => 'finance/own-revenue/testing/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1_000, 1_000_000),
            'uploaded_by' => User::factory(),
            'uploaded_at' => now(),
        ];
    }
}
