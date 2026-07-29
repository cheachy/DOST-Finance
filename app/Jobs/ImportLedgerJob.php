<?php

namespace App\Jobs;

use App\Exceptions\DuplicateLedgerUploadException;
use App\Services\LedgerImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Parsing a 10 MB workbook with cached formula values and styles is CPU- and
 * memory-bound (roughly a minute, and well past the default memory_limit).
 * It does not belong in a web request.
 */
class ImportLedgerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;   // a failed parse should be inspected, not retried blindly

    public function __construct(
        protected string $absolutePath,
        protected int $year,
        protected string $originalName,
        protected ?int $uploadedBy = null,
        protected bool $force = false,
    ) {}

    public function handle(LedgerImportService $importer): void
    {
        @ini_set('memory_limit', '1G');

        try {
            $upload = $importer->import(
                $this->absolutePath,
                $this->year,
                $this->originalName,
                $this->uploadedBy,
                $this->force,
            );

            Log::info('Ledger imported', [
                'upload_id' => $upload->id,
                'rows' => $upload->row_count,
                'transactions' => $upload->transaction_count,
            ]);
        } catch (DuplicateLedgerUploadException $e) {
            // The workbook is already imported and unchanged.
            Log::info('Ledger import skipped as duplicate', [
                'matched_on' => $e->matchedOn,
                'existing' => $e->existing->id,
            ]);
        }
    }
}
