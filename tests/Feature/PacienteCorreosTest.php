<?php

declare(strict_types=1);

use App\Models\Paciente;
use App\Models\PacienteCorreo;
use App\Models\User;
use App\Services\PacienteCorreoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication for editing patient emails', function (): void {
    $patient = Paciente::factory()->create();
    $this->putJson('/api/v1/pacientes/'.$patient->id.'/correos', ['correos' => []])->assertUnauthorized();
});

it('creates a patient with several unique emails and exactly one default', function (): void {
    $this->actingAs(User::factory()->create())->postJson('/api/v1/pacientes', [
        'nombre_completo' => 'Ana Torres', 'dni' => '01234567',
        'correos' => [
            ['correo' => 'ANA@example.com', 'predeterminado' => false],
            ['correo' => 'trabajo@example.com', 'predeterminado' => true],
        ],
    ])->assertCreated()->assertJsonPath('data.correo_electronico', 'trabajo@example.com')
        ->assertJsonCount(2, 'data.correos')->assertJsonPath('data.correos.0.predeterminado', true);

    $this->assertDatabaseHas('paciente_correos', ['correo' => 'ana@example.com', 'predeterminado' => false]);
    expect(PacienteCorreo::where('predeterminado', true)->count())->toBe(1);
});

it('allows a patient without email and never stores a blank address', function (): void {
    $this->actingAs(User::factory()->create())->postJson('/api/v1/pacientes', [
        'nombre_completo' => 'Sin correo', 'dni' => '01234567', 'correos' => [],
    ])->assertCreated()->assertJsonPath('data.correo_electronico', null)->assertJsonCount(0, 'data.correos');
    $this->assertDatabaseCount('paciente_correos', 0);
});

it('edits emails separately from attention and keeps only the selected default', function (): void {
    $patient = Paciente::factory()->create();
    app(PacienteCorreoService::class)->sync($patient, [
        ['correo' => 'old@example.com', 'predeterminado' => true],
        ['correo' => 'second@example.com', 'predeterminado' => false],
    ]);
    $this->actingAs(User::factory()->create())->putJson('/api/v1/pacientes/'.$patient->id.'/correos', [
        'correos' => [
            ['correo' => 'edited@example.com', 'predeterminado' => false],
            ['correo' => 'second@example.com', 'predeterminado' => true],
        ],
    ])->assertOk()->assertJsonPath('data.correo_electronico', 'second@example.com')->assertJsonCount(2, 'data.correos');
    $this->assertDatabaseMissing('paciente_correos', ['correo' => 'old@example.com']);
    $this->assertDatabaseCount('atenciones', 0);
    expect($patient->correos()->where('predeterminado', true)->count())->toBe(1);
});

it('rejects an email owned by a different patient and rolls back attention and demographic changes', function (): void {
    $owner = Paciente::factory()->create();
    app(PacienteCorreoService::class)->sync($owner, [['correo' => 'shared@example.com', 'predeterminado' => true]]);
    $patient = Paciente::factory()->create(['edad' => 30]);
    $this->actingAs(User::factory()->create())->postJson('/api/v1/atenciones', [
        'dni' => $patient->dni, 'edad' => 60, 'valor_1' => '120/80', 'fecha' => '2026-10-20',
        'correos' => [['correo' => 'SHARED@example.com', 'predeterminado' => true]],
    ])->assertUnprocessable()->assertJsonValidationErrors('correos');
    expect($patient->fresh()->edad)->toBe(30);
    $this->assertDatabaseCount('atenciones', 0);
    $this->assertDatabaseCount('paciente_correos', 1);
});

it('rejects duplicate addresses and multiple defaults without altering existing emails', function (): void {
    $patient = Paciente::factory()->create();
    $this->actingAs(User::factory()->create());
    foreach ([
        [['correo' => 'a@example.com', 'predeterminado' => true], ['correo' => 'A@example.com', 'predeterminado' => false]],
        [['correo' => 'a@example.com', 'predeterminado' => true], ['correo' => 'b@example.com', 'predeterminado' => true]],
        [['correo' => '', 'predeterminado' => true]],
        [['correo' => 'sin-arroba.com', 'predeterminado' => true]],
    ] as $rows) {
        $this->putJson('/api/v1/pacientes/'.$patient->id.'/correos', ['correos' => $rows])->assertUnprocessable();
    }
    $this->assertDatabaseCount('paciente_correos', 0);
});

it('assigns a default when none was marked and can clear the email list', function (): void {
    $patient = Paciente::factory()->create();
    $this->actingAs(User::factory()->create())->putJson('/api/v1/pacientes/'.$patient->id.'/correos', [
        'correos' => [['correo' => 'a@example.com', 'predeterminado' => false]],
    ])->assertOk()->assertJsonPath('data.correo_electronico', 'a@example.com');
    $this->putJson('/api/v1/pacientes/'.$patient->id.'/correos', ['correos' => []])
        ->assertOk()->assertJsonPath('data.correo_electronico', null)->assertJsonCount(0, 'data.correos');
});

it('preserves all alternatives when registering an attention with an updated default', function (): void {
    $patient = Paciente::factory()->create();
    $this->actingAs(User::factory()->create())->postJson('/api/v1/atenciones', [
        'dni' => $patient->dni, 'edad' => 40, 'valor_1' => '120/80', 'fecha' => '2026-10-20',
        'correos' => [
            ['correo' => 'a@example.com', 'predeterminado' => false],
            ['correo' => 'b@example.com', 'predeterminado' => true],
        ],
    ])->assertCreated()->assertJsonPath('data.paciente.correo_electronico', 'b@example.com')
        ->assertJsonCount(2, 'data.paciente.correos');
});
