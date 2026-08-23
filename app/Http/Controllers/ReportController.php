<?php

namespace App\Http\Controllers;

use App\Jobs\RodExportJob;
use App\Models\Upload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $year = (int) date('Y');
        $upload = Upload::current($year);

        return Inertia::render('reports/Index', [
            'hasLedger' => (bool) $upload,
            'snapshot' => $upload ? [
                'id' => $upload->id,
                'original_name' => $upload->original_name,
                'fiscal_year' => $upload->fiscal_year,
                'transaction_count' => $upload->transaction_count,
                'has_file' => $upload->hasFile(),
                'rod_export_status' => $upload->rod_export_status,
                'rod_export_error' => $upload->rod_export_error,
                'rod_exported_at' => $upload->rod_exported_at?->format('M j, Y g:i A'),
                'rod_export_ready' => $upload->hasRodExport(),
                // Finished once, but the .xlsx is no longer on disk (cleaned
                // up, moved, or wiped with the storage dir). Distinct from
                // "never exported" - she should be told it expired rather
                // than be shown a download link that 404s.
                'rod_export_missing' => $upload->rod_export_status === 'done' && ! $upload->hasRodExport(),
            ] : null,
        ]);
    }

    /**
     * Testing-only: queues RodExportJob to write the generated current-year
     * ROD figures into a COPY of the current snapshot's stored workbook.
     *
     * This is NOT done inline — writing the real production workbook back
     * out with PhpSpreadsheet takes several minutes and multiple gigabytes
     * of memory (confirmed 2026-08-13 against the real FY2026 file), which
     * has no place in a web request. See RodExportJob.
     */
    public function rodExportQueue(Request $request): RedirectResponse
    {
        $year = (int) date('Y');
        $upload = Upload::current($year);

        if (! $upload) {
            return back()->withErrors(['rod_export' => 'No current ledger snapshot to export from.']);
        }

        $upload->update([
            'rod_export_status' => 'queued',
            'rod_export_error' => null,
        ]);

        RodExportJob::dispatch($upload->id, $request->user()?->id);

        return back();
    }

    /**
     * Downloads the most recently finished export, once RodExportJob is done.
     *
     * A missing file is an ordinary state, not an error page: exports are
     * disposable build artefacts living in storage, so they can be cleaned
     * up or wiped between the page rendering and the click. Send her back to
     * Reports with an explanation and a working "re-run" button rather than
     * a bare 404 that says nothing about what to do next.
     */
    public function rodExportDownload(Request $request): BinaryFileResponse|RedirectResponse
    {
        $year = (int) date('Y');
        $upload = Upload::current($year);

        if (! $upload || ! $upload->hasRodExport()) {
            return redirect()->route('reports')->withErrors([
                'rod_export' => 'That export file is no longer available — it may have been cleaned up. Run the export again to generate a fresh copy.',
            ]);
        }

        $downloadName = "ROD-export-FY{$upload->fiscal_year}-snapshot{$upload->id}-"
            .$upload->rod_exported_at->format('Ymd_His').'.xlsx';

        return response()->download($upload->rod_export_path, $downloadName);
    }
}
