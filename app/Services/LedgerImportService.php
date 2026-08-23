<?php

namespace App\Services;

use App\Exceptions\DuplicateLedgerUploadException;
use App\Models\ActivityLog;
use App\Models\GeneralLedger;
use App\Models\Upload;
use App\Services\Ledger\LedgerHeaderAnalyzer;
use App\Services\Ledger\LedgerLegendAnalyzer;
use App\Services\Ledger\LedgerRetentionManager;
use App\Services\Ledger\LedgerRowParser;
use Database\Seeders\AccountReferenceSeeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LedgerImportService
{
    protected array $cfg;

    public function __construct(
        protected LedgerHeaderAnalyzer $headerAnalyzer,
        protected LedgerLegendAnalyzer $legendAnalyzer,
        protected LedgerRowParser $rowParser,
        protected LedgerRetentionManager $retentionManager
    ) {
        $this->cfg = config('ledger');
    }

    /**
     * @param  bool  $force  // force re-import
     *
     * @throws DuplicateLedgerUploadException
     */
    public function import(
        string $absolutePath,
        int $year,
        string $originalName,
        ?int $uploadedBy = null,
        bool $force = false
    ): Upload {
        $fileHash = hash_file('sha256', $absolutePath);

        if (! $force && $prior = $this->retentionManager->findDuplicate($year, 'file_hash', $fileHash)) {
            throw new DuplicateLedgerUploadException($prior, 'file');
        }

        $upload = Upload::create([
            'original_name' => $originalName,
            'stored_path' => $absolutePath,
            'sheet_name' => $this->cfg['sheet'],
            'fiscal_year' => $year,
            'status' => 'parsing',
            'is_current' => false,
            'file_hash' => $fileHash,
            'uploaded_by' => $uploadedBy,
        ]);

        try {
            $sheet = $this->loadSheet($absolutePath);
            $mainRow = $this->headerAnalyzer->detectHeaderRow($sheet);
            $map = $this->headerAnalyzer->buildColumnMap($sheet, $mainRow);
            $legend = $this->legendAnalyzer->discoverLegend($sheet, $mainRow);
            $records = $this->rowParser->parseRows($sheet, $mainRow, $map, $year, $legend);

            $contentHash = $this->retentionManager->contentHash($records);

            if (! $force && $prior = $this->retentionManager->findDuplicate($year, 'content_hash', $contentHash, $upload->id)) {
                $upload->delete();
                throw new DuplicateLedgerUploadException($prior, 'content');
            }

            $transactions = 0;
            foreach ($records as $rec) {
                if ($rec['row_type'] === 'transaction') {
                    $transactions++;
                }
            }

            DB::transaction(function () use ($records, $upload, $map, $transactions, $contentHash) {
                foreach (array_chunk($records, 500) as $chunk) {
                    GeneralLedger::insert(array_map(
                        fn ($rec) => $this->rowParser->toRow($rec, $upload->id),
                        $chunk
                    ));
                }

                Upload::where('fiscal_year', $upload->fiscal_year)
                    ->where('id', '!=', $upload->id)
                    ->update(['is_current' => false]);

                $upload->update([
                    'status' => 'done',
                    'is_current' => true,
                    'content_hash' => $contentHash,
                    'row_count' => count($records),
                    'transaction_count' => $transactions,
                    'header_map' => $this->headerAnalyzer->describeMap($map),
                ]);
            });

            $this->retentionManager->applyRetention($upload);

            ActivityLog::record(
                'import',
                'success',
                'Ledger imported',
                "{$originalName} — {$transactions} transactions",
                $uploadedBy
            );

            // Auto-refresh account_references from this workbook's own Ref
            // sheet, every import - no manual `db:seed` step to remember.
            // Safe to run unconditionally: AccountReferenceSeeder only ever
            // refreshes FACTS on every run and sets sl_tab/is_active ONCE per
            // code, so this can never undo a scaling decision made through
            // `ledger:sl-tab`. A failure here (e.g. a malformed Ref sheet)
            // is logged but does not fail an otherwise-successful import.
            try {
                app(AccountReferenceSeeder::class)->run();
            } catch (\Throwable $e) {
                report($e);
            }
        } catch (DuplicateLedgerUploadException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $upload->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);

            ActivityLog::record(
                'import',
                'error',
                'Import failed',
                "{$originalName} — {$e->getMessage()}",
                $uploadedBy
            );

            throw $e;
        }

        return $upload->fresh();
    }

    protected function loadSheet(string $absolutePath): Worksheet
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $limit = $this->cfg['import_row_limit'] ?? 10000;
        $reader->setReadFilter(new class($limit) implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
            public function __construct(private int $limit) {}
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool {
                return $row <= $this->limit;
            }
        });
        $spreadsheet = $reader->load($absolutePath);

        return $spreadsheet->getSheetByName($this->cfg['sheet'])
            ?? $spreadsheet->getActiveSheet();
    }
}
