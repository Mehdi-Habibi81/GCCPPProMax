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
// IMPORTANT: preserves ZWNJ (U+200C) and ZWJ (U+200D) — these are
// the Persian "half-space" characters used inside compound words
// like بسمه‌تعالی، نمونه‌گیری، ارجاع‌کننده، اندازه‌گیری. The
// previous version stripped them as "control characters", which is
// why those words were rendered joined together.
// ------------------------------------------------------------
function word_text(mixed $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $value = (string)$value;
    $value = preg_replace('/[^\P{C}\t\r\n\x{200C}\x{200D}]/u', '', $value) ?? '';
    return $value !== '' ? $value : '—';
}

// ------------------------------------------------------------
// Get sample
// ------------------------------------------------------------
$sampleId = (int)($_GET['sample_id'] ?? 0);
$sample = $sampleId ? lab_get_sample_by_id($pdo, $sampleId) : null;

if (!$sample || empty($sample['main_log_sheet_type_id'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(404);
    echo 'نمونه یا نوع لاگ‌شیت اصلی آن یافت نشد. <a href="indicator.php">بازگشت</a>';
    exit;
}

$mainLogSheetId = lab_get_or_create_main_sheet($pdo, $sampleId);
$rows = lab_get_main_sheet_rows($pdo, $mainLogSheetId, (int)$sample['main_log_sheet_type_id']);

$typeCodeStmt = $pdo->prepare("SELECT code FROM main_log_sheet_types WHERE id = :id");
$typeCodeStmt->execute(['id' => $sample['main_log_sheet_type_id']]);
$formCode = $typeCodeStmt->fetchColumn();
if ($formCode === false) {
    $formCode = '';
}

$hasUsedNewSplit = false;
foreach ($rows as $r) {
    if (!empty($r['limit_used']) && $r['limit_used'] !== '---') {
        $hasUsedNewSplit = true;
        break;
    }
}

// ------------------------------------------------------------
// Create Word document
// ------------------------------------------------------------
$phpWord = new PhpWord();
$phpWord->getSettings()->setThemeFontLang(new Language('fa-IR'));
$phpWord->setDefaultFontName('Tahoma');
$phpWord->setDefaultFontSize(11);

$rtlCenter = ['alignment' => Jc::CENTER, 'bidi' => true];
$rtlRight  = ['alignment' => Jc::END, 'bidi' => true];

$titleFont   = ['bold' => true, 'size' => 14, 'name' => 'Tahoma'];
$companyFont = ['bold' => true, 'size' => 12, 'name' => 'Tahoma'];
$normalFont  = ['size' => 11, 'name' => 'Tahoma'];
$smallFont   = ['size' => 9,  'name' => 'Tahoma'];
$tinyFont    = ['size' => 8,  'name' => 'Tahoma'];

// Landscape page; usable width ≈ 14400 twips after margins.
// Every table below is right-aligned (Jc::END) so it hugs the
// right margin instead of sitting stuck on the left, and each
// table's column widths are sized to use nearly the full width.
$section = $phpWord->addSection([
    'orientation' => 'landscape',
    'marginLeft'  => 500,
    'marginRight' => 500,
]);

$tableBase = ['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 60, 'alignment' => Jc::END];

// ------------------------------------------------------------
// Header block
// ------------------------------------------------------------
$headerTable = $section->addTable(['borderSize' => 0, 'cellMargin' => 40, 'alignment' => Jc::END]);
$headerTable->addRow();
$headerTable->addCell(3000)->addText(word_text($formCode), $smallFont, $rtlCenter);
$headerCell = $headerTable->addCell(8400);
$headerCell->addText(word_text('بسمه‌تعالی'), $titleFont, $rtlCenter);
$headerCell->addText(word_text('شرکت مدیریت تولید برق گیلان'), $companyFont, $rtlCenter);
$sampleTypeName = htmlspecialchars_decode((string)($sample['main_log_sheet_type_name'] ?? ''), ENT_QUOTES);
$headerCell->addText(word_text('فرم گزارش لاکتیویت کنترل کیفیت ' . $sampleTypeName), $normalFont, $rtlCenter);
$headerTable->addCell(3000)->addText('', $smallFont, $rtlCenter);

$section->addTextBreak(1);

// ------------------------------------------------------------
// Sample information box (label on the right, value on the left)
// ------------------------------------------------------------
$infoTable = $section->addTable($tableBase);

$addInfoRow = function (string $label, mixed $value) use ($infoTable, $normalFont, $rtlRight): void {
    $infoTable->addRow();
    $infoTable->addCell(10400)->addText(word_text($value), $normalFont, $rtlRight); // left
    $infoTable->addCell(4000)->addText(word_text($label), ['bold' => true] + $normalFont, $rtlRight);  // right
};

$addInfoRow('شماره نمونه', $sample['sample_number'] ?? null);
$addInfoRow('نام روغن / سوخت', $sampleTypeName);
$addInfoRow('محل نمونه‌گیری', $sample['sampling_location'] ?? null);
$addInfoRow('تاریخ نمونه‌گیری', !empty($sample['sampling_date']) ? lab_gregorian_to_jalali($sample['sampling_date']) : null);
$addInfoRow('تاریخ تحویل نمونه', !empty($sample['delivery_date']) ? lab_gregorian_to_jalali($sample['delivery_date']) : null);
$addInfoRow('ارجاع‌کننده', $sample['referrer'] ?? null);
$addInfoRow('تحویل‌گیرنده', $sample['receiver'] ?? null);

$section->addTextBreak(1);

// ------------------------------------------------------------
// Results table — true RTL column order (ردیف added last = rightmost)
// ------------------------------------------------------------
$resultsTable = $section->addTable($tableBase);
$hdr = ['bgColor' => 'EEEEEE'];

if ($hasUsedNewSplit) {
    $resultsTable->addRow();
    $resultsTable->addCell(2200, $hdr + ['vMerge' => 'restart'])->addText(word_text('نتیجه آزمایش'), ['bold' => true] + $tinyFont, $rtlCenter);
    $resultsTable->addCell(4000, $hdr + ['gridSpan' => 2])->addText(word_text('مقادیر مجاز'), ['bold' => true] + $tinyFont, $rtlCenter);
    $resultsTable->addCell(1900, $hdr + ['vMerge' => 'restart'])->addText(word_text('روش آزمایش'), ['bold' => true] + $tinyFont, $rtlCenter);
    $resultsTable->addCell(1450, $hdr + ['vMerge' => 'restart'])->addText(word_text('واحد اندازه‌گیری'), ['bold' => true] + $tinyFont, $rtlCenter);
    $resultsTable->addCell(3800, $hdr + ['vMerge' => 'restart'])->addText(word_text('آزمایش'), ['bold' => true] + $tinyFont, $rtlCenter);
    $resultsTable->addCell(1050, $hdr + ['vMerge' => 'restart'])->addText(word_text('ردیف'), ['bold' => true] + $tinyFont, $rtlCenter);

    $resultsTable->addRow();
    $resultsTable->addCell(2200, ['vMerge' => 'continue']);
    $resultsTable->addCell(2000, $hdr)->addText(word_text('روغن نو'), ['bold' => true] + $tinyFont, $rtlCenter);
    $resultsTable->addCell(2000, $hdr)->addText(word_text('روغن کارکرده'), ['bold' => true] + $tinyFont, $rtlCenter);
    $resultsTable->addCell(1900, ['vMerge' => 'continue']);
    $resultsTable->addCell(1450, ['vMerge' => 'continue']);
    $resultsTable->addCell(3800, ['vMerge' => 'continue']);
    $resultsTable->addCell(1050, ['vMerge' => 'continue']);
} else {
    $resultsTable->addRow();
    $resultsTable->addCell(2200, $hdr)->addText(word_text('نتیجه آزمایش'), ['bold' => true] + $tinyFont, $rtlCenter);
    $resultsTable->addCell(4000, $hdr)->addText(word_text('مقدار مجاز'), ['bold' => true] + $tinyFont, $rtlCenter);
    $resultsTable->addCell(1900, $hdr)->addText(word_text('روش آزمایش'), ['bold' => true] + $tinyFont, $rtlCenter);
    $resultsTable->addCell(1450, $hdr)->addText(word_text('واحد اندازه‌گیری'), ['bold' => true] + $tinyFont, $rtlCenter);
    $resultsTable->addCell(3800, $hdr)->addText(word_text('آزمایش'), ['bold' => true] + $tinyFont, $rtlCenter);
    $resultsTable->addCell(1050, $hdr)->addText(word_text('ردیف'), ['bold' => true] + $tinyFont, $rtlCenter);
}

foreach ($rows as $r) {
    $testName = str_replace(' — NEEDS VERIFICATION', '', (string)($r['test_name'] ?? ''));
    $resultsTable->addRow();

    $resultsTable->addCell(2200)->addText(word_text($r['result_value'] ?? null), $tinyFont, $rtlCenter);

    if ($hasUsedNewSplit) {
        $newVal = (isset($r['limit_new']) && $r['limit_new'] !== null && $r['limit_new'] !== '') ? $r['limit_new'] : '---';
        $usedVal = (!empty($r['limit_used']) && $r['limit_used'] !== '---') ? $r['limit_used'] : '---';
        $resultsTable->addCell(2000)->addText(word_text($newVal), $tinyFont, $rtlCenter);
        $resultsTable->addCell(2000)->addText(word_text($usedVal), $tinyFont, $rtlCenter);
    } else {
        $singleVal = (isset($r['limit_new']) && $r['limit_new'] !== null && $r['limit_new'] !== '') ? $r['limit_new'] : '---';
        $resultsTable->addCell(4000)->addText(word_text($singleVal), $tinyFont, $rtlCenter);
    }

    $resultsTable->addCell(1900)->addText(word_text($r['method'] ?? null), $tinyFont, $rtlCenter);
    $resultsTable->addCell(1450)->addText(word_text($r['unit'] ?? null), $tinyFont, $rtlCenter);
    $resultsTable->addCell(3800)->addText(word_text($testName), $tinyFont, $rtlRight);
    $resultsTable->addCell(1050)->addText(word_text($r['row_order'] ?? ''), $tinyFont, $rtlCenter);
}

$section->addTextBreak(1);

// ------------------------------------------------------------
// Remarks box (ملاحظات) — present on the paper form, was missing.
// ------------------------------------------------------------
$remarksTable = $section->addTable($tableBase);
$remarksTable->addRow();
$remarksCell = $remarksTable->addCell(14400, ['vMerge' => 'restart']);
$remarksCell->addText(word_text('ملاحظات:'), ['bold' => true] + $normalFont, $rtlRight);
$remarksCell->addTextBreak(2);
$remarksTable->addRow();
$remarksTable->addCell(14400)->addText('', $normalFont, $rtlRight);
$remarksTable->addRow();
$remarksTable->addCell(14400)->addText('', $normalFont, $rtlRight);

$section->addTextBreak(1);

// ------------------------------------------------------------
// Signature block — RTL order: مسئول آزمایشگاه روزکار rightmost
// (added last), مدیر امور شیمی leftmost (added first).
// ------------------------------------------------------------
$signTable = $section->addTable($tableBase);
$signTable->addRow();
$signTable->addCell(4800)->addText(word_text('مدیر امور شیمی:'), $smallFont, $rtlRight);
$signTable->addCell(4800)->addText(word_text('رئیس اداره آزمایشگاه رنگ و پوشش:'), $smallFont, $rtlRight);
$signTable->addCell(4800)->addText(word_text('مسئول آزمایشگاه روزکار:'), $smallFont, $rtlRight);

$section->addTextBreak(1);
$section->addText(word_text('تاریخ گزارش: ' . lab_gregorian_to_jalali(date('Y-m-d'))), $smallFont, $rtlRight);

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

$safeSampleNumber = preg_replace('/[^A-Za-z0-9\-]/', '_', (string)($sample['sample_number'] ?? 'sample'));
if ($safeSampleNumber === '' || $safeSampleNumber === null) {
    $safeSampleNumber = 'sample';
}
$fileName = 'main_log_sheet_' . $safeSampleNumber . '.docx';
$filePath = $exportsDir . DIRECTORY_SEPARATOR . $fileName;

try {
    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save($filePath);
} catch (\Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo 'خطا در ایجاد فایل Word: ';
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

if (!is_file($filePath) || filesize($filePath) === false || filesize($filePath) === 0) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    exit('فایل Word ایجاد نشد یا خالی است.');
}

$stmt = $pdo->prepare("UPDATE main_log_sheets SET word_file_path = :path WHERE id = :id");
$stmt->execute(['path' => 'exports/' . $fileName, 'id' => $mainLogSheetId]);

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . (string)filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
