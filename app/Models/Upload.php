<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per import attempt.
 *
 * Permanent: this is the audit trail (who imported what, when, with which
 * fingerprint). Its heavy artefacts - the ledger rows and the stored workbook -
 * are pruned independently by LedgerImportService::applyRetention().
 */
class Upload extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_current' => 'boolean',
        'header_map' => 'array',
        'rows_pruned_at' => 'datetime',
        'file_deleted_at' => 'datetime',
        'rod_exported_at' => 'datetime',
    ];

    public function generalLedgers(): HasMany
    {
        return $this->hasMany(GeneralLedger::class);
    }

    /** The live snapshot for a fiscal year, if any. */
    public static function current(int $year): ?self
    {
        return static::where('fiscal_year', $year)
            ->where('is_current', true)
            ->where('status', 'done')
            ->first();
    }

    public function isImported(): bool
    {
        return $this->status === 'done';
    }

    /** Ledger rows still present (not yet reclaimed by retention). */
    public function hasRows(): bool
    {
        return $this->rows_pruned_at === null;
    }

    /** The workbook is still on disk, so export can use it as a template. */
    public function hasFile(): bool
    {
        return $this->file_deleted_at === null
            && $this->stored_path
            && is_file($this->stored_path);
    }

    /** The most recent ROD export finished and its file is still on disk. */
    public function hasRodExport(): bool
    {
        return $this->rod_export_status === 'done'
            && $this->rod_export_path
            && is_file($this->rod_export_path);
    }

    /** Months present in this snapshot, ascending. */
    public function months(): array
    {
        return $this->generalLedgers()
            ->whereNotNull('ledger_month')
            ->distinct()
            ->orderBy('ledger_month')
            ->pluck('ledger_month')
            ->all();
    }
}
