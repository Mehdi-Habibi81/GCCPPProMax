GCCPPProMax

A modular PHP/MySQL laboratory management and authentication system

GCCPPProMax is a PHP/MySQL web application that combines a secure authentication system with a Fuel & Oil Laboratory Management System.

The project is designed to provide a practical platform for managing laboratory samples, test results, laboratory logs, user accounts, and security-related workflows.

«Project Status: Active Development»

---

✨ Features

🔐 Authentication & Security

- User registration
- Secure login/logout
- Session-based authentication
- Password hashing
- Password reset workflow
- Google Authenticator / TOTP two-factor authentication
- Account security management
- Login activity logging
- Application activity logging
- Secure handling of authentication-related secrets

🧪 Fuel & Oil Laboratory

The laboratory module provides functionality for managing fuel and oil laboratory workflows, including:

- Sample registration
- Sample types
- Sample numbering
- Laboratory test definitions
- Test results
- Internal laboratory log sheets
- Main laboratory log sheets
- Laboratory result tracking
- Laboratory activity auditing
- Jalali/Persian date support

🌐 Application

- PHP/MySQL architecture
- Apache support
- Clean URLs
- Persian/English localization
- Responsive web interface
- Composer dependency management
- Modular project structure

---

🛠️ Technology Stack

Technology| Purpose
PHP 8.1+| Backend application
MySQL / MariaDB| Database
Apache| Web server
HTML5 / CSS3| Frontend
Composer| Dependency management
Google Authenticator / TOTP| Two-factor authentication
Morilog Jalali| Persian/Jalali calendar
PHPWord| Word document generation
Apache mod_rewrite| Clean URLs

Composer Packages

The project uses PHP packages including:

- "pragmarx/google2fa"
- "phpoffice/phpword"
- "morilog/jalali"

---

📁 Project Structure

GCCPPProMax/
│
├── auth/
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── forgot.php
│   ├── reset.php
│   ├── 2fa_setup.php
│   ├── 2fa_verify.php
│   ├── security.php
│   ├── install.php
│   └── ...
│
├── lab/
│   └── Fuel & Oil Laboratory Module
│
├── assets/
│   └── Static assets
│
├── dashboard.php
├── index.html
│
├── composer.json
├── composer.lock
├── schema.sql
├── .htaccess
├── .gitignore
└── README.md

---

🔐 Authentication Architecture

The authentication system is implemented inside the "auth/" directory.

The general authentication flow is:

                    ┌───────────────┐
                    │     User      │
                    └───────┬───────┘
                            │
                   ┌────────▼────────┐
                   │ Login / Register│
                   └────────┬────────┘
                            │
                    ┌───────▼───────┐
                    │ Credentials    │
                    │ Verification   │
                    └───────┬───────┘
                            │
                       ┌────▼────┐
                       │  2FA?   │
                       └────┬────┘
                            │
                   ┌────────▼────────┐
                   │ Authenticated   │
                   │ Session         │
                   └────────┬────────┘
                            │
                    ┌───────▼────────┐
                    │    Dashboard   │
                    └───────┬────────┘
                            │
             ┌──────────────┴──────────────┐
             │                             │
      ┌──────▼──────┐              ┌───────▼──────┐
      │   Security  │              │   Lab System │
      └─────────────┘              └──────────────┘

---

🧪 Laboratory Module

The "lab/" directory contains the Fuel & Oil Laboratory functionality.

The system is designed around several related database entities.

Examples include:

sample_types
        │
        ▼
     samples
        │
        ▼
  test_results
        │
        ▼
laboratory reports

Additional entities support:

- Test definitions
- Sample number counters
- Internal laboratory logs
- Main laboratory logs
- Main laboratory results
- Activity auditing

This structure allows laboratory data to remain organized and connected rather than being stored as isolated records.

---

🗄️ Database

The project uses MySQL/MariaDB as its relational database.

The repository contains a consolidated database schema:

schema.sql

The database is responsible for storing:

- User accounts
- Authentication information
- Laboratory samples
- Sample types
- Test definitions
- Test results
- Laboratory logs
- Activity records
- Login records

---

🚀 Installation

