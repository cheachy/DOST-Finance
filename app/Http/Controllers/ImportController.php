<?php

namespace App\Http\Controllers;

use App\Services\LedgerImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function store(Request $request, LedgerImportService $importService)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        // Store the uploaded file permanently — this becomes the base
        // file the exporter opens and surgically edits later. Never
        // discard the original after parsing it.
        // Explicitly the 'local' disk, not whatever FILESYSTEM_DISK
        // happens to default to — this feature needs a real local file
        // path (the parser opens it directly, and export later reopens
        // the same original file to surgically edit it). A misconfigured
        // default disk here previously caused silent upload hangs.
        $path = $request->file('file')->store('ledger-imports', 'local');
        $fullPath = Storage::disk('local')->path($path);

        try {
            $import = $importService->import($fullPath, $request->user()->id);
        } catch (\RuntimeException $e) {
            // A sheet couldn't be safely parsed (missing required
            // columns) — surface this clearly rather than a generic 500.
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('status', "Import complete: {$import->notes}");
    }
}