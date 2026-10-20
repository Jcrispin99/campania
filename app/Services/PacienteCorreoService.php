<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Paciente;
use App\Models\PacienteCorreo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PacienteCorreoService
{
    /** @param array<string, mixed> $data */
    public function apply(Paciente $patient, array $data): Paciente
    {
        if (array_key_exists('correos', $data)) {
            /** @var list<array{correo: string, predeterminado: bool}> $rows */
            $rows = $data['correos'];

            return $this->sync($patient, $rows);
        }
        // Compatibility for older clients using the original single-email field.
        if (array_key_exists('correo_electronico', $data)) {
            /** @var string|null $legacyEmail */
            $legacyEmail = $data['correo_electronico'];
            $email = mb_strtolower(mb_trim($legacyEmail ?? ''));
            $rows = $email === '' ? [] : $patient->correos()->get()->map(fn ($row) => [
                'correo' => $row->correo, 'predeterminado' => false,
            ])->all();
            if ($email !== '') {
                $rows = array_values(array_filter($rows, fn ($row) => $row['correo'] !== $email));
                $rows[] = ['correo' => $email, 'predeterminado' => true];
            }

            return $this->sync($patient, $rows);
        }

        return $patient->load('correos');
    }

    /** @param array<int, array{correo: string, predeterminado: bool}> $rows */
    public function sync(Paciente $patient, array $rows): Paciente
    {
        try {
            return DB::transaction(function () use ($patient, $rows): Paciente {
                $patient = Paciente::query()->lockForUpdate()->findOrFail($patient->id);
                $normalized = [];
                foreach ($rows as $index => $row) {
                    $email = mb_strtolower(mb_trim($row['correo']));
                    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 254) {
                        throw ValidationException::withMessages(["correos.{$index}.correo" => 'Ingresa un correo electrónico válido.']);
                    }
                    if (isset($normalized[$email])) {
                        throw ValidationException::withMessages(['correos' => 'No repitas un correo en el paciente.']);
                    }
                    $normalized[$email] = (bool) $row['predeterminado'];
                }
                if (count(array_filter($normalized)) > 1) {
                    throw ValidationException::withMessages(['correos' => 'Solo puedes marcar un correo predeterminado.']);
                }
                if ($normalized !== [] && ! in_array(true, $normalized, true)) {
                    $normalized[array_key_first($normalized)] = true;
                }
                $owned = PacienteCorreo::query()->whereIn('correo', array_keys($normalized))->lockForUpdate()->get();
                foreach ($owned as $email) {
                    if ($email->paciente_id !== $patient->id) {
                        throw ValidationException::withMessages(['correos' => "El correo {$email->correo} ya pertenece a otro paciente."]);
                    }
                }

                $patient->correos()->whereNotIn('correo', array_keys($normalized))->delete();
                $patient->correos()->update(['predeterminado' => false]);
                foreach ($normalized as $email => $default) {
                    $patient->correos()->updateOrCreate(['correo' => $email], ['predeterminado' => $default]);
                }
                $default = array_search(true, $normalized, true);
                $patient->update(['correo_electronico' => $default === false ? null : $default]);

                return $patient->load('correos');
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['correos' => 'Un correo ya pertenece a otro paciente. Actualiza e inténtalo de nuevo.']);
        }
    }
}
