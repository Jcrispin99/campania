<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

final class CreateCampaniaUser extends Command
{
    protected $signature = 'campania:crear-usuario {email} {--nombre=Operador de campaña}';

    protected $description = 'Crea un usuario de campaña con una contraseña ingresada de forma oculta';

    public function handle(): int
    {
        $email = $this->argument('email');
        if (User::query()->where('email', $email)->exists()) {
            $this->error('Ese correo ya tiene una cuenta. No se modificó su contraseña.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->option('nombre'),
            'email' => $email,
            'password' => $this->secret('Contraseña (mínimo 12 caracteres)'),
            'password_confirmation' => $this->secret('Repite la contraseña'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        unset($data['password_confirmation']);
        User::query()->create($data);
        $this->info('Usuario creado. Ya puedes iniciar sesión en la web.');

        return self::SUCCESS;
    }
}
