<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Paciente;
use Illuminate\Database\Seeder;

final class PacienteSeeder extends Seeder
{
    public function run(): void
    {
        Paciente::factory()->count(10)->create();
    }
}