Requirements

Before installing GCCPPProMax, make sure you have:

- PHP 8.1 or newer
- Apache
- MySQL or MariaDB
- Composer
- Apache "mod_rewrite"
- Required PHP extensions
- A configured PHP/MySQL environment

The project can be developed using either:

- LAMP on Linux
- WAMP on Windows

---

1. Clone the Repository

git clone https://github.com/Mehdi-Habibi81/GCCPPProMax.git

Then:

cd GCCPPProMax

---

2. Install Composer Dependencies

composer install

For a production-oriented installation:

composer install --no-dev

---

3. Configure Apache

The project uses ".htaccess" and extensionless URLs.

On Ubuntu/Debian-based systems:

sudo a2enmod rewrite
sudo systemctl restart apache2

Make sure Apache allows ".htaccess" overrides for the project directory.

The application uses routes such as:

/auth/login
/auth/register
/dashboard

instead of:

/auth/login.php
/auth/register.php
/dashboard.php

---

4. Configure the Database

Create a MySQL/MariaDB database and a dedicated application database user.

Then import the database schema:

mysql -u YOUR_DB_USER -p YOUR_DATABASE < schema.sql

Alternatively, use the application's installation system where appropriate.

---

5. Run the Authentication Installer

Open:

/auth/install

and provide the required database connection information.

The installer is responsible for configuring the authentication portion of the application.

---

6. Configure Local Secrets

The following files contain local configuration or sensitive information and should not be committed to the repository:

auth/config.php
auth/.auth_encryption_key
auth/.installed

Never expose database credentials or encryption keys through public source control.

---

🔒 Security

Security is an important part of GCCPPProMax.

The project includes security mechanisms such as:

- Password hashing
- Prepared SQL statements
- Session-based authentication
- TOTP two-factor authentication
- Authentication secret protection
- Login logging
- Activity logging
- Output escaping
- Clean URL routing

Production Security Checklist

Before deploying the application publicly:

- [ ] Enable HTTPS
- [ ] Use a dedicated database user
- [ ] Do not use the MySQL "root" account for the application
- [ ] Keep database credentials outside source control
- [ ] Keep encryption keys outside source control
- [ ] Restrict access to installation functionality
- [ ] Disable unnecessary diagnostic functionality
- [ ] Keep PHP and database software updated
- [ ] Keep Composer dependencies updated
- [ ] Review Apache permissions and ".htaccess" configuration

---

🌐 Localization

The application includes support for multiple languages, including:

- 🇬🇧 English
- 🇮🇷 Persian

The localization system is designed so that application messages and interface text can be managed separately from the main application logic.

---

📅 Jalali Calendar

The laboratory system supports Persian/Jalali dates through:

morilog/jalali

This is useful for laboratory environments where records and reports are maintained according to the Persian calendar.

---

📄 Document Generation

The project includes support for generating Microsoft Word documents using:

PHPWord

This can be used for producing laboratory-related documents and reports.

---

🧑‍💻 Development

GCCPPProMax follows a modular structure in which authentication and laboratory functionality are separated into different areas of the application.

Authentication
      │
      ▼
   Session
      │
      ▼
  Dashboard
      │
      ▼
Laboratory Module
      │
      ├── Samples
      ├── Tests
      ├── Results
      ├── Logs
      └── Reports

The project is suitable for further development into a larger laboratory information management system.

---

🛣️ Roadmap

Potential future improvements include:

- [ ] Expanded laboratory reporting
- [ ] More advanced sample tracking
- [ ] Role-based access control
- [ ] Additional laboratory workflows
- [ ] REST API
- [ ] Automated testing
- [ ] Improved error handling
- [ ] Better administrative dashboard
- [ ] Advanced reporting and analytics
- [ ] Production deployment documentation
- [ ] CI/CD integration

---

🤝 Contributing

Contributions and suggestions are welcome.

When contributing:

1. Create a dedicated branch.
2. Keep commits focused.
3. Follow the existing project structure.
4. Do not commit credentials or private keys.
5. Test authentication functionality after security-related changes.
6. Test laboratory workflows after database-related changes.
7. Document significant architectural changes.

---

📜 License

