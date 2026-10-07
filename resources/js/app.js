const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebar-overlay');

const stepType = document.querySelector('[data-step-type]');
const optionsFields = document.querySelectorAll('[data-options-field]');

if (stepType && optionsFields.length) {
    const syncOptions = () => optionsFields.forEach((field) => field.classList.toggle('hidden', stepType.value !== 'choice'));

    stepType.addEventListener('change', syncOptions);
    syncOptions();
}

document.querySelectorAll('[data-multi-image-input]').forEach((input) => {
    input.addEventListener('change', () => {
        const box = document.querySelector('[data-multi-preview]');

        if (!box) {
            return;
        }

        box.replaceChildren(
            ...[...input.files].map((file) => {
                const image = document.createElement('img');
                image.src = URL.createObjectURL(file);
                image.alt = file.name;
                image.title = file.name;
                image.className = 'aspect-[4/3] w-full rounded-lg border border-slate-200 bg-white object-contain';

                return image;
            }),
        );
    });
});

if (sidebar && overlay) {
    const setOpen = (open) => {
        sidebar.classList.toggle('-translate-x-full', !open);
        overlay.classList.toggle('hidden', !open);
    };

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => setOpen(sidebar.classList.contains('-translate-x-full')));
    });
}

const webhookModal = document.getElementById('webhook-modal');

if (webhookModal) {
    const part = (name) => webhookModal.querySelector(`[data-modal-${name}]`);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    const showIcon = (name) => {
        webhookModal.querySelectorAll('[data-modal-icon]').forEach((icon) => {
            icon.classList.toggle('hidden', icon.dataset.modalIcon !== name);
        });
    };

    const showResult = (data) => {
        showIcon(data.ok ? 'ok' : 'error');
        part('title').textContent = data.title;
        part('loading').classList.add('hidden');
        part('result').classList.remove('hidden');
        part('message').textContent = data.message;

        const hint = part('hint');
        hint.textContent = data.hint ?? '';
        hint.classList.toggle('hidden', !data.hint);

        part('rows').replaceChildren(
            ...(data.rows ?? []).map((row) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'flex justify-between gap-4 py-2';

                const label = document.createElement('dt');
                label.className = 'text-slate-500';
                label.textContent = row.label;

                const value = document.createElement('dd');
                value.className = 'break-all text-right font-medium text-slate-900';
                value.textContent = row.value;

                wrapper.append(label, value);

                return wrapper;
            }),
        );
    };

    document.querySelectorAll('[data-webhook-action]').forEach((button) => {
        button.addEventListener('click', async () => {
            showIcon('loading');
            part('title').textContent = button.dataset.title;
            part('result').classList.add('hidden');
            part('loading').classList.remove('hidden');
            webhookModal.showModal();

            try {
                const response = await fetch(button.dataset.url, {
                    method: button.dataset.method,
                    headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error(`The server answered with status ${response.status}.`);
                }

                showResult(await response.json());
            } catch (error) {
                showResult({ ok: false, title: 'Request failed', message: error.message, hint: 'Reload the page and try again.', rows: [] });
            }
        });
    });

    webhookModal.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => webhookModal.close());
    });

    webhookModal.addEventListener('click', (event) => {
        if (event.target === webhookModal) {
            webhookModal.close();
        }
    });
}