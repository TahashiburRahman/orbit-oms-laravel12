# Orbit Office frontend redesign

The interface retains Orbit Office branding and the existing Laravel 12, Blade, Tailwind CSS 4, and Vite stack. The design uses restrained teal accents, warm neutral surfaces, compact tables, clear form labels, and shared light and dark theme tokens. Navigation, account actions, filters, notifications, dialogs, validation, empty states, and printable views follow the same visual language.

## Application scope

This repository is an office management system. Existing features include team dashboards, tasks and development requests, clients, projects, marketing leads, campaigns and posts, creative briefs and deliverables, client queries, employee records, payroll and payslips, users and teams, software updates, notifications, activity history, authentication, and password settings. It does not contain commerce orders, products, inventory, fulfillment, or a standalone reports module. The redesign covers the features that exist instead of adding inactive commerce screens.

Role permissions, route names, CSRF protection, backend validation, scoped queries, and normal workflow actions remain authoritative. Dashboard counts and completion progress use real tasks visible to the current employee. Overdue and blocked totals link to the existing task filters. Empty datasets show useful empty states.

## Appearance and interaction

The visible Color theme selector offers Light, Dark, and System on workspace and authentication screens. System is the default. The saved choice is read before the stylesheet loads, stored in browser local storage, and synchronized across tabs. System mode responds to device theme changes. Legacy account theme values are used as a fallback. Browser storage failures still allow the current page and device preference to work. Printed documents use a light background.

The responsive sidebar, account menu, confirmation dialog, table scrolling, visible keyboard focus, reduced motion handling, and labeled controls support daily use on desktop and mobile. Font Awesome icons are reserved for navigation and recognizable actions. The favicon follows the Orbit Office mark. New interface copy avoids dash characters; existing user data and required syntax are preserved.

## Design references

These official product references informed the navigation, work queues, status presentation, filters, density, and theme behavior. They are visual and interaction references rather than dependencies or copied screen designs.

* [Shopify: viewing and organizing orders](https://help.shopify.com/en/manual/fulfillment/managing-orders/viewing-orders) informed readable operational lists and practical filtering.
* [ShipStation: view, search, and sort orders](https://help.shipstation.com/hc/en-us/articles/360025869052-View-Search-and-Sort-Orders) informed status focused queues and compact table controls.
* [Linear: new interface](https://linear.app/changelog/2024-03-20-new-linear-ui) informed restrained hierarchy, navigation, and useful content density.
* [Linear: account preferences](https://linear.app/docs/account-preferences) informed the explicit Light, Dark, and System preference.

## Verification

Run commands from the repository root. Use `npm.cmd` on this Windows installation because PowerShell may block `npm.ps1`.

```powershell
npm.cmd run build
php artisan test
php artisan view:cache
php artisan oms:check
php artisan migrate:status
```

Feature tests force an in memory SQLite database through `phpunit.xml`. They do not reset the configured MySQL database. `oms:check` and `migrate:status` inspect the configured application database without changing business records.

The existing feature suite covers scoped dashboard access and multi team navigation, page rendering, authentication, recovery controls, form CSRF protection, marketing workflows, project sharing, development handovers, payroll permissions, notifications, and audit integrity. Browser verification should check representative pages at desktop and mobile sizes in both themes, including preference persistence, menu and dialog keyboard behavior, filters, forms, and print layout. Record the commands and browser checks actually completed in the final handoff rather than assuming visual inspection from passing feature tests.