No explicit open-source license is currently specified for this repository.

Unless a license is added, the source code should not be assumed to be freely reusable, modified, or redistributed under an open-source license.

---

👨‍💻 Author

Mehdi Habibi

GCCPPProMax is a personal software project focused on combining secure PHP application development with practical Fuel & Oil Laboratory workflow management.

---

🇮🇷 نسخه فارسی

GCCPPProMax

یک سامانه ماژولار مدیریت آزمایشگاه و احراز هویت مبتنی بر PHP و MySQL

GCCPPProMax یک برنامه تحت وب مبتنی بر PHP و MySQL است که یک سیستم احراز هویت امن را با یک سامانه مدیریت آزمایشگاه سوخت و روغن ترکیب می‌کند.

هدف پروژه ایجاد بستری برای مدیریت کاربران، نمونه‌های آزمایشگاهی، نتایج آزمایش‌ها، گزارش‌ها، لاگ‌های آزمایشگاه و امکانات امنیتی است.

«وضعیت پروژه: در حال توسعه»

---

✨ امکانات

🔐 احراز هویت و امنیت

- ثبت‌نام کاربران
- ورود و خروج امن
- مدیریت Session
- Hash کردن رمز عبور
- بازیابی رمز عبور
- احراز هویت دومرحله‌ای با Google Authenticator / TOTP
- مدیریت تنظیمات امنیتی حساب
- ثبت فعالیت‌های ورود
- ثبت فعالیت‌های سیستم
- مدیریت امن اطلاعات حساس احراز هویت

🧪 آزمایشگاه سوخت و روغن

ماژول آزمایشگاه برای مدیریت فرآیندهای مربوط به نمونه‌های سوخت و روغن طراحی شده است.

امکانات این بخش شامل:

- ثبت نمونه
- مدیریت انواع نمونه
- شماره‌گذاری نمونه‌ها
- تعریف آزمایش‌ها
- ثبت نتایج آزمایش
- لاگ‌های داخلی آزمایشگاه
- لاگ‌های اصلی آزمایشگاه
- مدیریت نتایج
- ثبت فعالیت‌های آزمایشگاهی
- پشتیبانی از تاریخ شمسی

🌐 امکانات کلی

- معماری PHP/MySQL
- پشتیبانی از Apache
- URLهای تمیز
- پشتیبانی از زبان فارسی و انگلیسی
- رابط کاربری وب
- مدیریت وابستگی‌ها با Composer
- ساختار ماژولار

---

🛠️ تکنولوژی‌های استفاده‌شده

تکنولوژی| کاربرد
PHP 8.1+| Backend
MySQL / MariaDB| پایگاه داده
Apache| Web Server
HTML5 / CSS3| Frontend
Composer| مدیریت وابستگی‌ها
Google Authenticator / TOTP| احراز هویت دومرحله‌ای
Morilog Jalali| تاریخ شمسی
PHPWord| تولید فایل Word
Apache mod_rewrite| URLهای تمیز

---

📁 ساختار پروژه

GCCPPProMax/
│
├── auth/
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── forgot.php
│   ├── reset.php
│   ├── 2fa_setup.php
│   ├── 2fa_verify.php
│   ├── security.php
│   ├── install.php
│   └── ...
│
├── lab/
│   └── ماژول آزمایشگاه سوخت و روغن
│
├── assets/
│   └── فایل‌های استاتیک
│
├── dashboard.php
├── index.html
│
├── composer.json
├── composer.lock
├── schema.sql
├── .htaccess
├── .gitignore
└── README.md

---

🔐 معماری احراز هویت

سیستم احراز هویت در پوشه "auth/" قرار دارد.

روند کلی احراز هویت:

                    ┌───────────────┐
                    │     کاربر     │
                    └───────┬───────┘
                            │
                   ┌────────▼────────┐
                   │ ثبت‌نام / ورود │
                   └────────┬────────┘
                            │
                    ┌───────▼───────┐
                    │ بررسی اطلاعات │
                    │   کاربری      │
                    └───────┬───────┘
                            │
                       ┌────▼────┐
                       │  2FA؟   │
                       └────┬────┘
                            │
                   ┌────────▼────────┐
                   │ ایجاد Session   │
                   │ احراز هویت‌شده │
                   └────────┬────────┘
                            │
                    ┌───────▼────────┐
                    │    داشبورد     │
                    └───────┬────────┘
                            │
             ┌──────────────┴──────────────┐
             │                             │
      ┌──────▼──────┐              ┌───────▼──────┐
      │   امنیت     │              │   آزمایشگاه  │
      └─────────────┘              └──────────────┘

