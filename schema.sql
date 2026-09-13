-- ============================================================
-- Lab digitization module — CONSOLIDATED schema (fresh install)
-- Use this on a device that has NONE of the lab tables yet.
-- It combines schema.sql through schema_v8.sql into one file,
-- in the correct final form (including a fix for a table that
-- schema_v6 failed to update on devices where schema.sql had
-- already created it with an older column layout).
-- ============================================================

-- ------------------------------------------------------------
-- Reference tables
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS sample_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code TINYINT NOT NULL UNIQUE,
    name_fa VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO sample_types (code, name_fa) VALUES
    (1, 'گازوئیل'),
    (2, 'روغن'),
    (3, 'آب'),
    (4, 'کلرید سدیم'),
    (5, 'اسید کلریدریک'),
    (6, 'هیدروکلرید سدیم'),
    (7, 'رسوب'),
    (8, 'B&B'),
    (9, 'نفت سفید');

CREATE TABLE IF NOT EXISTS main_log_sheet_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name_fa VARCHAR(150) NOT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO main_log_sheet_types (code, name_fa) VALUES
    ('FG-PC-0201', 'سوخت مایع'),
    ('FG-PC-0206', 'روغن دیزل ژنراتور'),
    ('FG-PC-0428', 'روغن توربین گاز'),
    ('FG-PC-0202', 'روغن توربین بخار'),
    ('FG-PC-0429', 'روغن کنترل'),
    ('FG-PC-0203', 'روغن ترانسفورماتور'),
    ('FG-PC-0191', 'مایعات سیکل خنک‌کننده بسته');

CREATE TABLE IF NOT EXISTS main_log_sheet_test_definitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    main_log_sheet_type_id INT NOT NULL,
    row_order INT NOT NULL,
    test_name VARCHAR(150) NOT NULL,
    unit VARCHAR(30),
    method VARCHAR(50),
    limit_new VARCHAR(50),
    limit_used VARCHAR(50),
    test_location VARCHAR(50) DEFAULT 'داخل نیروگاه',
    FOREIGN KEY (main_log_sheet_type_id) REFERENCES main_log_sheet_types(id)
) ENGINE=InnoDB;

