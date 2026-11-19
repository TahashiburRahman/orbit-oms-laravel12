<script data-theme-init>
(() => {
    const root = document.documentElement;
    const key = 'orbit-office-theme';
    root.dataset.themeStorageKey = key;
    let preference = 'system';
    try {
        const saved = window.localStorage.getItem(key) ?? window.localStorage.getItem(@json('orbit-office-theme-'.auth()->id()));
        if (['light', 'dark', 'system'].includes(saved)) preference = saved;
    } catch { /* Device preference also works without browser storage. */ }
    const dark = typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const theme = preference === 'system' ? (dark ? 'dark' : 'light') : preference;
    root.dataset.themePreference = preference;
    root.dataset.theme = theme;
    root.style.colorScheme = theme;
})();
</script>
