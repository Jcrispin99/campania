<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Atencion;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use Throwable;
use XMLWriter;
use ZipArchive;

final class AtencionExcelExporter
{
    public const HEADERS = ['Fecha', 'DNI', 'Paciente', 'Edad', 'Correo', 'Presión arterial', 'Diabetes', 'Hipert.', 'Ayuna', 'Otros'];

    /** @param Builder<Atencion> $query */
    public function create(Builder $query): string
    {
        $path = tempnam(sys_get_temp_dir(), 'atenciones-xlsx-');
        $sheet = tempnam(sys_get_temp_dir(), 'atenciones-sheet-');
        if ($path === false || $sheet === false) {
            throw new RuntimeException('No se pudo preparar el Excel.');
        }
        try {
            $writer = new XMLWriter;
            if (! $writer->openUri($sheet)) {
                throw new RuntimeException('No se pudo escribir el Excel.');
            }
            $writer->startDocument('1.0', 'UTF-8');
            $writer->startElement('worksheet');
            $writer->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $writer->writeRaw('<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
            $writer->startElement('cols');
            foreach ([14, 14, 40, 10, 42, 20, 13, 13, 13, 60] as $index => $width) {
                $writer->writeRaw('<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>');
            }
            $writer->endElement();
            $writer->startElement('sheetData');
            $this->row($writer, 1, self::HEADERS, true);
            $number = 1;
            // New samples entered during an export belong to the next export.
            $lastId = (clone $query)->max('atenciones.id');
            if ($lastId !== null) {
                foreach ((clone $query)->where('atenciones.id', '<=', $lastId)->orderByDesc('fecha')->orderByDesc('id')->lazy(500) as $attention) {
                    $patient = $attention->paciente;
                    $date = new DateTimeImmutable($attention->fecha->format('Y-m-d'), new DateTimeZone('UTC'));
                    $serial = 25569 + (int) ($date->getTimestamp() / 86400);
                    $this->row($writer, ++$number, [
                        $serial, $patient->dni, $patient->nombre_completo, $patient->edad,
                        $patient->correos->firstWhere('predeterminado', true)?->correo,
                        $attention->valor_1, $attention->diabetes ? 'Sí' : 'No',
                        $attention->hipertension ? 'Sí' : 'No', $attention->ayuna ? 'Sí' : 'No',
                        $attention->otros,
                    ]);
                }
            }
            $writer->endElement();
            $writer->writeRaw('<autoFilter ref="A1:J'.$number.'"/>');
            $writer->endElement();
            $writer->endDocument();
            $writer->flush();

            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo crear el archivo Excel.');
            }
            $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Atenciones" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', $this->styles());
            $zip->addFile($sheet, 'xl/worksheets/sheet1.xml');
            if (! $zip->close()) {
                throw new RuntimeException('No se pudo finalizar el Excel.');
            }

            return $path;
        } catch (Throwable $exception) {
            unlink($path);
            throw $exception;
        } finally {
            unlink($sheet);
        }
    }

    /** @param list<string|int|null> $values */
    private function row(XMLWriter $writer, int $number, array $values, bool $header = false): void
    {
        $writer->startElement('row');
        $writer->writeAttribute('r', (string) $number);
        foreach ($values as $index => $value) {
            $writer->startElement('c');
            $writer->writeAttribute('r', chr(65 + $index).$number);
            $writer->writeAttribute('s', $header ? '1' : ($index === 0 ? '2' : ($index === 1 ? '3' : '0')));
            if ($value !== null && $value !== '') {
                if (! $header && is_int($value)) {
                    $writer->writeElement('v', (string) $value);
                } else {
                    $writer->writeAttribute('t', 'inlineStr');
                    $writer->startElement('is');
                    $writer->startElement('t');
                    $writer->writeAttribute('xml:space', 'preserve');
                    // Literal text prevents formulas and preserves leading-zero DNIs.
                    $writer->text(preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $value) ?? '');
                    $writer->endElement();
                    $writer->endElement();
                }
            }
            $writer->endElement();
        }
        $writer->endElement();
    }

    private function styles(): string
    {
        return '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
