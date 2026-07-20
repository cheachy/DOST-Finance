<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property string|null $code
 * @property string $charging_value
 * @property string|null $category
 * @property string $fund_status
 * @property string|null $sub_project
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereChargingValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereFundStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereSubProject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereUpdatedAt($value)
 */
	class Account extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $fiscal_year
 * @property string $category
 * @property string $alloment_year_type
 * @property string|null $allotment_class
 * @property numeric $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allotment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allotment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allotment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allotment whereAllomentYearType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allotment whereAllotmentClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allotment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allotment whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allotment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allotment whereFiscalYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allotment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Allotment whereUpdatedAt($value)
 */
	class Allotment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payee newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payee newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payee query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payee whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payee whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payee whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payee whereUpdatedAt($value)
 */
	class Payee extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $sheet_name
 * @property string|null $template_file_path
 * @property array<array-key, mixed> $column_map
 * @property int|null $first_data_row_number
 * @property int|null $last_data_row_number
 * @property int|null $default_fiscal_year
 * @property bool $is_master_template
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SheetTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SheetTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SheetTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SheetTemplate whereColumnMap($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SheetTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SheetTemplate whereDefaultFiscalYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SheetTemplate whereFirstDataRowNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SheetTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SheetTemplate whereIsMasterTemplate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SheetTemplate whereLastDataRowNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SheetTemplate whereSheetName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SheetTemplate whereTemplateFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SheetTemplate whereUpdatedAt($value)
 */
	class SheetTemplate extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TransactionTaxLine> $transactionTaxLines
 * @property-read int|null $transaction_tax_lines_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxType query()
 */
	class TaxType extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $account_id
 * @property int $import_id
 * @property int $payee_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $date
 * @property string|null $particulars
 * @property string|null $rc
 * @property string|null $w
 * @property int|null $fiscal_year
 * @property string|null $obr_month
 * @property string|null $obr_sequence
 * @property string|null $dv_month
 * @property string|null $dv_sequence
 * @property string|null $jev_no
 * @property string|null $deduction_type
 * @property string|null $payment_mode
 * @property numeric|null $receipts
 * @property numeric|null $charging_breakdown
 * @property numeric|null $gross
 * @property numeric|null $net
 * @property numeric|null $payment_net_wtx
 * @property numeric|null $paid_nydd_prior
 * @property numeric|null $paid_aps_prior
 * @property numeric|null $paid_nydd_curr
 * @property numeric|null $paid_aps_curr
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Account $account
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\auditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read \App\Models\excelImport $excelImport
 * @property-read \App\Models\Payee $payee
 * @property-read \App\Models\statusLegend|null $statusLegend
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereChargingBreakdown($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereDeductionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereDvMonth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereDvSequence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereFiscalYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereGross($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereImportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereJevNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereNet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereObrMonth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereObrSequence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction wherePaidApsCurr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction wherePaidApsPrior($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction wherePaidNyddCurr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction wherePaidNyddPrior($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereParticulars($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction wherePayeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction wherePaymentMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction wherePaymentNetWtx($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereRc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereReceipts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereW($value)
 */
	class Transaction extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read \App\Models\TaxType|null $taxType
 * @property-read \App\Models\Transaction|null $transaction
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionTaxLine newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionTaxLine newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionTaxLine query()
 */
	class TransactionTaxLine extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string $role
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\auditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\excelImport> $excelImports
 * @property-read int|null $excel_imports_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\fundReceipt> $fundReceipts
 * @property-read int|null $fund_receipts_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\reportExport> $reportExports
 * @property-read int|null $report_exports_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|appSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|appSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|appSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|appSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|appSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|appSetting whereUpdatedAt($value)
 */
	class appSetting extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $transaction_id
 * @property array<array-key, mixed>|null $old_values
 * @property array<array-key, mixed>|null $new_values
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\Transaction $transaction
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|auditLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|auditLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|auditLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|auditLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|auditLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|auditLog whereNewValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|auditLog whereOldValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|auditLog whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|auditLog whereUserId($value)
 */
	class auditLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $file_name
 * @property string $file_path
 * @property int $uploaded_by
 * @property \Illuminate\Support\Carbon $uploaded_at
 * @property string $status
 * @property int|null $fiscal_year
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\fundReceipt> $fundReceipts
 * @property-read int|null $fund_receipts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read \App\Models\User $uploadedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|excelImport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|excelImport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|excelImport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|excelImport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|excelImport whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|excelImport whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|excelImport whereFiscalYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|excelImport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|excelImport whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|excelImport whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|excelImport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|excelImport whereUploadedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|excelImport whereUploadedBy($value)
 */
	class excelImport extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $date
 * @property int|null $fiscal_year
 * @property string|null $source
 * @property string|null $reference_no
 * @property numeric $amount
 * @property int|null $import_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\excelImport|null $excelImport
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|fundReceipt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|fundReceipt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|fundReceipt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|fundReceipt whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|fundReceipt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|fundReceipt whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|fundReceipt whereFiscalYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|fundReceipt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|fundReceipt whereImportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|fundReceipt whereReferenceNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|fundReceipt whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|fundReceipt whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|fundReceipt whereUserId($value)
 */
	class fundReceipt extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $account_id
 * @property string|null $period
 * @property string $file_path
 * @property int $generated_by
 * @property \Illuminate\Support\Carbon $generated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Account $account
 * @property-read \App\Models\User $generatedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|reportExport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|reportExport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|reportExport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|reportExport whereAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|reportExport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|reportExport whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|reportExport whereGeneratedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|reportExport whereGeneratedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|reportExport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|reportExport wherePeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|reportExport whereUpdatedAt($value)
 */
	class reportExport extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|statusLegend newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|statusLegend newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|statusLegend query()
 */
	class statusLegend extends \Eloquent {}
}

