<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['account_id', 'import_id', 'payee_id', 'user_id', 'date', 'particulars', 'rc',
            'w', 'fiscal_year', 'obr_month', 'obr_sequence', 'dv_month', 'dv_sequence',
            'jev_no', 'acct_code', 'remarks', 'deduction_type',
            'payment_mode', 'receipts', 'charging_breakdown', 'gross', 'net', 'payment_net_wtx',
            'paid_nydd_prior', 'paid_aps_prior', 'paid_nydd_curr', 'paid_aps_curr',
            'due_to_officers'])]
class Transaction extends Model
{
    protected $casts = [
        'date' => 'date',
        'fiscal_year' => 'integer',
        'receipts' => 'decimal:2',
        'charging_breakdown' => 'decimal:2',
        'gross' => 'decimal:2',
        'net' => 'decimal:2',
        'payment_nex_wtx' => 'decimal:2',
        'paid_nydd_prior' => 'decimal:2',
        'paid_aps_prior' => 'decimal:2',
        'paid_nydd_curr' => 'decimal:2',
        'paid_aps_curr' => 'decimal:2',
    ];

    # No balance attribute because balance is a running total across many rows in date order, computed via
    # a query/service (e.g. a LedgerService). Summaries are all computed at read.

    public function account(){
        return $this->belongsTo(Account::class);
    }
    public function payee(){
        return $this->belongsTo(Payee::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function excelImport(){
        return $this->belongsTo(excelImport::class, 'import_id');
    }
    public function statusLegend(){
        return $this->belongsTo(statusLegend::class, 'w', 'status_code');
    }
    public function tax()
    {
        return $this->hasOne(TransactionTaxLine::class, 'transaction_id');
    }
    public function auditLogs(){
        return $this->hasMany(auditLog::class);
    }
}
