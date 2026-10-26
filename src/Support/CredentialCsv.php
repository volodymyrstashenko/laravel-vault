<?php

namespace Thevps\Vault\Support;

/**
 * CSV format shared by the import template, import and export — one format, so an export can be
 * re-imported as-is. Ukrainian headers (the file is filled in Excel by people, not machines);
 * English keys are accepted as aliases. `;` + UTF-8 BOM on output — what Excel with a Ukrainian
 * locale opens correctly by double-click; on input the delimiter is sniffed from the header line.
 */
class CredentialCsv
{
    /** key => header label, in column order. */
    public const COLUMNS = [
        'name' => 'Назва',
        'login' => 'Логін',
        'password' => 'Пароль',
        'url' => 'Посилання',
        'totp_secret' => 'TOTP секрет',
        'notes' => 'Нотатки',
        'groups' => 'Групи',
    ];

    private const ALIASES = [
        'назва' => 'name', 'name' => 'name', 'title' => 'name',
        'логін' => 'login', 'login' => 'login', 'username' => 'login', 'user' => 'login',
        'пароль' => 'password', 'password' => 'password',
        'посилання' => 'url', 'url' => 'url', 'сайт' => 'url',
        'totp секрет' => 'totp_secret', 'totp' => 'totp_secret', 'totp_secret' => 'totp_secret',
        'нотатки' => 'notes', 'notes' => 'notes', 'примітки' => 'notes',
        'групи' => 'groups', 'група' => 'groups', 'groups' => 'groups', 'group' => 'groups',
    ];

    /** Group names inside one cell: "Хостинг, Домени" or "Хостинг | Домени". */
    public const GROUP_SEPARATOR = '/\s*[,|]\s*/u';

    // A cell starting with one of these is run as a formula by Excel/LibreOffice (CSV injection).
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * An uploaded file — .xlsx (sniffed by its ZIP signature, not the extension) or CSV.
     *
     * @return array{rows: array<int, array<string, string>>, error: ?string} rows keyed by the
     *                                                                        1-based line/row
     */
    public static function parseFile(string $path): array
    {
        $head = (string) file_get_contents($path, false, null, 0, 4);
        if (str_starts_with($head, "PK\x03\x04")) {
            $matrix = XlsxReader::firstSheet($path);

            return $matrix === null
                ? ['rows' => [], 'error' => 'Не вдалося прочитати файл Excel. Збережіть його як CSV або вставте текстом.']
                : self::fromMatrix($matrix);
        }

        return self::parseText((string) file_get_contents($path));
    }

    /**
     * CSV or text pasted from a spreadsheet. Excel's clipboard is tab-separated; the delimiter
     * (tab / `;` / `,`) is sniffed from the header line.
     */
    public static function parseText(string $text): array
    {
        $text = self::stripBom($text);
        $firstLine = strtok($text, "\n") ?: '';
        $counts = ["\t" => substr_count($firstLine, "\t"), ';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ',')];
        arsort($counts);
        $delimiter = array_key_first($counts);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $text);
        rewind($stream);
        $matrix = [];
        while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $matrix[] = $cells;
        }
        fclose($stream);

        return self::fromMatrix($matrix);
    }

    /** @param  array<int, array<int, ?string>>  $matrix  first row = headers */
    private static function fromMatrix(array $matrix): array
    {
        if (! $matrix) {
            return ['rows' => [], 'error' => 'Дані порожні.'];
        }

        $headers = array_map(
            fn ($cell) => self::ALIASES[mb_strtolower(trim((string) $cell))] ?? null,
            array_shift($matrix),
        );

        if (! in_array('name', $headers, true)) {
            return ['rows' => [], 'error' => 'Не знайдено колонку «Назва» в першому рядку. Скористайтеся шаблоном.'];
        }

        $rows = [];
        foreach ($matrix as $index => $cells) {
            if (implode('', array_map(fn ($cell) => trim((string) $cell), $cells)) === '') {
                continue;
            }

            $row = [];
            foreach ($headers as $column => $key) {
                if ($key !== null) {
                    $row[$key] = self::unescapeFormula(trim((string) ($cells[$column] ?? '')));
                }
            }
            $rows[$index + 2] = $row;
        }

        return ['rows' => $rows, 'error' => null];
    }

    /** One CSV line (with trailing newline), formula-escaped. */
    public static function line(array $cells): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_map(fn ($cell) => self::escapeFormula((string) $cell), $cells), ';', '"', '');
        rewind($out);
        $line = stream_get_contents($out);
        fclose($out);

        return $line;
    }

    public static function header(): string
    {
        return "\xEF\xBB\xBF".self::line(array_values(self::COLUMNS));
    }

    /**
     * Leading apostrophe = Excel's own "this is text" marker, which Excel drops on save — so an
     * exported `'=abc` re-saved through Excel comes back as `=abc`, and imported directly it's
     * unescaped here. Either way the stored password round-trips unchanged.
     */
    private static function escapeFormula(string $value): string
    {
        return $value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true) ? "'".$value : $value;
    }

    private static function unescapeFormula(string $value): string
    {
        return strlen($value) > 1 && $value[0] === "'" && in_array($value[1], self::FORMULA_PREFIXES, true)
            ? substr($value, 1)
            : $value;
    }

    private static function stripBom(string $line): string
    {
        return str_starts_with($line, "\xEF\xBB\xBF") ? substr($line, 3) : $line;
    }
}
