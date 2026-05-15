<?php

namespace Database\Factories;

use App\Enums\ResultadoFinal;
use App\Enums\SerieEscolar;
use App\Enums\StatusMatricula;
use App\Models\Escola;
use App\Models\Matricula;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matricula>
 */
class MatriculaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'escola_id'       => Escola::factory(),
            'serie_escolar'   => fake()->randomElement(SerieEscolar::cases())->value,
            'ano_letivo'      => fake()->numberBetween(2020, 2025),
            'data_de_criacao' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'status'          => StatusMatricula::Ativo->value,
            'resultado_final'  => ResultadoFinal::EmAberto->value,
        ];
    }

    public function aprovado(): static
    {
        return $this->state(['resultado_final' => ResultadoFinal::Aprovado->value]);
    }

    public function reprovado(): static
    {
        return $this->state(['resultado_final' => ResultadoFinal::Reprovado->value]);
    }
}
