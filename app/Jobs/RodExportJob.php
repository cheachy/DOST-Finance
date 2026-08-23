<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\Upload;
use App\Services\RodExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writing the ROD back into a copy of the real workbook is CPU- and
 * memory-bound the same way parsing one is (see ImportLedgerJob) - worse,
 * in fact, since it loads AND re-saves the whole file. Confirmed 2026-08-13
 * against the production FY2026 workbook (2,142 transactions, 10 MB):
 * ~288s and ~1.9 GB peak. Does not belong in a web request.
 */
class RodExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        protected int $uploadId,
        protected ?int $requestedBy = null,
    ) {}

    public function handle(RodExportService $exportService): void
    {
        @ini_set('memory_limit', '3G');

        $upload = Upload::find($this->uploadId);
        if (! $upload) {
            return;
        }

        try {
            $result = $exportService->export($upload);

            $scope = $result->months
                ? 'months '.implode('/', $result->months)
                : 'all months';

            $summary = $scope.' — '.collect($result->written)
                ->map(fn ($s, $class) => "{$class} {$s['count']}/".number_format($s['total'], 2))
                ->implode(', ');

            $upload->update([
                'rod_export_status' => 'done',
                'rod_export_path' => $result->path,
                'rod_exported_at' => now(),
                'rod_export_error' => null,
            ]);

            ActivityLog::record(
                'export',
                'success',
                'ROD test export generated',
                "Snapshot #{$upload->id} — {$summary}",
                $this->requestedBy
            );

            Log::info('ROD export generated', ['upload_id' => $upload->id, 'path' => $result->path]);
        } catch (Throwable $e) {
            $upload->update([
                'rod_export_status' => 'failed',
                'rod_export_error' => $e->getMessage(),
            ]);

            ActivityLog::record(
                'export',
                'error',
                'ROD test export failed',
                "Snapshot #{$upload->id} — {$e->getMessage()}",
                $this->requestedBy
            );

            Log::error('ROD export failed', ['upload_id' => $upload->id, 'error' => $e->getMessage()]);
        }
    }
}
