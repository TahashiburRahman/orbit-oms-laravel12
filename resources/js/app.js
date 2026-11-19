import './theme';
import './client-entry';

const sidebar = document.getElementById('sidebar');
const backdrop = document.querySelector('.sidebar-backdrop');
const menuButton = document.querySelector('[data-sidebar-toggle]');
const mobile = window.matchMedia('(max-width: 800px)');
const setSidebar = (open, restoreFocus = false) => {
    document.body.classList.toggle('sidebar-open', open);
    menuButton?.setAttribute('aria-expanded', String(open));
    menuButton?.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    if (backdrop) backdrop.hidden = !open;
    if (sidebar) sidebar.inert = mobile.matches && !open;
    if (open) sidebar?.querySelector('a')?.focus();
    if (restoreFocus) menuButton?.focus();
};
menuButton?.addEventListener('click', () => setSidebar(!document.body.classList.contains('sidebar-open')));
document.querySelectorAll('[data-sidebar-close]').forEach((button) => button.addEventListener('click', () => setSidebar(false, true)));
mobile.addEventListener('change', () => setSidebar(false));
setSidebar(false);

const popovers = [...document.querySelectorAll('details[data-popover]')];
popovers.forEach((popover) => popover.addEventListener('toggle', () => {
    if (popover.open) popovers.filter((item) => item !== popover).forEach((item) => { item.open = false; });
}));
document.addEventListener('click', (event) => popovers.forEach((popover) => {
    if (!popover.contains(event.target)) popover.open = false;
}));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        const sidebarWasOpen = document.body.classList.contains('sidebar-open');
        popovers.forEach((popover) => {
            if (popover.open) {
                popover.open = false;
                if (!sidebarWasOpen) popover.querySelector('summary')?.focus();
            }
        });
        if (sidebarWasOpen) setSidebar(false, true);
    }
    if (event.key === 'Tab' && mobile.matches && document.body.classList.contains('sidebar-open')) {
        const items = [...sidebar.querySelectorAll('a, button, select, summary')].filter((item) => item.getClientRects().length);
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }
});

const confirmDialog = document.querySelector('[data-confirm-dialog]');
document.querySelectorAll('form[data-confirm]').forEach((form) => {
    let confirmed = false;
    form.addEventListener('submit', (event) => {
        if (confirmed) return;
        if (!confirmDialog?.showModal) {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
            return;
        }
        event.preventDefault();
        const submitter = event.submitter;
        confirmDialog.querySelector('#confirm-message').textContent = form.dataset.confirm;
        confirmDialog.returnValue = '';
        confirmDialog.addEventListener('close', () => {
            if (confirmDialog.returnValue === 'confirm') {
                confirmed = true;
                form.requestSubmit(submitter);
            }
        }, { once: true });
        confirmDialog.showModal();
    });
});

// Make horizontally scrolling tables reachable from the keyboard.
const tables = [...document.querySelectorAll('.table-wrap')];
const updateTables = () => tables.forEach((table) => {
    const overflow = table.scrollWidth > table.clientWidth + 1;
    if (overflow) {
        table.tabIndex = 0;
        table.setAttribute('role', 'region');
        if (!table.hasAttribute('aria-label')) table.setAttribute('aria-label', 'Scrollable table');
    } else {
        table.removeAttribute('tabindex');
        table.removeAttribute('role');
    }
});
updateTables();
if (window.ResizeObserver) {
    const observer = new ResizeObserver(updateTables);
    tables.forEach((table) => observer.observe(table));
}

document.querySelectorAll('form[method="post" i]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented || !event.submitter) return;
        event.submitter.setAttribute('aria-busy', 'true');
    });
});
window.addEventListener('pageshow', () => document.querySelectorAll('[aria-busy="true"]').forEach((button) => button.removeAttribute('aria-busy')));
