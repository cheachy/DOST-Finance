<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'sheet_name',
    'template_file_path',
    'column_map',
    'header_row_number',
    'first_data_row_number',
    'last_data_row_number',
    'default_fiscal_year',
    'is_master_template',
    'unmapped_headers',
])]
class SheetTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'is_master_template' => 'boolean',
            'unmapped_headers' => 'array',
        ];
    }

    // Deliberately no relationship to Account here — a physical tab
    // can contain rows with multiple Charging values, and one Charging
    // value can span multiple tabs (e.g. ONELAB vs ONELAB 2025). See
    // schema notes on this table.
    public static function masterTemplate()
    {
        return static::query()
            ->where('is_master_template', true)
            ->orderByDesc('uploaded_at')
            ->first();
    }
}