---

🧪 ماژول آزمایشگاه

پوشه "lab/" شامل قابلیت‌های مربوط به مدیریت آزمایشگاه سوخت و روغن است.

ساختار اطلاعات آزمایشگاه شامل موجودیت‌هایی مانند:

sample_types
      │
      ▼
   samples
      │
      ▼
test_results
      │
      ▼
گزارش‌های آزمایشگاهی

همچنین سیستم از موارد زیر پشتیبانی می‌کند:

- تعریف آزمایش‌ها
- شمارنده شماره نمونه
- لاگ‌های داخلی آزمایشگاه
- لاگ‌های اصلی آزمایشگاه
- نتایج آزمایشگاه
- ثبت فعالیت‌ها

---

🗄️ پایگاه داده

GCCPPProMax از MySQL/MariaDB به‌عنوان پایگاه داده رابطه‌ای استفاده می‌کند.

فایل اصلی Schema پروژه:

schema.sql

این پایگاه داده اطلاعاتی مانند موارد زیر را مدیریت می‌کند:

- کاربران
- اطلاعات احراز هویت
- نمونه‌های آزمایشگاهی
- انواع نمونه
- آزمایش‌ها
- نتایج آزمایش‌ها
- لاگ‌های آزمایشگاه
- فعالیت‌های سیستم
- سوابق ورود

---

🚀 نصب و راه‌اندازی

پیش‌نیازها

قبل از نصب GCCPPProMax باید موارد زیر را داشته باشید:

- PHP 8.1 یا بالاتر
- Apache
- MySQL یا MariaDB
- Composer
- Apache "mod_rewrite"
- Extensionهای موردنیاز PHP

پروژه را می‌توان در محیط‌های زیر اجرا و توسعه داد:

- LAMP در Linux
- WAMP در Windows

---

1. دریافت پروژه

git clone https://github.com/Mehdi-Habibi81/GCCPPProMax.git

سپس:

cd GCCPPProMax

---

2. نصب وابستگی‌ها

composer install

برای محیط Production:

composer install --no-dev

---

3. تنظیم Apache

پروژه از ".htaccess" و URLهای بدون پسوند استفاده می‌کند.

در Ubuntu/Debian:

sudo a2enmod rewrite
sudo systemctl restart apache2

همچنین Apache باید اجازه استفاده از ".htaccess" را داشته باشد.

برای مثال مسیرهای پروژه به شکل زیر هستند:

/auth/login
/auth/register
/dashboard

و نه:

/auth/login.php
/auth/register.php
/dashboard.php

---

4. تنظیم پایگاه داده

یک Database اختصاصی برای پروژه ایجاد کنید و ترجیحاً از یک کاربر اختصاصی MySQL/MariaDB استفاده کنید.

سپس Schema را وارد کنید:

mysql -u YOUR_DB_USER -p YOUR_DATABASE < schema.sql

---

5. اجرای Installer

صفحه نصب احراز هویت:

/auth/install

اطلاعات اتصال به Database را وارد کنید.

---

6. اطلاعات حساس

فایل‌های زیر نباید در Repository عمومی قرار بگیرند:

auth/config.php
auth/.auth_encryption_key
auth/.installed

اطلاعاتی مانند:

- رمز Database
- کلیدهای رمزنگاری
- اطلاعات SMTP
- Secretهای احراز هویت

باید خارج از Source Control نگهداری شوند.

---

🔒 امنیت

امنیت یکی از بخش‌های مهم معماری GCCPPProMax است.

سیستم شامل مواردی مانند:

