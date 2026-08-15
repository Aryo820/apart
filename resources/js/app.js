document.addEventListener('DOMContentLoaded', () => {
    const menuToggle = document.querySelector('[data-mobile-menu-toggle]');
    const mobileMenu = document.querySelector('[data-mobile-menu]');

    if (menuToggle && mobileMenu) {
        const openLabel = menuToggle.getAttribute('aria-label');

        const setMenuState = (isOpen) => {
            menuToggle.setAttribute('aria-expanded', String(isOpen));
            menuToggle.setAttribute('aria-label', isOpen ? 'Tutup menu navigasi' : openLabel);
            mobileMenu.classList.toggle('hidden', !isOpen);
            document.body.classList.toggle('overflow-hidden', isOpen);
        };

        menuToggle.addEventListener('click', () => {
            setMenuState(menuToggle.getAttribute('aria-expanded') !== 'true');
        });

        mobileMenu.querySelectorAll('a, button').forEach((element) => {
            element.addEventListener('click', () => setMenuState(false));
        });

        document.addEventListener('keydown', (event) => {
            // Hanya bereaksi saat menu benar-benar terbuka — kalau tidak, Escape
            // di dalam field pencarian ikut merampas fokus ke tombol menu.
            if (event.key !== 'Escape' || menuToggle.getAttribute('aria-expanded') !== 'true') {
                return;
            }

            setMenuState(false);
            menuToggle.focus();
        });
    }

    document.querySelectorAll('form[data-submit-loading]').forEach((form) => {
        form.addEventListener('submit', () => {
            const submitButton = form.querySelector('button[type="submit"]');

            if (!submitButton || submitButton.disabled) {
                return;
            }

            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');

            const label = submitButton.querySelector('span');
            const loadingLabel = submitButton.dataset.loadingLabel;

            if (label && loadingLabel) {
                label.textContent = loadingLabel;
            }
        });
    });
});
