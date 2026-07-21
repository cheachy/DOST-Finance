<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LedgerImportService;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class ImportLedger extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ledger:import {file : The absolute path to the Excel file to import} {--user=1 : ID of the user performing the import} {--year= : Fiscal year of the ledger (defaults to the current year)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports a ledger excel file (bypassing HTTP file size limits)';

    /**
     * Execute the console command.
     */
    public function handle(LedgerImportService $importService)
    {
        $filePath = $this->argument('file');
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $userId = $this->option('user');
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        $this->info("Copying file into storage/app/ledger-imports...");
        // Ensure directory exists
        if (!Storage::disk('local')->exists('ledger-imports')) {
            Storage::disk('local')->makeDirectory('ledger-imports');
        }

        // Generate a filename and copy it
        $filename = uniqid('cmd_import_') . '.xlsx';
        Storage::disk('local')->put('ledger-imports/' . $filename, file_get_contents($filePath));
        $fullPath = Storage::disk('local')->path('ledger-imports/' . $filename);

        $this->info("Parsing file (this might take a few minutes for large files)...");
        try {
            $year = (int) ($this->option('year') ?? date('Y'));
            $import = $importService->import($fullPath, $year, basename($filePath), $user->id);
            $this->info("Import complete: {$import->row_count} rows imported (Upload #{$import->id}).");
            return 0;
        } catch (\Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
