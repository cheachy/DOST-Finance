<?php

namespace Tests\Feature;

use App\Models\GeneralLedger;
use App\Models\User;
use App\Services\Ledger\LedgerHeaderAnalyzer;
use App\Services\LedgerImportService;
use App\Services\RodExportService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use Throwable;

/**
 * Proves the ROD write-back (RodExportService) lands in the right cells and
 * touches nothing else - the two guarantees the accountant actually cares
 * about: her prior-year columns stay exactly as she typed them, and every
 * value that DOES move is one the generator produced for a real A/C-gated
 * current-year row.
 *
 * "Right cells" is checked by summing the generated columns OFF THE WRITTEN
 * FILE, restricted to the exact source_rows the generator is responsible for
 * (see RodExportService's own doc block) - summing the whole column range
 * would double-count against the sheet's own subtotal/monthly-total rows,
 * which legitimately share the same columns.
 *
 * Runs against POSTGRES for the same reason as ChargingCodeAutoRegistrationTest
 * (jsonb + ROD_AMOUNT_SQL). Skips (never fails) when no postgres is reachable.
 */
class RodExportTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../Fixtures/sample-master-ledger.xlsx';

    private ?string $workingCopy = null;

    private ?string $exportedPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $database = env('DB_TEST_DATABASE', 'dost_finance_test');
        $primary = config('database.connections.pgsql.database');
        if ($database === $primary) {
            $this->fail("refusing to run: DB_TEST_DATABASE ({$database}) is the app's own database");
        }

        $this->usePostgres($database);
        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->workingCopy = sys_get_temp_dir().'/ledger-fixture-rodexport-'.getmypid().'.xlsx';
        copy(self::FIXTURE, $this->workingCopy);
    }

    protected function tearDown(): void
    {
        if ($this->workingCopy && is_file($this->workingCopy)) {
            @unlink($this->workingCopy);
        }
        if ($this->exportedPath && is_file($this->exportedPath)) {
            @unlink($this->exportedPath);
        }

        parent::tearDown();
    }

    public function test_export_writes_only_current_year_ps_mooe_co_on_eligible_rows(): void
    {
        $upload = app(LedgerImportService::class)
            ->import($this->workingCopy, 2026, 'sample-master-ledger.xlsx');

        $result = app(RodExportService::class)->export($upload);
        $this->exportedPath = $result->path;

        $this->assertFileExists($result->path);

        $sheet = IOFactory::createReaderForFile($result->path)->load($result->path)->getSheetByName('MDS 101');
        $map = app(LedgerHeaderAnalyzer::class)->buildColumnMap(
            $sheet,
            app(LedgerHeaderAnalyzer::class)->detectHeaderRow($sheet)
        );

        // 1. Column sums off the FILE, on the exact rows the generator owns
        // WITHIN THE SCOPED MONTHS, equal what ledger:rod-verify checks.
        $months = config('ledger.rod_export_months');
        $this->assertNotEmpty($months, 'the export scope must be configured for this test to mean anything');

        foreach (['PS' => 'cur_ps', 'MOOE' => 'cur_mooe', 'CO' => 'cur_co'] as $class => $field) {
            $colLetter = Coordinate::stringFromColumnIndex($map[$field]);

            $eligible = fn () => GeneralLedger::where('upload_id', $upload->id)
                ->transactions()->disbursed()->currentYearAllotment()
                ->whereIn('ledger_month', $months)
                ->whereIn('charging_code', fn ($q) => $q->select('code')->from('account_references')
                    ->where('ref_type', 'charging')->where('allotment_class', $class)->where('is_prior_year', false));

            $rows = $eligible()->pluck('source_row');

            $fileSum = 0.0;
            foreach ($rows as $r) {
                $v = $sheet->getCell($colLetter.$r)->getValue();
                if (is_numeric($v)) {
                    $fileSum += (float) $v;
                }
            }

            $generated = (float) $eligible()->sum(DB::raw(GeneralLedger::ROD_AMOUNT_SQL));

            $this->assertEqualsWithDelta($generated, $fileSum, 0.01, "{$class} column sum off the written file must match the generated total for months ".implode('/', $months));
        }

        // 2. Prior-year columns are byte-identical to the source workbook.
        $srcSheet = IOFactory::createReaderForFile($this->workingCopy)->load($this->workingCopy)->getSheetByName('MDS 101');
        $mainRow = app(LedgerHeaderAnalyzer::class)->detectHeaderRow($srcSheet);
        $highestRow = $sheet->getHighestRow();

        foreach (['prior_ps', 'prior_mooe', 'prior_co'] as $field) {
            $colLetter = Coordinate::stringFromColumnIndex($map[$field]);
            for ($r = $mainRow + 1; $r <= $highestRow; $r++) {
                $before = $srcSheet->getCell($colLetter.$r)->getValue();
                $after = $sheet->getCell($colLetter.$r)->getValue();
                $this->assertSame($before, $after, "prior-year cell {$colLetter}{$r} must be untouched");
            }
        }

        // 3. Every current-year cell that DID change belongs to an eligible row
        // IN A SCOPED MONTH - a value landing in March would be out of scope
        // even though the row itself is otherwise eligible.
        $eligible = GeneralLedger::where('upload_id', $upload->id)
            ->transactions()->disbursed()->currentYearAllotment()
            ->whereIn('ledger_month', $months)
            ->pluck('source_row')->flip();

        foreach (['cur_ps', 'cur_mooe', 'cur_co'] as $field) {
            $colLetter = Coordinate::stringFromColumnIndex($map[$field]);
            for ($r = $mainRow + 1; $r <= $highestRow; $r++) {
                $before = $srcSheet->getCell($colLetter.$r)->getValue();
                $after = $sheet->getCell($colLetter.$r)->getValue();
                if ($before !== $after) {
                    $this->assertTrue($eligible->has($r), "changed cell {$colLetter}{$r} must be an eligible A/C-gated current-year row");
                }
            }
        }

        // 4. Format survives: same tabs, same merges.
        $srcWb = IOFactory::createReaderForFile($this->workingCopy)->load($this->workingCopy);
        $outWb = IOFactory::createReaderForFile($result->path)->load($result->path);
        $this->assertSame($srcWb->getSheetNames(), $outWb->getSheetNames());
        $this->assertSame(count($srcSheet->getMergeCells()), count($sheet->getMergeCells()));
    }

    /**
     * The write-back is queued (RodExportJob), never run inline - confirmed
     * 2026-08-13 against the real production workbook that it takes ~288s
     * and ~1.9 GB peak, which has no place in a web request (see
     * RodExportJob's doc block). QUEUE_CONNECTION=sync in phpunit.xml runs
     * the job inline for the test, so this still exercises the real job
     * class end to end without needing a worker process.
     */
    public function test_export_is_queued_and_becomes_downloadable_without_touching_the_stored_snapshot(): void
    {
        $upload = app(LedgerImportService::class)
            ->import($this->workingCopy, 2026, 'sample-master-ledger.xlsx');

        $originalHash = hash_file('sha256', $upload->stored_path);
        $user = User::factory()->create();

        $queued = $this->actingAs($user)->post('/reports/rod-export');
        $queued->assertSessionHasNoErrors();

        $upload->refresh();
        $this->assertSame('done', $upload->rod_export_status);
        $this->assertNotNull($upload->rod_export_path);
        $this->assertNotNull($upload->rod_exported_at);
        $this->exportedPath = $upload->rod_export_path;

        $download = $this->actingAs($user)->get('/reports/rod-export/download');
        $download->assertOk();
        $download->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertSame($originalHash, hash_file('sha256', $upload->stored_path), 'the stored snapshot must never be modified by export');
    }

    /**
     * The write-back is deliberately scoped to a month slice (January and
     * February first) so it can be checked against totals that were verified
     * independently. A row in March is eligible in every other respect - it
     * is an A/C-gated current-year transaction - and must STILL be left alone.
     */
    public function test_export_writes_only_the_configured_month_slice(): void
    {
        $upload = app(LedgerImportService::class)
            ->import($this->workingCopy, 2026, 'sample-master-ledger.xlsx');

        // The fixture carries January only, so scoping the export to February
        // must produce a file with nothing written at all - proving the month
        // filter EXCLUDES rows that are eligible in every other respect,
        // rather than the scope being decorative.
        $present = GeneralLedger::where('upload_id', $upload->id)
            ->transactions()->disbursed()->currentYearAllotment()
            ->distinct()->pluck('ledger_month')->all();

        $this->assertSame([1], $present, 'fixture is expected to hold January only');

        config()->set('ledger.rod_export_months', [2]);

        $result = app(RodExportService::class)->export($upload);
        $this->exportedPath = $result->path;

        $this->assertSame([2], $result->months);
        $this->assertSame(0, $result->totalWritten(), 'no January row may be written when the scope is February');
        $this->assertSame([], $result->byMonth);

        // Every current-year cell must be exactly as she typed it.
        $sheet = IOFactory::createReaderForFile($result->path)->load($result->path)->getSheetByName('MDS 101');
        $srcSheet = IOFactory::createReaderForFile($this->workingCopy)->load($this->workingCopy)->getSheetByName('MDS 101');
        $analyzer = app(LedgerHeaderAnalyzer::class);
        $mainRow = $analyzer->detectHeaderRow($sheet);
        $map = $analyzer->buildColumnMap($sheet, $mainRow);
        $highestRow = $sheet->getHighestRow();

        foreach (['cur_ps', 'cur_mooe', 'cur_co'] as $field) {
            $col = Coordinate::stringFromColumnIndex($map[$field]);
            for ($r = $mainRow + 1; $r <= $highestRow; $r++) {
                $this->assertSame(
                    $srcSheet->getCell($col.$r)->getValue(),
                    $sheet->getCell($col.$r)->getValue(),
                    "{$col}{$r} is outside the month scope and must be untouched"
                );
            }
        }
    }

    /** Before any export has ever run there is simply nothing to hand her. */
    public function test_download_redirects_when_no_export_has_ever_run(): void
    {
        app(LedgerImportService::class)
            ->import($this->workingCopy, 2026, 'sample-master-ledger.xlsx');

        $this->actingAs(User::factory()->create())
            ->get('/reports/rod-export/download')
            ->assertRedirect(route('reports'))
            ->assertSessionHasErrors('rod_export');
    }

    /**
     * Exports are disposable build artefacts in storage - they get cleaned
     * up, wiped, or moved between a page render and a click. When that
     * happens she must be told the file expired and offered a re-run, not
     * dropped on a bare 404 that explains nothing.
     */
    public function test_download_redirects_with_an_explanation_when_the_export_file_is_gone(): void
    {
        $upload = app(LedgerImportService::class)
            ->import($this->workingCopy, 2026, 'sample-master-ledger.xlsx');

        $user = User::factory()->create();
        $this->actingAs($user)->post('/reports/rod-export')->assertSessionHasNoErrors();

        $upload->refresh();
        $this->assertTrue($upload->hasRodExport());

        // Simulate the file being cleaned up while the DB still says 'done'.
        unlink($upload->rod_export_path);
        $upload->refresh();

        $this->assertSame('done', $upload->rod_export_status, 'status stays done; only the artefact is gone');
        $this->assertFalse($upload->hasRodExport());

        $this->actingAs($user)
            ->get('/reports/rod-export/download')
            ->assertRedirect(route('reports'))
            ->assertSessionHasErrors('rod_export');

        // The Reports page flags it as expired rather than offering a download.
        $this->actingAs($user)->get('/reports')->assertInertia(
            fn ($page) => $page->where('snapshot.rod_export_missing', true)
                ->where('snapshot.rod_export_ready', false)
        );
    }

    private function usePostgres(string $database): void
    {
        $host = config('database.connections.pgsql.host', '127.0.0.1');
        $port = config('database.connections.pgsql.port', 5432);
        $user = config('database.connections.pgsql.username', 'postgres');
        $pass = config('database.connections.pgsql.password', '');

        try {
            $pdo = new PDO("pgsql:host={$host};port={$port};dbname=postgres", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $exists = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
            $exists->execute([$database]);

            if (! $exists->fetchColumn()) {
                $pdo->exec('CREATE DATABASE "'.str_replace('"', '', $database).'"');
            }
        } catch (Throwable $e) {
            $this->markTestSkipped('postgres unavailable, cannot exercise the real schema: '.$e->getMessage());
        }

        config()->set('database.connections.pgsql.database', $database);
        config()->set('database.default', 'pgsql');
        DB::purge('pgsql');
    }
}
