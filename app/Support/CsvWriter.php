<?php

namespace App\Support;

final class CsvWriter
{
    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    public static function write(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');

        foreach ($rows as $row) {
            fputcsv($stream, array_map(static function ($value): string {
                $value = (string) ($value ?? '');

                if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
                    return "'{$value}";
                }

                return $value;
            }, $row), ',', '"', '\\');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
