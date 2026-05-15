<?php

namespace Database\Factories;

use App\Models\Documento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Documento>
 */
class DocumentoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cpf'     => fake()->numerify('###########'), // 11 dígitos
            'rg'      => fake()->numerify('#########'),   // 9 dígitos: XX.XXX.XXX-X
        ];
    }
}
