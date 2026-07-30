<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The intended way to teach the app about a typo/inconsistent charging code,
 * with no code change:
 *
 *   php artisan ledger:alias list
 *   php artisan ledger:alias add "Regular-Onelab" "Regular MOOE (Onelab)"
 *   php artisan ledger:alias remove "Regular-Onelab"
 *
 * An alias row's scope (sl_tab / allotment_class / is_prior_year / is_active)
 * is ALWAYS a mirror of its canonical row - set canonical_code once here, and
 * AccountReferenceSeeder keeps the mirrored fields in sync forever after.
 * There is no PHP array of known aliases; this command IS the source of
 * truth, same as `ledger:sl-tab` is for tab scope.
 */
class AliasCommand extends Command
{
    protected $signature = 'ledger:alias
        {action : list|add|remove}
        {raw?    : the exact string seen in the ledger, e.g. "Regular-Onelab"}
        {canonical? : the real charging code it means, e.g. "Regular MOOE (Onelab)"}';

    protected $description = 'Manage charging-code aliases (typos/variants) as data';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'list' => $this->list(),
            'add' => $this->add(),
            'remove' => $this->remove(),
            default => $this->abortCommand('action must be one of: list, add, remove'),
        };
    }

    private function list(): int
    {
        $rows = DB::table('account_references')
            ->where('ref_type', 'charging')
            ->whereNotNull('canonical_code')
            ->orderBy('canonical_code')
            ->get(['code', 'canonical_code', 'sl_tab', 'is_active']);

        if ($rows->isEmpty()) {
            $this->info('No aliases registered yet.');
            $this->line('  php artisan ledger:alias add "<raw string>" "<canonical code>"');

            return self::SUCCESS;
        }

        $this->table(['Alias (as typed)', 'Canonical', 'SL tab', 'Active'], $rows->map(fn ($r) => [
            $r->code, $r->canonical_code, $r->sl_tab ?? '(unrouted)', $r->is_active ? 'yes' : 'no',
        ]));

        return self::SUCCESS;
    }

    private function add(): int
    {
        $raw = $this->argument('raw');
        $canonical = $this->argument('canonical');
        if (! $raw || ! $canonical) {
            return $this->abortCommand('usage: ledger:alias add "<raw string>" "<canonical code>"');
        }

        $target = DB::table('account_references')
            ->where('ref_type', 'charging')
            ->where('code', $canonical)
            ->whereNull('canonical_code') // must itself be a real canonical row, not another alias
            ->first();

        if (! $target) {
            $this->error("No canonical charging code '{$canonical}' found.");
            $this->line('Check spelling with: php artisan ledger:sl-tab codes');

            return self::FAILURE;
        }

        DB::table('account_references')->updateOrInsert(
            ['ref_type' => 'charging', 'code' => $raw],
            [
                'canonical_code' => $canonical,
                'label' => $canonical,
                'allotment_class' => $target->allotment_class,
                'is_prior_year' => $target->is_prior_year,
                'sl_tab' => $target->sl_tab,
                'is_active' => $target->is_active,
            ]
        );

        $this->info("'{$raw}' now aliases '{$canonical}' (sl_tab: ".($target->sl_tab ?? 'none').').');
        $this->line('This mirrors automatically if the canonical\'s tab ever changes.');

        return self::SUCCESS;
    }

    private function remove(): int
    {
        $raw = $this->argument('raw');
        if (! $raw) {
            return $this->abortCommand('usage: ledger:alias remove "<raw string>"');
        }

        $deleted = DB::table('account_references')
            ->where('ref_type', 'charging')
            ->where('code', $raw)
            ->whereNotNull('canonical_code')
            ->delete();

        if ($deleted === 0) {
            $this->error("No alias found for '{$raw}'.");

            return self::FAILURE;
        }

        $this->info("Alias '{$raw}' removed.");

        return self::SUCCESS;
    }

    private function abortCommand(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
