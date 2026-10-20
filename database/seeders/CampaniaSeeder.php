<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

final class CampaniaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        if (! User::query()->where('email', 'test@example.com')->exists()) {
            User::factory()->create(['name' => 'Operador de campaña', 'email' => 'test@example.com']);
        }
    }
}