- Hash کردن رمز عبور
- Prepared Statements
- Session Authentication
- احراز هویت دومرحله‌ای TOTP
- محافظت از Secretهای امنیتی
- ثبت Loginها
- ثبت Activityها
- Escape کردن خروجی‌ها
- URLهای تمیز

است.

چک‌لیست Production

قبل از انتشار عمومی:

- [ ] فعال‌سازی HTTPS
- [ ] استفاده از کاربر اختصاصی Database
- [ ] عدم استفاده از MySQL "root"
- [ ] خارج نگه داشتن رمز Database از Git
- [ ] خارج نگه داشتن Encryption Key از Git
- [ ] محدود کردن دسترسی به Installer
- [ ] حذف قابلیت‌های Diagnostic غیرضروری
- [ ] به‌روزرسانی PHP
- [ ] به‌روزرسانی MySQL/MariaDB
- [ ] به‌روزرسانی Composer Dependencies
- [ ] بررسی Permissionهای Apache

---

🌐 چندزبانه بودن

پروژه از زبان‌های زیر پشتیبانی می‌کند:

- 🇬🇧 انگلیسی
- 🇮🇷 فارسی

سیستم Localization امکان جدا کردن متن‌های رابط کاربری از منطق اصلی برنامه را فراهم می‌کند.

---

📅 تقویم شمسی

برای مدیریت تاریخ‌های شمسی از کتابخانه:

morilog/jalali

استفاده شده است.

این قابلیت برای محیط‌هایی که ثبت سوابق بر اساس تقویم فارسی انجام می‌شود، کاربرد دارد.

---

📄 تولید اسناد

پروژه از PHPWord برای تولید فایل‌های Word پشتیبانی می‌کند.

این قابلیت می‌تواند برای تولید گزارش‌ها و اسناد آزمایشگاهی مورد استفاده قرار گیرد.

---

🧑‍💻 توسعه

ساختار پروژه به‌صورت ماژولار طراحی شده است.

احراز هویت
     │
     ▼
  Session
     │
     ▼
 داشبورد
     │
     ▼
ماژول آزمایشگاه
     │
     ├── نمونه‌ها
     ├── آزمایش‌ها
     ├── نتایج
     ├── لاگ‌ها
     └── گزارش‌ها

این ساختار امکان توسعه پروژه به یک سیستم جامع مدیریت اطلاعات آزمایشگاه (LIMS) را فراهم می‌کند.

---

🛣️ مسیر توسعه آینده

برنامه‌های احتمالی آینده:

- [ ] توسعه سیستم گزارش‌گیری آزمایشگاه
- [ ] مدیریت پیشرفته نمونه‌ها
- [ ] Role-Based Access Control
- [ ] توسعه Workflowهای آزمایشگاه
- [ ] REST API
- [ ] تست‌های خودکار
- [ ] مدیریت بهتر خطاها
- [ ] داشبورد مدیریتی پیشرفته
- [ ] گزارش‌ها و تحلیل‌های آماری
- [ ] مستندات کامل Deployment
- [ ] CI/CD

---

🤝 مشارکت

پیشنهادها و مشارکت‌های توسعه‌ای مورد استقبال هستند.

هنگام مشارکت:

1. برای تغییرات خود Branch جداگانه ایجاد کنید.
2. Commitها را متمرکز و واضح نگه دارید.
3. ساختار فعلی پروژه را حفظ کنید.
4. اطلاعات حساس و کلیدهای خصوصی را Commit نکنید.
5. تغییرات مربوط به Authentication را تست کنید.
6. تغییرات مربوط به Database و Laboratory را تست کنید.
7. تغییرات معماری مهم را مستندسازی کنید.

---

📜 License

در حال حاضر License متن‌باز مشخصی برای Repository تعریف نشده است.

بنابراین تا زمانی که License مشخصی به پروژه اضافه نشده، نباید Source Code را آزادانه قابل استفاده، تغییر یا بازتوزیع تحت یک License متن‌باز در نظر گرفت.

---

👨‍💻 توسعه‌دهنده

Mehdi Habibi

GCCPPProMax یک پروژه نرم‌افزاری با تمرکز بر توسعه برنامه‌های امن PHP و مدیریت فرآیندهای آزمایشگاه سوخت و روغن است.