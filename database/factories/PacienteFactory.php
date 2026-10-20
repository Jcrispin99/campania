<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Paciente;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Paciente> */
final class PacienteFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nombre_completo' => fake()->name(),
            'dni' => fake()->unique()->numerify('########'),
            'nombre_area' => fake()->word(),
            'telefono' => fake()->numerify('9########'),
        ];
    }
}
