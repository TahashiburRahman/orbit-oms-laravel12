document.querySelectorAll('[data-client-entry]').forEach((entry) => {
    const name = entry.querySelector('[data-client-name]');
    const clientId = entry.querySelector('[data-client-id]');
    const list = entry.querySelector('[data-client-suggestions]');
    const select = entry.querySelector('[data-client-website]');
    const domain = entry.querySelector('[data-client-domain]');
    const websiteName = entry.querySelector('[data-client-website-name]');
    const message = entry.querySelector('[data-client-message]');
    const domainRequired = entry.dataset.domainRequired === 'true';
    let websites = JSON.parse(entry.dataset.initial || 'null')?.websites || [];
    let timer, request;
    let generation = 0;
    let selectedName = clientId.value ? name.value : '';
    let active = -1;
    const close = () => {
        list.hidden = true;
        name.setAttribute('aria-expanded', 'false');
        name.removeAttribute('aria-activedescendant');
        active = -1;
    };
    const websiteChanged = () => {
        const website = websites.find((item) => String(item.id) === select.value);
        domain.hidden = domain.disabled = !!website;
        domain.required = domainRequired && !website;
        websiteName.disabled = !!website;
        domain.value = website?.domain || '';
        websiteName.value = website?.website_name || '';
    };
    select.addEventListener('change', websiteChanged);
    const choose = (client) => {
        generation++;
        request?.abort();
        clearTimeout(timer);
        name.value = selectedName = client.name;
        clientId.value = client.id;
        websites = client.websites;
        select.replaceChildren(new Option('Add a new domain or website', ''));
        websites.forEach((website) => select.add(new Option(website.website_name ? `${website.website_name} (${website.domain})` : website.domain, website.id)));
        select.hidden = select.disabled = false;
        entry.querySelector('[data-domain-label]').htmlFor = 'saved-client-domain';
        select.value = websites.length ? String(websites[0].id) : '';
        websiteChanged();
        message.textContent = websites.length ? 'Existing client selected. Choose a saved domain or add another.' : 'Existing client selected. Enter its first domain below.';
        close();
    };
    name.addEventListener('input', () => {
        clearTimeout(timer);
        request?.abort();
        const currentGeneration = ++generation;
        close();
        if (name.value !== selectedName) {
            const wasSelected = !!clientId.value;
            clientId.value = '';
            selectedName = '';
            select.hidden = select.disabled = true;
            entry.querySelector('[data-domain-label]').htmlFor = 'client-domain';
            select.value = '';
            websites = [];
            if (wasSelected) websiteChanged();
        }
        const query = name.value.trim();
        message.textContent = '';
        if (!query) return;
        timer = setTimeout(async () => {
            request = new AbortController();
            try {
                const url = new URL(entry.dataset.url, location.origin);
                url.searchParams.set('q', query);
                const response = await fetch(url, { signal: request.signal, headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) throw new Error('Search unavailable');
                const clients = await response.json();
                if (currentGeneration !== generation) return;
                list.replaceChildren();
                clients.forEach((client, index) => {
                    const option = document.createElement('div');
                    option.id = `client-option-${index}`;
                    option.role = 'option';
                    option.setAttribute('aria-selected', 'false');
                    option.textContent = `${client.name}${client.company ? ` (${client.company})` : ''} · ${client.websites.length} domains · #${client.id}`;
                    option.addEventListener('mousedown', (event) => event.preventDefault());
                    option.addEventListener('click', () => choose(client));
                    option.choose = () => choose(client);
                    list.append(option);
                });
                list.hidden = !clients.length;
                name.setAttribute('aria-expanded', String(!!clients.length));
                message.textContent = clients.length ? `${clients.length} matching clients. Select one or keep typing a new name.` : 'No matching accessible client. A new client will be created when you save.';
            } catch (error) {
                if (error.name !== 'AbortError' && currentGeneration === generation) message.textContent = 'Suggestions unavailable. You can still enter a name and save.';
            }
        }, 180);
    });
    name.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { close(); return; }
        if (list.hidden) return;
        const options = [...list.children];
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            active = (active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((option, index) => option.setAttribute('aria-selected', String(index === active)));
            name.setAttribute('aria-activedescendant', options[active].id);
            options[active].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            options[active].choose();
        } else if (event.key === 'Tab') close();
    });
    const cancelSearch = () => {
        clearTimeout(timer);
        request?.abort();
        generation++;
        close();
    };
    name.addEventListener('blur', cancelSearch);
    document.addEventListener('submit', cancelSearch);
    window.addEventListener('pagehide', cancelSearch);
});
