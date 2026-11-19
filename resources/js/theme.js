const root = document.documentElement;
const key = root.dataset.themeStorageKey || 'orbit-office-theme';
const controls = [...document.querySelectorAll('[data-theme-select]')];
const system = window.matchMedia?.('(prefers-color-scheme: dark)');
const valid = (value) => ['light', 'dark', 'system'].includes(value);
let preference = valid(root.dataset.themePreference) ? root.dataset.themePreference : 'system';

const apply = () => {
    const theme = preference === 'system' ? (system?.matches ? 'dark' : 'light') : preference;
    root.dataset.themePreference = preference;
    root.dataset.theme = theme;
    root.style.colorScheme = theme;
    controls.forEach((control) => { control.value = preference; });
};
apply();
controls.forEach((control) => control.addEventListener('change', () => {
    if (!valid(control.value)) return;
    preference = control.value;
    apply();
    try { window.localStorage.setItem(key, preference); } catch { /* Keep the selection for this page. */ }
}));
system?.addEventListener?.('change', () => { if (preference === 'system') apply(); });
window.addEventListener('storage', (event) => {
    if (event.key !== key && event.key !== null) return;
    preference = valid(event.newValue) ? event.newValue : 'system';
    apply();
});
