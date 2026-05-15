<?php

namespace Database\Factories;

use App\Models\Escola;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Escola>
 */
class EscolaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => fake()->company() . ' Escola',
        ];
    }
}
