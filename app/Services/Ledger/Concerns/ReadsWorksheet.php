<?php

namespace App\Services\Ledger\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait ReadsWorksheet
{
    /** Cached value of a cell (formula result if it is a formula). */
    protected function cellVal(Worksheet $sheet, int $row, int $col): mixed
    {
        $coord = Coordinate::stringFromColumnIndex($col).$row;
        if (! $sheet->cellExists($coord)) {
            return null;
        }
        $cell = $sheet->getCell($coord);
        if ($cell->isFormula()) {
            $cached = $cell->getOldCalculatedValue();

            return $cached === '' ? null : $cached;
        }
        $v = $cell->getValue();

        return $v === '' ? null : $v;
    }

    protected function norm(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }

        return trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $v)));
    }

    protected function isNum(mixed $v): bool
    {
        return is_int($v) || is_float($v);
    }

    protected function isStr(mixed $v): bool
    {
        return is_string($v) && trim($v) !== '';
    }
}
