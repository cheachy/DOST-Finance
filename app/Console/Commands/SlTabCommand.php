<?php

namespace App\Console\Commands;

use App\Models\SlTab;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The intended way to scale to a new subsidiary ledger, with no code change:
 *
 *   php artisan ledger:sl-tab list
 *   php artisan ledger:sl-tab add ONELAB "Onelab"
 *   php artisan ledger:sl-tab assign "Regular MOOE (Onelab)" ONELAB
 *   php artisan ledger:sl-tab codes ONELAB
 *
 * This is what account_references / sl_tabs were designed for: adding a tab
 * is a DATA operation, and AccountReferenceSeeder is written to never
 * overwrite a scope decision made here on a subsequent re-seed.
 */
class SlTabCommand extends Command
{
    protected $signature = 'ledger:sl-tab
        {action : list|add|assign|codes}
        {arg1?} {arg2?}';

    protected $description = 'Manage subsidiary-ledger tabs and charging-code routing';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'list' => $this->list(),
            'add' => $this->add(),
            'assign' => $this->assign(),
            'codes' => $this->codes(),
            default => $this->abortCommand('action must be one of: list, add, assign, codes'),
        };
    }

    private function list(): int
    {
        $rows = SlTab::orderBy('display_order')->get();
        $this->table(['Code', 'Label', 'Order', 'Active', 'Charging codes routed'], $rows->map(fn ($t) => [
            $t->code,
            $t->label,
            $t->display_order,
            $t->is_active ? 'yes' : 'no',
            DB::table('account_references')->where('sl_tab', $t->code)->count(),
        ]));

        return self::SUCCESS;
    }

    private function add(): int
    {
        $code = $this->argument('arg1');
        $label = $this->argument('arg2') ?? $code;
        if (! $code) {
            return $this->abortCommand('usage: ledger:sl-tab add CODE "Label"');
        }

        $nextOrder = (int) SlTab::max('display_order') + 1;

        SlTab::updateOrInsert(
            ['code' => $code],
            ['label' => $label, 'display_order' => $nextOrder, 'is_active' => true]
        );

        $this->info("Tab '{$code}' ready. Now route charging codes to it:");
        $this->line("  php artisan ledger:sl-tab assign \"<charging code>\" {$code}");

        return self::SUCCESS;
    }

    private function assign(): int
    {
        $chargingCode = $this->argument('arg1');
        $tab = $this->argument('arg2');
        if (! $chargingCode || ! $tab) {
            return $this->abortCommand('usage: ledger:sl-tab assign "<charging code>" TAB_CODE');
        }

        if (! SlTab::where('code', $tab)->exists()) {
            $this->error("No such tab '{$tab}'. Run: ledger:sl-tab add {$tab} \"Label\" first.");

            return self::FAILURE;
        }

        $updated = DB::table('account_references')
            ->where('ref_type', 'charging')
            ->where('code', $chargingCode)
            ->update(['sl_tab' => $tab, 'is_active' => true]);

        if ($updated === 0) {
            $this->error("No account_references row for charging code '{$chargingCode}'.");
            $this->line('Check spelling with: ledger:sl-tab codes '.$tab);

            return self::FAILURE;
        }

        $this->info("'{$chargingCode}' now routes to {$tab}.");

        return self::SUCCESS;
    }

    private function codes(): int
    {
        $tab = $this->argument('arg1');
        $rows = DB::table('account_references')
            ->where('ref_type', 'charging')
            ->when($tab, fn ($q) => $q->where('sl_tab', $tab))
            ->orderBy('code')
            ->get(['code', 'sl_tab', 'is_active', 'allotment_class']);

        $this->table(['Charging code', 'SL tab', 'Active', 'Class'], $rows->map(fn ($r) => [
            $r->code, $r->sl_tab ?? '(unrouted)', $r->is_active ? 'yes' : 'no', $r->allotment_class,
        ]));

        return self::SUCCESS;
    }

    private function abortCommand(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
