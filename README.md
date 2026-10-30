# Matajer-Pro Backend

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/) [![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/) [![Sanctum](https://img.shields.io/badge/Auth-Laravel_Sanctum-FF2D20?style=for-the-badge)](https://laravel.com/docs/sanctum) [![Database](https://img.shields.io/badge/Database-MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/) [![Matajer-Pro](https://img.shields.io/badge/Matajer--Pro-SaaS-6f42c1?style=for-the-badge)](https://github.com/maichealalbotrous/matajer-pro-FrontEnd)

The Laravel backend for **Matajer-Pro**, a SaaS platform for retail operations, sales, inventory, branches, employees, subscriptions, and financial reporting.

## Backend scope visible in the public repository

- Authentication and protected API flows using Laravel Sanctum.
- Products, product variants, variant images, and inventory management.
- Branches, users, employees, roles, and access-controlled workflows.
- Invoices, invoice items, returns, exchanges, approvals, and settlements.
- Discounts, expenses, payment logs, subscription keys, and global configuration.
- Reports for settlements, inventory audits, profitable products, and low-stock alerts.
- Laravel migrations, models, controllers, validation, and feature structure.

## Technology stack

- PHP 8.2+
- Laravel 12
- Laravel Sanctum
- MySQL configuration
- Vite and Tailwind CSS assets
- Pest and PHPUnit tooling

> The public `composer.json` currently specifies Laravel `^12.0`. Older portfolio materials may mention Laravel 11; the repository dependency file is the current technical evidence for this public codebase.

## Run locally

```bash
composer install
cp .env.example .env
php artisan key:generate

# Configure the database in .env before migrating
php artisan migrate
php artisan serve
```

For frontend assets:

```bash
npm install
npm run dev
```

## Related repository

- [Matajer-Pro Frontend](https://github.com/maichealalbotrous/matajer-pro-FrontEnd)

## Project status

Completed project repository; no public production deployment is claimed here.

## Portfolio

- [Michael Alboutros on GitHub](https://github.com/maichealalbotrous)
- [Professional portfolio](https://michael-alboutros-portfolio--maichelalbotrou.replit.app/)
- [LinkedIn](https://www.linkedin.com/in/michael-alboutros-2664a12b2/)
