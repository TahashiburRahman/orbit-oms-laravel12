# Adapting the OMS for another company

Use this repository as source. Each company should have its own environment, application key, database, private storage and superadmin. Do not transfer previous-company credentials, payroll, sessions or uploads as part of a checkout.

## Define the change

Record the users/responsibilities, affected modules/screens/actions, record visibility, workflow states/approvals, required fields, relationships, migration, integrations and success checks. Separate a visual redesign from a business change. Renaming Operations to Customer Success changes a label; allowing Customer Success to approve payroll changes permissions/workflow.

## Teams and roles

Default teams provide SaaS-oriented workflows. A team's label can change while its slug continues identifying existing rules. A custom team does not automatically get a specialized report, technical-verification authority or payroll permission.

Superadmin manages the installation, but ordinary employee access still needs deliberate policies, scopes, assignment and sharing. Define each privilege by action and record scope, then test access and denial.

## Data and migration

Use forward migrations, private backups and disposable rehearsals. Preserve hashes, relationships, history, private files and exact money unless a defined change is authorized. Imports must be repeat-safe and preserve changed passwords.

If retiring a workflow, decide how historical records remain readable, are archived or are migrated. Removing a menu is not a retention strategy. Approved payroll and audit need explicit integrity rules.

## Branding, locale and money

Application name/URL and sender identity use environment configuration. Shared Blade layouts/favicon also include Orbit Office branding; change those intentionally. Preserve themes and printing.

The current domain assumes Asia/Dhaka, BDT, Dhaka report times and Bangladesh phone normalization. Payroll uses poisha arithmetic. Another locale/currency needs coordinated validation, imports, calculations, formatting, reports and migration; changing a label alone is insufficient.

## Integrations

Configure SMTP locally and test controlled delivery before relying on recovery. Other integrations are new scope unless present in code. Document variable names, keep credentials out of Git, and define timeouts, retries, safe errors, idempotency and external-failure handling.

## Acceptance and handoff

Implement in dependency order: data model, permissions, workflow, UI, migration and docs. Use synthetic isolated tests; rehearse MySQL separately. Browser checks should cover ordinary roles and superadmin, mobile/themes, keyboard interaction, validation and deletion confirmation.

Update README, AGENTS and module notes with actual behavior and report what ran. Do not embed installation-specific credentials or runtime counts in reusable documentation.

## Copyable customization request

> Continue this existing Laravel 12 OMS. Read AGENTS.md and current source before editing. My desired changes are: [describe the outcome]. Define affected teams, screens, actions, record access, states and migration. Preserve existing work, accounts, private files, exact payroll and audit unless explicitly changed. Use forward migrations and isolated tests. Implement and verify the authorized change, update documentation and report actual results and unresolved dependencies. Do not invent credentials or integrations.
