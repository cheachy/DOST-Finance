<?php

namespace App\Services\Ledger;

use App\Models\GeneralLedger;
use App\Models\Upload;
use Illuminate\Support\Facades\Log;

class LedgerRetentionManager
{
    protected array $cfg;

    public function __construct()
    {
        $this->cfg = config('ledger');
    }

    public function findDuplicate(int $year, string $column, string $hash, ?int $excludeId = null): ?Upload
    {
        return Upload::where('fiscal_year', $year)
            ->where('status', 'done')
            ->where($column, $hash)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('id')
            ->first();
    }

    public function contentHash(array $records): string
    {
        $ctx = hash_init('sha256');

        foreach ($records as $rec) {
            $slim = [
                'r' => $rec['source_row'],
                't' => $rec['row_type'],
                'y' => $rec['ledger_year'],
                'm' => $rec['ledger_month'],
                'x' => $rec['tax_details'],
            ];
            foreach ($this->cfg['fields'] as $field) {
                $slim[$field] = $rec[$field] ?? null;
            }
            ksort($slim);
            hash_update($ctx, json_encode($slim)."\n");
        }

        return hash_final($ctx);
    }

    public function applyRetention(Upload $current): void
    {
        $completed = Upload::where('fiscal_year', $current->fiscal_year)
            ->where('status', 'done')
            ->orderByDesc('id')
            ->get();

        $keepRows = (int) ($this->cfg['keep_row_snapshots'] ?? 2);
        $keepFiles = (int) ($this->cfg['keep_files'] ?? 1);

        foreach ($completed as $i => $upload) {
            // rank 0 is the newest (the snapshot just imported)
            if ($keepRows > 0 && $i >= $keepRows && ! $upload->rows_pruned_at) {
                GeneralLedger::where('upload_id', $upload->id)->delete();
                $upload->forceFill(['rows_pruned_at' => now()])->save();
            }

            if ($i >= $keepFiles && ! $upload->file_deleted_at) {
                $this->deleteStoredFile($upload);
            }
        }
    }

    protected function deleteStoredFile(Upload $upload): void
    {
        $path = $upload->stored_path;

        if ($path && is_file($path) && ! @unlink($path)) {
            // Leave file_deleted_at null so the next import retries this
            // upload instead of silently forgetting it was never removed
            // (unlink can fail transiently - permissions, AV/indexer lock).
            Log::warning('Ledger retention: failed to delete stored file', [
                'upload_id' => $upload->id,
                'path' => $path,
            ]);

            return;
        }

        $upload->forceFill(['file_deleted_at' => now()])->save();
    }
}
