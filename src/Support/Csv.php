<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields\Support;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use JsonException;
use MrNewport\LaravelFields\Schema;
use MrNewport\LaravelFields\Type;

final class Csv
{
    /** Export untrusted values safely for spreadsheet readers. No file or network side effects. */
    public static function export(Schema $schema, iterable $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        try {
            $headers = array_keys($schema->exportRow([]));
            $jsonFields = [];
            foreach ($schema->fields() as $field) {
                if ($field->isExportable() && $field->type === Type::Json) {
                    $jsonFields[$field->name] = true;
                }
            }
            fputcsv($stream, $headers, ',', '"', '', "\r\n");
            foreach ($rows as $row) {
                $values = $schema->exportRow($row);
                foreach ($jsonFields as $name => $unused) {
                    $values[$name] = json_encode($values[$name], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
                }
                $cells = array_map(self::cell(...), array_values($values));
                // An explicit empty record must remain distinct from a skipped blank line.
                if ($cells === ['']) {
                    fwrite($stream, "\"\"\r\n");
                } else {
                    fputcsv($stream, $cells, ',', '"', '', "\r\n");
                }
            }
            rewind($stream);

            return stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
    }

    private static function cell(mixed $value): string
    {
        if (is_object($value) || is_resource($value)) {
            throw new InvalidArgumentException('CSV values must contain scalar or array data.');
        }
        $text = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)
            : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);

        // Leading whitespace/control characters are ignored by some spreadsheet readers.
        return preg_match('/\A[\x00-\x20]*[=+@-]/', $text) || preg_match('/\A[\t\r\n]/', $text)
            ? "'".$text : $text;
    }

    /**
     * Validate all rows before returning any. The application owns preview/approval/persistence.
     * Exports escape spreadsheet formulas deliberately; importing never silently removes that prefix.
     */
    public static function import(Schema $schema, string $csv, int $maxRows = 1000, int $maxBytes = 2_000_000): array
    {
        if ($maxRows < 1 || $maxBytes < 1 || strlen($csv) > $maxBytes) {
            throw new InvalidArgumentException('CSV exceeds the configured input limit.');
        }
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        try {
            $headers = fgetcsv($stream, null, ',', '"', '');
            if (! is_array($headers) || $headers === [null]) {
                throw new InvalidArgumentException('CSV requires a header row.');
            }
            $headers[0] = preg_replace('/\A\xEF\xBB\xBF/', '', $headers[0]);
            $allowed = [];
            foreach ($schema->fields() as $field) {
                if ($field->isEditable() && ! $field->isSensitive() && $field->type !== Type::File) {
                    $allowed[$field->name] = $field;
                }
            }
            if (count(array_unique($headers)) !== count($headers) || array_diff($headers, array_keys($allowed))) {
                throw new InvalidArgumentException('CSV headers must be unique, editable schema fields.');
            }
            $rows = [];
            while (($cells = fgetcsv($stream, null, ',', '"', '')) !== false) {
                if ($cells === [null]) {
                    continue;
                }
                if (count($rows) >= $maxRows || count($cells) !== count($headers)) {
                    throw new InvalidArgumentException('CSV row count or column count is invalid.');
                }
                $data = [];
                foreach ($headers as $index => $header) {
                    $value = $cells[$index];
                    if ($allowed[$header]->type === Type::MultiSelect && $value !== '') {
                        try {
                            $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                        } catch (JsonException) {
                            // Keep malformed text for the schema's array validation error.
                        }
                    }
                    Arr::set($data, $header, $value === '' ? null : $value);
                }
                $rows[] = $schema->validate($data);
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }
}
