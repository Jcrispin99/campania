<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('can disable public sign-up in production', function (): void {
    config(['campania.public_registration' => false]);
    $this->postJson('/api/v1/register', [
        'name' => 'Visitante', 'email' => 'visitante@example.com',
        'password' => 'ClaveSegura2026!', 'password_confirmation' => 'ClaveSegura2026!',
    ])->assertForbidden();
    $this->assertDatabaseCount('users', 0);
});

it('serves the compiled app for browser routes while keeping API and missing assets separate', function (): void {
    $public = sys_get_temp_dir().'/campania-web-'.bin2hex(random_bytes(8));
    mkdir($public);
    file_put_contents($public.'/app.html', '<html>Campaña web</html>');
    app()->usePublicPath($public);

    try {
        $this->get('/')->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->get('/module/atenciones')->assertOk();
        $this->get('/module/campania/pacientes')->assertOk();
        $this->getJson('/api/v1/pacientes')->assertUnauthorized();
        $this->getJson('/api/v1/no-existe')->assertNotFound();
        $this->get('/_expo/missing.js')->assertNotFound();
        $this->get('/missing.css')->assertNotFound();
    } finally {
        unlink($public.'/app.html');
        rmdir($public);
    }
});

it('creates the first campaign account without a demo password', function (): void {
    $this->artisan('campania:crear-usuario', ['email' => 'operador@example.com'])
        ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'ClaveSegura2026!')
        ->expectsQuestion('Repite la contraseña', 'ClaveSegura2026!')
        ->assertSuccessful();

    $user = User::query()->where('email', 'operador@example.com')->firstOrFail();
    expect(Hash::check('ClaveSegura2026!', $user->password))->toBeTrue();
    $this->artisan('campania:crear-usuario', ['email' => 'operador@example.com'])->assertFailed();
    expect($user->fresh()->password)->toBe($user->password);
});
