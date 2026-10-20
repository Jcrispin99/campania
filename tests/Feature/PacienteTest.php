<?php

declare(strict_types=1);

use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('protects patient data from anonymous requests', function (): void {
    $this->getJson('/api/v1/pacientes')->assertUnauthorized();
    $this->getJson('/api/v1/pacientes/dni/01234567')->assertUnauthorized();
    $this->postJson('/api/v1/pacientes', [])->assertUnauthorized();
});

it('stores all campaign columns and preserves leading zeroes in the DNI', function (): void {
    $user = User::factory()->create();
    $payload = [
        'numero' => 1,
        'edad' => 35,
        'nombre_completo' => 'Ana Torres',
        'dni' => '01234567',
        'nombre_posicion' => 'Analista',
        'nombre_area' => 'Operaciones',
        'nombre_unidad_org' => 'Unidad Lima',
        'telefono' => '987654321',
        'correo_electronico' => 'ana@example.com',
        'asistencia' => 'Sí',
        'presion_arterial' => '120/80',
        'fecha' => '2026-10-15',
    ];

    $this->actingAs($user)->postJson('/api/v1/pacientes', $payload)
        ->assertCreated()->assertJsonPath('data.edad', 35)->assertJsonPath('data.correo_electronico', 'ana@example.com')->assertJsonPath('data.telefono', '987654321')->assertJsonPath('data.dni', '01234567')
        ->assertJsonPath('data.fecha', '2026-10-15');

    $this->assertDatabaseHas('pacientes', [...$payload, 'fecha' => '2026-10-15 00:00:00']);
});

it('finds patients by exact DNI and returns 404 when missing', function (): void {
    $user = User::factory()->create();
    Paciente::factory()->create(['dni' => '01234567', 'nombre_completo' => 'Ana Torres']);

    $this->actingAs($user)->getJson('/api/v1/pacientes/dni/01234567')
        ->assertOk()->assertJsonPath('data.nombre_completo', 'Ana Torres');
    $this->getJson('/api/v1/pacientes/dni/99999999')->assertNotFound();
});

it('rejects duplicate DNI and invalid patient input', function (): void {
    $user = User::factory()->create();
    Paciente::factory()->create(['dni' => '01234567']);

    $this->actingAs($user)->postJson('/api/v1/pacientes', [
        'nombre_completo' => 'Otro paciente', 'dni' => '01234567',
    ])->assertUnprocessable()->assertJsonValidationErrors('dni')
        ->assertJsonPath('errors.dni.0', 'Ya existe un paciente con este DNI.');
    $this->postJson('/api/v1/pacientes', [
        'dni' => '123', 'correo_electronico' => ['incorrecto'], 'fecha' => 'no-fecha',
    ])->assertUnprocessable()->assertJsonValidationErrors(['nombre_completo', 'dni', 'correo_electronico', 'fecha']);
    $this->assertDatabaseCount('pacientes', 1);
});

it('updates a patient while retaining its own DNI and searches paginated results', function (): void {
    $user = User::factory()->create();
    $paciente = Paciente::factory()->create(['dni' => '01234567']);
    Paciente::factory()->create(['dni' => '87654321']);

    $this->actingAs($user)->putJson('/api/v1/pacientes/'.$paciente->id, [
        'nombre_completo' => 'Nombre actualizado', 'dni' => '01234567',
    ])->assertOk()->assertJsonPath('data.nombre_completo', 'Nombre actualizado');
    $this->getJson('/api/v1/pacientes?search=01234567')->assertOk()
        ->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.dni', '01234567');
    $this->assertDatabaseHas('pacientes', ['id' => $paciente->id, 'nombre_completo' => 'Nombre actualizado']);
});

it('limits search results per page and lists the closest matches first', function (): void {
    $this->actingAs(User::factory()->create());
    Paciente::factory()->create(['dni' => '10000075', 'nombre_completo' => 'Aguilar Rojas, Ana']);
    Paciente::factory()->create(['dni' => '75000001', 'nombre_completo' => 'Zapata Luna, Rosa']);
    Paciente::factory()->create(['dni' => '20075000', 'nombre_completo' => 'Benites Cruz, Luis']);
    foreach (range(1, 6) as $number) {
        Paciente::factory()->create(['dni' => sprintf('3%02d75000', $number), 'nombre_completo' => "Paciente {$number}"]);
    }

    $this->getJson('/api/v1/pacientes?search=75&per_page=5')->assertOk()
        ->assertJsonCount(5, 'data.items')->assertJsonPath('data.total', 9)
        ->assertJsonPath('data.last_page', 2)->assertJsonPath('data.items.0.dni', '75000001');
    $this->getJson('/api/v1/pacientes?search=75&per_page=5&page=2')->assertOk()
        ->assertJsonCount(4, 'data.items');
    $this->getJson('/api/v1/pacientes?search=luna')->assertOk()
        ->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.dni', '75000001');
    $this->getJson('/api/v1/pacientes?search=be')->assertOk()
        ->assertJsonPath('data.items.0.nombre_completo', 'Benites Cruz, Luis');
    $this->getJson('/api/v1/pacientes?per_page=100')->assertUnprocessable()->assertJsonValidationErrors(['per_page']);
});

it('rejects invalid ages without storing a patient', function (): void {
    $this->actingAs(User::factory()->create());
    foreach ([-1, 121, 25.5, 'abc'] as $edad) {
        $this->postJson('/api/v1/pacientes', [
            'nombre_completo' => 'Ana Torres', 'dni' => '01234567', 'edad' => $edad,
        ])->assertUnprocessable()->assertJsonValidationErrors('edad');
    }
    $this->assertDatabaseCount('pacientes', 0);
});
