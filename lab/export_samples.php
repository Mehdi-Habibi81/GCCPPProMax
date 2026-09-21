<?php
declare(strict_types=1);

ob_start();

require __DIR__ . '/../auth/config.php';
require __DIR__ . '/Functions.php';

lab_require_login();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$samples = lab_get_all_samples_for_export($pdo);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setRightToLeft(true);
$sheet->setTitle('نمونه‌ها');

$headers = [
    'شماره نمونه', 'نوع نمونه', 'نوع لاگ‌شیت اصلی', 'مقدار', 'واحد اندازه‌گیری',
    'تاریخ نمونه‌گیری (شمسی)', 'تاریخ تحویل (شمسی)', 'محل نمونه‌گیری',
    'ارجاع‌کننده', 'تحویل‌گیرنده', 'وضعیت',
];
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:K1')->getFont()->setBold(true);
$sheet->getStyle('A1:K1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->freezePane('A2');

$rowNum = 2;
foreach ($samples as $s) {
    $sheet->fromArray([
        $s['sample_number'],
        $s['sample_type_name'],
        $s['main_log_sheet_type_name'] ?? '',
        $s['quantity'],
        $s['quantity_unit'],
        $s['sampling_date_fa'],
        $s['delivery_date_fa'],
        $s['sampling_location'],
        $s['referrer'],
        $s['receiver'],
        $s['status'],
    ], null, 'A' . $rowNum);
    $rowNum++;
}

foreach (range('A', 'K') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$fileName = 'samples_export_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
