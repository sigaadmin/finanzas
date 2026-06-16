<?php

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\ReceiptCancellation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptCancellation>
 */
class ReceiptCancellationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_id' => Receipt::factory(),
            'cancelled_by' => User::factory(),
            'reason' => fake()->sentence(),
            'cancelled_at' => now(),
        ];
    }
}
