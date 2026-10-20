<?php

declare(strict_types=1);

use App\Models\Paciente;
use App\Models\PacienteCorreo;
use App\Services\PacienteCorreoImporter;
use App\Services\PacienteCorreoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function emailWorkbook(array $rows): string
{
    $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ([['DNI', 'Correo Electrónico'], ...$rows] as $index => $row) {
        $number = $index + 1;
        $xml .= '<row r="'.$number.'">';
        foreach ($row as $column => $value) {
            $xml .= '<c r="'.chr(65 + $column).$number.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
        }
        $xml .= '</row>';
    }
    $path = tempnam(sys_get_temp_dir(), 'email-workbook-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData></worksheet>');
    $zip->close();

    return $path;
}

it('crosses by DNI, preserves leading zeroes and defaults, and is idempotent', function (): void {
    $patient = Paciente::factory()->create(['dni' => '01234567']);
    app(PacienteCorreoService::class)->sync($patient, [['correo' => 'prior@example.com', 'predeterminado' => true]]);
    $path = emailWorkbook([['1234567', 'Ana Torres <ANA@example.com>; trabajo@example.com;ANA@example.com']]);
    try {
        $importer = app(PacienteCorreoImporter::class);
        $preview = $importer->import($path, true);
        expect($preview['correos_agregados'])->toBe(2);
        $this->assertDatabaseCount('paciente_correos', 1);
        $result = $importer->import($path);
        expect($result['correos_agregados'])->toBe(2)->and($result['pacientes_cruzados'])->toBe(1);
        $this->assertDatabaseCount('paciente_correos', 3);
        expect($patient->fresh()->correo_electronico)->toBe('prior@example.com');
        expect($importer->import($path)['correos_agregados'])->toBe(0);
    } finally {
        unlink($path);
    }
});

it('skips every ambiguous address, invalid text, unknown DNI and existing foreign ownership', function (): void {
    $first = Paciente::factory()->create(['dni' => '01234567']);
    Paciente::factory()->create(['dni' => '76543210']);
    $owner = Paciente::factory()->create();
    app(PacienteCorreoService::class)->sync($owner, [['correo' => 'owned@example.com', 'predeterminado' => true]]);
    $path = emailWorkbook([
        ['01234567', 'shared@example.com;valid@example.com;sin-arroba;owned@example.com'],
        ['76543210', 'SHARED@example.com;niega'],
        ['99999999', 'unknown@example.com'],
    ]);
    try {
        $result = app(PacienteCorreoImporter::class)->import($path);
        expect($result['correos_agregados'])->toBe(1)->and($result['sin_paciente'])->toBe(1)
            ->and($result['direcciones_en_conflicto'])->toBe(1);
        $this->assertDatabaseMissing('paciente_correos', ['correo' => 'shared@example.com']);
        $this->assertDatabaseMissing('paciente_correos', ['correo' => 'unknown@example.com']);
        expect($first->fresh()->correo_electronico)->toBe('valid@example.com');
        expect(array_column($result['incidencias'], 'motivo'))->toContain('correo_pertenece_a_otro_paciente', 'correo_en_varios_dni', 'paciente_no_encontrado', 'sin_correo_valido');
        expect(PacienteCorreo::count())->toBe(2);
    } finally {
        unlink($path);
    }
});
