<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$files = glob(storage_path('app/private/ledger-imports/*.*'));
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($files[0]);
$count = 0;
foreach($spreadsheet->getSheetNames() as $name) {
    if (!in_array($name, ['MDS 101'])) continue;
    $sheet = $spreadsheet->getSheetByName($name);
    $lastRow = $sheet->getHighestDataRow();
    for($i = 13; $i <= $lastRow; $i++) {
        $payee = trim($sheet->getCell("C{$i}")->getValue());
        if ($payee !== '') {
            $count++;
        }
    }
}
echo "Total rows with payee across PS, MOOE, GIA (starting at row 13): " . $count . PHP_EOL;
