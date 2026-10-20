<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Paciente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

final class PacienteExcelImporter
{
    /** @var array<string, string> */
    private const COLUMNS = [
        'A' => 'numero', 'B' => 'nombre_completo', 'C' => 'dni',
        'D' => 'nombre_posicion', 'E' => 'nombre_area', 'F' => 'nombre_unidad_org',
        'G' => 'telefono', 'H' => 'correo_electronico', 'I' => 'asistencia',
        'J' => 'presion_arterial', 'K' => 'fecha',
    ];

    /** @var list<string> */
    private const HEADERS = ['N°', 'Nombre Completo', 'Nº de documento', 'Nombre posición', 'Nombre de Area', 'Nombre unidad org.', 'TELEFONO', 'CORREO ELECTRONICO', 'ASISTENCIA', 'PRESION ARTERIAL', 'FECHA'];

    /** @return array{importados: int, omitidos: int} */
    public function import(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo abrir el archivo XLSX.']);
        }

        try {
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($this->xml($zip, 'xl/sharedStrings.xml')->si as $item) {
                    $strings[] = $this->text($item);
                }
            }
            $sheet = $this->xml($zip, 'xl/worksheets/sheet1.xml');
            $records = [];
            $skipped = 0;
            $seen = [];
            $hasHeaders = false;
            foreach ($sheet->sheetData->row as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    $column = preg_replace('/[0-9]+/', '', (string) $cell['r']);
                    $type = (string) $cell['t'];
                    $value = match ($type) {
                        's' => $strings[(int) $cell->v] ?? '',
                        'inlineStr' => $this->text($cell->is),
                        default => (string) $cell->v,
                    };
                    $values[$column] = mb_trim($value);
                }
                if (! $hasHeaders) {
                    $headers = array_map(fn (string $column): string => preg_replace('/\s+/u', ' ', $values[$column] ?? '') ?? '', array_keys(self::COLUMNS));
                    if ($headers !== self::HEADERS) {
                        throw ValidationException::withMessages(['archivo' => 'Las columnas no coinciden con la lista de trabajadores.']);
                    }
                    $hasHeaders = true;

                    continue;
                }
                if (($values['B'] ?? '') === '' && ($values['C'] ?? '') === '') {
                    $skipped++;

                    continue;
                }
                $record = [];
                foreach (self::COLUMNS as $column => $field) {
                    $record[$field] = ($values[$column] ?? '') !== '' ? $values[$column] : null;
                }
                if ($record['fecha'] !== null && is_numeric($record['fecha'])) {
                    $record['fecha'] = Carbon::parse('1899-12-30')->addDays((int) $record['fecha'])->format('Y-m-d');
                }
                $validator = Validator::make($record, [
                    'numero' => ['nullable', 'integer', 'min:1'],
                    'nombre_completo' => ['required', 'string', 'max:255'],
                    'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/'],
                    'nombre_posicion' => ['nullable', 'string', 'max:255'],
                    'nombre_area' => ['nullable', 'string', 'max:255'],
                    'nombre_unidad_org' => ['nullable', 'string', 'max:255'],
                    'telefono' => ['nullable', 'string', 'max:30'],
                    'correo_electronico' => ['nullable', 'string', 'max:255'],
                    'asistencia' => ['nullable', 'string', 'max:255'],
                    'presion_arterial' => ['nullable', 'string', 'max:255'],
                    'fecha' => ['nullable', 'date_format:Y-m-d'],
                ]);
                if ($validator->fails()) {
                    throw ValidationException::withMessages(['archivo' => 'Fila '.(string) $row['r'].': '.implode(' ', $validator->errors()->all())]);
                }
                if (isset($seen[$record['dni']])) {
                    throw ValidationException::withMessages(['archivo' => 'DNI repetido en la fila '.(string) $row['r'].'.']);
                }
                $seen[$record['dni']] = true;
                $records[] = $record;
            }
            if (! $hasHeaders || $records === []) {
                throw ValidationException::withMessages(['archivo' => 'El archivo no contiene pacientes.']);
            }
            DB::transaction(function () use ($records): void {
                foreach ($records as $record) {
                    Paciente::query()->firstOrCreate(['dni' => $record['dni']], $record);
                }
            });

            return ['importados' => count($records), 'omitidos' => $skipped];
        } finally {
            $zip->close();
        }
    }

    private function xml(ZipArchive $zip, string $entry): SimpleXMLElement
    {
        $stat = $zip->statName($entry);
        if ($stat === false || $stat['size'] > 30 * 1024 * 1024) {
            throw ValidationException::withMessages(['archivo' => 'El XLSX no es válido o excede el tamaño permitido.']);
        }
        $contents = $zip->getFromName($entry);
        if ($contents === false || mb_stripos($contents, '<!DOCTYPE') !== false) {
            throw ValidationException::withMessages(['archivo' => 'El XLSX contiene XML no permitido.']);
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false) {
                throw ValidationException::withMessages(['archivo' => 'El XLSX contiene XML inválido.']);
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function text(SimpleXMLElement $element): string
    {
        if (isset($element->t)) {
            return (string) $element->t;
        }
        $value = '';
        foreach ($element->r as $run) {
            $value .= (string) $run->t;
        }

        return $value;
    }
}
