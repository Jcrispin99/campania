<?php

declare(strict_types=1);

use App\Models\Atencion;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication for attention endpoints', function (): void {
    $this->getJson('/api/v1/atenciones')->assertUnauthorized();
    $this->postJson('/api/v1/atenciones', [])->assertUnauthorized();
});

it('registers multiple measurements by DNI and uses the authenticated operator', function (): void {
    $user = User::factory()->create();
    $paciente = Paciente::factory()->create(['dni' => '01234567']);
    Atencion::factory()->for($paciente)->for($user)->create();

    $this->actingAs($user)->postJson('/api/v1/atenciones', [
        'dni' => '01234567', 'valor_1' => '120/80 mmHg', 'fecha' => '2026-10-15',
        'user_id' => 999, 'paciente_id' => 999,
    ])->assertCreated()->assertJsonPath('data.valor_1', '120/80 mmHg')
        ->assertJsonPath('data.paciente.dni', '01234567')
        ->assertJsonPath('data.registrado_por', $user->name);

    $this->assertDatabaseHas('atenciones', [
        'paciente_id' => $paciente->id, 'user_id' => $user->id,
        'valor_1' => '120/80 mmHg', 'fecha' => '2026-10-15 00:00:00',
    ]);
    $this->assertDatabaseCount('atenciones', 2);
});

it('rejects an unknown DNI without creating an attention', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/atenciones', [
        'dni' => '99999999', 'valor_1' => '120/80', 'fecha' => '2026-10-15',
    ])->assertUnprocessable()->assertJsonPath('errors.dni.0', 'No existe un paciente con este DNI.');

    $this->assertDatabaseCount('atenciones', 0);
});

it('rejects non textual or missing measurements and invalid dates', function (): void {
    $user = User::factory()->create();
    Paciente::factory()->create(['dni' => '01234567']);

    $this->actingAs($user)->postJson('/api/v1/atenciones', [
        'dni' => '01234567', 'valor_1' => 120, 'fecha' => 'incorrecta',
    ])->assertUnprocessable()->assertJsonValidationErrors(['valor_1', 'fecha'])
        ->assertJsonPath('errors.valor_1.0', 'La medición debe ser un texto.');
    $this->postJson('/api/v1/atenciones', [])->assertUnprocessable()
        ->assertJsonValidationErrors(['dni', 'valor_1', 'fecha']);
    $this->assertDatabaseCount('atenciones', 0);
});

it('filters attention history by exact patient DNI', function (): void {
    $user = User::factory()->create();
    $paciente = Paciente::factory()->create(['dni' => '01234567']);
    Atencion::factory()->for($paciente)->for($user)->create(['valor_1' => '118/79']);
    Atencion::factory()->for($user)->create();

    $this->actingAs($user)->getJson('/api/v1/atenciones?dni=01234567')
        ->assertOk()->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.items.0.valor_1', '118/79');
});
