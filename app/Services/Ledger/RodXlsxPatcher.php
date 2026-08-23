<?php

namespace App\Services\Ledger;

use RuntimeException;
use ZipArchive;

/**
 * Writes numbers into specific cells of an .xlsx WITHOUT parsing the workbook.
 *
 * PhpSpreadsheet builds an object model of the entire file to change anything:
 * on her real workbook that is 54 MB of XML for the MDS 101 sheet alone, and
 * measured at ~288s / ~1.9 GB to load and re-save just to set ~243 cells. An
 * .xlsx is a zip of XML parts, so patching one sheet part and copying the rest
 * through byte-for-byte does the same job in seconds and cannot perturb parts
 * it never opens (styles, merges, the colour legend, external links, the other
 * 20-odd SL tabs).
 *
 * What it deliberately does:
 *
 *   - Inserts a cell that does not exist yet, in the correct column order
 *     inside its row. Excel omits empty cells entirely, so most target cells
 *     are absent rather than blank.
 *
 *   - Carries the existing cell's style (s=) when there is one, and otherwise
 *     applies $defaultStyle. Without this the value lands as General format -
 *     her 5,716.98 rendering as 5716.98, with none of the block's borders.
 *
 *   - Drops any <f> formula on a cell it writes, and then discards calcChain
 *     so Excel rebuilds it. Leaving the formula would mean Excel recalculates
 *     on open and silently replaces the generated value with the formula's own
 *     result - the written number would appear correct here and be wrong on
 *     her screen. Callers get told how many formulas were replaced.
 */
class RodXlsxPatcher
{
    /** @var int number of cells whose formula was replaced by a literal */
    private int $formulasReplaced = 0;

    /**
     * @param  array<string, float>  $cells  cell reference => numeric value, e.g. ['AR94' => 3900.0]
     * @param  int|null  $defaultStyle  style index applied to cells that do not exist yet
     * @return array{written:int,inserted:int,formulas_replaced:int}
     */
    public function patch(string $srcPath, string $outPath, string $sheetEntry, array $cells, ?int $defaultStyle = null): array
    {
        if (! is_file($srcPath)) {
            throw new RuntimeException("Source workbook not found: {$srcPath}");
        }

        if ($srcPath !== $outPath && ! copy($srcPath, $outPath)) {
            throw new RuntimeException("Could not copy workbook to {$outPath}");
        }

        $zip = new ZipArchive;
        if ($zip->open($outPath) !== true) {
            throw new RuntimeException("Could not open {$outPath} as a zip archive");
        }

        $xml = $zip->getFromName($sheetEntry);
        if ($xml === false) {
            $zip->close();
            throw new RuntimeException("Sheet part {$sheetEntry} not found inside the workbook");
        }

        $this->formulasReplaced = 0;
        [$patched, $written, $inserted] = $this->patchSheetXml($xml, $cells, $defaultStyle);

        $zip->addFromString($sheetEntry, $patched);

        // A stale calcChain lists cells that no longer hold formulas; Excel
        // repairs the file rather than opening it cleanly. Removing it makes
        // Excel rebuild the chain silently on open.
        if ($this->formulasReplaced > 0 && $zip->locateName('xl/calcChain.xml') !== false) {
            $zip->deleteName('xl/calcChain.xml');
        }

        $zip->close();

        return [
            'written' => $written,
            'inserted' => $inserted,
            'formulas_replaced' => $this->formulasReplaced,
        ];
    }

    /**
     * @param  array<string, float>  $cells
     * @return array{0:string,1:int,2:int}
     */
    private function patchSheetXml(string $xml, array $cells, ?int $defaultStyle): array
    {
        // Group by row, because a row is patched as one unit: its cells must
        // stay ordered by column or Excel rejects the sheet.
        $byRow = [];
        foreach ($cells as $ref => $value) {
            if (! preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
                throw new RuntimeException("Not a cell reference: {$ref}");
            }
            $byRow[(int) $m[2]][$m[1]] = $value;
        }
        ksort($byRow);

        $out = '';
        $cursor = 0;
        $written = 0;
        $inserted = 0;

        foreach ($byRow as $rowNum => $rowCells) {
            $start = $this->findRowStart($xml, $rowNum, $cursor);
            if ($start === null) {
                // Nothing to anchor to: the sheet has no such row at all.
                continue;
            }

            $openEnd = strpos($xml, '>', $start);
            $selfClosing = $xml[$openEnd - 1] === '/';

            if ($selfClosing) {
                // <row r="94" .../> - reopen it so cells can live inside.
                $openTag = rtrim(substr($xml, $start, $openEnd - $start), '/').'>';
                $body = '';
                $end = $openEnd + 1;
            } else {
                $openTag = substr($xml, $start, $openEnd - $start + 1);
                $close = strpos($xml, '</row>', $openEnd);
                if ($close === false) {
                    continue;
                }
                $body = substr($xml, $openEnd + 1, $close - $openEnd - 1);
                $end = $close + 6;
            }

            [$newBody, $w, $i] = $this->patchRowBody($body, $rowNum, $rowCells, $defaultStyle);
            $written += $w;
            $inserted += $i;

            $out .= substr($xml, $cursor, $start - $cursor).$openTag.$newBody.'</row>';
            $cursor = $end;
        }

        return [$out.substr($xml, $cursor), $written, $inserted];
    }

