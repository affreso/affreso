# Contributing to Affreso

Thank you for helping. Affreso is free software for churches, and every bug report, translation and fix makes it better for congregations everywhere.

## Reporting a problem

Open an issue and include:

- your Affreso version (shown in **Administration → Settings**)
- your PHP and MySQL/MariaDB versions
- what you did, what you expected, and what happened instead

Please never paste real church members' information, passwords or your `config/config.php` into an issue. For security problems, follow [SECURITY.md](.github/SECURITY.md) instead.

## Translating

Interface text lives in `lang/`. Read [docs/TRANSLATING.md](docs/TRANSLATING.md). Native speakers are especially needed for Samoan and Tongan.

## Changing the code

1. Fork the repository and create a branch.
2. Set up a test install with sample data (the installer offers this). Never test on a real church's database.
3. Keep to the existing style: plain PHP 8.1, no Composer or build step, prepared statements for every query, `e()` for every value printed in HTML, and a permission check in every controller action.
4. Database changes go in a new file in `database/migrations/`. Don't edit old migrations.
5. Wrap new interface text in `__()` so it can be translated, and add it to `lang/TEMPLATE.php`.
6. Check new pages with a keyboard and a screen reader, or with the axe browser extension.
7. Open a pull request that explains what changed and why.

By contributing, you agree that your contribution is licensed under the GNU General Public License v3.0, the same licence as Affreso.
