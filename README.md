# Orbit Office OMS

An office management system built with **Laravel 12**, PHP 8.2+, Blade, Tailwind CSS 4 and Vite. It combines team workspaces, client/project tracking, employee management, payroll, creative workflows and audit history.

The source can be adapted for another company. This is a single-company application; separate companies need separate installations unless tenant isolation is explicitly implemented. No employee roster, owner account, password or production database is shipped in source.

## Features

- Protected superadmin access, accounts, multiple team memberships and team management.
- Dashboards, tasks, Developer/Operations work records, comments and technical handover verification.
- Clients/projects, inline client creation, scoped suggestions and multiple domains per client.
- Leads, referrals, qualified-lead conversion, campaigns and posts.
- Creative briefs, private deliverables, versions, review and revisions.
- Client queries, employee profiles, payroll, corrections, payslips and CSV export.
- Scheduled updates, notifications and append-only activity history.
- Email login, optional password settings, email OTP recovery and Light/Dark/System appearance.

Superadmin deletion controls respect dependencies and protected history. Referenced records must be handled through their related workflows; audit history and approved/paid payroll are not ordinary editable records.

## Requirements

PHP 8.2+ with PDO MySQL/SQLite, mbstring, OpenSSL, fileinfo, XML, curl and zip; Composer; Node.js/npm; MySQL or MariaDB. A configured SMTP sender is needed for email recovery. Windows/XAMPP is supported but not required.

## Install

From a new checkout:

```sh
composer install
npm ci
```

Create `.env` from `.env.example` if it does not already exist, then generate a key for this new installation:

```sh
cp .env.example .env
php artisan key:generate
```

In PowerShell use `Copy-Item .env.example .env` and `npm.cmd` if npm's PowerShell script is blocked. Composer may already have created `.env`. Never overwrite an existing environment file or regenerate an existing application's key.

Create an empty database and configure its connection locally:

```dotenv
APP_NAME="Your Company OMS"
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=oms
DB_USERNAME=your_database_user
DB_PASSWORD=
```

Initialize the schema, provision your own superadmin and start the application:

```sh
php artisan migrate
php artisan oms:superadmin
npm run build
php artisan oms:check
php artisan serve
```

The superadmin command prompts for account details and a hidden, confirmed password. There is no shipped login or default password. Public registration is disabled. Ordinary seeding creates no employee accounts; `php artisan oms:demo` is an explicit local/testing option using synthetic data and a hidden password prompt.

Open **http://127.0.0.1:8000/login**. On Windows, `scripts\serve.cmd` uses XAMPP PHP when available and applies local upload settings. Apache is unnecessary with `artisan serve`.

For an existing installation, back up its database/private files, inspect `php artisan migrate:status`, then apply forward migrations. Never use a destructive reset as an upgrade procedure.

## Permissions and teams

Each user has a primary team and may belong to additional teams. The primary team determines the default dashboard; switching dashboards does not grant ordinary users extra permissions.

| Default slug | Responsibilities |
| --- | --- |
| `admin` | Oversight, accounts, assignments, payroll approvals, releases and audit |
| `operations` | Clients/projects, onboarding, technical verification and authorized payroll |
| `marketing` | Leads, updates, referrals, campaigns, posts and creative review |
| `graphics` | Briefs, private drafts, deliverables and revisions |
| `developer` | Technical assignments, reports, blockers, handover and queries |

Superadmin is an explicit protected account role, separate from ordinary Admin membership. Normal policies and visibility scopes use ownership, assignment and project sharing. Payroll access for ordinary Operations members requires explicit authorization. Server checks cover lists, suggestions, downloads and write endpoints, not only navigation.

All default teams can create a client separately or while adding a task. Selecting an existing client offers saved domains and an option to add another. Non-manager general tasks are self-assigned in the primary team; managers retain assignment controls.

See [client/task entry](docs/client-task-entry.md), [team work records](docs/team-work-records.md) and [frontend conventions](docs/frontend-redesign.md).

## Scheduler and recovery email

Assignment/review notifications and recovery emails are synchronous. Scheduled release publication needs the Laravel scheduler:

```sh
php artisan schedule:work
```

For deployment, arrange `schedule:run` through the hosting scheduler. `php artisan releases:notify` processes due notifications once.

Configure SMTP host, port, scheme, credentials and sender address only in `.env`. One sender delivers OTPs to users' login emails. The example environment does not establish working email delivery. Log/array transports are rejected for recovery so codes are not written to logs. Gmail can use a Google app password; other SMTP services can also be configured.

Recovery stores hashes of expiring, single-use six-digit codes with attempt/resend limits and browser/email binding. Successful recovery revokes sessions and returns to login. Password changes remain optional through account settings; there is no first-login gate. Normal changes require the current password and a different confirmed new password of at least 12 characters.

## Imports and data

The optional import command reads a private local CSV:

```csv
name,email,phone,primary_team,additional_teams
```

Use `|` between additional teams. Run `php artisan oms:import-employees /private/path/employees.csv`; append `--verify` for verification. Its default local location is inside ignored `.runtime/imports`. Imports normalize emails and Bangladesh string phone numbers, initialize only new accounts' passwords using Laravel's hasher, and preserve changed passwords, extra memberships and existing HR values. Complete profile placeholders before real payroll use.

Do not commit real imports, database dumps, SQLite files, uploads, logs, session data, `.env` or credentials. Local account cleanup does not provision another installation: each new company should create its own environment, database and superadmin.

## Customize for another company

Start with [the adaptation guide](docs/company-adaptation.md). Define workflows, permissions, teams, terminology, timezone, currency and integrations before changing schema/screens. Team slugs identify workflows; renaming a label does not rename authorization rules. Adding a team does not automatically create a specialized report form.

Current defaults are Asia/Dhaka and BDT. Payroll uses exact integer-poisha/decimal arithmetic; changing currency requires coordinated calculations, display and validation. Domain configuration, advertising, couriers, social publishing and salary payments remain manual without a separate integration. Commerce orders/products/inventory/fulfillment are outside current scope.

## Verification

```sh
php artisan test --compact
npm run build
php artisan view:cache
php artisan oms:check
php artisan migrate:status
php artisan oms:verify-dashboard-access
```

`phpunit.xml` forces SQLite `:memory:`. Keep resettable tests separate from real databases. MySQL connectivity and migration status are checked separately. The dashboard verifier checks active accounts without changing credentials or business records; a private roster path can be supplied to verify an import.

Report actual test, browser and SMTP results for each change. Fake-mail tests do not prove live delivery; feature tests are not an exhaustive visual/security audit.

## Repository and deployment

Commit source, migrations and lockfiles. Rebuild dependencies/assets using `composer install`, `npm ci` and `npm run build`. Keep `.env.example` generic. Review staged files before publishing; ignore rules do not remove already tracked files or history. Never publish local backups to make an installation portable.

Point the web server at `public`, disable debug mode, configure HTTPS/session settings, protect private storage and run the scheduler. Preserve the existing application key during upgrades. Read [AGENTS.md](AGENTS.md) for implementation constraints and the source map.
