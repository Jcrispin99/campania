<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PacienteExcelImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class ImportPacientes extends Command
{
    protected $signature = 'pacientes:importar {archivo : Ruta del XLSX de trabajadores}';

    protected $description = 'Importa pacientes sin duplicar ni sobrescribir los DNI existentes';

    public function handle(PacienteExcelImporter $importer): int
    {
        $path = $this->argument('archivo');
        if (! is_file($path)) {
            $this->error('No se encontró el archivo indicado.');

            return self::FAILURE;
        }
        try {
            $result = $importer->import($path);
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
        $this->info("Pacientes procesados: {$result['importados']}. Filas vacías omitidas: {$result['omitidos']}.");

        return self::SUCCESS;
    }
}