-- Seed test definitions for all 7 main log sheet types
-- (only runs if the table is currently empty, so re-running this
--  consolidated file is safe and won't duplicate rows)
INSERT INTO main_log_sheet_test_definitions
    (main_log_sheet_type_id, row_order, test_name, unit, method, limit_new, limit_used, test_location)
SELECT * FROM (
    SELECT id, 1, 'دانسیته در 15 درجه سانتیگراد', 'g/cm3', 'DIN 51757', '---', '---', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0206'
    UNION ALL SELECT id, 2, 'ویسکوزیته سینماتیک در 40 درجه سانتیگراد', 'mm2/s', 'DIN 53019', '100-185', '---', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0206'
    UNION ALL SELECT id, 3, 'غلظت مس (Cu)', 'ppm', 'AAS', '---', 'کمتر از 8', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0206'
    UNION ALL SELECT id, 4, 'غلظت آلومینیوم (Al)', 'ppm', 'AAS', '---', 'کمتر از 8', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0206'
    UNION ALL SELECT id, 5, 'غلظت آهن (Fe)', 'ppm', 'AAS', '---', 'کمتر از 28', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0206'
    UNION ALL SELECT id, 6, 'غلظت سرب (Pb)', 'ppm', 'AAS', '---', 'کمتر از 8', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0206'
    UNION ALL SELECT id, 7, 'غلظت کروم (Cr)', 'ppm', 'AAS', '---', 'کمتر از 8', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0206'
    UNION ALL SELECT id, 8, 'غلظت سیلیس (Si)', 'ppm', 'AAS', '---', 'کمتر از 12', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0206'
    UNION ALL SELECT id, 9, 'ویسکوزیته سینماتیک در 100 درجه سانتیگراد', 'mm2/s', 'ASTM D445', '12.5-16.3*', '---', 'خارج از نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0206'
    UNION ALL SELECT id, 10, 'قلیائیت (TBN)', 'mgKOH/g', 'ASTM D2866', '**', 'بیشتر از 10', 'خارج از نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0206'
    UNION ALL SELECT id, 11, 'مقدار آب محلول', '%(wt)', 'DIN 51777', '---', '0.5%>', 'خارج از نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0206'

    UNION ALL SELECT id, 1, 'خاکستر سولفاته', 'ppm', 'DIN 51575', '100 >', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 2, 'غلظت سدیم', 'ppm', 'AAS', '0.5 >', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 3, 'غلظت پتاسیم', 'ppm', 'AAS', '10 >', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 4, 'غلظت کلسیم', 'ppm', 'AAS', '0.5 >', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 5, 'غلظت وانادیم', 'ppm', 'AAS', '2 >', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 6, 'غلظت روی', 'ppm', 'AAS', '1 >', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 7, 'غلظت سرب — NEEDS VERIFICATION', 'ppm', 'AAS', '1 >', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 8, 'مقدار گوگرد', '%(wt)', 'IR', '%1 >', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 9, 'دانسیته در 15 درجه سانتیگراد', 'g/cm3', 'DIN 51757', 'Max 0.86', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 10, 'ویسکوزیته سینماتیک در 40 درجه سانتیگراد', 'mm2/s', 'DIN 53019', '1.9-2.8', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 11, 'نقطه اشتعال', 'C', 'ASTM D92', '62', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 12, 'نقطه ابری شدن', 'C', 'DIN 51597', '----', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 13, 'نقطه ریزش', 'C', 'DIN 51597', '-6 <', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 14, 'باقیمانده کربنی', '%(wt)', 'DIN 51551', 'Trace', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 15, 'مقدار ذرات معلق', '%(wt)', 'ISO 3735', 'Trace', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 16, 'مقدار آب', '%(wt)', 'DIN 51777', 'Max 0.1', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 17, 'ارزش حرارتی ناخالص', 'kJ/kg', 'DIN 51900', '45295', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'
    UNION ALL SELECT id, 18, 'ارزش حرارتی خالص', 'kJ/kg', 'DIN 51900', '42000 <', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0201'

    UNION ALL SELECT id, 1, 'دانسیته در 15 درجه سانتیگراد', 'g/cm3', 'DIN 51757', '0.9>', '0.9>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'
    UNION ALL SELECT id, 2, 'ویسکوزیته سینماتیک در 40 درجه سانتیگراد', 'mm2/s', 'DIN 53019', '28.8-35.2', '28.8-35.2', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'
    UNION ALL SELECT id, 3, 'توانائی جداسازی هوا در 50 درجه سانتیگراد', 'min', 'DIN 51381', '4>', '85<', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'
    UNION ALL SELECT id, 4, 'توانائی جداسازی آب', 'Sec', 'DIN 51589', '300>', '500>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'
    UNION ALL SELECT id, 5, 'عدد خنثی‌سازی', 'mgKOH/g', 'DIN 51558', '0.3>', '0.5>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'
    UNION ALL SELECT id, 6, 'مقدار آب', '%(wt)', 'DIN 51777', '0.01>', '0.01>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'
    UNION ALL SELECT id, 7, 'مقدار ذرات معلق', '%(wt)', 'ISO 3735', '---', '---', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'
    UNION ALL SELECT id, 8, 'خوردگی مس در 100 درجه سانتیگراد', NULL, 'DIN 51759', '2-100A3>', '2-100A3>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'
    UNION ALL SELECT id, 9, 'خوردگی فولاد در 60 درجه سانتیگراد', NULL, 'DIN 51585', '0-A', '0-A', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'
    UNION ALL SELECT id, 10, 'نقطه اشتعال', 'C', 'ASTM D92', '160<', '160<', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'
    UNION ALL SELECT id, 11, 'نقطه ابری شدن', 'C', 'DIN 51597', '---', '---', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'
    UNION ALL SELECT id, 12, 'نقطه ریزش', 'C', 'DIN 51597', '-6>', '-6>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'
    UNION ALL SELECT id, 13, 'کف — تمایل به ایجاد کف (بدون همزدن) — NEEDS VERIFICATION', 'cm3', 'ASTM D892', '400>', '600>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'
    UNION ALL SELECT id, 14, 'کف — زمان از بین رفتن کف (بدون همزدن) — NEEDS VERIFICATION', 'Sec', 'ASTM D892', '450>', '600>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'
    UNION ALL SELECT id, 15, 'کف — تمایل به ایجاد کف (پس از همزدن) — NEEDS VERIFICATION', 'cm3', 'ASTM D892', '400>', '600>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'
    UNION ALL SELECT id, 16, 'کف — زمان از بین رفتن کف (پس از همزدن) — NEEDS VERIFICATION', 'Sec', 'ASTM D892', '450>', '600>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0428'

    UNION ALL SELECT id, 1, 'دانسیته در 15 درجه سانتیگراد', 'g/cm3', 'DIN 51757', '0.9>', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'
    UNION ALL SELECT id, 2, 'ویسکوزیته سینماتیک در 40 درجه سانتیگراد', 'mm2/s', 'DIN 53019', '41.4-50.6', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'
    UNION ALL SELECT id, 3, 'توانائی جداسازی هوا در 50 درجه سانتیگراد', 'min', 'DIN 51381', '4>', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'
    UNION ALL SELECT id, 4, 'توانائی جداسازی آب', 'Sec', 'DIN 51589', '300>', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'
    UNION ALL SELECT id, 5, 'عدد خنثی‌سازی', 'mgKOH/g', 'DIN 51558', '0.2>', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'
    UNION ALL SELECT id, 6, 'مقدار آب', '%(wt)', 'DIN 51777', '0.01>', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'
    UNION ALL SELECT id, 7, 'مقدار ذرات معلق', '%(wt)', 'ISO 3735', '---', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'
    UNION ALL SELECT id, 8, 'خوردگی مس در 100 درجه سانتیگراد', NULL, 'DIN 51759', '2-100A3>', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'
    UNION ALL SELECT id, 9, 'خوردگی فولاد در 60 درجه سانتیگراد', NULL, 'DIN 51585', '0-A', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'
    UNION ALL SELECT id, 10, 'نقطه اشتعال', 'C', 'ASTM D92', '165<', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'
    UNION ALL SELECT id, 11, 'نقطه ابری شدن', 'C', 'DIN 51597', '---', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'
    UNION ALL SELECT id, 12, 'نقطه ریزش', 'C', 'DIN 51597', '-6>', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'
    UNION ALL SELECT id, 13, 'کف — تمایل به ایجاد کف (بدون همزدن) — NEEDS VERIFICATION', 'cm3', 'ASTM D892', '400>', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'
    UNION ALL SELECT id, 14, 'کف — زمان از بین رفتن کف (بدون همزدن) — NEEDS VERIFICATION', 'Sec', 'ASTM D892', '450>', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'
    UNION ALL SELECT id, 15, 'کف — تمایل به ایجاد کف (پس از همزدن) — NEEDS VERIFICATION', 'cm3', 'ASTM D892', '400>', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'
    UNION ALL SELECT id, 16, 'کف — زمان از بین رفتن کف (پس از همزدن) — NEEDS VERIFICATION', 'Sec', 'ASTM D892', '450>', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0202'

    UNION ALL SELECT id, 1, 'دانسیته در 15 درجه سانتیگراد', 'g/cm3', 'DIN 51757', '0.92≥', '0.92≥', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'
    UNION ALL SELECT id, 2, 'ویسکوزیته سینماتیک در 40 درجه سانتیگراد', 'mm2/s', 'DIN 53019', '90-110', '±5%', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'
    UNION ALL SELECT id, 3, 'توانائی جداسازی هوا در 50 درجه سانتیگراد', 'min', 'DIN 51581', '12>', '18>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'
    UNION ALL SELECT id, 4, 'عدد خنثی‌سازی', 'mgKOH/g', 'DIN 51558', '0.5>', '0.5>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'
    UNION ALL SELECT id, 5, 'مقدار آب', '%(wt)', 'DIN 51777', '0.01>', '0.01>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'
    UNION ALL SELECT id, 6, 'مقدار ذرات معلق', '%(wt)', 'ISO 3735', '---', '---', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'
    UNION ALL SELECT id, 7, 'خوردگی مس در 100 درجه سانتیگراد', NULL, 'DIN 51759', '2-100A3>', '2-100A3>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'
    UNION ALL SELECT id, 8, 'خوردگی فولاد در 60 درجه سانتیگراد', NULL, 'DIN 51585', '0-A>', '0-A>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'
    UNION ALL SELECT id, 9, 'نقطه اشتعال', 'C', 'ASTM D92', '>205', '>205', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'
    UNION ALL SELECT id, 10, 'نقطه ابری شدن', 'C', 'DIN 51597', '---', '---', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'
    UNION ALL SELECT id, 11, 'نقطه ریزش', 'C', 'DIN 51597', '-12>', '-12>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'
    UNION ALL SELECT id, 12, 'مقدار گلیکول', NULL, 'D 2982', 'Negative', 'Negative', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'
    UNION ALL SELECT id, 13, 'کف — تمایل به ایجاد کف (بدون همزدن) — NEEDS VERIFICATION', 'cm3', 'ASTM D892', '200>', '400>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'
    UNION ALL SELECT id, 14, 'کف — زمان از بین رفتن کف (بدون همزدن) — NEEDS VERIFICATION', 'Sec', 'ASTM D892', '200>', '300', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'
    UNION ALL SELECT id, 15, 'کف — تمایل به ایجاد کف (پس از همزدن) — NEEDS VERIFICATION', 'cm3', 'ASTM D892', '200>', '400>', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'
    UNION ALL SELECT id, 16, 'کف — زمان از بین رفتن کف (پس از همزدن) — NEEDS VERIFICATION', 'Sec', 'ASTM D892', '200>', '300', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0429'

    UNION ALL SELECT id, 1, 'دانسیته در 15 درجه سانتیگراد', 'g/cm3', 'DIN 51757', '---*', '---', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0203'
    UNION ALL SELECT id, 2, 'دانسیته در 20 درجه سانتیگراد', 'g/cm3', 'IEC 296', '0.895≥', '---', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0203'
    UNION ALL SELECT id, 3, 'ویسکوزیته سینماتیک در 40 درجه سانتیگراد', 'mm2/S', 'DIN 53019', '11≥', '---', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0203'
    UNION ALL SELECT id, 4, 'نقطه اشتعال', 'C', 'DIN 51755', '130≤', '---', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0203'
    UNION ALL SELECT id, 5, 'نقطه ابری شدن', 'C', 'DIN 51597', '---', '---', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0203'
    UNION ALL SELECT id, 6, 'نقطه ریزش', 'C', 'DIN 51597', '-45≥', '---', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0203'
    UNION ALL SELECT id, 7, 'عدد خنثی‌سازی', 'mgKOH/g', 'DIN 51558', '0.03≥', '0.5≥', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0203'
    UNION ALL SELECT id, 8, 'عدد صابونی شدن', 'mgKOH/g', 'DIN 51559', '---', '1.25≥', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0203'
    UNION ALL SELECT id, 9, 'مقدار آب', '%(WT)', 'DIN 51777', '0.003≥', '---', 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0203'

    UNION ALL SELECT id, 1, 'دانسیته در 20 درجه سانتیگراد', 'g/cm3', NULL, '1.033 <', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0191'
    UNION ALL SELECT id, 2, 'درجه حرارت نمونه', 'C', NULL, '---', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0191'
    UNION ALL SELECT id, 3, 'غلظت ضد یخ', '%', NULL, '22.5 >', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0191'
    UNION ALL SELECT id, 4, 'نقطه انجماد نمونه', 'C', NULL, '-12 >', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0191'
    UNION ALL SELECT id, 5, 'نوع ممانعت کننده', NULL, NULL, '---', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0191'
    UNION ALL SELECT id, 6, 'غلظت ممانعت کننده', 'g/l', NULL, '0.3 <', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0191'
    UNION ALL SELECT id, 7, 'غلظت آهن', 'ppm', NULL, '---', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0191'
    UNION ALL SELECT id, 8, 'قلیائیت ذخیره', 'ml HCl 0.1 N', NULL, '25 <', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0191'
    UNION ALL SELECT id, 9, 'pH', NULL, NULL, '7.8-8.3', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0191'
    UNION ALL SELECT id, 10, 'مقدار ذرات معلق', 'ppm', NULL, '---', NULL, 'داخل نیروگاه' FROM main_log_sheet_types WHERE code = 'FG-PC-0191'
) AS seed_data
WHERE NOT EXISTS (SELECT 1 FROM main_log_sheet_test_definitions LIMIT 1);

CREATE TABLE IF NOT EXISTS test_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    unit VARCHAR(20)
) ENGINE=InnoDB;

