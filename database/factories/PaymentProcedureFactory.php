<?php

namespace Database\Factories;

use App\Enums\Finance\PaymentProcedureStatus;
use App\Models\PaymentProcedure;
use App\Models\StudentSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentProcedure>
 */
class PaymentProcedureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_snapshot_id' => StudentSnapshot::factory(),
            'created_by' => User::factory(),
            'status' => PaymentProcedureStatus::Draft,
            'total_pesos' => 0,
            'paid_at' => null,
            'cancelled_at' => null,
        ];
    }
}
