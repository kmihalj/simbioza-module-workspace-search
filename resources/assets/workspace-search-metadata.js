/* EN: Metadata choices load only on interaction and always come from bounded ACL-aware server pages.
 * HR: Opcije metapodataka učitavaju se tek interakcijom iz ograničenih ACL-svjesnih stranica poslužitelja. */
(() => {
    'use strict';
    document.querySelectorAll('.hph-workspace-search__form').forEach((form) => {
        form.addEventListener('change', () => form.querySelector('[name="q"]')?.setCustomValidity(''));
    });
    document.querySelectorAll('[data-workspace-search-metadata-picker]').forEach((picker) => {
        const form = picker.closest('form');
        const value = picker.querySelector('[data-metadata-value]');
        const toggle = picker.querySelector('[data-metadata-toggle]');
        const search = picker.querySelector('[data-metadata-search]');
        const list = picker.querySelector('[data-metadata-list]');
        const loading = picker.querySelector('[data-metadata-loading]');
        const errorBox = picker.querySelector('[data-metadata-error]');
        const empty = picker.querySelector('[data-metadata-empty]');
        const more = picker.querySelector('[data-metadata-more]');
        const endpoint = picker.dataset.endpoint;
        const allLabel = picker.dataset.allLabel || '';
        const errorLabel = picker.dataset.errorLabel || '';
        const name = picker.dataset.filterName;
        if (!form || !value || !toggle || !list || !endpoint) return;
        const state = { loaded: false, loading: false, sequence: 0, page: 0, hasMore: false, timer: null };
        const choose = (id, label) => {
            value.value = id;
            toggle.textContent = label || allLabel;
            toggle.title = label || '';
            value.dispatchEvent(new Event('change', { bubbles: true }));
            if (window.bootstrap?.Dropdown) window.bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        };
        const option = (id, label) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.textContent = label;
            button.title = label;
            button.classList.toggle('active', value.value === id);
            button.addEventListener('click', () => choose(id, label));
            return button;
        };
        const render = (items, append) => {
            if (!append) list.replaceChildren(option('', allLabel));
            for (const item of Array.isArray(items) ? items : []) {
                const id = String(item?.id ?? '');
                if (id) list.appendChild(option(id, String(item?.label ?? id)));
            }
            empty?.toggleAttribute('hidden', list.children.length > 1);
        };
        const load = async (page, append) => {
            if (toggle.disabled) return;
            const sequence = ++state.sequence;
            state.loading = true;
            if (!append) {
                state.loaded = false;
                state.hasMore = false;
                more?.setAttribute('hidden', '');
            }
            loading?.removeAttribute('hidden');
            errorBox?.setAttribute('hidden', '');
            const url = new URL(endpoint, window.location.href);
            const fields = new FormData(form);
            for (const scope of fields.getAll('workspaces[]')) url.searchParams.append('workspaces[]', String(scope));
            url.searchParams.set('lang', String(fields.get('lang') || ''));
            url.searchParams.set('property_key', String(fields.get('property_key') || ''));
            url.searchParams.set('q', String(search?.value || '').trim());
            url.searchParams.set('page', String(page));
            try {
                const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const payload = await response.json();
                if (sequence !== state.sequence) return;
                if (!response.ok || payload?.ok !== true) throw new Error(errorLabel);
                render(payload.items, append);
                state.page = Number(payload.page || page);
                state.hasMore = payload.hasMore === true;
                state.loaded = true;
                more?.toggleAttribute('hidden', !state.hasMore);
            } catch {
                if (sequence !== state.sequence) return;
                if (!append) render([], false);
                if (errorBox) {
                    errorBox.textContent = errorLabel;
                    errorBox.removeAttribute('hidden');
                }
            } finally {
                if (sequence === state.sequence) {
                    state.loading = false;
                    loading?.setAttribute('hidden', '');
                }
            }
        };
        const first = () => { if (!state.loaded && !state.loading) load(1, false); };
        toggle.addEventListener('focus', first);
        toggle.addEventListener('click', first);
        picker.addEventListener('shown.bs.dropdown', () => { first(); search?.focus(); });
        search?.addEventListener('input', () => {
            window.clearTimeout(state.timer);
            state.timer = window.setTimeout(() => load(1, false), 250);
        });
        more?.addEventListener('click', () => {
            if (state.hasMore && !state.loading) load(state.page + 1, true);
        });
        form.addEventListener('change', (event) => {
            if (event.target?.name === 'workspaces[]' || (name === 'property_value' && event.target?.name === 'property_key')) {
                state.sequence++;
                state.loaded = false;
                state.loading = false;
                state.hasMore = false;
                more?.setAttribute('hidden', '');
                loading?.setAttribute('hidden', '');
                list.replaceChildren(option('', allLabel));
                empty?.setAttribute('hidden', '');
                errorBox?.setAttribute('hidden', '');
                if (name === 'property_value' && event.target?.name === 'property_key') {
                    if (search) search.value = '';
                    value.value = '';
                    toggle.textContent = allLabel;
                    toggle.title = '';
                    toggle.disabled = !String(event.target.value || '');
                }
            }
        });
    });
})();
