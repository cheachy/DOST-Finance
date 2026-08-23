<?php

namespace App\Services;

/**
 * Outcome of one RodExportService::export() call — the file written and the
 * per-class counts/totals, so the caller (route, command, test) can log or
 * display what actually landed without re-reading the workbook.
 */
readonly class RodExportResult
{
    /**
     * @param  array<string, array{count:int,total:float}>  $written  keyed by 'PS'|'MOOE'|'CO'
     * @param  array<string, string>  $headerMap  field => column letter, for audit
     * @param  int[]|null  $months  the month slice written; null = every month
     * @param  array<int, array<string, array{count:int,total:float}>>  $byMonth  month => class => totals
     */
    public function __construct(
        public string $path,
        public string $downloadName,
        public array $written,
        public int $unclassified,
        public array $headerMap,
        public ?array $months = null,
        public array $byMonth = [],
    ) {}

    public function totalWritten(): int
    {
        return array_sum(array_column($this->written, 'count'));
    }
}
