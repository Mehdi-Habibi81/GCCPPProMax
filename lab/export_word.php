<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php'; // provides $pdo, starts session
require __DIR__ . '/functions.php';       // also loads vendor/autoload.php

lab_require_login();

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Language;

$sampleId = (int)($_GET['sample_id'] ?? 0);
$sample = $sampleId ? lab_get_sample_by_id($pdo, $sampleId) : null;

if (!$sample || empty($sample['main_log_sheet_type_id'])) {
    http_response_code(404);
    echo 'نمونه یا نوع لاگ‌شیت اصلی آن یافت نشد. <a href="indicator.php">بازگشت</a>';
    exit;
}

$mainLogSheetId = lab_get_or_create_main_sheet($pdo, $sampleId);
$rows = lab_get_main_sheet_rows($pdo, $mainLogSheetId, (int)$sample['main_log_sheet_type_id']);

$typeCodeStmt = $pdo->prepare("SELECT code FROM main_log_sheet_types WHERE id = :id");
$typeCodeStmt->execute(['id' => $sample['main_log_sheet_type_id']]);
$formCode = $typeCodeStmt->fetchColumn() ?: '';

// ------------------------------------------------------------
// Build the Word document
// ------------------------------------------------------------
$phpWord = new PhpWord();
$phpWord->getSettings()->setThemeFontLang(new Language(Language::FA_IR));

$rtlParagraph = ['alignment' => Jc::CENTER, 'bidi' => true];
$rtlParagraphRight = ['alignment' => Jc::END, 'bidi' => true];
$titleFont = ['bold' => true, 'size' => 14, 'name' => 'Tahoma'];
$normalFont = ['size' => 11, 'name' => 'Tahoma'];
$smallFont = ['size' => 9, 'name' => 'Tahoma'];

$section = $phpWord->addSection();

$section->addText('بسمه‌تعالی', $titleFont, $rtlParagraph);
$section->addText('شرکت مدیریت تولید برق گیلان', ['bold' => true, 'size' => 12, 'name' => 'Tahoma'], $rtlParagraph);
$section->addText(htmlspecialchars_decode($sample['main_log_sheet_type_name']) . ' — لاکتیویت کنترل کیفیت', $normalFont, $rtlParagraph);
$section->addText('کد سند: ' . $formCode, $smallFont, $rtlParagraph);
$section->addTextBreak(1);

// Sample info table
$infoTable = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80]);

$addInfoRow = function ($label, $value) use ($infoTable, $normalFont, $rtlParagraphRight) {
    $infoTable->addRow();
    $infoTable->addCell(3000)->addText($label, ['bold' => true] + $normalFont, $rtlParagraphRight);
    $infoTable->addCell(6000)->addText($value !== null && $value !== '' ? $value : '—', $normalFont, $rtlParagraphRight);
};

$addInfoRow('شماره نمونه', $sample['sample_number']);
$addInfoRow('تاریخ نمونه‌گیری', $sample['sampling_date'] ? lab_gregorian_to_jalali($sample['sampling_date']) : null);
$addInfoRow('تاریخ تحویل نمونه', $sample['delivery_date'] ? lab_gregorian_to_jalali($sample['delivery_date']) : null);
$addInfoRow('محل نمونه‌گیری', $sample['sampling_location'] ?? null);
$addInfoRow('ارجاع‌کننده', $sample['referrer'] ?? null);
$addInfoRow('تحویل‌گیرنده', $sample['receiver'] ?? null);

$section->addTextBreak(1);

// Test results table
$resultsTable = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80]);

$headerCellStyle = ['bgColor' => 'EEEEEE'];
$resultsTable->addRow();
$resultsTable->addCell(700, $headerCellStyle)->addText('ردیف', ['bold' => true] + $smallFont, $rtlParagraph);
$resultsTable->addCell(3200, $headerCellStyle)->addText('آزمایش', ['bold' => true] + $smallFont, $rtlParagraph);
$resultsTable->addCell(1200, $headerCellStyle)->addText('واحد', ['bold' => true] + $smallFont, $rtlParagraph);
$resultsTable->addCell(1500, $headerCellStyle)->addText('روش', ['bold' => true] + $smallFont, $rtlParagraph);
$resultsTable->addCell(2200, $headerCellStyle)->addText('مقادیر مجاز', ['bold' => true] + $smallFont, $rtlParagraph);
$resultsTable->addCell(1700, $headerCellStyle)->addText('نتیجه', ['bold' => true] + $smallFont, $rtlParagraph);

foreach ($rows as $r) {
    $testName = str_replace(' — NEEDS VERIFICATION', '', $r['test_name']);

    $limits = [];
    if (!empty($r['limit_used']) && $r['limit_used'] !== '---') {
        $limits[] = 'کارکرده: ' . $r['limit_used'];
    }
    if (!empty($r['limit_new'])) {
        $limits[] = 'نو: ' . $r['limit_new'];
    }
    $limitsText = $limits ? implode(' / ', $limits) : '—';

    $resultsTable->addRow();
    $resultsTable->addCell(700)->addText((string)$r['row_order'], $smallFont, $rtlParagraph);
    $resultsTable->addCell(3200)->addText($testName, $smallFont, $rtlParagraphRight);
    $resultsTable->addCell(1200)->addText($r['unit'] ?? '—', $smallFont, $rtlParagraph);
    $resultsTable->addCell(1500)->addText($r['method'] ?? '—', $smallFont, $rtlParagraph);
    $resultsTable->addCell(2200)->addText($limitsText, $smallFont, $rtlParagraphRight);
    $resultsTable->addCell(1700)->addText($r['result_value'] ?? '—', $smallFont, $rtlParagraph);
}

$section->addTextBreak(2);
$section->addText('تاریخ گزارش: ' . lab_gregorian_to_jalali(date('Y-m-d')), $smallFont, $rtlParagraphRight);

// ------------------------------------------------------------
// Save to disk (so word_file_path is recorded) and stream download
// ------------------------------------------------------------
$exportsDir = __DIR__ . '/exports';
if (!is_dir($exportsDir)) {
    mkdir($exportsDir, 0750, true);
}

$safeSampleNumber = preg_replace('/[^A-Za-z0-9\-]/', '_', $sample['sample_number']);
$fileName = 'main_log_sheet_' . $safeSampleNumber . '.docx';
$filePath = $exportsDir . '/' . $fileName;

$writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($filePath);

$stmt = $pdo->prepare("UPDATE main_log_sheets SET word_file_path = :path WHERE id = :id");
$stmt->execute(['path' => 'exports/' . $fileName, 'id' => $mainLogSheetId]);

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
