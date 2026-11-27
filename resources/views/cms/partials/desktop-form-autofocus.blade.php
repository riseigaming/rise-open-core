<script>
    (function () {
        if (window.matchMedia('(min-width: 1025px)').matches) {
            const field = document.querySelector('[data-desktop-autofocus]');
            if (field) {
                try { field.focus({ preventScroll: true }); } catch (error) { field.focus(); }
            }
        }
    })();
</script>
