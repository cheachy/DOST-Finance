<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activity log - imports, sign-ins, exports.
 *
 * Permanent record: rows are appended, never edited or deleted, so this
 * table is the audit trail for "who did what, when."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->string('type');                      // import | login | export
            $t->string('level')->default('info');     // success | info | error - drives the dot color
            $t->string('title');                      // "Ledger imported", "Signed in", "Import failed"
            $t->string('description')->nullable();    // e.g. filename + detail
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();

            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
