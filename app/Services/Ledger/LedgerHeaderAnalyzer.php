<?php

namespace App\Services\Ledger;

use App\Services\Ledger\Concerns\ReadsWorksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LedgerHeaderAnalyzer
{
    use ReadsWorksheet;

    protected array $cfg;

    public function __construct()
    {
        $this->cfg = config('ledger');
    }

    public function detectHeaderRow(Worksheet $sheet): int
    {
        $anchors = $this->cfg['anchors'];
        $cols = $this->highestColumn($sheet);

        for ($r = 1; $r <= $this->cfg['header_scan_max']; $r++) {
            $labels = [];
            for ($c = 1; $c <= $cols; $c++) {
                $labels[$this->norm($this->cellVal($sheet, $r, $c))] = true;
            }
            $hit = true;
            foreach ($anchors as $a) {
                if (! isset($labels[$a])) {
                    $hit = false;
                    break;
                }
            }
            if ($hit) {
                return $r;
            }
        }

        throw new \RuntimeException('Ledger header row not found');
    }

    public function buildColumnMap(Worksheet $sheet, int $mainRow): array
    {
        $merges = $this->mergeLookup($sheet);
        $cols = $this->highestColumn($sheet);
        $map = [];
        $dvCols = [];

        for ($c = 1; $c <= $cols; $c++) {
            $sup = $this->bandValue($sheet, $mainRow - 1, $c, $merges);
            $main = $this->bandValue($sheet, $mainRow, $c, $merges);
            $sub = $this->bandValue($sheet, $mainRow + 1, $c, $merges);

            $field = $this->canon($sup, $main, $sub);
            if ($field === null) {
                continue;
            }
            if ($field === 'dv') {
                $dvCols[] = $c;

                continue;
            }
            $map[$field] ??= $c;
        }

        // OBR # is split: labelled column = prefix, the next column = number
        if (isset($map['obr_prefix'])) {
            $map['obr_no'] = $map['obr_prefix'] + 1;
        }
        // DV# spans two merged columns: first = prefix, second = number
        if ($dvCols) {
            $map['dv_prefix'] = $dvCols[0];
            if (isset($dvCols[1])) {
                $map['dv_no'] = $dvCols[1];
            }
        }

        $map['__tax__'] = $this->discoverTaxBlock($sheet, $mainRow, $merges, $map, $cols);

        return $map;
    }

    public function describeMap(array $map): array
    {
        $out = ['fields' => [], 'tax_details' => []];
        foreach ($map as $field => $col) {
            if ($field === '__tax__') {
                continue;
            }
            $out['fields'][$field] = Coordinate::stringFromColumnIndex($col);
        }
        foreach ($map['__tax__'] ?? [] as [$label, $col]) {
            $out['tax_details'][$label] = Coordinate::stringFromColumnIndex($col);
        }

        return $out;
    }

    protected function highestColumn(Worksheet $sheet): int
    {
        return Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
    }

    protected function mergeLookup(Worksheet $sheet): array
    {
        $look = [];
        foreach ($sheet->getMergeCells() as $range) {
            [$start] = explode(':', $range);
            $topLeft = $this->cellVal(
                $sheet,
                (int) preg_replace('/\D/', '', $start),
                Coordinate::columnIndexFromString(preg_replace('/\d/', '', $start))
            );
            foreach (Coordinate::extractAllCellReferencesInRange($range) as $coord) {
                $look[$coord] = $topLeft;
            }
        }

        return $look;
    }

    protected function bandValue(Worksheet $sheet, int $row, int $col, array $merges): mixed
    {
        $direct = $this->cellVal($sheet, $row, $col);
        if ($direct !== null && $direct !== '') {
            return $direct;
        }
        $coord = Coordinate::stringFromColumnIndex($col).$row;

        return $merges[$coord] ?? null;
    }

    protected function discoverTaxBlock(Worksheet $sheet, int $mainRow, array $merges, array $map, int $cols): array
    {
        $tb = $this->cfg['tax_block'];
        $anchor = $map[$tb['start_after']] ?? null;
        if (! $anchor) {
            return [];
        }

        $start = $anchor + 1;
        $end = null;
        for ($c = $start; $c <= $cols; $c++) {
            $main = $this->norm($this->bandValue($sheet, $mainRow, $c, $merges));
            foreach ($tb['end_labels'] as $lbl) {
                if ($main !== '' && str_starts_with($main, $lbl)) {
                    $end = $c;
                    break 2;
                }
            }
        }
        if (! $end) {
            return [];
        }

        $block = [];
        for ($c = $start; $c < $end; $c++) {
            $main = $this->norm($this->bandValue($sheet, $mainRow, $c, $merges));
            $sub = $this->norm($this->bandValue($sheet, $mainRow + 1, $c, $merges));

            if (! empty($tb['join_continuation']) && str_starts_with($sub, '/') && $main !== '') {
                $label = $main.$sub;
            } elseif ($sub !== '' && ! in_array($sub, $tb['group_labels'], true)) {
                $label = $sub;
            } else {
                $label = $main;
            }

            $label = trim($label);
            if ($label === '' || in_array($label, $tb['skip_labels'], true)) {
                continue;
            }
            $block[] = [$label, $c];
        }

        return $block;
    }

    protected function canon(?string $sup, ?string $main, ?string $sub): ?string
    {
        $s = $this->norm($sup);
        $m = $this->norm($main);
        $b = $this->norm($sub);

        if (in_array($s, $this->cfg['ignore_super'], true)) {
            return null; // whole POSTING block
        }

        $h = $this->cfg['header'];

        if (array_key_exists($m, $h['by_main'])) {
            return $h['by_main'][$m];
        }
        if ($m === $h['dv_main']) {
            return 'dv';
        }
        // the only fixed field inside the deduction block
        if ($b === $h['status_sub_label'] && in_array($m, $h['status_allowed_main'], true)) {
            return 'status';
        }

        return null;
    }
}
