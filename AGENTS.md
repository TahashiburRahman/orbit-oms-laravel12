# Agent instructions

## Scope and authority

This is an existing Laravel 12 office management system. Inspect source and the user's request before editing. Extend it in place; this document does not authorize unrelated rewrites, destructive resets or external publication.

The repository is reusable across companies. Keep real identities, credentials, employee rosters, private snapshots and personal workstation paths out of source, fixtures, documentation and AI prompts. Installation accounts and business data are not application-template data.

## Stack and runtime

- PHP 8.2+, Laravel 12, Blade, Tailwind CSS 4, Vite and JavaScript.
- MySQL/MariaDB application database configured through ignored `.env`; SQLite `:memory:` tests forced by `phpunit.xml`.
- Current operational defaults are Asia/Dhaka and BDT, not generic runtime timezone/currency support.
- Native forms carry CSRF tokens. Scoped suggestions use native fetch; do not restore unused Axios/starter code.
- On Windows use `npm.cmd` if PowerShell blocks npm. `scripts/serve.cmd` supports XAMPP PHP; other environments may use `php artisan serve`.
- Respect an existing development server. Scheduled releases need the scheduler.

## Authorization

Default workflow slugs: `admin`, `operations`, `marketing`, `graphics`, `developer`. Marketing is Digital Marketing. Renamable labels differ from stable authorization/workflow identifiers.

`users.team_id` is primary/default dashboard; `team_user` stores unique memberships. Ordinary access uses user helpers, policies, `visibleTo` scopes, ownership, assignments, project sharing and explicit payroll authorization. Navigation is not authorization; dashboard selection must not grant ordinary users extra rights.

Superadmin is an explicit protected account role, separate from ordinary Admin membership. Only authorized superadmin management may change it. Do not accept privilege fields from ordinary updates/imports. Preserve self/last-superadmin safeguards, last-active-Admin rules and session revocation. Inactive accounts must not retain superadmin access.

Management actions must retain dependency checks, immutable audit and approved/paid payroll history. Never replace deletion guards with disabled foreign keys, broad cascades or arbitrary table deletion.

## Modules and workflows

- Clients, multiple domains, projects, members, general tasks, assignments, comments and history.
- Developer/Operations reports and technical request -> Developer submission -> Operations verification.
- Leads/updates/referrals, qualified-lead conversion, campaigns/posts, briefs, private deliverables, review and revisions.
- Client queries, employee profiles, monthly payroll, approval, append-only corrections, manually recorded payment, payslips and CSV export.
- Team-targeted scheduled releases, owned read/unread notifications and append-only audit.

Automatic domain/advertising/courier/social publishing/salary payments, commerce orders/products/inventory/fulfillment, and tenant isolation are new scope unless explicitly implemented.

All default teams can create clients separately or inline with general tasks. Ordinary non-manager tasks are self-assigned in the primary team; managers retain assignment controls. Developer/Operations forms share client fields. Suggestions are scoped. Sites are unique per client/lowercase hostname; task URL/name text remains a snapshot. Keep client/project/site consistency and transactional rollback.

Developer classifications: Support, Site Delivery, Landing Page, Meetings, Theme Customize, Pixel, Courier. Classification does not change technical type. Technical Completed means submission, not self-verification. Preserve existing configuration/handover notes. New structured entries enforce required fields; historical assignments may retain missing/unknown values without fabricated backfills.

Deal status is separate from operational status and qualification. Deal Approved does not create a Client. Explicit qualified conversion is transactional and repeat-safe. Ordinary referral decisions require authorized ownership/assignment, not just membership. Protect converted history.

Read [work records](docs/team-work-records.md) and [client entry](docs/client-task-entry.md) before changing these workflows.

## Accounts and secrets

