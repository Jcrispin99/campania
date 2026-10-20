<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PacienteCorreoImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class ImportPacienteCorreos extends Command
{
    protected $signature = 'pacientes:importar-correos {archivo} {--simular} {--reporte=}';

    protected $description = 'Cruza correos por DNI sin duplicar direcciones ni cambiar el predeterminado existente';

    public function handle(PacienteCorreoImporter $importer): int
    {
        if (! is_file($this->argument('archivo'))) {
            $this->error('No se encontró el XLSX.');

            return self::FAILURE;
        }
        try {
            $result = $importer->import($this->argument('archivo'), (bool) $this->option('simular'));
        } catch (ValidationException $exception) {
            /** @var array<string, list<string>> $errors */
            $errors = $exception->errors();
            foreach ($errors as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        if ($this->option('reporte')) {
            $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (file_put_contents($this->option('reporte'), $json) === false) {
                $this->error('No se pudo escribir el reporte.');

                return self::FAILURE;
            }
            chmod($this->option('reporte'), 0600);
        }
        $this->info(($result['simulacion'] ? 'Simulación. ' : '')."Pacientes cruzados: {$result['pacientes_cruzados']}. Correos agregados: {$result['correos_agregados']}. Ya existentes: {$result['correos_existentes']}. DNI sin paciente: {$result['sin_paciente']}. Direcciones en varios DNI: {$result['direcciones_en_conflicto']}.");

        return self::SUCCESS;
    }
}
