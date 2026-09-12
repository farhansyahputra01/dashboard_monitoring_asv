/**
 * Pengalih Tema (Dark / Light Mode) Dashboard ASV Laksamana 5
 */

(function () {
    const THEME_KEY = 'asv_dashboard_theme';

    // Dapatkan preferensi yang tersimpan atau sistem default
    function getStoredTheme() {
        const stored = localStorage.getItem(THEME_KEY);
        if (stored === 'light' || stored === 'dark') {
            return stored;
        }
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
    }

    // Terapkan tema ke document
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
        updateToggleButtons(theme);
        window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme } }));
    }

    // Perbarui ikon & status tombol toggle di UI
    function updateToggleButtons(theme) {
        const toggleButtons = document.querySelectorAll('[data-theme-toggle]');
        toggleButtons.forEach((btn) => {
            const isLight = theme === 'light';
            btn.setAttribute('aria-label', isLight ? 'Beralih ke Mode Gelap' : 'Beralih ke Mode Terang');
            btn.setAttribute('title', isLight ? 'Mode Gelap' : 'Mode Terang');
            
            const icon = btn.querySelector('i');
            if (icon) {
                if (isLight) {
                    icon.className = 'bi bi-moon-stars-fill';
                } else {
                    icon.className = 'bi bi-sun-fill';
                }
            }

            const label = btn.querySelector('.theme-label');
            if (label) {
                label.textContent = isLight ? 'Gelap' : 'Terang';
            }
        });
    }

    // Inisialisasi saat DOM siap
    function initTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || getStoredTheme();
        applyTheme(currentTheme);

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-theme-toggle]');
            if (!btn) return;
            e.preventDefault();

            const current = document.documentElement.getAttribute('data-theme') || 'dark';
            const nextTheme = current === 'light' ? 'dark' : 'light';
            applyTheme(nextTheme);
        });

        // Sinkronisasi jika berganti preferensi sistem jika user belum menyetel manual
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (!localStorage.getItem(THEME_KEY)) {
                    applyTheme(e.matches ? 'dark' : 'light');
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
})();
