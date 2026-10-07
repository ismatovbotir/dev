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
