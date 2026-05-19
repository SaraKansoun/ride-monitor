<?php

namespace App\Services;

use Generator;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportService
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    public function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                throw new RuntimeException('Unable to open output stream.');
            }

            fputcsv($output, $headers);

            foreach ($rows as $row) {
                fputcsv($output, array_map($this->sanitizeCell(...), $row));
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  iterable<int, mixed>  $models
     * @param  callable(mixed): list<string|int|float|null>  $map
     * @return Generator<int, list<string|int|float|null>>
     */
    public function rows(iterable $models, callable $map): Generator
    {
        foreach ($models as $model) {
            yield $map($model);
        }
    }

    private function sanitizeCell(string|int|float|null $value): string|int|float|null
    {
        if (! is_string($value)) {
            return $value;
        }

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'{$value}";
        }

        return $value;
    }
}
