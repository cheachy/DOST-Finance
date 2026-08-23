<?php

namespace App\Services;

use App\Models\AccountReference;
use App\Models\GeneralLedger;
use App\Models\Upload;
use App\Services\Ledger\LedgerHeaderAnalyzer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Writes generated current-year ROD figures into a COPY of her actual MDS
 * 101 workbook. Write-back only — every value comes from the same rules
 * already proven by `ledger:rod-verify` (charging code decides class, A/C
 * gates inclusion, NET + numeric RC is the amount). See
 * GeneralLedger::ROD_AMOUNT_SQL and RodVerifyCommand::generated().
 *
 * Three things this deliberately does NOT do:
 *
 *   - Never writes prior-year PS/MOOE/CO. There is no derivation rule for
 *     the prior-year split (confirmed with the accountant) - a generated
 *     number there would be a guess, not a fact. Those three columns are
 *     left exactly as she typed them, including on PY NY BECAME DD rows.
 *
 *   - Never writes a non-transaction row. Section headers, subtotals, NYDD
 *     tracking rows and the monthly summary blocks are row_type != 'transaction'
 *     and are never touched.
 *
 *   - Never writes a transaction that isn't A/C-gated current-year. Payment
 *     mode gates the ROD (GeneralLedger::scopeDisbursed); the prior-year
 *     flag override gates allotment year (scopeCurrentYearAllotment).
 *
 * Columns are discovered fresh from the workbook being written to, via the
 * same LedgerHeaderAnalyzer the importer uses at import time — never
 * hardcoded letters, so a column she inserts next year does not silently
 * misroute a value.
 *
 * Operates on a COPY of the stored snapshot file; the original is never
 * modified by this service.
 *
 * SCOPED BY MONTH. The write-back is being proven a slice at a time, so it
 * writes only the months in config('ledger.rod_export_months') - January and
 * February at present. Rows in every other month are not written at all; her
 * typed values there survive untouched. Pass $months explicitly to override.
 */
class RodExportService
{
    private const CLASSES = ['PS', 'MOOE', 'CO'];

    public function __construct(
        protected LedgerHeaderAnalyzer $headerAnalyzer
    ) {}

    /**
     * @param  int[]|null  $months  1-12; null falls back to the configured
     *                              scope, and an empty config means every month.
     */
    public function export(Upload $upload, ?array $months = null): RodExportResult
    {
        $months ??= config('ledger.rod_export_months');
        $months = $months ? array_values(array_map('intval', $months)) : null;

        if (! $upload->hasFile()) {
            throw new \RuntimeException(
                "Snapshot #{$upload->id} has no workbook file on disk — cannot export."
            );
        }

        $outPath = $this->outputPath($upload);
        if (! copy($upload->stored_path, $outPath)) {
            throw new \RuntimeException("Could not copy the source workbook to {$outPath}.");
        }

        // Read only the top 50 rows to find headers. Bypassing PhpSpreadsheet's
        // full load prevents massive memory usage on 25MB files.
        $reader = IOFactory::createReaderForFile($outPath);
        $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool {
                return $row <= 50;
            }
        });
        $spreadsheet = $reader->load($outPath);
        $sheetName = $upload->sheet_name ?: config('ledger.sheet');
        $sheet = $spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getActiveSheet();

        $mainRow = $this->headerAnalyzer->detectHeaderRow($sheet);
        $map = $this->headerAnalyzer->buildColumnMap($sheet, $mainRow);

        $colFor = [];
        foreach (self::CLASSES as $class) {
            $field = 'cur_'.strtolower($class);
            if (! isset($map[$field])) {
                throw new \RuntimeException(
                    "Column for '{$field}' (CURRENT YEAR ALLOTMENT / {$class}) was not found in the workbook header — refusing to export."
                );
            }
            $colFor[$class] = $map[$field];
        }

        // Same lookup RodVerifyCommand::generated() sums against: a charging
        // code's class, current-year codes only.
        $classOf = AccountReference::query()
            ->where('ref_type', 'charging')
            ->where('is_prior_year', false)
            ->pluck('allotment_class', 'code');

        $written = [];
        foreach (self::CLASSES as $class) {
            $written[$class] = ['count' => 0, 'total' => 0.0];
        }
        $unclassified = 0;
        // Per-month totals, so the caller can check a written month against a
        // figure that was verified independently of this service.
        $byMonth = [];
        $csvData = [];

        GeneralLedger::query()
            ->where('upload_id', $upload->id)
            ->transactions()
            ->disbursed()
            ->currentYearAllotment()
            // Slice under test only - see config('ledger.rod_export_months').
            ->when($months, fn ($q) => $q->whereIn('ledger_month', $months))
            ->orderBy('source_row')
            ->chunkById(500, function ($rows) use (&$written, &$unclassified, &$byMonth, &$csvData, $classOf, $colFor, $sheet) {
                foreach ($rows as $row) {
                    $class = $classOf[$row->charging_code] ?? null;
                    if ($class === null || ! isset($colFor[$class])) {
                        $unclassified++;

                        continue;
                    }

                    $coord = Coordinate::stringFromColumnIndex($colFor[$class]).$row->source_row;
                    $amount = round($row->rodAmount(), 2);
                    $csvData[] = [$coord, $amount];

                    $written[$class]['count']++;
                    $written[$class]['total'] += $amount;

                    $m = (int) $row->ledger_month;
                    $byMonth[$m][$class] ??= ['count' => 0, 'total' => 0.0];
                    $byMonth[$m][$class]['count']++;
                    $byMonth[$m][$class]['total'] += $amount;
                }
            }, 'id');

        // ----- NATIVE EXCEL INJECTION -----
        // PhpSpreadsheet corrupts this specific template on save due to its
        // complex formatting and data validation. Since this system runs on
        // a Windows machine with Excel installed, we write the data to a CSV
        // and use a tiny PowerShell script to command native Excel to inject
        // the values instantly and flawlessly without destroying the file.

        $csvPath = storage_path('app/private/ledger-exports/temp_'.uniqid().'.csv');
        $csvHandle = fopen($csvPath, 'w');
        fputcsv($csvHandle, ['Cell', 'Value']);
        foreach ($csvData as $d) {
            fputcsv($csvHandle, $d);
        }
        fclose($csvHandle);

        $ps1Path = storage_path('app/private/ledger-exports/inject_'.uniqid().'.ps1');
        $ps1Code = <<<PS1
\$ErrorActionPreference = "Stop"
\$excel = New-Object -ComObject Excel.Application
\$excel.Visible = \$false
\$excel.DisplayAlerts = \$false
try {
    \$wb = \$excel.Workbooks.Open("$outPath")
    \$ws = \$wb.Sheets.Item(1)
    \$csv = Import-Csv "$csvPath"
    foreach (\$row in \$csv) {
        if (\$row.Value -ne '') {
            \$ws.Range(\$row.Cell).Value = [double]\$row.Value
            \$ws.Range(\$row.Cell).NumberFormat = "#,##0.00"
        }
    }
    \$wb.Save()
    \$wb.Close()
} finally {
    \$excel.Quit()
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject(\$excel) | Out-Null
}
PS1;
        file_put_contents($ps1Path, $ps1Code);

        $output = shell_exec('powershell.exe -ExecutionPolicy Bypass -File "' . $ps1Path . '" 2>&1');
        
        @unlink($csvPath);
        @unlink($ps1Path);
        
        if (str_contains(strtolower($output), 'error') || str_contains(strtolower($output), 'exception')) {
            throw new \RuntimeException("PowerShell COM Error: " . $output);
        }

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
