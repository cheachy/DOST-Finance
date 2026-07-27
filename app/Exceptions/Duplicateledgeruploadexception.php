<?php

namespace App\Exceptions;

use App\Models\Upload;
use RuntimeException;

/**
 * Thrown when the uploaded workbook is identical to one already imported.
 *
 * Carries the prior Upload so the UI can say *which* import it duplicates and
 * when it happened, rather than a bare "duplicate file" message.
 */
class DuplicateLedgerUploadException extends RuntimeException
{
    public function __construct(
        public readonly Upload $existing,
        public readonly string $matchedOn   // either as 'file' | 'content'
    ) {
        $when = $existing->created_at?->format('M j, Y g:i A') ?? 'earlier';

        parent::__construct(
            $matchedOn === 'file'
                ? "This workbook was already imported on {$when}. Nothing to update."
                : "This workbook was re-saved but its data is unchanged since the import on {$when}. Nothing to update."
        );
    }

    /** Payload for an Inertia/JSON error response. */
    public function context(): array
    {
        return [
            'matched_on'      => $this->matchedOn,
            'existing_upload' => [
                'id'            => $this->existing->id,
                'original_name' => $this->existing->original_name,
                'imported_at'   => $this->existing->created_at?->toIso8601String(),
                'row_count'     => $this->existing->row_count,
            ],
        ];
    }
}