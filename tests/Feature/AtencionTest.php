<?php

declare(strict_types=1);

use App\Models\Atencion;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows attention endpoints without a login', function (): void {
    $this->getJson('/api/v1/atenciones')->assertOk();
    $this->postJson('/api/v1/atenciones', [])->assertUnprocessable();
});

it('registers multiple measurements by DNI and uses the authenticated operator', function (): void {
    $user = User::factory()->create();
    $paciente = Paciente::factory()->create(['dni' => '01234567']);
    Atencion::factory()->for($paciente)->for($user)->create();

    $this->actingAs($user)->postJson('/api/v1/atenciones', [
        'dni' => '01234567', 'edad' => 40, 'valor_1' => '120/80 mmHg', 'fecha' => '2026-10-15',
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
        'dni' => '99999999', 'edad' => 40, 'valor_1' => '120/80', 'fecha' => '2026-10-15',
    ])->assertUnprocessable()->assertJsonPath('errors.dni.0', 'No existe un paciente con este DNI.');

    $this->assertDatabaseCount('atenciones', 0);
});

it('rejects non textual or missing measurements and invalid dates', function (): void {
    $user = User::factory()->create();
    Paciente::factory()->create(['dni' => '01234567']);

    $this->actingAs($user)->postJson('/api/v1/atenciones', [
        'dni' => '01234567', 'edad' => 40, 'valor_1' => 120, 'fecha' => 'incorrecta',
    ])->assertUnprocessable()->assertJsonValidationErrors(['valor_1', 'fecha'])
        ->assertJsonPath('errors.valor_1.0', 'La presión arterial debe ser un texto.');
    $this->postJson('/api/v1/atenciones', [])->assertUnprocessable()
        ->assertJsonValidationErrors(['dni', 'fecha']);
    $this->assertDatabaseCount('atenciones', 0);
});

it('stores conditions and notes and updates the patient age and email', function (): void {
    $user = User::factory()->create();
    $paciente = Paciente::factory()->create(['dni' => '01234567', 'edad' => null, 'correo_electronico' => null]);

    $this->actingAs($user)->postJson('/api/v1/atenciones', [
        'dni' => '01234567', 'edad' => 52, 'correo_electronico' => 'paciente@example.com',
        'valor_1' => '140/90', 'diabetes' => true, 'hipertension' => true, 'ayuna' => false,
        'otros' => 'Toma metformina', 'fecha' => '2026-10-20',
    ])->assertCreated()->assertJsonPath('data.diabetes', true)
        ->assertJsonPath('data.hipertension', true)->assertJsonPath('data.ayuna', false)
        ->assertJsonPath('data.otros', 'Toma metformina')
        ->assertJsonPath('data.paciente.edad', 52)
        ->assertJsonPath('data.paciente.correo_electronico', 'paciente@example.com');

    expect($paciente->refresh())->edad->toBe(52)->correo_electronico->toBe('paciente@example.com');
});

it('defaults conditions to false and keeps the email when it is not sent', function (): void {
    $user = User::factory()->create();
    $paciente = Paciente::factory()->create(['dni' => '01234567', 'correo_electronico' => 'previo@example.com']);

    $this->actingAs($user)->postJson('/api/v1/atenciones', [
        'dni' => '01234567', 'edad' => 30, 'valor_1' => '110/70', 'fecha' => '2026-10-20',
    ])->assertCreated()->assertJsonPath('data.diabetes', false)
        ->assertJsonPath('data.hipertension', false)->assertJsonPath('data.ayuna', false)
        ->assertJsonPath('data.otros', null);

    expect($paciente->refresh()->correo_electronico)->toBe('previo@example.com');
});

it('clears the patient email when it is sent empty', function (): void {
    $user = User::factory()->create();
    $paciente = Paciente::factory()->create(['dni' => '01234567', 'correo_electronico' => 'previo@example.com']);

    $this->actingAs($user)->postJson('/api/v1/atenciones', [
        'dni' => '01234567', 'edad' => 30, 'correo_electronico' => '', 'valor_1' => '110/70', 'fecha' => '2026-10-20',
    ])->assertCreated();

    expect($paciente->refresh()->correo_electronico)->toBeNull();
});

it('rejects an invalid age or email without changing the patient', function (): void {
    $user = User::factory()->create();
    $paciente = Paciente::factory()->create(['dni' => '01234567', 'edad' => 40, 'correo_electronico' => null]);

    $this->actingAs($user)->postJson('/api/v1/atenciones', [
        'dni' => '01234567', 'edad' => 150, 'correo_electronico' => 'no-es-correo',
        'valor_1' => '120/80', 'diabetes' => 'quizas', 'fecha' => '2026-10-20',
    ])->assertUnprocessable()->assertJsonValidationErrors(['edad', 'correo_electronico', 'diabetes'])
        ->assertJsonPath('errors.edad.0', 'Ingresa una edad válida entre 0 y 120 años.')
        ->assertJsonPath('errors.correo_electronico.0', 'Ingresa un correo electrónico válido.');

    expect($paciente->refresh())->edad->toBe(40)->correo_electronico->toBeNull();
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

it('searches attention history by partial patient name or DNI', function (): void {
    $user = User::factory()->create();
    $ana = Paciente::factory()->create(['dni' => '01234567', 'nombre_completo' => 'Ana Torres Ruiz']);
    $luis = Paciente::factory()->create(['dni' => '76543210', 'nombre_completo' => 'Luis Vega Soto']);
    Atencion::factory()->for($ana)->for($user)->create(['valor_1' => '118/79', 'diabetes' => true]);
    Atencion::factory()->for($luis)->for($user)->create(['valor_1' => '130/85']);

    $this->actingAs($user)->getJson('/api/v1/atenciones?search=torres')
        ->assertOk()->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.items.0.valor_1', '118/79')
        ->assertJsonPath('data.items.0.diabetes', true)
        ->assertJsonPath('data.items.0.paciente.nombre_completo', 'Ana Torres Ruiz');
    $this->getJson('/api/v1/atenciones?search=7654')
        ->assertOk()->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.items.0.valor_1', '130/85');
    $this->getJson('/api/v1/atenciones')->assertOk()->assertJsonPath('data.total', 2);
});
