{{-- Pencegahan FOUC (Flash of Unstyled Content): Set data-theme sebelum CSS selesai di-render --}}
<script>
    (function () {
        try {
            var theme = localStorage.getItem('asv_dashboard_theme');
            if (!theme) {
                theme = (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) ? 'light' : 'dark';
            }
            document.documentElement.setAttribute('data-theme', theme);
        } catch (e) {}
    })();
</script>
