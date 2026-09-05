# PHP Authentication System

A PHP/MySQL authentication application with registration, password reset, session login, and optional Google Authenticator 2FA.

## Requirements

- PHP 8.1 or newer
- Apache with `mod_rewrite` and `.htaccess` overrides enabled
- MySQL or MariaDB
- Composer

## Setup

1. Install PHP dependencies:

   ```bash
   composer install --no-dev
   ```

2. Ensure Apache allows overrides for the document root and has rewrite enabled:

   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

3. Open `/auth/install` in the browser and enter the database connection details.

4. Keep `auth/config.php`, `auth/.auth_encryption_key`, and `auth/.installed` out of version control. They are generated or used locally and are ignored by Git.

5. Configure a dedicated application database user instead of using MySQL `root`.

## Security notes

- Keep the TOTP encryption key outside the database and outside public source control.
- Use HTTPS in production.
- Do not expose `phpinfo()` or database credentials publicly.
- Existing legacy password hashes created before account binding must be replaced through password reset.

## Clean URLs

The application uses extensionless routes such as `/auth/login`, `/auth/register`, and `/dashboard`. Apache must be configured to read `.htaccess` for these routes to work.
