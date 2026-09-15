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

    $value = preg_replace('/[^\P{C}\t\r\n]/u', '', $value) ?? '';

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

// Does this form actually distinguish "روغن کارکرده" from "روغن نو"?
// (fuel / cooling-liquid style forms only have a single limit column)
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

// Note: use the string 'fa-IR' rather than Language::FA_IR — that
// constant doesn't exist in all PHPWord versions.
$phpWord->getSettings()->setThemeFontLang(new Language('fa-IR'));
$phpWord->setDefaultFontName('Tahoma');
$phpWord->setDefaultFontSize(11);

$rtlParagraph = ['alignment' => Jc::CENTER, 'bidi' => true];
$rtlParagraphRight = ['alignment' => Jc::END, 'bidi' => true];

$titleFont = ['bold' => true, 'size' => 14, 'name' => 'Tahoma'];
$companyFont = ['bold' => true, 'size' => 12, 'name' => 'Tahoma'];
$normalFont = ['size' => 11, 'name' => 'Tahoma'];
$smallFont = ['size' => 9, 'name' => 'Tahoma'];
$tinyFont = ['size' => 8, 'name' => 'Tahoma'];

$section = $phpWord->addSection([
    'orientation' => 'landscape',
    'marginLeft'  => 600,
    'marginRight' => 600,
]);

// ------------------------------------------------------------
// Header block — mirrors the paper form's top area:
// document code on one side, company/title centered.
// ------------------------------------------------------------
$headerTable = $section->addTable(['borderSize' => 0, 'cellMargin' => 40]);
$headerTable->addRow();
$headerTable->addCell(3000)->addText(word_text('کد سند: ' . $formCode), $smallFont, $rtlParagraphRight);
$headerCell = $headerTable->addCell(6000);
$headerCell->addText(word_text('بسمه‌تعالی'), $titleFont, $rtlParagraph);
$headerCell->addText(word_text('شرکت مدیریت تولید برق گیلان'), $companyFont, $rtlParagraph);

$sampleTypeName = htmlspecialchars_decode((string)($sample['main_log_sheet_type_name'] ?? ''), ENT_QUOTES);
$headerCell->addText(word_text('فرم گزارش لاکتیویت کنترل کیفیت ' . $sampleTypeName), $normalFont, $rtlParagraph);
$headerTable->addCell(3000)->addText('', $smallFont, $rtlParagraph);

$section->addTextBreak(1);

// ------------------------------------------------------------
// Sample information box — matches the paper form's info strip
// (شماره نمونه / شرح نمونه / محل نمونه‌گیری / نام روغن یا سوخت /
//  تاریخ نمونه‌گیری)
// ------------------------------------------------------------
$infoTable = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80]);

$addInfoRow = function (string $label, mixed $value) use ($infoTable, $normalFont, $rtlParagraphRight): void {
    $infoTable->addRow();
    $infoTable->addCell(3000)->addText(word_text($label), ['bold' => true] + $normalFont, $rtlParagraphRight);
    $infoTable->addCell(6000)->addText(word_text($value), $normalFont, $rtlParagraphRight);
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
// Results table — mirrors the paper form's column layout:
// separate "روغن کارکرده" / "روغن نو" columns when the form
// actually uses that split; a single "مقدار مجاز" column otherwise.
// ------------------------------------------------------------
$resultsTable = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 60]);
$headerCellStyle = ['bgColor' => 'EEEEEE'];

$resultsTable->addRow();
$resultsTable->addCell(600, $headerCellStyle)->addText(word_text('ردیف'), ['bold' => true] + $tinyFont, $rtlParagraph);
$resultsTable->addCell(2600, $headerCellStyle)->addText(word_text('آزمایش'), ['bold' => true] + $tinyFont, $rtlParagraph);
$resultsTable->addCell(1000, $headerCellStyle)->addText(word_text('واحد اندازه‌گیری'), ['bold' => true] + $tinyFont, $rtlParagraph);
$resultsTable->addCell(1300, $headerCellStyle)->addText(word_text('روش آزمایش'), ['bold' => true] + $tinyFont, $rtlParagraph);

if ($hasUsedNewSplit) {
    $resultsTable->addCell(1400, $headerCellStyle)->addText(word_text('مقادیر مجاز (روغن کارکرده)'), ['bold' => true] + $tinyFont, $rtlParagraph);
    $resultsTable->addCell(1400, $headerCellStyle)->addText(word_text('مقادیر مجاز (روغن نو)'), ['bold' => true] + $tinyFont, $rtlParagraph);
} else {
    $resultsTable->addCell(2800, $headerCellStyle)->addText(word_text('مقدار مجاز'), ['bold' => true] + $tinyFont, $rtlParagraph);
}

$resultsTable->addCell(1500, $headerCellStyle)->addText(word_text('نتیجه آزمایش'), ['bold' => true] + $tinyFont, $rtlParagraph);

foreach ($rows as $r) {
    $testName = str_replace(' — NEEDS VERIFICATION', '', (string)($r['test_name'] ?? ''));

    $resultsTable->addRow();
    $resultsTable->addCell(600)->addText(word_text($r['row_order'] ?? ''), $tinyFont, $rtlParagraph);
    $resultsTable->addCell(2600)->addText(word_text($testName), $tinyFont, $rtlParagraphRight);
    $resultsTable->addCell(1000)->addText(word_text($r['unit'] ?? null), $tinyFont, $rtlParagraph);
    $resultsTable->addCell(1300)->addText(word_text($r['method'] ?? null), $tinyFont, $rtlParagraph);

    if ($hasUsedNewSplit) {
        $usedVal = (!empty($r['limit_used']) && $r['limit_used'] !== '---') ? $r['limit_used'] : '---';
        $newVal = (isset($r['limit_new']) && $r['limit_new'] !== null && $r['limit_new'] !== '') ? $r['limit_new'] : '---';
        $resultsTable->addCell(1400)->addText(word_text($usedVal), $tinyFont, $rtlParagraph);
        $resultsTable->addCell(1400)->addText(word_text($newVal), $tinyFont, $rtlParagraph);
    } else {
        $singleVal = (isset($r['limit_new']) && $r['limit_new'] !== null && $r['limit_new'] !== '') ? $r['limit_new'] : '---';
        $resultsTable->addCell(2800)->addText(word_text($singleVal), $tinyFont, $rtlParagraph);
    }

    $resultsTable->addCell(1500)->addText(word_text($r['result_value'] ?? null), $tinyFont, $rtlParagraph);
}

$section->addTextBreak(2);

// ------------------------------------------------------------
// Signature block — matches the paper form's sign-off row
// ------------------------------------------------------------
$signTable = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80]);
$signTable->addRow();
$signTable->addCell(3000)->addText(word_text('مسئول آزمایشگاه روزکار:'), $smallFont, $rtlParagraphRight);
$signTable->addCell(3000)->addText(word_text('رئیس اداره آزمایشگاه رنگ و پوشش:'), $smallFont, $rtlParagraphRight);
$signTable->addCell(3000)->addText(word_text('مدیر امور شیمی:'), $smallFont, $rtlParagraphRight);

$section->addTextBreak(1);
$section->addText(word_text('تاریخ گزارش: ' . lab_gregorian_to_jalali(date('Y-m-d'))), $smallFont, $rtlParagraphRight);

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