INSERT IGNORE INTO test_types (id, name, unit) VALUES
    (1, 'دانسیته', 'kg/m3'),
    (2, 'ویسکوزیته', 'cSt');

-- ------------------------------------------------------------
-- Samples (indicator log) — final structure
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS samples (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sample_number VARCHAR(30) NOT NULL UNIQUE,
    sample_type_id INT NOT NULL,
    main_log_sheet_type_id INT NOT NULL,
    jalali_year SMALLINT NOT NULL,
    quantity DECIMAL(10,2) NULL,
    quantity_unit VARCHAR(20) NULL,
    sampling_date DATE NULL,
    delivery_date DATE NULL,
    sampling_location VARCHAR(150) NULL,
    referrer VARCHAR(150) NULL,
    receiver VARCHAR(150) NULL,
    status VARCHAR(50) DEFAULT 'in_progress',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sample_type_id) REFERENCES sample_types(id),
    FOREIGN KEY (main_log_sheet_type_id) REFERENCES main_log_sheet_types(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sample_number_counters (
    jalali_year SMALLINT NOT NULL PRIMARY KEY,
    last_seq INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Internal log sheets (per test type) and their results
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS internal_log_sheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_type_id INT NOT NULL,
    sheet_date DATE NOT NULL,
    title VARCHAR(150),
    FOREIGN KEY (test_type_id) REFERENCES test_types(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS test_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sample_id INT NOT NULL,
    internal_log_sheet_id INT NOT NULL,
    result_value VARCHAR(100),
    tested_date DATE NOT NULL,
    is_used_in_main_sheet BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (sample_id) REFERENCES samples(id),
    FOREIGN KEY (internal_log_sheet_id) REFERENCES internal_log_sheets(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Main log sheets (one per sample) and their results
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS main_log_sheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sample_id INT NOT NULL UNIQUE,
    compiled_date DATE,
    status ENUM('draft','final','sent') DEFAULT 'draft',
    word_file_path VARCHAR(255),
    sent_to_cmms_date DATE,
    cmms_reference_no VARCHAR(100),
    FOREIGN KEY (sample_id) REFERENCES samples(id)
) ENGINE=InnoDB;

-- IMPORTANT FIX: on a device where schema.sql previously created
-- main_log_sheet_results with the OLD columns (test_result_id),
-- "CREATE TABLE IF NOT EXISTS" below would silently do nothing
-- and leave the old, incompatible structure in place. This block
-- detects that case and rebuilds the table with the correct
-- columns (test_definition_id) before continuing.
SET @old_structure := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'main_log_sheet_results'
      AND COLUMN_NAME = 'test_result_id'
);
SET @drop_sql := IF(@old_structure > 0, 'DROP TABLE main_log_sheet_results', 'SELECT 1');
PREPARE stmt FROM @drop_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS main_log_sheet_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    main_log_sheet_id INT NOT NULL,
    test_definition_id INT NOT NULL,
    result_value VARCHAR(100),
    FOREIGN KEY (main_log_sheet_id) REFERENCES main_log_sheets(id),
    FOREIGN KEY (test_definition_id) REFERENCES main_log_sheet_test_definitions(id),
    UNIQUE KEY uniq_sheet_test (main_log_sheet_id, test_definition_id)
) ENGINE=InnoDB;
