<?php

namespace Thevps\Vault\Support;

use ZipArchive;

/**
 * Minimal .xlsx reader — cell text of the first worksheet, nothing else (no formulas, styles,
 * dates). Enough for the credential import without pulling a spreadsheet library into the package.
 */
class XlsxReader
{
    /** @return array<int, array<int, string>>|null rows of cells, or null when unreadable */
    public static function firstSheet(string $path): ?array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $stringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        if ($sheetXml === false) {
            return null;
        }

        $shared = [];
        if ($stringsXml !== false) {
            $strings = @simplexml_load_string($stringsXml, options: LIBXML_NONET);
            foreach ($strings?->si ?? [] as $item) {
                // Rich text splits one string into several <r><t> runs.
                $shared[] = isset($item->t) ? (string) $item->t : implode('', array_map('strval', $item->xpath('.//*[local-name()="t"]')));
            }
        }

        $sheet = @simplexml_load_string($sheetXml, options: LIBXML_NONET);
        if ($sheet === false) {
            return null;
        }

        $rows = [];
        foreach ($sheet->sheetData->row ?? [] as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $column = self::columnIndex((string) $cell['r']);
                $type = (string) $cell['t'];
                $value = match ($type) {
                    's' => $shared[(int) $cell->v] ?? '',
                    'inlineStr' => (string) ($cell->is->t ?? ''),
                    default => (string) $cell->v,
                };
                $cells[$column] = $value;
            }
            if ($cells) {
                $filled = array_fill(0, max(array_keys($cells)) + 1, '');
                $rows[] = array_replace($filled, $cells);
            } else {
                $rows[] = [];
            }
        }

        return $rows;
    }

    /** "C7" => 2 */
    private static function columnIndex(string $reference): int
    {
        $letters = preg_replace('/\d+/', '', strtoupper($reference));
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }
}