- Email login is normalized. No default owner or password belongs in migrations/seeds/docs.
- Provision owners through `php artisan oms:superadmin`; use hidden confirmed password input, never password arguments/output.
- Password changes are optional. Do not restore a first-login gate. Keep `must_change_password` compatibility data unless a specific migration removes it safely.
- Normal changes verify current password and require a different confirmed new password of at least 12 characters.
- OTP recovery hashes expiring single-use codes, limits attempts/resends, binds browser/email, invalidates pending codes on password changes, revokes sessions and does not auto-login.
- `OtpMailReadiness` rejects incomplete configuration and log/array mailers. Fake-mail tests do not prove delivery.
- Preserve no-store auth forms, CSRF, stale-form recovery and sanitized errors. Never print passwords, hashes, OTPs, cookies, keys or original credential-bearing exceptions.
- Private imports normalize email/string phones transactionally. Preserve changed passwords, extra memberships and HR values. Profile placeholders are not actual salary/joining data.

## Data preservation

Preserve existing data, independent edits, keys and private files unless the user explicitly authorizes a defined change. A one-time account cleanup does not authorize destructive startup/deployment behavior.

Never use `migrate:fresh`, `db:wipe`, destructive seeds or resettable tests against a real installation. Inspect state first. Use forward migrations, private backups and disposable rehearsals for data changes. Do not remove applied migrations as dead code.

Money uses integer poisha/decimal storage, not floating point. Protect approved originals, paid records, corrections and audit model/database guards. Keep private downloads checked and outside public storage.

`.runtime`, env files, backups, databases, uploads, logs and sessions are local/private. Ignore rules do not sanitize existing Git history. Inspect repository state before publication without exposing environment contents.

Remove only demonstrably unused code. Keep active/dynamic templates, relations, styles, compatibility fields, migrations and useful tooling. Continue routine authorized implementation/checks without repeated phase approvals.

## Frontend

Use shared components and theme tokens. Light/Dark/System initializes before styles, persists/synchronizes in browser storage and follows device changes in System. Print stays light. Preserve responsive navigation, focus, reduced motion, labeled controls and confirmations. Counts/progress use real accessible data.

Read [frontend conventions](docs/frontend-redesign.md). Keep implementation details out of product choices. Never rewrite user data to satisfy UI copy style.

## Source map

- `routes/web.php` and marketing/payroll/experience modules: routes and application boundaries.
- `app/Models`, `app/Policies`: relationships, scopes and permissions.
- `app/Http/Controllers`: validated CRUD and transitions; TeamWorkController/DealRecordController handle reports/referrals.
- `app/Support`: audit, exact money, imports, notifications, recovery, releases and inline task clients.
- `app/Rules/DomainOrUrl.php`: stored domain/HTTP validation without fetching.
- `resources/views`, `resources/css`, `resources/js`: shared Blade UI and interactions.
- `database/migrations`: complete forward schema/history.
- `tests/Feature`: functional, access, workflow, import, file, money, recovery and HTML regressions.

## Verification

```sh
php artisan test --compact
npm run build
php artisan view:cache
php artisan oms:check
php artisan migrate:status
php artisan oms:verify-dashboard-access
```

Reconfirm SQLite isolation when changing test configuration. Inspect MySQL separately. Dashboard verification uses read-only internal requests and must not mutate credentials/business records. Use synthetic fixtures for browser tests that write data.

Run positive and negative access checks, then appropriate tests/build/Blade checks. Report commands/results actually obtained, data effects and external dependencies. Do not claim browser/SMTP/exhaustive security checks from unrelated tests. Avoid permanent account/test-count snapshots in reusable docs.

## Company adaptation and external planning

Read [the adaptation guide](docs/company-adaptation.md). Define workflows, permissions, terminology and migration before implementing company-specific changes. Consider labels, stable identifiers, authorization and specialized forms together.

When asked for an external-AI customization prompt, describe the baseline without claiming workspace access. Ask essential missing questions, then return one self-contained implementation prompt covering objective, screens, team/action/record access, workflow/data changes, preservation, configuration, phases, acceptance and handoff. State unresolved assumptions and include no real employees or secrets. This planning protocol does not interrupt a repository implementation request.
