<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Atencion;
use Illuminate\Database\Seeder;

final class AtencionSeeder extends Seeder
{
    public function run(): void
    {
        Atencion::factory()->count(10)->create();
    }
}
