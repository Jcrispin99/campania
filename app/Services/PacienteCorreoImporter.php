<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Paciente;
use App\Models\PacienteCorreo;
use Illuminate\Validation\ValidationException;

final class PacienteCorreoImporter
{
    public function __construct(private SimpleXlsxReader $reader, private PacienteCorreoService $emails) {}

    /** @return array{simulacion: bool, filas: int, pacientes_cruzados: int, correos_agregados: int, correos_existentes: int, sin_paciente: int, direcciones_en_conflicto: int, incidencias: list<array<string, mixed>>} */
    public function import(string $path, bool $simulate = false): array
    {
        $report = [
            'simulacion' => $simulate, 'filas' => 0, 'pacientes_cruzados' => 0,
            'correos_agregados' => 0, 'correos_existentes' => 0, 'sin_paciente' => 0,
            'direcciones_en_conflicto' => 0, 'incidencias' => [],
        ];
        $groups = [];
        $owners = [];
        $headers = false;
        foreach ($this->reader->rows($path) as $row => $values) {
            if (! $headers) {
                $emailHeader = mb_strtolower($values['B'] ?? '');
                if (mb_strtolower($values['A'] ?? '') !== 'dni' || ! in_array($emailHeader, ['correo electrónico', 'correo electronico'], true)) {
                    throw ValidationException::withMessages(['archivo' => 'El Excel debe tener DNI y Correo Electrónico en las columnas A y B.']);
                }
                $headers = true;

                continue;
            }
            $report['filas']++;
            $dni = $values['A'] ?? '';
            if (! preg_match('/^[0-9]{1,8}$/', $dni)) {
                $report['incidencias'][] = ['fila' => $row, 'dni' => $dni, 'motivo' => 'dni_invalido'];

                continue;
            }
            $dni = mb_str_pad($dni, 8, '0', STR_PAD_LEFT);
            $groups[$dni] ??= ['filas' => [], 'correos' => []];
            $groups[$dni]['filas'][] = $row;
            foreach (preg_split('/[;\r\n]+/', $values['B'] ?? '') ?: [] as $token) {
                $email = mb_strtolower(mb_trim($token));
                if ($email === '') {
                    continue;
                }
                // A display name in angle brackets identifies an explicit address;
                // missing @ signs and concatenated addresses are never guessed.
                if (preg_match('/^[^<>]*<([^<>]+)>\s*$/u', $email, $match)) {
                    $email = mb_trim($match[1]);
                }
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 254) {
                    $report['incidencias'][] = ['fila' => $row, 'dni' => $dni, 'valor' => $token, 'motivo' => 'sin_correo_valido'];

                    continue;
                }
                $groups[$dni]['correos'][$email] = $row;
                $owners[$email][$dni] = true;
            }
        }
        if (! $headers || $report['filas'] === 0) {
            throw ValidationException::withMessages(['archivo' => 'El archivo no contiene registros.']);
        }
        $conflicts = array_filter($owners, fn ($documents) => count($documents) > 1);
        $report['direcciones_en_conflicto'] = count($conflicts);
        $patients = Paciente::query()->with('correos')->whereIn('dni', array_keys($groups))->get()->keyBy('dni');
        $registered = PacienteCorreo::query()->whereIn('correo', array_keys($owners))->get()->keyBy('correo');
        foreach ($groups as $dni => $group) {
            $patient = $patients->get($dni);
            if ($patient === null) {
                $report['sin_paciente']++;
                $report['incidencias'][] = ['filas' => $group['filas'], 'dni' => (string) $dni, 'motivo' => 'paciente_no_encontrado'];

                continue;
            }
            $report['pacientes_cruzados']++;
            $rows = $patient->correos->map(fn ($email) => ['correo' => $email->correo, 'predeterminado' => $email->predeterminado])->all();
            $existing = array_column($rows, 'correo');
            $added = 0;
            foreach ($group['correos'] as $email => $line) {
                if (isset($conflicts[$email])) {
                    $report['incidencias'][] = ['fila' => $line, 'dni' => (string) $dni, 'correo' => $email, 'motivo' => 'correo_en_varios_dni', 'dni_relacionados' => array_map('strval', array_keys($conflicts[$email]))];

                    continue;
                }
                $owner = $registered->get($email);
                if ($owner !== null && $owner->paciente_id !== $patient->id) {
                    $report['incidencias'][] = ['fila' => $line, 'dni' => (string) $dni, 'correo' => $email, 'motivo' => 'correo_pertenece_a_otro_paciente'];

                    continue;
                }
                if (in_array($email, $existing, true)) {
                    $report['correos_existentes']++;

                    continue;
                }
                $rows[] = ['correo' => $email, 'predeterminado' => false];
                $existing[] = $email;
                $added++;
            }
            if ($added > 0) {
                try {
                    if (! $simulate) {
                        $this->emails->sync($patient, $rows);
                    }
                    $report['correos_agregados'] += $added;
                } catch (ValidationException $exception) {
                    $report['incidencias'][] = ['dni' => (string) $dni, 'motivo' => 'conflicto_al_guardar', 'detalle' => $exception->errors()];
                }
            }
        }

        return $report;
    }
}
