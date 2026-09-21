<?php
declare(strict_types=1);

ob_start();

require __DIR__ . '/../auth/config.php';
require __DIR__ . '/Functions.php';

lab_require_login();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$sampleTypes = lab_get_sample_types($pdo);
$mainLogSheetTypes = lab_get_main_log_sheet_types($pdo);

$spreadsheet = new Spreadsheet();

$sheet = $spreadsheet->getActiveSheet();
$sheet->setRightToLeft(true);
$sheet->setTitle('نمونه‌ها');

$headers = [
    'نوع نمونه', 'نوع لاگ‌شیت اصلی', 'مقدار', 'واحد اندازه‌گیری',
    'تاریخ نمونه‌گیری (شمسی مثل 1404/06/16)', 'تاریخ تحویل (شمسی)',
    'محل نمونه‌گیری', 'ارجاع‌کننده', 'تحویل‌گیرنده',
];
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:I1')->getFont()->setBold(true);
$sheet->getStyle('A1:I1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->freezePane('A2');

// One example row so the expected format is obvious.
if ($sampleTypes && $mainLogSheetTypes) {
    $sheet->fromArray([
        $sampleTypes[0]['name_fa'],
        $mainLogSheetTypes[0]['name_fa'],
        '5',
        'لیتر',
        '1404/06/16',
        '1404/06/17',
        'واحد تولید ۲',
        'نام ارجاع‌کننده',
        'نام تحویل‌گیرنده',
    ], null, 'A2');
}

// A reference sheet listing the exact allowed names (must match exactly).
$refSheet = $spreadsheet->createSheet();
$refSheet->setRightToLeft(true);
$refSheet->setTitle('نام‌های مجاز');
$refSheet->fromArray(['نوع نمونه (دقیقاً همین نام‌ها)'], null, 'A1');
$refSheet->fromArray(['نوع لاگ‌شیت اصلی (دقیقاً همین نام‌ها)'], null, 'B1');
$refSheet->getStyle('A1:B1')->getFont()->setBold(true);

$r = 2;
foreach ($sampleTypes as $t) {
    $refSheet->setCellValue('A' . $r, $t['name_fa']);
    $r++;
}
$r = 2;
foreach ($mainLogSheetTypes as $t) {
    $refSheet->setCellValue('B' . $r, $t['name_fa']);
    $r++;
}
$refSheet->getColumnDimension('A')->setAutoSize(true);
$refSheet->getColumnDimension('B')->setAutoSize(true);
$refSheet->freezePane('A2');

foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="sample_import_template.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
