<?php

declare(strict_types=1);

use App\Models\Atencion;
use App\Models\Paciente;
use App\Services\AtencionExcelExporter;
use App\Services\PacienteCorreoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function readAttentionExport(string $path): array
{
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    try {
        $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $values[] = (string) $cell['t'] === 'inlineStr' ? (string) $cell->is->t : (isset($cell->v) ? (string) $cell->v : null);
            }
            $rows[] = $values;
        }

        return ['rows' => $rows, 'xml' => $xml];
    } finally {
        $zip->close();
        unlink($path);
    }
}

it('exports all pages anonymously with the requested columns and safe Excel cell types', function (): void {
    $patient = Paciente::factory()->create(['dni' => '01234567', 'nombre_completo' => 'Ana & Torres', 'edad' => 0]);
    app(PacienteCorreoService::class)->sync($patient, [
        ['correo' => 'alternate@example.com', 'predeterminado' => false],
        ['correo' => 'principal@example.com', 'predeterminado' => true],
    ]);
    Atencion::factory()->count(18)->for($patient)->create([
        'fecha' => '2026-10-21', 'valor_1' => '120/80', 'diabetes' => true,
        'hipertension' => false, 'ayuna' => true, 'otros' => '=1+1',
    ]);

    $response = $this->get('/api/v1/atenciones/exportar')->assertOk()->assertDownload()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $export = readAttentionExport($response->baseResponse->getFile()->getPathname());
    expect($export['rows'])->toHaveCount(19);
    expect($export['rows'][0])->toBe(['Fecha', 'DNI', 'Paciente', 'Edad', 'Correo', 'Presión arterial', 'Diabetes', 'Hipert.', 'Ayuna', 'Otros']);
    expect($export['rows'][1])->toBe(['46316', '01234567', 'Ana & Torres', '0', 'principal@example.com', '120/80', 'Sí', 'No', 'Sí', '=1+1']);
    expect((string) $export['xml']->sheetData->row[1]->c[1]['t'])->toBe('inlineStr');
    expect((string) $export['xml']->sheetData->row[1]->c[5]['t'])->toBe('inlineStr');
    expect(count($export['xml']->sheetData->row[1]->c[9]->f))->toBe(0);
});

it('uses inclusive dates and the same patient search in history and export', function (): void {
    $patient = Paciente::factory()->create(['nombre_completo' => 'Ana Torres']);
    $other = Paciente::factory()->create(['nombre_completo' => 'Luis Vega']);
    foreach (['2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23'] as $date) {
        Atencion::factory()->for($patient)->create(['fecha' => $date]);
    }
    Atencion::factory()->for($other)->create(['fecha' => '2026-10-21']);
    $params = '?desde=2026-10-21&hasta=2026-10-22&search=torres';
    $this->getJson('/api/v1/atenciones'.$params)->assertOk()->assertJsonPath('data.total', 2);
    $response = $this->get('/api/v1/atenciones/exportar'.$params)->assertOk();
    $export = readAttentionExport($response->baseResponse->getFile()->getPathname());
    expect($export['rows'])->toHaveCount(3);
    expect($export['rows'][1][0])->toBe('46317')->and($export['rows'][2][0])->toBe('46316');
    $this->getJson('/api/v1/atenciones?hasta=2026-10-20')->assertOk()->assertJsonPath('data.total', 1);
    $this->getJson('/api/v1/atenciones?desde=2026-10-23')->assertOk()->assertJsonPath('data.total', 1);
});

it('rejects invalid or reversed date ranges for history and Excel', function (): void {
    foreach (['/api/v1/atenciones', '/api/v1/atenciones/exportar'] as $endpoint) {
        $this->getJson($endpoint.'?desde=2026-10-22&hasta=2026-10-21')
            ->assertUnprocessable()->assertJsonValidationErrors('hasta');
        $this->getJson($endpoint.'?desde=2026-02-31')
            ->assertUnprocessable()->assertJsonValidationErrors('desde');
    }
});

it('exports blank optional values and provides an empty workbook with the same headers', function (): void {
    $patient = Paciente::factory()->create(['edad' => null]);
    Atencion::factory()->for($patient)->create(['valor_1' => null, 'user_id' => null, 'fecha' => '2026-10-21']);
    $response = $this->get('/api/v1/atenciones/exportar')->assertOk();
    $export = readAttentionExport($response->baseResponse->getFile()->getPathname());
    expect($export['rows'][1][3])->toBeNull()->and($export['rows'][1][4])->toBeNull()->and($export['rows'][1][5])->toBeNull();
    $response = $this->get('/api/v1/atenciones/exportar?hasta=2025-01-01')->assertOk();
    $export = readAttentionExport($response->baseResponse->getFile()->getPathname());
    expect($export['rows'])->toBe([AtencionExcelExporter::HEADERS]);
});
