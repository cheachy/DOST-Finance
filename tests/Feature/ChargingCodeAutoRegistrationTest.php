<?php

namespace Tests\Feature;

use App\Services\LedgerImportService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;
use Throwable;

/**
 * Pins the regression that bit us on 2026-08-13.
 *
 * The 14 charging codes the Ref sheet spells differently from the ledger
 * ("Green Wave" vs "SAA-Green Wave", "Regular-Onelab" vs "Regular MOOE
 * (Onelab)") used to match no reference row at all, so they belonged to no
 * allotment class and contributed to NO ROD column. The only symptom was a
 * total that came out low. It was first "fixed" by registering aliases by
 * hand - which a rebuild silently wiped, taking the fix with it.
 *
 * The real regression is therefore not "are the codes classified" but
 * "does a FRESH database classify them with nobody typing anything". That is
 * what this test exercises end to end: empty schema -> one real import through
 * LedgerImportService -> every charging code classified, zero manual steps.
 * Reading the import ordering and concluding it must work is not enough; this
 * project has been bitten by "correct by construction" before.
 *
 * Runs against POSTGRES, not the suite's default sqlite: the schema uses jsonb
 * and GeneralLedger::ROD_AMOUNT_SQL uses postgres json operators, so sqlite
 * cannot host it. Skips (never fails) when no postgres is reachable.
 */
class ChargingCodeAutoRegistrationTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../Fixtures/sample-master-ledger.xlsx';

    /** Copy of the fixture that the importer is allowed to own and prune. */
    private ?string $workingCopy = null;

    protected function setUp(): void
    {
        parent::setUp();

        $database = env('DB_TEST_DATABASE', 'dost_finance_test');

        // This test runs migrate:fresh, which DROPS EVERY TABLE. Refuse to
        // point that at the database the app itself uses - a stray
        // DB_TEST_DATABASE would otherwise destroy the real ledger.
        $primary = config('database.connections.pgsql.database');
        if ($database === $primary) {
            $this->fail("refusing to run: DB_TEST_DATABASE ({$database}) is the app's own database");
        }

        $this->usePostgres($database);
        Artisan::call('migrate:fresh', ['--force' => true]);

        // Retention may delete the file it imported, so never hand it the
        // committed fixture itself.
        $this->workingCopy = sys_get_temp_dir().'/ledger-fixture-'.getmypid().'.xlsx';
        copy(self::FIXTURE, $this->workingCopy);
    }

    protected function tearDown(): void
    {
        if ($this->workingCopy && is_file($this->workingCopy)) {
            @unlink($this->workingCopy);
        }

        parent::tearDown();
    }

    public function test_a_fresh_import_classifies_every_charging_code_with_no_manual_steps(): void
    {
        // A rebuild leaves nothing behind - this is the state that broke us.
        $this->assertSame(0, $this->chargingRefs()->count(), 'fresh schema should hold no charging references');

        $upload = app(LedgerImportService::class)
            ->import($this->workingCopy, 2026, 'sample-master-ledger.xlsx');

        $this->assertSame('done', $upload->status);
        $this->assertGreaterThan(0, $upload->transaction_count);

        // THE regression: no transaction may carry a charging code that
        // resolves to no reference row. Such a row belongs to no allotment
        // class and silently contributes nothing to the ROD.
        $orphans = DB::table('general_ledgers')
            ->where('upload_id', $upload->id)
            ->where('row_type', 'transaction')
            ->whereNotNull('charging_code')
            ->where('charging_code', '<>', '')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('account_references')
                ->whereColumn('account_references.code', 'general_ledgers.charging_code')
                ->where('account_references.ref_type', 'charging'))
            ->pluck('charging_code')
            ->unique()
            ->values();

        $this->assertCount(0, $orphans, 'unclassified charging codes: '.$orphans->implode(', '));

        // Nobody typed anything: no aliases were created to achieve the above.
        $this->assertSame(
            0,
            $this->chargingRefs()->whereNotNull('canonical_code')->count(),
            'classification must not depend on hand-registered aliases'
        );
    }

    public function test_discovered_codes_are_classified_by_rule_but_left_unrouted(): void
    {
        app(LedgerImportService::class)
            ->import($this->workingCopy, 2026, 'sample-master-ledger.xlsx');

        $discovered = $this->chargingRefs()->where('source', 'ledger')->get()->keyBy('code');

        $this->assertNotEmpty($discovered, 'the fixture must exercise ledger-only codes');

        // Class is derived from the code string - the same rule Ref codes use.
        $this->assertSame('MOOE', $discovered['Green Wave']->allotment_class);
        $this->assertSame('CO', $discovered['CO']->allotment_class);
        $this->assertTrue((bool) $discovered['2025 NYDD']->is_prior_year);

        // ...but TAB IDENTITY is never guessed. Every auto-registered code
        // stays unrouted and inactive until she confirms it, because two codes
        // sharing a class are not thereby the same subsidiary ledger. See
        // AccountReferenceSeeder::syncObserved(). If this assertion ever fails,
        // something started inventing SL routing.
        foreach ($discovered as $code => $ref) {
            $this->assertNull($ref->sl_tab, "{$code} must not be auto-routed to a tab");
            $this->assertFalse((bool) $ref->is_active, "{$code} must not be auto-activated");
        }
    }

    private function chargingRefs()
    {
        return DB::table('account_references')->where('ref_type', 'charging');
    }

    /**
     * Point the default connection at a scratch postgres database, creating it
     * if absent. Skips the test when postgres is unreachable.
     */
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
