<?php

namespace App\Services\Ledger;

use App\Services\Ledger\Concerns\ReadsWorksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LedgerLegendAnalyzer
{
    use ReadsWorksheet;

    protected array $cfg;

    public function __construct()
    {
        $this->cfg = config('ledger');
    }

    public function discoverLegend(Worksheet $sheet, int $mainRow): array
    {
        $cfg = $this->cfg['legend'];
        $cols = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $best = [];

        for ($c = 1; $c <= $cols; $c++) {
            $run = [];
            for ($r = 1; $r < $mainRow; $r++) {
                $argb = $this->fillArgb($sheet, $r, $c);
                $label = $this->cellVal($sheet, $r, $c + 1);

                if ($argb !== null && is_string($label) && trim($label) !== '') {
                    $run[$argb] = trim($label);
                } else {
                    if (count($run) > count($best)) {
                        $best = $run;
                    }
                    $run = [];
                }
            }
            if (count($run) > count($best)) {
                $best = $run;
            }
        }

        return count($best) >= ($cfg['min_entries'] ?? 2) ? $best : [];
    }

    public function rowStatusFlag(Worksheet $sheet, int $row, array $map, array $legend): ?string
    {
        if (! $legend) {
            return null;
        }

        foreach ($this->cfg['legend']['probe_columns'] as $field) {
            $col = $map[$field] ?? null;
            if (! is_int($col)) {
                continue;
            }
            $argb = $this->fillArgb($sheet, $row, $col);
            if ($argb !== null && isset($legend[$argb])) {
                return $legend[$argb];
            }
        }

        return null;
    }

    protected function fillArgb(Worksheet $sheet, int $row, int $col): ?string
    {
        $coord = Coordinate::stringFromColumnIndex($col).$row;
        if (! $sheet->cellExists($coord)) {
            return null;
        }

        $fill = $sheet->getStyle($coord)->getFill();
        if ($fill->getFillType() === null || $fill->getFillType() === Fill::FILL_NONE) {
            return null;
        }

        $argb = $fill->getStartColor()->getARGB();
        if (! is_string($argb) || in_array($argb, $this->cfg['legend']['ignore_argb'], true)) {
            return null;
        }

        return $argb;
    }
}
