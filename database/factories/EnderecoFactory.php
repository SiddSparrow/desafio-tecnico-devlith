<?php

namespace Database\Factories;

use App\Models\Endereco;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Endereco>
 */
class EnderecoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'logradouro' => fake()->streetAddress(),
            'cep'        => fake()->numerify('########'), // 8 dígitos
        ];
    }
}
