/*
 * Foto unit yang filenya hilang dari disk (atau URL remote dari seeder yang
 * tidak terjangkau saat offline) tidak boleh meninggalkan ikon rusak. src yang
 * kosong sudah dicegah di server oleh Apartment::display_image_url — blok ini
 * menangani kasus yang tidak bisa dideteksi tanpa stat per gambar.
 *
 * Path harus sama dengan Apartment::PLACEHOLDER_IMAGE.
 */
const IMAGE_FALLBACK = '/images/unit-placeholder.svg';

function applyImageFallback(image) {
    if (!(image instanceof HTMLImageElement) || image.dataset.fallbackApplied) {
        return;
    }

    // Ditandai supaya placeholder yang ikut gagal tidak memicu loop.
    image.dataset.fallbackApplied = 'true';
    image.src = IMAGE_FALLBACK;
}

/*
 * Didaftarkan di top level, bukan di dalam DOMContentLoaded: gambar mulai
 * dimuat saat HTML masih diparse, jadi listener yang dipasang belakangan bisa
 * melewatkan error yang sudah terjadi. Fase capture wajib — event 'error' pada
 * <img> tidak bubble ke document.
 */
document.addEventListener('error', (event) => applyImageFallback(event.target), true);

document.addEventListener('DOMContentLoaded', () => {
    // Jaring pengaman untuk gambar yang errornya terjadi sebelum script ini
    // dieksekusi: selesai dimuat tapi tanpa dimensi = gagal.
    document.querySelectorAll('img').forEach((image) => {
        if (image.complete && image.naturalWidth === 0) {
            applyImageFallback(image);
        }
    });

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
            // Tombol submit bisa berada di luar form lewat atribut
            // form="<id>" (bar CTA mobile di halaman unit). Properti .form
            // mengembalikan form pemilik untuk kedua cara asosiasi, jadi
            // semuanya ikut masuk state loading — bukan hanya yang di dalam.
            const submitButtons = Array.from(document.querySelectorAll('button[type="submit"]'))
                .filter((button) => button.form === form && !button.disabled);

            submitButtons.forEach((submitButton) => {
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
});
