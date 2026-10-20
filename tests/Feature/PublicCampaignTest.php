<?php

declare(strict_types=1);

use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers an anonymous sample without age or blood pressure', function (): void {
    $patient = Paciente::factory()->create(['edad' => null]);
    $this->postJson('/api/v1/atenciones', [
        'dni' => $patient->dni, 'fecha' => '2026-10-20',
    ])->assertCreated()->assertJsonPath('data.valor_1', null)
        ->assertJsonPath('data.registrado_por', null)->assertJsonPath('data.paciente.edad', null);
    $this->assertDatabaseHas('atenciones', [
        'paciente_id' => $patient->id, 'user_id' => null, 'valor_1' => null,
    ]);
    $this->getJson('/api/v1/atenciones')->assertOk()->assertJsonPath('data.items.0.registrado_por', null);
});

it('accepts empty optional sample fields and preserves a previously recorded age', function (): void {
    $patient = Paciente::factory()->create(['edad' => 45]);
    $this->postJson('/api/v1/atenciones', [
        'dni' => $patient->dni, 'fecha' => '2026-10-20', 'edad' => '', 'valor_1' => '',
    ])->assertCreated()->assertJsonPath('data.valor_1', null)->assertJsonPath('data.paciente.edad', 45);
    expect($patient->fresh()->edad)->toBe(45);
});

it('validates optional fields when they contain a value', function (): void {
    $patient = Paciente::factory()->create();
    $this->postJson('/api/v1/atenciones', [
        'dni' => $patient->dni, 'fecha' => '2026-10-20', 'edad' => 'abc', 'valor_1' => ['invalid'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['edad', 'valor_1']);
    $this->assertDatabaseCount('atenciones', 0);
});

it('supports patient creation, email editing and sample registration without credentials', function (): void {
    $response = $this->postJson('/api/v1/pacientes', [
        'nombre_completo' => 'Paciente de campaña', 'dni' => '01234567', 'correos' => [],
    ])->assertCreated();
    $id = $response->json('data.id');
    $this->putJson('/api/v1/pacientes/'.$id.'/correos', [
        'correos' => [['correo' => 'paciente@example.com', 'predeterminado' => true]],
    ])->assertOk();
    $this->postJson('/api/v1/atenciones', [
        'dni' => '01234567', 'fecha' => '2026-10-20', 'diabetes' => true,
    ])->assertCreated()->assertJsonPath('data.diabetes', true)
        ->assertJsonPath('data.paciente.correo_electronico', 'paciente@example.com');
    // Account management is outside the public campaign workflow.
    $this->getJson('/api/v1/users')->assertUnauthorized();
});
