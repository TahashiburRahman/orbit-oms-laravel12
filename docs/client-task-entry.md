# Client entry while adding tasks

## User flow

All five teams have **Add task** on the task list and task creation on their dashboard. Type **Client name** to see accessible existing clients. Choose a suggestion using the mouse or Arrow keys and Enter. Its domain field becomes a dropdown of saved domains, with **Add a new domain or website** available. A new name creates a client when the task saves. Client name remains optional so internal tasks and existing reports can still be recorded without inventing a client.

Developer and Operations routine forms use the same client/domain controls. Their new structured records still require a saved or new domain. General tasks allow the domain to be blank. Saved domains may have optional website names. Each client can have multiple domains; names alone never overwrite another client's contact details.

**Clients > Add client** remains available to all five teams, with optional initial domain/website name. Admin/Operations can append domains from Edit client. Other members can add another domain while adding a task for a client they can access. The client detail page lists all saved domains.

Marketing deal updates and Bring a Client are Lead workflows, separate from Tasks. Their qualification and explicit conversion behavior is preserved; a deal approval does not silently create a Client.

## Access and consistency

- Suggestions return only client ID/name/company and domain choices under `Client::visibleTo`, with no-store responses. Client ownership, project sharing and explicitly linked accessible tasks grant the existing client context. Unrelated private clients remain hidden.
- All five active teams can create clients. Client contact editing, reassignment and technical request creation retain Admin/Operations controls. Creating a client does not grant payroll or other team access.
- Non-manager general tasks are assigned by the server to their creator in the primary team. Forged team, assignee, technical type and verification state are rejected. Admin/Operations retain existing assignment forms and checks.
- Inline creation and task saving share a database transaction. Failed task, project or site validation rolls back new client/site/audit rows.
- A selected site must belong to the selected authorized client. A task's project and client must agree. The server derives stored domain/website values when a saved site is selected.
- A typed exact name reuses a single accessible match, ignoring case and surrounding whitespace. Ambiguous matches require selecting a specific suggestion. Private clients are never merged by name or disclosed through suggestions. Separate client creation still permits legitimate clients with the same name.
- Domain identity is the lowercase hostname without a trailing dot, unique per client. HTTP/HTTPS and URL paths on that hostname share a saved domain. Historical task URL text and website labels remain snapshots; an unchanged site selection preserves them. No website is fetched.

## Schema and preservation

`2026_11_19_001000_add_client_websites.php` adds `client_websites` with a client foreign key and unique client/domain key, plus nullable `tasks.client_website_id`. Task `client_id`, `client_name`, `domain_name` and `website_name` remain.

The backfill uses valid domains on tasks with explicit client IDs only. It links duplicate hostnames to one site while preserving original task text, status and timestamps. Invalid historical strings and unlinked names remain intact. It does not infer client identity from historical names or convert leads.

Before applying to a populated installation, take a private database backup including triggers. Rehearse the migration on disposable MySQL with duplicate hosts, historical text/status, invalid legacy domains and unlinked names. Compare established record values before and after; do not infer clients from names alone.

## Verification

- Run `php artisan test --compact --filter=InlineTaskClientTest` for creation, scoped suggestions, domain reuse, record restrictions, assignment restrictions, ambiguity, project integrity, rollback and historical snapshots.
- Run the complete isolated suite, production Vite build and Blade compilation after a change.
- Check browser keyboard selection, domain dropdown switching, a second domain, clearing a previous client, mobile Light/Dark layout, labels, overflow and JavaScript/HTTP errors. Use synthetic data in a separate database for browser writes.
- Verify MySQL connectivity and migration status separately from tests.

Report checks actually performed. These tests do not establish exhaustive security/visual coverage or SMTP delivery.