    /** Locates `<row r="N"` at or after $from, skipping r="N0" style false hits. */
    private function findRowStart(string $xml, int $rowNum, int $from): ?int
    {
        $needle = '<row r="'.$rowNum.'"';
        $at = strpos($xml, $needle, $from);

        return $at === false ? null : $at;
    }

    /**
     * @param  array<string, float>  $rowCells  column letter => value
     * @return array{0:string,1:int,2:int}
     */
    private function patchRowBody(string $body, int $rowNum, array $rowCells, ?int $defaultStyle): array
    {
        $written = 0;
        $inserted = 0;

        // Existing cells in document order, with their spans in $body.
        preg_match_all('/<c r="([A-Z]+)'.$rowNum.'"([^>]*?)(\/>|>(.*?)<\/c>)/s', $body, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $existing = [];
        foreach ($matches as $m) {
            $existing[$m[1][0]] = [
                'start' => $m[0][1],
                'length' => strlen($m[0][0]),
                'attrs' => $m[2][0],
                'hasFormula' => isset($m[4]) && str_contains($m[4][0], '<f'),
            ];
        }

        // Apply right-to-left so earlier offsets stay valid.
        uksort($rowCells, fn ($a, $b) => $this->colIndex($b) <=> $this->colIndex($a));

        foreach ($rowCells as $col => $value) {
            $cell = $this->buildCell(
                $col.$rowNum,
                $value,
                isset($existing[$col]) ? $existing[$col]['attrs'] : null,
                $defaultStyle
            );

            if (isset($existing[$col])) {
                if ($existing[$col]['hasFormula']) {
                    $this->formulasReplaced++;
                }

                $body = substr_replace($body, $cell, $existing[$col]['start'], $existing[$col]['length']);
                $written++;

                continue;
            }

            $body = substr_replace($body, $cell, $this->insertOffset($body, $rowNum, $col), 0);
            $written++;
            $inserted++;
        }

        return [$body, $written, $inserted];
    }

    /**
     * Byte offset at which a new cell must be spliced so the row stays ordered
     * by column: immediately before the first existing cell to its right.
     */
    private function insertOffset(string $body, int $rowNum, string $col): int
    {
        $target = $this->colIndex($col);
        $best = null;

        // Re-scan: offsets shift as cells are spliced in, so the cached spans
        // in $existing cannot be trusted for positioning.
        if (preg_match_all('/<c r="([A-Z]+)'.$rowNum.'"/', $body, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $hit) {
                if ($this->colIndex($hit[1][0]) > $target) {
                    $best = $hit[0][1];
                    break;
                }
            }
        }

        return $best ?? strlen($body);
    }

    private function buildCell(string $ref, float $value, ?string $existingAttrs, ?int $defaultStyle): string
    {
        $style = null;

        if ($existingAttrs !== null && preg_match('/\bs="(\d+)"/', $existingAttrs, $m)) {
            $style = (int) $m[1];   // keep whatever formatting that cell already carries
        } elseif ($defaultStyle !== null) {
            $style = $defaultStyle;
        }

        // Any t= is dropped: the cell becomes a plain number, so a leftover
        // t="s" would make Excel read the value as a shared-string index.
        $attrs = ' r="'.$ref.'"'.($style !== null ? ' s="'.$style.'"' : '');

        return '<c'.$attrs.'><v>'.$this->number($value).'</v></c>';
    }

    /** Excel stores raw numbers; formatting is the style's business. */
    private function number(float $value): string
    {
        $s = rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');

        return $s === '' || $s === '-' ? '0' : $s;
    }

    private function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n;
    }

    public function formulasReplaced(): int
    {
        return $this->formulasReplaced;
    }
}
