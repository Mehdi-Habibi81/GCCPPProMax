<?php
declare(strict_types=1);

// ------------------------------------------------------------
// Prevent accidental output from corrupting the DOCX download
// ------------------------------------------------------------
ob_start();

require __DIR__ . '/../auth/config.php';
require __DIR__ . '/Functions.php';

lab_require_login();

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Language;

// ------------------------------------------------------------
// Helper: safely prepare text for Word XML
// Preserves Persian ZWNJ and ZWJ characters.
// PHPWord handles XML escaping.
// ------------------------------------------------------------
function word_text(mixed $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $value = (string)$value;

    // Remove invalid XML control characters while preserving:
    // - tabs
    // - line breaks
    // - Persian ZWNJ (U+200C)
    // - Persian ZWJ (U+200D)
    $value = preg_replace(
        '/[^\P{C}\t\r\n\x{200C}\x{200D}]/u',
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
// Get form/document code
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
// Determine whether this form has separate new/used limits
// ------------------------------------------------------------
$hasUsedNewSplit = false;

foreach ($rows as $r) {
    if (
        !empty($r['limit_used']) &&
        $r['limit_used'] !== '---'
    ) {
        $hasUsedNewSplit = true;
        break;
    }
}

// ------------------------------------------------------------
// Create Word document
// ------------------------------------------------------------
Settings::setOutputEscapingEnabled(true);

$phpWord = new PhpWord();

// Persian language
$phpWord->getSettings()->setThemeFontLang(
    new Language('fa-IR')
);

// Word document uses Tahoma
$phpWord->setDefaultFontName('Tahoma');
$phpWord->setDefaultFontSize(11);

// ------------------------------------------------------------
// Paragraph styles
// ------------------------------------------------------------

// Centered Persian text
$rtlCenter = [
    'alignment' => Jc::CENTER,
    'bidi'      => true,
];

// RTL text aligned to its logical START.
// In a Persian/RTL paragraph this is the visual right side.
$rtlRight = [
    'alignment' => Jc::START,
    'bidi'      => true,
];

// Test names:
// START + bidi gives the visual right side in an RTL paragraph.
$testRight = [
    'alignment' => Jc::START,
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

$tinyFont = [
    'size' => 8,
    'name' => 'Tahoma',
];

// ------------------------------------------------------------
// Landscape section
// ------------------------------------------------------------
$section = $phpWord->addSection([
    'orientation' => 'landscape',
    'marginLeft'  => 500,
    'marginRight' => 500,
]);

// ------------------------------------------------------------
// Base table style
// ------------------------------------------------------------
$tableBase = [
    'borderSize'  => 6,
    'borderColor' => '999999',
    'cellMargin'  => 60,
];

// ------------------------------------------------------------
// Header block
// ------------------------------------------------------------
$headerTable = $section->addTable([
    'borderSize' => 0,
    'cellMargin' => 40,
]);

$headerTable->addRow();

// ------------------------------------------------------------
// Top-left document code
// ------------------------------------------------------------
$headerTable
    ->addCell(3000)
    ->addText(
        word_text('کد سند: ' . $formCode),
        $smallFont,
        $rtlCenter
    );

// ------------------------------------------------------------
// Center header
// ------------------------------------------------------------
$headerCell = $headerTable->addCell(8400);

$headerCell->addText(
    word_text('بسمه‌تعالی'),
    $titleFont,
    $rtlCenter
);

$headerCell->addText(
    word_text('شرکت مدیریت تولید برق گیلان'),
    $companyFont,
    $rtlCenter
);

$sampleTypeName = htmlspecialchars_decode(
    (string)($sample['main_log_sheet_type_name'] ?? ''),
    ENT_QUOTES
);

// Correct wording: لاگ‌شیت
$headerCell->addText(
    word_text(
        'فرم گزارش لاگ‌شیت کنترل کیفیت ' .
        $sampleTypeName
    ),
    $normalFont,
    $rtlCenter
);

// ------------------------------------------------------------
// Top-right empty cell
// ------------------------------------------------------------
$headerTable
    ->addCell(3000)
    ->addText(
        '',
        $smallFont,
        $rtlCenter
    );

$section->addTextBreak(1);

// ------------------------------------------------------------
// Sample information table
// ------------------------------------------------------------
$infoTable = $section->addTable($tableBase);

$addInfoRow = function (
    string $label,
    mixed $value
) use (
    $infoTable,
    $normalFont,
    $rtlRight
): void {
    $infoTable->addRow();

    // Value on the left
    $infoTable
        ->addCell(10400)
        ->addText(
            word_text($value),
            $normalFont,
            $rtlRight
        );

    // Label on the right
    $infoTable
        ->addCell(4000)
        ->addText(
            word_text($label),
            ['bold' => true] + $normalFont,
            $rtlRight
        );
};

$addInfoRow(
    'شماره نمونه',
    $sample['sample_number'] ?? null
);

$addInfoRow(
    'نام روغن / سوخت',
    $sampleTypeName
);

$addInfoRow(
    'محل نمونه‌گیری',
    $sample['sampling_location'] ?? null
);

$addInfoRow(
    'تاریخ نمونه‌گیری',
    !empty($sample['sampling_date'])
        ? lab_gregorian_to_jalali(
            $sample['sampling_date']
        )
        : null
);

$addInfoRow(
    'تاریخ تحویل نمونه',
    !empty($sample['delivery_date'])
        ? lab_gregorian_to_jalali(
            $sample['delivery_date']
        )
        : null
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
$resultsTable = $section->addTable($tableBase);

$hdr = [
    'bgColor' => 'EEEEEE',
];

// ------------------------------------------------------------
// Two-level header when new/used limits exist
// ------------------------------------------------------------
if ($hasUsedNewSplit) {

    // ========================================================
    // FIRST HEADER ROW
    // ========================================================
    $resultsTable->addRow();

    // Result
    $resultsTable
        ->addCell(
            2200,
            $hdr + ['vMerge' => 'restart']
        )
        ->addText(
            word_text('نتیجه آزمایش'),
            ['bold' => true] + $tinyFont,
            $rtlCenter
        );

    // Parent header: allowed values
    $resultsTable
        ->addCell(
            4000,
            $hdr + ['gridSpan' => 2]
        )
        ->addText(
            word_text('مقادیر مجاز'),
            ['bold' => true] + $tinyFont,
            $rtlCenter
        );

    // Method
    $resultsTable
        ->addCell(
            1900,
            $hdr + ['vMerge' => 'restart']
        )
        ->addText(
            word_text('روش آزمایش'),
            ['bold' => true] + $tinyFont,
            $rtlCenter
        );

    // Unit
    $resultsTable
        ->addCell(
            1450,
            $hdr + ['vMerge' => 'restart']
        )
        ->addText(
            word_text('واحد اندازه‌گیری'),
            ['bold' => true] + $tinyFont,
            $rtlCenter
        );

    // Test header
    $resultsTable
        ->addCell(
            3800,
            $hdr + ['vMerge' => 'restart']
        )
        ->addText(
            word_text('آزمایش'),
            ['bold' => true] + $tinyFont,
            $rtlCenter
        );

    // Row number
    $resultsTable
        ->addCell(
            1050,
            $hdr + ['vMerge' => 'restart']
        )
        ->addText(
            word_text('ردیف'),
            ['bold' => true] + $tinyFont,
            $rtlCenter
        );

    // ========================================================
    // SECOND HEADER ROW
    // ========================================================
    $resultsTable->addRow();

    // Continue Result
    $resultsTable->addCell(
        2200,
        ['vMerge' => 'continue']
    );

    // New oil
    $resultsTable
        ->addCell(2000, $hdr)
        ->addText(
            word_text('روغن نو'),
            ['bold' => true] + $tinyFont,
            $rtlCenter
        );

    // Used oil
    $resultsTable
        ->addCell(2000, $hdr)
        ->addText(
            word_text('روغن کارکرده'),
            ['bold' => true] + $tinyFont,
            $rtlCenter
        );

    // Continue Method
    $resultsTable->addCell(
        1900,
        ['vMerge' => 'continue']
    );

    // Continue Unit
    $resultsTable->addCell(
        1450,
        ['vMerge' => 'continue']
    );

    // Continue Test
    $resultsTable->addCell(
        3800,
        ['vMerge' => 'continue']
    );

    // Continue Row
    $resultsTable->addCell(
        1050,
        ['vMerge' => 'continue']
    );

} else {

    // ========================================================
    // SINGLE-LEVEL HEADER
    // ========================================================
    $resultsTable->addRow();

    // Result
    $resultsTable
        ->addCell(2200, $hdr)
        ->addText(
            word_text('نتیجه آزمایش'),
            ['bold' => true] + $tinyFont,
            $rtlCenter
        );

    // Allowed value
    $resultsTable
        ->addCell(4000, $hdr)
        ->addText(
            word_text('مقدار مجاز'),
            ['bold' => true] + $tinyFont,
            $rtlCenter
        );

    // Method
    $resultsTable
        ->addCell(1900, $hdr)
        ->addText(
            word_text('روش آزمایش'),
            ['bold' => true] + $tinyFont,
            $rtlCenter
        );

    // Unit
    $resultsTable
        ->addCell(1450, $hdr)
        ->addText(
            word_text('واحد اندازه‌گیری'),
            ['bold' => true] + $tinyFont,
            $rtlCenter
        );

    // Test
    $resultsTable
        ->addCell(3800, $hdr)
        ->addText(
            word_text('آزمایش'),
            ['bold' => true] + $tinyFont,
            $rtlCenter
        );

    // Row
    $resultsTable
        ->addCell(1050, $hdr)
        ->addText(
            word_text('ردیف'),
            ['bold' => true] + $tinyFont,
            $rtlCenter
        );
}

// ------------------------------------------------------------
// Result rows
// ------------------------------------------------------------
foreach ($rows as $r) {

    $testName = str_replace(
        ' — NEEDS VERIFICATION',
        '',
        (string)($r['test_name'] ?? '')
    );

    $resultsTable->addRow();

    // --------------------------------------------------------
    // Result
    // --------------------------------------------------------
    $resultsTable
        ->addCell(2200)
        ->addText(
            word_text($r['result_value'] ?? null),
            $tinyFont,
            $rtlCenter
        );

    // --------------------------------------------------------
    // Allowed values
    // --------------------------------------------------------
    if ($hasUsedNewSplit) {

        $newVal = (
            isset($r['limit_new']) &&
            $r['limit_new'] !== null &&
            $r['limit_new'] !== ''
        )
            ? $r['limit_new']
            : '---';

        $usedVal = (
            !empty($r['limit_used']) &&
            $r['limit_used'] !== '---'
        )
            ? $r['limit_used']
            : '---';

        // New oil
        $resultsTable
            ->addCell(2000)
            ->addText(
                word_text($newVal),
                $tinyFont,
                $rtlCenter
            );

        // Used oil
        $resultsTable
            ->addCell(2000)
            ->addText(
                word_text($usedVal),
                $tinyFont,
                $rtlCenter
            );

    } else {

        $singleVal = (
            isset($r['limit_new']) &&
            $r['limit_new'] !== null &&
            $r['limit_new'] !== ''
        )
            ? $r['limit_new']
            : '---';

        $resultsTable
            ->addCell(4000)
            ->addText(
                word_text($singleVal),
                $tinyFont,
                $rtlCenter
            );
    }

    // --------------------------------------------------------
    // Method
    // --------------------------------------------------------
    $resultsTable
        ->addCell(1900)
        ->addText(
            word_text($r['method'] ?? null),
            $tinyFont,
            $rtlCenter
        );

    // --------------------------------------------------------
    // Unit
    // --------------------------------------------------------
    $resultsTable
        ->addCell(1450)
        ->addText(
            word_text($r['unit'] ?? null),
            $tinyFont,
            $rtlCenter
        );

    // --------------------------------------------------------
    // Test name
    //
    // START + bidi=true is intentionally used so the Persian
    // text is visually aligned to the RIGHT side.
    // --------------------------------------------------------
    $resultsTable
        ->addCell(3800)
        ->addText(
            word_text($testName),
            $tinyFont,
            $testRight
        );

    // --------------------------------------------------------
    // Row number
    // --------------------------------------------------------
    $resultsTable
        ->addCell(1050)
        ->addText(
            word_text($r['row_order'] ?? ''),
            $tinyFont,
            $rtlCenter
        );
}

$section->addTextBreak(1);

// ------------------------------------------------------------
// Remarks box
// ------------------------------------------------------------
$remarksTable = $section->addTable($tableBase);

$remarksTable->addRow();

$remarksCell = $remarksTable->addCell(14400);

$remarksCell->addText(
    word_text('ملاحظات:'),
    ['bold' => true] + $normalFont,
    $rtlRight
);

$remarksCell->addTextBreak(2);

$remarksTable->addRow();

$remarksTable
    ->addCell(14400)
    ->addText(
        '',
        $normalFont,
        $rtlRight
    );

$remarksTable->addRow();

$remarksTable
    ->addCell(14400)
    ->addText(
        '',
        $normalFont,
        $rtlRight
    );

$section->addTextBreak(1);

// ------------------------------------------------------------
// Signature block
// ------------------------------------------------------------
$signTable = $section->addTable($tableBase);

$signTable->addRow();

$signTable
    ->addCell(4800)
    ->addText(
        word_text('مدیر امور شیمی:'),
        $smallFont,
        $rtlRight
    );

$signTable
    ->addCell(4800)
    ->addText(
        word_text('رئیس اداره آزمایشگاه رنگ و پوشش:'),
        $smallFont,
        $rtlRight
    );

$signTable
    ->addCell(4800)
    ->addText(
        word_text('مسئول آزمایشگاه روزکار:'),
        $smallFont,
        $rtlRight
    );

// ------------------------------------------------------------
// Report date
// ------------------------------------------------------------
$section->addTextBreak(1);

$section->addText(
    word_text(
        'تاریخ گزارش: ' .
        lab_gregorian_to_jalali(date('Y-m-d'))
    ),
    $smallFont,
    $rtlRight
);

// ------------------------------------------------------------
// Prepare export directory
// ------------------------------------------------------------
$exportsDir = __DIR__ . '/exports';

if (!is_dir($exportsDir)) {

    if (
        !mkdir($exportsDir, 0750, true) &&
        !is_dir($exportsDir)
    ) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code(500);

        exit('خطا در ایجاد پوشه خروجی Word.');
    }
}

// ------------------------------------------------------------
// Safe filename
// ------------------------------------------------------------
$safeSampleNumber = preg_replace(
    '/[^A-Za-z0-9\-]/',
    '_',
    (string)($sample['sample_number'] ?? 'sample')
);

if (
    $safeSampleNumber === '' ||
    $safeSampleNumber === null
) {
    $safeSampleNumber = 'sample';
}

$fileName = 'main_log_sheet_' .
            $safeSampleNumber .
            '.docx';

$filePath = $exportsDir .
            DIRECTORY_SEPARATOR .
            $fileName;

// ------------------------------------------------------------
// Generate DOCX
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
// Clear all previous output
// ------------------------------------------------------------
while (ob_get_level() > 0) {
    ob_end_clean();
}

// ------------------------------------------------------------
// Send DOCX download
// ------------------------------------------------------------
header('Content-Description: File Transfer');

header(
    'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document'
);

header(
    'Content-Disposition: attachment; filename="' .
    $fileName .
    '"'
);

header(
    'Content-Length: ' .
    (string)filesize($filePath)
);

header(
    'Cache-Control: private, max-age=0, must-revalidate'
);

header('Pragma: public');

readfile($filePath);

exit;
