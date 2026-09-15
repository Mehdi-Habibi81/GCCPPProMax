<?php
declare(strict_types=1);

// ------------------------------------------------------------
// Prevent accidental output from corrupting the DOCX download
// ------------------------------------------------------------
ob_start();

require __DIR__ . '/../auth/config.php';
require __DIR__ . '/functions.php';

lab_require_login();

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Language;

// ------------------------------------------------------------
// Helper: safely prepare text for Word XML
// ------------------------------------------------------------
function word_text(mixed $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $value = (string)$value;

    // Remove invalid XML control characters while preserving
    // normal Unicode/Persian text.
    $value = preg_replace(
        '/[^\P{C}\t\r\n]/u',
        '',
        $value
    ) ?? '';

    return $value !== '' ? $value : '—';
}

// ------------------------------------------------------------
// Get sample
// ------------------------------------------------------------
$sampleId = (int)($_GET['sample_id'] ?? 0);

$sample = $sampleId
    ? lab_get_sample_by_id($pdo, $sampleId)
    : null;

if (!$sample || empty($sample['main_log_sheet_type_id'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(404);

    echo 'نمونه یا نوع لاگ‌شیت اصلی آن یافت نشد. '
       . '<a href="indicator.php">بازگشت</a>';

    exit;
}

// ------------------------------------------------------------
// Get/create main sheet
// ------------------------------------------------------------
$mainLogSheetId = lab_get_or_create_main_sheet(
    $pdo,
    $sampleId
);

$rows = lab_get_main_sheet_rows(
    $pdo,
    $mainLogSheetId,
    (int)$sample['main_log_sheet_type_id']
);

// ------------------------------------------------------------
// Get document/form code
// ------------------------------------------------------------
$typeCodeStmt = $pdo->prepare(
    "SELECT code
     FROM main_log_sheet_types
     WHERE id = :id"
);

$typeCodeStmt->execute([
    'id' => $sample['main_log_sheet_type_id'],
]);

$formCode = $typeCodeStmt->fetchColumn();

if ($formCode === false) {
    $formCode = '';
}

// ------------------------------------------------------------
// Create Word document
// ------------------------------------------------------------
$phpWord = new PhpWord();

// Persian language.
// Do not use Language::FA_IR because that constant is not
// available in your installed PHPWord version.
$phpWord->getSettings()->setThemeFontLang(
    new Language('fa-IR')
);

// The exported Word document uses Tahoma.
$phpWord->setDefaultFontName('Tahoma');
$phpWord->setDefaultFontSize(11);

// ------------------------------------------------------------
// Paragraph styles
// ------------------------------------------------------------
$rtlParagraph = [
    'alignment' => Jc::CENTER,
    'bidi'      => true,
];

$rtlParagraphRight = [
    'alignment' => Jc::END,
    'bidi'      => true,
];

// ------------------------------------------------------------
// Font styles
// ------------------------------------------------------------
$titleFont = [
    'bold' => true,
    'size' => 14,
    'name' => 'Tahoma',
];

$companyFont = [
    'bold' => true,
    'size' => 12,
    'name' => 'Tahoma',
];

$normalFont = [
    'size' => 11,
    'name' => 'Tahoma',
];

$smallFont = [
    'size' => 9,
    'name' => 'Tahoma',
];

// ------------------------------------------------------------
// Section
// ------------------------------------------------------------
$section = $phpWord->addSection();

// ------------------------------------------------------------
// Header / title
// ------------------------------------------------------------
$section->addText(
    word_text('بسمه‌تعالی'),
    $titleFont,
    $rtlParagraph
);

$section->addText(
    word_text('شرکت مدیریت تولید برق گیلان'),
    $companyFont,
    $rtlParagraph
);

$sampleTypeName = htmlspecialchars_decode(
    (string)($sample['main_log_sheet_type_name'] ?? ''),
    ENT_QUOTES
);

$section->addText(
    word_text($sampleTypeName . ' — لاکتیویت کنترل کیفیت'),
    $normalFont,
    $rtlParagraph
);

$section->addText(
    word_text('کد سند: ' . $formCode),
    $smallFont,
    $rtlParagraph
);

$section->addTextBreak(1);

// ------------------------------------------------------------
// Sample information table
// ------------------------------------------------------------
$infoTable = $section->addTable([
    'borderSize'  => 6,
    'borderColor' => '999999',
    'cellMargin'  => 80,
]);

$addInfoRow = function (
    string $label,
    mixed $value
) use (
    $infoTable,
    $normalFont,
    $rtlParagraphRight
): void {
    $infoTable->addRow();

    $infoTable
        ->addCell(3000)
        ->addText(
            word_text($label),
            ['bold' => true] + $normalFont,
            $rtlParagraphRight
        );

    $infoTable
        ->addCell(6000)
        ->addText(
            word_text($value),
            $normalFont,
            $rtlParagraphRight
        );
};

$addInfoRow(
    'شماره نمونه',
    $sample['sample_number'] ?? null
);

$addInfoRow(
    'تاریخ نمونه‌گیری',
    !empty($sample['sampling_date'])
        ? lab_gregorian_to_jalali($sample['sampling_date'])
        : null
);

$addInfoRow(
    'تاریخ تحویل نمونه',
    !empty($sample['delivery_date'])
        ? lab_gregorian_to_jalali($sample['delivery_date'])
        : null
);

$addInfoRow(
    'محل نمونه‌گیری',
    $sample['sampling_location'] ?? null
);

$addInfoRow(
    'ارجاع‌کننده',
    $sample['referrer'] ?? null
);

$addInfoRow(
    'تحویل‌گیرنده',
    $sample['receiver'] ?? null
);

$section->addTextBreak(1);

// ------------------------------------------------------------
// Results table
// ------------------------------------------------------------
$resultsTable = $section->addTable([
    'borderSize'  => 6,
    'borderColor' => '999999',
    'cellMargin'  => 80,
]);

$headerCellStyle = [
    'bgColor' => 'EEEEEE',
];

$resultsTable->addRow();

$resultsTable
    ->addCell(700, $headerCellStyle)
    ->addText(
        word_text('ردیف'),
        ['bold' => true] + $smallFont,
        $rtlParagraph
    );

$resultsTable
    ->addCell(3200, $headerCellStyle)
    ->addText(
        word_text('آزمایش'),
        ['bold' => true] + $smallFont,
        $rtlParagraph
    );

$resultsTable
    ->addCell(1200, $headerCellStyle)
    ->addText(
        word_text('واحد'),
        ['bold' => true] + $smallFont,
        $rtlParagraph
    );

$resultsTable
    ->addCell(1500, $headerCellStyle)
    ->addText(
        word_text('روش'),
        ['bold' => true] + $smallFont,
        $rtlParagraph
    );

$resultsTable
    ->addCell(2200, $headerCellStyle)
    ->addText(
        word_text('مقادیر مجاز'),
        ['bold' => true] + $smallFont,
        $rtlParagraph
    );

$resultsTable
    ->addCell(1700, $headerCellStyle)
    ->addText(
        word_text('نتیجه'),
        ['bold' => true] + $smallFont,
        $rtlParagraph
    );

// ------------------------------------------------------------
// Result rows
// ------------------------------------------------------------
foreach ($rows as $r) {
    $testName = str_replace(
        ' — NEEDS VERIFICATION',
        '',
        (string)($r['test_name'] ?? '')
    );

    $limits = [];

    if (
        !empty($r['limit_used']) &&
        $r['limit_used'] !== '---'
    ) {
        $limits[] = 'کارکرده: ' . $r['limit_used'];
    }

    if (
        isset($r['limit_new']) &&
        $r['limit_new'] !== null &&
        $r['limit_new'] !== ''
    ) {
        $limits[] = 'نو: ' . $r['limit_new'];
    }

    $limitsText = $limits
        ? implode(' / ', $limits)
        : '—';

    $resultsTable->addRow();

    $resultsTable
        ->addCell(700)
        ->addText(
            word_text($r['row_order'] ?? ''),
            $smallFont,
            $rtlParagraph
        );

    $resultsTable
        ->addCell(3200)
        ->addText(
            word_text($testName),
            $smallFont,
            $rtlParagraphRight
        );

    $resultsTable
        ->addCell(1200)
        ->addText(
            word_text($r['unit'] ?? null),
            $smallFont,
            $rtlParagraph
        );

    $resultsTable
        ->addCell(1500)
        ->addText(
            word_text($r['method'] ?? null),
            $smallFont,
            $rtlParagraph
        );

    $resultsTable
        ->addCell(2200)
        ->addText(
            word_text($limitsText),
            $smallFont,
            $rtlParagraphRight
        );

    $resultsTable
        ->addCell(1700)
        ->addText(
            word_text($r['result_value'] ?? null),
            $smallFont,
            $rtlParagraph
        );
}

// ------------------------------------------------------------
// Report date
// ------------------------------------------------------------
$section->addTextBreak(2);

$section->addText(
    word_text(
        'تاریخ گزارش: ' .
        lab_gregorian_to_jalali(date('Y-m-d'))
    ),
    $smallFont,
    $rtlParagraphRight
);

// ------------------------------------------------------------
// Prepare export directory
// ------------------------------------------------------------
$exportsDir = __DIR__ . '/exports';

if (!is_dir($exportsDir)) {
    if (!mkdir($exportsDir, 0750, true) && !is_dir($exportsDir)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code(500);
        exit('خطا در ایجاد پوشه خروجی Word.');
    }
}

// ------------------------------------------------------------
// Filename
// ------------------------------------------------------------
$safeSampleNumber = preg_replace(
    '/[^A-Za-z0-9\-]/',
    '_',
    (string)($sample['sample_number'] ?? 'sample')
);

if ($safeSampleNumber === '' || $safeSampleNumber === null) {
    $safeSampleNumber = 'sample';
}

$fileName = 'main_log_sheet_' . $safeSampleNumber . '.docx';

$filePath = $exportsDir . DIRECTORY_SEPARATOR . $fileName;

// ------------------------------------------------------------
// Create DOCX
// ------------------------------------------------------------
try {
    $writer = \PhpOffice\PhpWord\IOFactory::createWriter(
        $phpWord,
        'Word2007'
    );

    $writer->save($filePath);
} catch (\Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);

    echo 'خطا در ایجاد فایل Word: ';
    echo htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    );

    exit;
}

// ------------------------------------------------------------
// Verify generated file
// ------------------------------------------------------------
if (
    !is_file($filePath) ||
    filesize($filePath) === false ||
    filesize($filePath) === 0
) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);
    exit('فایل Word ایجاد نشد یا خالی است.');
}

// ------------------------------------------------------------
// Update database
// ------------------------------------------------------------
$stmt = $pdo->prepare(
    "UPDATE main_log_sheets
     SET word_file_path = :path
     WHERE id = :id"
);

$stmt->execute([
    'path' => 'exports/' . $fileName,
    'id'   => $mainLogSheetId,
]);

// ------------------------------------------------------------
// IMPORTANT:
// Remove every previous output before sending DOCX bytes.
// ------------------------------------------------------------
while (ob_get_level() > 0) {
    ob_end_clean();
}

// ------------------------------------------------------------
// Download
// ------------------------------------------------------------
header('Content-Description: File Transfer');

header(
    'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document'
);

header(
    'Content-Disposition: attachment; filename="' . $fileName . '"'
);

header(
    'Content-Length: ' . (string)filesize($filePath)
);

header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
