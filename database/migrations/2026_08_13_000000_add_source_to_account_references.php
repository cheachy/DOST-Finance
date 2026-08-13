<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a charging reference came from.
 *
 *   'ref'    - read from the workbook's own Ref sheet. Authoritative: she
 *              maintains that list deliberately.
 *   'ledger' - never seen in Ref, but used on a real transaction row, so
 *              AccountReferenceSeeder registered it automatically and derived
 *              its class by rule. Correct enough to report on, but nobody has
 *              confirmed it - this is what the dashboard asks her to review.
 *
 * Without this column the two are indistinguishable: a Ref code awaiting an
 * SL tab and an auto-discovered code look identical (both sl_tab null,
 * is_active false), so the dashboard could not tell "out of Phase 1 scope"
 * from "we guessed this".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_references', function (Blueprint $t) {
            $t->string('source', 16)->default('ref')->after('canonical_code');
        });
    }

    public function down(): void
    {
        Schema::table('account_references', function (Blueprint $t) {
            $t->dropColumn('source');
        });
    }
};
