<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Atencion;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Atencion> */
final class AtencionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'paciente_id' => Paciente::factory(),
            'user_id' => User::factory(),
            'valor_1' => '120/80',
            'fecha' => fake()->date(),
        ];
    }
}
