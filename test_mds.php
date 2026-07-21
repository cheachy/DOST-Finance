<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$files = glob(storage_path('app/private/ledger-imports/*.*'));
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($files[0]);
$sheet = $spreadsheet->getSheetByName('MDS 101');

// dump rows 7, 8, 9
for($r = 7; $r <= 9; $r++) {
    echo "ROW $r:\n";
    for($c = 1; $c <= 30; $c++) {
        $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
        $val = $sheet->getCell("{$letter}{$r}")->getValue();
        if ($val) {
            echo "  $letter: " . trim($val) . "\n";
        }
    }
}
