<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ExcelImport;
use App\Models\Payee;
use App\Models\SheetTemplate;
use App\Models\StatusLegend;
use App\Models\TaxType;
use App\Models\Transaction;
use App\Models\TransactionTaxLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LedgerImportService
{
    private array $config;

    private int $rowsImported = 0;
    private int $rowsSkippedNta = 0;
    private int $rowsSkippedExcludedCharging = 0;
    private int $rowsSkippedDeferredCharging = 0;
    private int $rowsSkippedDuplicate = 0;
    private int $accountsCreated = 0;
    private int $payeesCreated = 0;

    public function __construct()
    {
        $this->config = require config_path('ledger.php');
    }

    public function import(string $filePath, int $userId): ExcelImport
    {
        $import = ExcelImport::create([
            'file_name' => basename($filePath),
            'file_path' => $filePath,
            'uploaded_by' => $userId,
            'uploaded_at' => now(),
            'status' => 'processing',
        ]);

        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true); // never recalculate formulas — trust Excel's last-saved value
        $spreadsheet = $reader->load($filePath);

        $deferredSheetsEncountered = [];

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $trimmedName = trim($sheetName);

            if ($this->isNonLedgerSheet($trimmedName) || $trimmedName === $this->config['general_ledger_sheet']) {
                continue; // never a real SL, or the GL (handled separately)
            }

            if (!$this->isInActiveScope($trimmedName)) {
                // A real SL, just not built yet — log it so it's visible
                // that data exists here waiting for a later phase, rather
                // than disappearing silently the same way a non-ledger
                // sheet does.
                $deferredSheetsEncountered[] = $trimmedName;
                continue;
            }

            $this->parseSheet($spreadsheet->getSheetByName($sheetName), $import, $userId);
        }

        if (!empty($deferredSheetsEncountered)) {
            Log::info('Import skipped sheets not yet in active scope: ' . implode(', ', $deferredSheetsEncountered));
        }

        $import->update([
            'status' => 'completed',
            'notes' => sprintf(
                '%d rows imported, %d skipped (NTA/no payment mode), %d skipped (excluded charging value), %d skipped (deferred/not-yet-in-scope charging value), %d skipped (duplicate), %d new accounts created, %d new payees created.',
                $this->rowsImported, $this->rowsSkippedNta, $this->rowsSkippedExcludedCharging,
                $this->rowsSkippedDeferredCharging, $this->rowsSkippedDuplicate,
                $this->accountsCreated, $this->payeesCreated,
            ),
        ]);

        return $import;
    }

    private function isNonLedgerSheet(string $sheetName): bool
    {
        return in_array(trim($sheetName), $this->config['non_ledger_sheets'], true);
    }

    private function isExcludedChargingValue(string $chargingValue): bool
    {
        return in_array(trim($chargingValue), $this->config['non_ledger_sheets'], true);
    }

    private function isDeferredChargingValue(string $chargingValue): bool
    {
        // Real SL, not yet in active scope — e.g. a stray "SAA-Green
        // Wave" row sitting inside an active-scope tab (this exact case
        // happened with the April NYDD row). Skipped for now, but
        // tracked separately from truly excluded, since this data
        // will matter once that SL is brought into scope later.
        return in_array(trim($chargingValue), $this->config['deferred_sheets'], true);
    }

    private function isInActiveScope(string $sheetName): bool
    {
        return in_array(trim($sheetName), $this->config['active_scope_sheets'], true);
    }

    // ------------------------------------------------------------
    // HEADER / COLUMN DETECTION — this is the dynamic part. Nothing
    // here assumes a fixed column letter. Row position is scanned;
    // column position is scanned; only the DICTIONARY of label text
    // is fixed, because something has to define what "PAYEE" means.
    // ------------------------------------------------------------

    private function parseSheet(Worksheet $sheet, ExcelImport $import, int $userId): void
    {
        $fiscalYear = $this->parseFiscalYear($sheet);

        $existingTemplate = SheetTemplate::where('sheet_name', $sheet->getTitle())->first();

        if ($existingTemplate && !empty($existingTemplate->column_map)) {
            // Reuse the previously detected layout for this physical tab
            // rather than re-scanning every import — cheaper, and the
            // layout for an already-known tab shouldn't be changing
            // under us. If it ever does change, delete this row and the
            // next import will re-detect from scratch.
            $map = $existingTemplate->column_map;
            $firstDataRow = $existingTemplate->first_data_row_number;
            $template = $existingTemplate;
        } else {
            [$headerRowPrimary, $headerRowSecondary] = $this->detectHeaderRows($sheet);
            [$map, $unmapped] = $this->detectColumnMap($sheet, $headerRowPrimary, $headerRowSecondary);

            $this->assertRequiredFieldsPresent($map, $sheet->getTitle());

            $map['__tax_columns'] = $this->detectTaxZone($sheet, $map, $headerRowPrimary, $headerRowSecondary);

            // Remove tax-zone columns from the unmapped list — they're
            // handled by detectTaxZone(), not the fixed dictionary, so
            // they appear "unmapped" from detectColumnMap()'s perspective
            // but are actually fully claimed and imported.
            if (!empty($map['__tax_columns'])) {
                $taxColLetters = array_map(fn ($tc) => $tc['col'], $map['__tax_columns']);
                $unmapped = array_values(array_filter($unmapped, function ($entry) use ($taxColLetters) {
                    // Each unmapped entry starts with "COL_LETTER{row}/..."
                    // Extract the column letter prefix before the row number.
                    if (preg_match('/^([A-Z]+)\d/', $entry, $m)) {
                        return !in_array($m[1], $taxColLetters, true);
                    }
                    return true;
                }));
            }

            if (!empty($unmapped)) {
                Log::warning(sprintf(
                    'Sheet "%s" has %d unrecognized header column(s) — data under them was NOT imported: %s',
                    $sheet->getTitle(),
                    count($unmapped),
                    implode(' | ', $unmapped)
                ));
            }

            $firstDataRow = $headerRowPrimary + $this->config['data_row_offset'];

            $template = SheetTemplate::create([
                'sheet_name' => $sheet->getTitle(),
                'column_map' => $map,
                'unmapped_headers' => $unmapped,
                'header_row_number' => $headerRowPrimary,
                'first_data_row_number' => $firstDataRow,
                'default_fiscal_year' => $fiscalYear,
            ]);
        }

        $lastRow = $sheet->getHighestDataRow();
        $lastImportedRow = $firstDataRow - 1;

        // Guard against a well-known PhpSpreadsheet/Excel quirk: a stray
        // formatted (but empty) cell far below the real data can make
        // getHighestDataRow() return a wildly inflated number — in the
        // worst case, close to Excel's absolute row limit. Scanning that
        // many empty rows one by one is what causes a multi-minute hang.
        // Bail out early after a long run of genuinely blank rows rather
        // than trusting the reported row count blindly.
        $consecutiveBlankRows = 0;
        $maxConsecutiveBlankRows = 50;

        $carryObrMonth = null;
        $carryObrSequence = null;
        $carryDvMonth = null;
        $carryDvSequence = null;

        for ($row = $firstDataRow; $row <= $lastRow; $row++) {
            $particulars = trim((string) $this->cellByField($sheet, $map, 'particulars', $row));
            $chargingValue = trim((string) $this->cellByField($sheet, $map, 'charging_value', $row));
            $paymentModeRaw = trim((string) $this->cellByField($sheet, $map, 'payment_mode', $row));

            if ($particulars === '' && $chargingValue === '' && $paymentModeRaw === '') {
                $consecutiveBlankRows++;
                if ($consecutiveBlankRows >= $maxConsecutiveBlankRows) {
                    Log::info(sprintf(
                        'Sheet "%s": stopped scanning at row %d after %d consecutive blank rows (reported last row was %d) — likely stray formatting inflating the sheet\'s reported size, not real data.',
                        $sheet->getTitle(), $row, $maxConsecutiveBlankRows, $lastRow
                    ));
                    break;
                }
                continue; // blank spacer/summary row
            }
            $consecutiveBlankRows = 0;

            if ($paymentModeRaw === '') {
                $this->rowsSkippedNta++; // NTA / release-of-funds notation row
                continue;
            }

            if ($chargingValue !== '' && $this->isExcludedChargingValue($chargingValue)) {
                $this->rowsSkippedExcludedCharging++;
                continue;
            }

            if ($chargingValue !== '' && $this->isDeferredChargingValue($chargingValue)) {
                $this->rowsSkippedDeferredCharging++;
                continue;
            }

            // --- Forward-fill OBR#/DV# (shared-voucher rows) ---
            $obrMonthRaw = trim((string) $this->cellByField($sheet, $map, 'obr_number', $row, 0));
            $obrSequenceRaw = trim((string) $this->cellByField($sheet, $map, 'obr_number', $row, 1));
            $dvMonthRaw = trim((string) $this->cellByField($sheet, $map, 'dv_number', $row, 0));
            $dvSequenceRaw = trim((string) $this->cellByField($sheet, $map, 'dv_number', $row, 1));

            if ($obrMonthRaw !== '') {
                $carryObrMonth = $obrMonthRaw;
                $carryObrSequence = $obrSequenceRaw;
            }
            if ($dvMonthRaw !== '') {
                $carryDvMonth = $dvMonthRaw;
                $carryDvSequence = $dvSequenceRaw;
            }

            $payeeName = trim((string) $this->cellByField($sheet, $map, 'payee', $row));
            if ($payeeName === '') {
                continue; // no payee on a real row is unexpected — skip, don't guess
            }
            $payee = $this->resolvePayee($payeeName);

            $account = $this->resolveAccount($chargingValue);

            $wRaw = trim((string) $this->cellByField($sheet, $map, 'w', $row));
            $wCode = $this->resolveStatusCode($wRaw);

            $paymentMode = $this->config['payment_mode_map'][$paymentModeRaw] ?? null;
            if ($paymentMode === null) {
                continue; // unrecognized A/C value — don't guess, skip
            }

            $date = $this->parseExcelDate($this->cellByField($sheet, $map, 'date', $row));

            $exists = Transaction::where('account_id', $account->id)
                ->where('date', $date)
                ->where('dv_sequence', $carryDvSequence)
                ->where('obr_sequence', $carryObrSequence)
                ->where('particulars', $particulars)
                ->exists();

            if ($exists) {
                $this->rowsSkippedDuplicate++;
                continue;
            }

            DB::transaction(function () use (
                $sheet, $map, $row, $import, $userId, $account, $payee, $wCode,
                $paymentMode, $date, $particulars, $fiscalYear,
                $carryObrMonth, $carryObrSequence, $carryDvMonth, $carryDvSequence
            ) {
                $transaction = Transaction::create([
                    'account_id' => $account->id,
                    'import_id' => $import->id,
                    'payee_id' => $payee->id,
                    'user_id' => $userId,
                    'date' => $date,
                    'particulars' => $particulars,
                    'rc' => $this->trimmedOrNull($sheet, $map, 'rc', $row),
                    'w' => $wCode,
                    'fiscal_year' => $fiscalYear,
                    'obr_month' => $carryObrMonth,
                    'obr_sequence' => $carryObrSequence,
                    'dv_month' => $carryDvMonth,
                    'dv_sequence' => $carryDvSequence,
                    'jev_no' => $this->trimmedOrNull($sheet, $map, 'jev_no', $row),
                    'acct_code' => $this->trimmedOrNull($sheet, $map, 'acct_code', $row),
                    'remarks' => $this->trimmedOrNull($sheet, $map, 'remarks', $row),
                    'deduction_type' => null, // RLIP/etc. detection not yet implemented
                    'receipts' => $this->numericByField($sheet, $map, 'receipts', $row),
                    'charging_breakdown' => $this->numericByField($sheet, $map, 'charging_breakdown', $row),
                    'gross' => $this->numericByField($sheet, $map, 'gross', $row),
                    'net' => $this->numericByField($sheet, $map, 'net', $row),
                    'payment_net_wtx' => $this->numericByField($sheet, $map, 'payment_net_wtx', $row),
                    'paid_nydd_prior' => $this->numericByField($sheet, $map, 'paid_nydd_prior', $row),
                    'paid_aps_prior' => $this->numericByField($sheet, $map, 'paid_aps_prior', $row),
                    'paid_nydd_curr' => $this->numericByField($sheet, $map, 'paid_nydd_curr', $row),
                    'paid_aps_curr' => $this->numericByField($sheet, $map, 'paid_aps_curr', $row),
                    'due_to_officers' => $this->numericByField($sheet, $map, 'due_to_officers', $row), // formula cell — value only
                    'payment_mode' => $paymentMode,
                ]);

                // Tax lines — one row per detected tax column, entirely
                // driven by whatever was found in the tax zone. Adding a
                // new tax type next year (a real law change) needs zero
                // code changes here; it's already handled generically.
                foreach ($map['__tax_columns'] as $taxColumn) {
                    $rawValue = $sheet->getCell("{$taxColumn['col']}{$row}")->getValue();
                    $amount = is_numeric($rawValue) ? (float) $rawValue : null;

                    if ($amount !== null) {
                        TransactionTaxLine::create([
                            'transaction_id' => $transaction->id,
                            'tax_type_id' => $taxColumn['tax_type_id'],
                            'amount' => $amount,
                        ]);
                    }
                }
            });

            $this->rowsImported++;
            $lastImportedRow = $row;
        }

        $template->update(['last_data_row_number' => $lastImportedRow]);
    }

    /**
     * Scans the first N rows looking for the row that best matches the
     * dictionary's group-level labels. Returns [primaryRow, secondaryRow].
     * Falls back to config defaults only if nothing scores well — this
     * is the "don't guess silently" principle applied to row detection.
     */
    private function detectHeaderRows(Worksheet $sheet): array
    {
        $singleLabels = [];
        foreach ($this->config['header_dictionary'] as $label => $field) {
            $singleLabels[] = str_contains($label, '|') ? explode('|', $label)[0] : $label;
        }
        $singleLabels = array_unique($singleLabels);

        $bestRow = null;
        $bestScore = 0;

        $maxScanRow = min(20, $sheet->getHighestDataRow());
        for ($row = 1; $row <= $maxScanRow; $row++) {
            $score = 0;
            foreach ($sheet->getRowIterator($row, $row) as $rowData) {
                foreach ($rowData->getCellIterator() as $cell) {
                    $text = $this->normalize((string) $cell->getValue());
                    if ($text !== '' && in_array($text, $singleLabels, true)) {
                        $score++;
                    }
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $row;
            }
        }

        if ($bestRow === null || $bestScore < 3) {
            // Detection didn't find a confident header row — fall back
            // to the confirmed defaults rather than proceeding on a
            // guess that scored almost nothing.
            return [$this->config['header_row_primary'], $this->config['header_row_secondary']];
        }

        return [$bestRow, $bestRow + 1];
    }

    private function detectColumnMap(Worksheet $sheet, int $primaryRow, int $secondaryRow): array
    {
        $map = []; // field => ['col' => letter] or ['col' => letter, 'col2' => letter] for spans
        $unmapped = []; // coordinates + text of header cells that matched nothing

        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($col = 1; $col <= $highestColumn; $col++) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $primaryText = $this->normalize((string) $sheet->getCell("{$letter}{$primaryRow}")->getValue());
            $secondaryText = $this->normalize((string) $sheet->getCell("{$letter}{$secondaryRow}")->getValue());

            $matchedThisColumn = false;

            foreach ($this->config['header_dictionary'] as $label => $field) {
                if (isset($map[$field])) {
                    continue; // already matched, first match wins
                }

                if (str_contains($label, '|')) {
                    [$groupPart, $subPart] = explode('|', $label);
                    $effectiveGroupText = $primaryText !== ''
                        ? $primaryText
                        : $this->nearestLeftPrimaryText($sheet, $col, $primaryRow);

                    if ($effectiveGroupText === $groupPart && $secondaryText === $subPart) {
                        $map[$field] = ['col' => $letter];
                        $matchedThisColumn = true;
                    }
                } else {
                    // Try single-row match first (row8 alone or row9 alone)
                    if ($primaryText === $label || $secondaryText === $label) {
                        $mergedRange = $this->mergedRangeContaining($sheet, "{$letter}{$primaryRow}");
                        if ($mergedRange && $this->rangeColumnSpan($mergedRange) > 1) {
                            $map[$field] = [
                                'col' => $letter,
                                'col2' => Coordinate::stringFromColumnIndex($col + 1),
                            ];
                        } else {
                            $map[$field] = ['col' => $letter];
                        }
                        $matchedThisColumn = true;
                    }

                    // Combined-label fallback: concatenate row8 + row9 text
                    // and match as a single label. Handles cases where the
                    // Excel splits a logical label across two rows (e.g.
                    // P8="refund of overpayment" + P9="/liquidated damages"
                    // → combined = "REFUND OF OVERPAYMENT LIQUIDATED DAMAGES").
                    if (!$matchedThisColumn && $primaryText !== '' && $secondaryText !== '') {
                        $combinedText = $this->normalize($primaryText . ' ' . $secondaryText);
                        if ($combinedText === $label) {
                            $map[$field] = ['col' => $letter];
                            $matchedThisColumn = true;
                        }
                    }
                }
            }

            // Genuinely new/unrecognized column — surface it rather than
            // silently dropping whatever data lives under it. A human
            // still has to decide what it means and add it to the
            // dictionary (plus a new DB field if needed), but at least
            // it won't vanish without a trace.
            if (!$matchedThisColumn && ($primaryText !== '' || $secondaryText !== '')) {
                $combinedLabel = trim($primaryText . ' ' . $secondaryText);
                // Skip known-decorative/out-of-scope zones rather than
                // flagging every ROD summary or Processing Time column
                // as "unmapped" noise.
                $isDecorativeOrOutOfScope = str_contains($combinedLabel, 'ROD')
                    || $combinedLabel === 'PS'
                    || $combinedLabel === 'MOOE'
                    || $combinedLabel === 'CO'
                    || $combinedLabel === 'IN'
                    || $combinedLabel === 'OUT'
                    || str_contains($combinedLabel, 'PROCESSING TIME');
                if (!$isDecorativeOrOutOfScope) {
                    $unmapped[] = "{$letter}{$primaryRow}/{$letter}{$secondaryRow}: \"{$combinedLabel}\"";
                }
            }
        }

        return [$map, $unmapped];
    }

    /**
     * Scans every column strictly between the 'w' column and the
     * 'charging_breakdown' column, treats each labeled one as a tax
     * line, and finds-or-creates a matching row in tax_types. This is
     * what makes new tax categories (from a future law change) work
     * automatically — no code change needed when one appears, the
     * same way a brand-new SL just becomes a new accounts row.
     */
    private function detectTaxZone(Worksheet $sheet, array $map, int $primaryRow, int $secondaryRow): array
    {
        if (!isset($map['w']) || !isset($map['charging_breakdown'])) {
            return [];
        }

        // Columns already claimed by another mapped field (e.g.
        // due_to_officers sits inside this numeric range) must be
        // skipped here — otherwise they'd get double-processed as a
        // "new" tax type on top of their correct existing mapping.
        $claimedColumns = [];
        foreach ($map as $field => $position) {
            if ($field === '__tax_columns' || !is_array($position)) {
                continue;
            }
            $claimedColumns[] = $position['col'];
            if (isset($position['col2'])) {
                $claimedColumns[] = $position['col2'];
            }
        }

        $startCol = Coordinate::columnIndexFromString($map['w']['col']) + 1;
        $endCol = Coordinate::columnIndexFromString($map['charging_breakdown']['col']) - 1;

        $taxColumns = [];

        for ($col = $startCol; $col <= $endCol; $col++) {
            $letter = Coordinate::stringFromColumnIndex($col);

            if (in_array($letter, $claimedColumns, true)) {
                continue; // already a different, correctly-mapped field
            }

            $primaryText = $this->normalize((string) $sheet->getCell("{$letter}{$primaryRow}")->getValue());
            $secondaryText = $this->normalize((string) $sheet->getCell("{$letter}{$secondaryRow}")->getValue());

            // Combine group + sub-label when both exist, so a grouped
            // header like WTX/Total produces "WTX TOTAL" instead of
            // just the ambiguous "TOTAL" — matters most for genuinely
            // new tax columns that will only ever get an auto-generated
            // code, since there's no dictionary entry to give them a
            // clean name yet.
            $label = trim($primaryText . ' ' . $secondaryText);
            if ($label === '') {
                continue; // no header text at all here — not a real column
            }

            $code = $this->config['known_tax_labels'][$label]
                ?? $this->config['known_tax_labels'][$secondaryText]
                ?? $this->slugify($label);

            $taxType = TaxType::firstOrCreate(
                ['code' => $code],
                ['label' => $label]
            );

            $taxColumns[] = ['col' => $letter, 'tax_type_id' => $taxType->id, 'code' => $code];
        }

        return $taxColumns;
    }

    private function slugify(string $text): string
    {
        $slug = strtolower($text);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);

        return trim($slug, '_');
    }

    private function nearestLeftPrimaryText(Worksheet $sheet, int $col, int $primaryRow): string
    {
        for ($c = $col; $c >= 1; $c--) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $text = $this->normalize((string) $sheet->getCell("{$letter}{$primaryRow}")->getValue());
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function mergedRangeContaining(Worksheet $sheet, string $coordinate): ?string
    {
        foreach ($sheet->getMergeCells() as $range) {
            if ($this->coordinateWithinRange($coordinate, $range)) {
                return $range;
            }
        }

        return null;
    }

    private function coordinateWithinRange(string $coordinate, string $range): bool
    {
        [$rangeStart, $rangeEnd] = explode(':', $range);
        $rangeStartCol = Coordinate::columnIndexFromString(Coordinate::coordinateFromString($rangeStart)[0]);
        $rangeEndCol = Coordinate::columnIndexFromString(Coordinate::coordinateFromString($rangeEnd)[0]);
        $targetCol = Coordinate::columnIndexFromString(Coordinate::coordinateFromString($coordinate)[0]);
        $rangeStartRow = (int) Coordinate::coordinateFromString($rangeStart)[1];
        $rangeEndRow = (int) Coordinate::coordinateFromString($rangeEnd)[1];
        $targetRow = (int) Coordinate::coordinateFromString($coordinate)[1];

        return $targetCol >= $rangeStartCol && $targetCol <= $rangeEndCol
            && $targetRow >= $rangeStartRow && $targetRow <= $rangeEndRow;
    }

    private function rangeColumnSpan(string $range): int
    {
        [$start, $end] = explode(':', $range);
        $startCol = Coordinate::columnIndexFromString(Coordinate::coordinateFromString($start)[0]);
        $endCol = Coordinate::columnIndexFromString(Coordinate::coordinateFromString($end)[0]);

        return ($endCol - $startCol) + 1;
    }

    private function assertRequiredFieldsPresent(array $map, string $sheetName): void
    {
        $required = ['payee', 'charging_value', 'gross', 'date', 'payment_mode'];
        $missing = array_filter($required, fn ($field) => !isset($map[$field]));

        if (!empty($missing)) {
            throw new \RuntimeException(sprintf(
                'Sheet "%s": could not detect required column(s): %s. This tab needs manual review before it can be imported.',
                $sheetName,
                implode(', ', $missing)
            ));
        }
    }

    private function normalize(string $text): string
    {
        $text = strtoupper(trim($text));
        $text = str_replace(["\n", "\r", '#'], ' ', $text);
        $text = ltrim($text, '/ '); // strips a leading slash, e.g. "/LIQUIDATED DAMAGES" -> "LIQUIDATED DAMAGES", without touching internal slashes like in "A/C"
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * $part: 0 = first column of a span (e.g. obr_month), 1 = second
     * column of a span (e.g. obr_sequence). Ignored for single-column fields.
     */
    private function cellByField(Worksheet $sheet, array $map, string $field, int $row, int $part = 0)
    {
        if (!isset($map[$field])) {
            return null;
        }

        $letter = $part === 1 && isset($map[$field]['col2'])
            ? $map[$field]['col2']
            : $map[$field]['col'];

        return $sheet->getCell("{$letter}{$row}")->getValue();
    }

    private function trimmedOrNull(Worksheet $sheet, array $map, string $field, int $row): ?string
    {
        $value = trim((string) $this->cellByField($sheet, $map, $field, $row));

        return $value === '' ? null : $value;
    }

    private function numericByField(Worksheet $sheet, array $map, string $field, int $row): ?float
    {
        $value = $this->cellByField($sheet, $map, $field, $row);

        return is_numeric($value) ? (float) $value : null;
    }

    private function resolvePayee(string $name): Payee
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $name));

        $payee = Payee::where('name', $normalized)->first();
        if (!$payee) {
            $payee = Payee::create(['name' => $normalized]);
            $this->payeesCreated++;
        }

        return $payee;
    }

    private function resolveAccount(string $chargingValue): Account
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $chargingValue));

        $account = Account::where('charging_value', $normalized)->first();
        if (!$account) {
            $account = Account::create([
                'charging_value' => $normalized,
                'category' => null,
                'fund_status' => null,
            ]);
            $this->accountsCreated++;
        }

        return $account;
    }

    private function resolveStatusCode(string $rawText): ?string
    {
        // Exact match only against the known dictionary — never fuzzy,
        // per the matching-strategy decision. A truly unrecognized
        // status (not even in our known list) is treated as a real
        // anomaly worth flagging, not something to auto-register blindly
        // — unlike tax types, a status code carries specific business
        // meaning (and a color) that can't be safely invented.
        $code = $this->config['status_text_map'][$rawText] ?? null;
        if ($code === null) {
            return null;
        }

        // The code IS known (it's in our dictionary) — but the actual
        // status_legend row might not exist yet in this database. Same
        // find-or-create pattern as accounts/tax_types: don't require
        // manual seeding, register it automatically the first time it's
        // encountered.
        StatusLegend::firstOrCreate(
            ['status_code' => $code],
            ['label' => $rawText, 'fill_color_hex' => null]
        );

        return $code;
    }

    private function parseFiscalYear(Worksheet $sheet): ?int
    {
        $raw = trim((string) $sheet->getCell($this->config['fiscal_year_cell'])->getValue());
        if (preg_match('/(\d{4})/', $raw, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function parseExcelDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}