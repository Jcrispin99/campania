<?php

declare(strict_types=1);

namespace App\Services;

use Generator;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

final class SimpleXlsxReader
{
    /** @return Generator<int, array<string, string>> */
    public function rows(string $path): Generator
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo abrir el XLSX.']);
        }
        try {
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($this->xml($zip, 'xl/sharedStrings.xml')->si as $item) {
                    $strings[] = $this->text($item);
                }
            }
            foreach ($this->xml($zip, 'xl/worksheets/sheet1.xml')->sheetData->row as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    $column = preg_replace('/[0-9]+/', '', (string) $cell['r']);
                    $value = match ((string) $cell['t']) {
                        's' => $strings[(int) $cell->v] ?? '',
                        'inlineStr' => $this->text($cell->is),
                        default => (string) $cell->v,
                    };
                    $values[$column] = mb_trim($value);
                }
                if (array_filter($values, fn ($value) => $value !== '') !== []) {
                    yield (int) $row['r'] => $values;
                }
            }
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
