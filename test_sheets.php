<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$files = glob(storage_path('app/private/ledger-imports/*.*'));
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($files[0]);
print_r($spreadsheet->getSheetNames());
