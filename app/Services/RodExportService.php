<?php

namespace App\Services;

use App\Models\AccountReference;
use App\Models\GeneralLedger;
use App\Models\Upload;
use App\Services\Ledger\LedgerHeaderAnalyzer;
use App\Services\Ledger\RodXlsxPatcher;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class RodExportService
{
    private const CLASSES = ['PS', 'MOOE', 'CO'];

    public function __construct(
        protected LedgerHeaderAnalyzer $headerAnalyzer,
        protected RodXlsxPatcher $patcher
    ) {}

    public function export(Upload $upload, ?array $months = null): RodExportResult
    {
        $months ??= config('ledger.rod_export_months');
        $months = $months ? array_values(array_map('intval', $months)) : null;

        if (! $upload->hasFile()) {
            throw new \RuntimeException("Snapshot #{$upload->id} has no workbook file on disk.");
        }

        $outPath = $this->outputPath($upload);

        $reader = IOFactory::createReaderForFile($upload->stored_path);
        $reader->setReadFilter(new class implements IReadFilter {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool {
                return $row <= 50;
            }
        });
        
        $spreadsheet = $reader->load($upload->stored_path);
        $sheetName = $upload->sheet_name ?: config('ledger.sheet');
        $sheet = $spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getActiveSheet();

        $mainRow = $this->headerAnalyzer->detectHeaderRow($sheet);
        $map = $this->headerAnalyzer->buildColumnMap($sheet, $mainRow);

        $colFor = [];
        foreach (self::CLASSES as $class) {
            $field = 'cur_'.strtolower($class);
            if (! isset($map[$field])) {
                throw new \RuntimeException("Column for '{$field}' not found.");
            }
            $colFor[$class] = $map[$field];
        }

        $classOf = AccountReference::query()
            ->where('ref_type', 'charging')
            ->where('is_prior_year', false)
            ->pluck('allotment_class', 'code');

        $written = [];
        foreach (self::CLASSES as $class) {
            $written[$class] = ['count' => 0, 'total' => 0.0];
        }
        $unclassified = 0;
        $byMonth = [];
        $patchCells = [];

        GeneralLedger::query()
            ->where('upload_id', $upload->id)
            ->transactions()
            ->disbursed()
            ->currentYearAllotment()
            ->when($months, fn ($q) => $q->whereIn('ledger_month', $months))
            ->orderBy('source_row')
            ->chunkById(500, function ($rows) use (&$written, &$unclassified, &$byMonth, &$patchCells, $classOf, $colFor) {
                foreach ($rows as $row) {
                    $class = $classOf[$row->charging_code] ?? null;
                    if ($class === null || ! isset($colFor[$class])) {
                        $unclassified++;
                        continue;
                    }

                    $coord = Coordinate::stringFromColumnIndex($colFor[$class]).$row->source_row;
                    $amount = round($row->rodAmount(), 2);
                    $patchCells[$coord] = $amount;

                    $written[$class]['count']++;
                    $written[$class]['total'] += $amount;

                    $m = (int) $row->ledger_month;
                    $byMonth[$m][$class] ??= ['count' => 0, 'total' => 0.0];
                    $byMonth[$m][$class]['count']++;
                    $byMonth[$m][$class]['total'] += $amount;
                }
            }, 'id');

        $sheetIndex = $spreadsheet->getIndex($sheet);
        $sheetId = $sheetIndex + 1;
        $sheetEntry = "xl/worksheets/sheet{$sheetId}.xml";

        $this->patcher->patch($upload->stored_path, $outPath, $sheetEntry, $patchCells);

        ksort($byMonth);

        return new RodExportResult(
            path: $outPath,
            downloadName: $this->downloadName($upload),
            written: $written,
            unclassified: $unclassified,
            headerMap: $this->headerAnalyzer->describeMap($map),
            months: $months,
            byMonth: $byMonth,
        );
    }

    protected function outputPath(Upload $upload): string
    {
        $dir = storage_path('app/private/ledger-exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir.'/rod_'.$upload->id.'_'.now()->format('Ymd_His').'.xlsx';
    }

    protected function downloadName(Upload $upload): string
    {
        return "ROD-export-FY{$upload->fiscal_year}-snapshot{$upload->id}-".now()->format('Ymd_His').'.xlsx';
    }
}
