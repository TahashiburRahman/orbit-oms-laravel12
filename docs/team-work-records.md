# Team work records and client referrals

Task assignments, team reports and technical verification share the existing workflow while retaining separate validation and status meanings.

## Where to open the forms

- Developer dashboard: **Add Developer update**, `/work/developer/create`.
- Operations dashboard: **Add Operations update**, `/work/operations/create`.
- Marketing dashboard: **Add Marketing update**, with its list at `/marketing-updates`.
- Every team's shared navigation/dashboard: **Bring a Client**, with listing at `/referrals`. Use **Submitted by me** or the dashboard **My referrals** link for personal submissions.
- Task lists also link the forms for actual memberships. A multi-team employee can use each membership's form. A pure Admin has assignment/oversight actions, not a routine Admin form.
- Task detail: **Record work update** opens the team-specific report. Existing **Update work** and verification actions remain available. Admin/authorized Operations can use **Manage assignment**, including on structured records.

## Data model and validation

Task adds `record_type` (developer/operations/legacy), `structured_fields_required`, `reported_at`, `reported_by`, `website_name`, `domain_name`, `classification`, `client_name` and `actions`. Existing `description` stores Task details. Creation/edit timestamps remain separate from reported work time; audit entries retain prior field values and actors.

Developer Date/Time default to now in Asia/Dhaka and remain editable. Name is readonly; its server identity always comes from authentication. Client name and website name are optional. Typing a client name can create one inline or select an accessible suggestion; selecting an existing client offers its saved domains or a new domain. Domain, classification, Task details and status are required for new routine entries. Classifications are exactly Support, Site Delivery, Landing Page, Meetings, Theme Customize, Pixel and Courier.

Operations has optional inline client/website name, required saved or new domain and Task details, Actions and status. Actions may be blank before completion; new completed routine work needs Actions describing what was done. Domains accept a bare hostname or HTTP/HTTPS link and reject invalid hosts, credentials, non-HTTP schemes and whitespace. Validation does not fetch a website or execute an integration. All five teams also have general task entry with the shared client fields; see [client entry](client-task-entry.md).

Marketing updates/referrals reuse Lead: `name` stores Client name, `domain_name` is optional, and `deal_status` is In Progress / Deal Approved / Declined. `record_type` is lead/marketing_update/referral; `submitted_by` records the submitter. New records begin at lead stage `new` and do not create Clients.

## Status mapping and preserved workflows

| Displayed task state | Existing canonical state |
| --- | --- |
| Pending | `open` |
| In Progress | `in_progress`; legacy `blocked` and technical `submitted` retain their readable workflow values too |
| Completed | Ordinary `completed`, or technical `verified` |

Choosing Completed on technical work submits it to Operations. Actions supply the configuration summary and handover notes when those fields are missing; existing technical notes are preserved. It stays **In Progress (awaiting verification)** until Operations uses the existing submitted-to-verified transition and records verification results. Classification never changes technical `work_type`, and developers cannot self-verify by forging status values.

Task list filters support displayed buckets and original workflow states, plus Developer classification and record type. Dashboard task totals and completion remain based on canonical accessible Tasks. Marketing updates have a separate scoped count/list and never inflate operational task completion. Existing exports are payroll-only and were not changed.

The migration backfills task type from the assigned Developer/Operations team; other tasks stay legacy. Only unambiguous classification mappings occur: pixel -> Pixel, courier -> Courier, landing_page -> Landing Page. Domain/general/other tasks are not guessed into Support or Site Delivery. Original types, statuses, IDs, timestamps, links, comments and audit history remain unchanged. Historical reported times/submitters are not invented.

Existing/traditional assignment records retain their authorized workflow and permit missing historical structured values. New routine forms produce strictly validated structured entries; their required fields cannot be bypassed via the old task endpoint. Unknown historical statuses remain explicitly labeled legacy values and are preserved when editing other information. Assignment creation remains separate from entering a routine report.

All old leads remain type lead. No historical deal statuses or submitters are fabricated. Deal Approved does not qualify or convert a lead: authorized staff must still qualify it and invoke the existing transactional, repeat-safe conversion action.

## Referral permissions

Every active team member can submit, with no occasional-use cap. New referrals default to In Progress on the server. Owners can view their submissions and correct name/domain while In Progress. Non-Marketing referrers cannot make deal decisions or bypass this using ordinary lead edit/conversion endpoints.

Marketing decision authority uses existing Lead ownership/assignment scope, not blanket access to all referrals. Admin can use **Edit lead** to assign a referral to a marketer; that marketer can then manage it in the referral list. The original referrer retains visibility. Admin/Marketing referrers may make their own decisions only because their existing permissions independently authorize that action. Converted history stays preserved.

## Migration and verification

- Forward migration: `2026_11_19_000900_add_team_work_records.php`.
- Back up a populated installation privately, including audit triggers; do not publish backups.
- Rehearse MySQL-specific backfills with synthetic legacy client/project/task/comment/lead fixtures before applying a data transformation.
- Compare original classification, relationships, comments, stages, statuses, account hashes and record values before/after. Do not invent missing historical data.

```powershell
php artisan test --compact --filter=TeamWorkRestructureTest
php artisan test --compact
npm.cmd run build
php artisan view:cache
php artisan migrate:status
php artisan oms:check
php artisan oms:verify-dashboard-access
```

Tests use SQLite `:memory:` isolation. MySQL checks are separate. Browser checks should cover task creation, keyboard client suggestions, multiple domains, mobile layouts and both themes. Report results actually obtained; feature tests alone do not establish visual correctness.
