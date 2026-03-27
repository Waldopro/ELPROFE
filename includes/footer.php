</main>
<?php if (isset($_SESSION['user_id'])): ?>
  </div> <!-- elprofe-body -->
</div> <!-- elprofe-app -->
<?php endif; ?>
<footer class="mt-auto py-3 bg-body-tertiary border-top text-center text-muted">
    <div class="container">
        <small>&copy; <?php echo date('Y'); ?> ELPROFE. Todos los derechos reservados.</small>
        <?php if (isset($_SESSION['user_id'])): ?>
        <span class="ms-3 text-muted"><i class="fa-solid fa-keyboard"></i> Atajos: F1 Inicio | F2 Ventas | F3 Inventario | F4 Compras | Alt+T Tema</span>
        <?php endif; ?>
    </div>
</footer>

<script>
    window.isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<?php displayFlash(); ?>
<!-- Custom JS -->
<script src="/ELPROFE/assets/js/main.js"></script>

<!-- PWA Service Worker (Desactivado temporalmente por diagnóstico)
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/ELPROFE/sw.js')
            .then(reg => console.log('PWA Service Worker registered.', reg.scope))
            .catch(err => console.log('Service worker not registered.', err));
        });
    }
</script>
-->
</body>
</html>
