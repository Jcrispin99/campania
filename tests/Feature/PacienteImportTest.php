<?php

declare(strict_types=1);

use App\Models\Paciente;
use App\Services\PacienteExcelImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/** @param list<list<string>> $rows */
function patientWorkbook(array $rows): string
{
    $headers = ['N°', 'Nombre Completo', 'Nº de documento', 'Nombre posición', 'Nombre de Area', 'Nombre unidad org.', 'TELEFONO', 'CORREO ELECTRONICO', 'ASISTENCIA', 'PRESION ARTERIAL', 'FECHA'];
    $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ([$headers, ...$rows] as $index => $row) {
        $number = $index + 1;
        $xml .= '<row r="'.$number.'">';
        foreach ($row as $column => $value) {
            $xml .= '<c r="'.chr(65 + $column).$number.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
        }
        $xml .= '</row>';
    }
    $xml .= '</sheetData></worksheet>';
    $path = tempnam(sys_get_temp_dir(), 'pacientes-test-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
    $zip->close();

    return $path;
}

it('imports the spreadsheet columns and skips empty numbered rows', function (): void {
    $path = patientWorkbook([
        ['1', 'Ana Torres', '01234567', 'Analista', 'Operaciones', 'Lima', '987654321', 'ana@example.com', 'Sí', '120/80', '46310'],
        ['2', '', ''],
    ]);

    try {
        $this->artisan('pacientes:importar', ['archivo' => $path])->assertSuccessful();
        $this->assertDatabaseHas('pacientes', [
            'numero' => 1, 'nombre_completo' => 'Ana Torres', 'dni' => '01234567',
            'nombre_posicion' => 'Analista', 'nombre_area' => 'Operaciones',
            'nombre_unidad_org' => 'Lima', 'telefono' => '987654321',
            'correo_electronico' => 'ana@example.com', 'asistencia' => 'Sí', 'presion_arterial' => '120/80',
            'fecha' => '2026-10-15 00:00:00',
        ]);
        $this->assertDatabaseCount('pacientes', 1);
    } finally {
        unlink($path);
    }
});

it('does not duplicate or overwrite an existing patient when importing again', function (): void {
    $patient = Paciente::factory()->create(['dni' => '01234567', 'telefono' => '999888777']);
    $path = patientWorkbook([['1', 'Nombre en Excel', '01234567']]);

    try {
        app(PacienteExcelImporter::class)->import($path);
        app(PacienteExcelImporter::class)->import($path);

        $this->assertDatabaseCount('pacientes', 1);
        expect($patient->fresh()->telefono)->toBe('999888777');
    } finally {
        unlink($path);
    }
});

it('rejects an invalid row atomically without partially importing the spreadsheet', function (): void {
    $path = patientWorkbook([['1', 'Paciente válido', '01234567'], ['2', 'DNI inválido', 'abc']]);

    try {
        expect(fn () => app(PacienteExcelImporter::class)->import($path))->toThrow(ValidationException::class);
        $this->assertDatabaseCount('pacientes', 0);
    } finally {
        unlink($path);
    }
});

it('rejects duplicated documents inside the workbook', function (): void {
    $path = patientWorkbook([['1', 'Ana Torres', '01234567'], ['2', 'Otro nombre', '01234567']]);

    try {
        expect(fn () => app(PacienteExcelImporter::class)->import($path))->toThrow(ValidationException::class);
        $this->assertDatabaseCount('pacientes', 0);
    } finally {
        unlink($path);
    }
});

it('returns a command error for a missing workbook', function (): void {
    $this->artisan('pacientes:importar', ['archivo' => '/missing/patients.xlsx'])->assertFailed();
});
