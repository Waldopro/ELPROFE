</main>
<?php if (isset($_SESSION['user_id'])): ?>
  </div> <!-- elprofe-body -->
</div> <!-- elprofe-app -->
<?php endif; ?>
<footer class="mt-auto elprofe-footer shadow-sm">
    <div class="container-fluid px-4 py-3">
        <div class="row align-items-center g-3">
            <div class="col-lg-4 text-center text-lg-start">
                <div class="d-flex align-items-center justify-content-center justify-content-lg-start gap-2">
                    <img src="/ELPROFE/assets/img/logo.png" alt="Logo" width="24" height="24" class="rounded-circle elprofe-logo-shell shadow-sm p-0.5">
                    <small class="elprofe-footer-copy">
                        &copy; <?php echo date('Y'); ?> <span class="fw-bold">ELPROFE POS</span>. Todos los derechos reservados.
                    </small>
                </div>
            </div>
            
            <div class="col-lg-4 text-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="elprofe-footer-shortcuts justify-content-center">
                    <span class="text-uppercase fw-bold"><i class="fa-solid fa-keyboard me-1"></i>Atajos:</span>
                    <kbd>F1</kbd><span>Inicio</span>
                    <kbd>F2</kbd><span>Ventas</span>
                    <kbd>F6</kbd><span>Clientes</span>
                    <kbd>F7</kbd><span>Cobranza</span>
                    <?php if (isAdmin()): ?>
                    <kbd>F3</kbd><span>Inventario</span>
                    <kbd>F4</kbd><span>Compras</span>
                    <?php endif; ?>
                    <kbd>F8</kbd><span>Notificaciones</span>
                    <kbd>F10</kbd><span>Caja</span>
                    <kbd>Alt+T</kbd><span>Tema</span>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4 text-center text-lg-end">
                <span class="elprofe-footer-version">
                    <i class="fa-solid fa-code-branch me-1"></i>v1.0 | <i class="fa-solid fa-shield-halved ms-1 text-success"></i> Sistema Seguro
                </span>
            </div>
        </div>
    </div>
</footer>

<script>
    window.isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
    window.userRole = <?php echo isset($_SESSION['user_role']) ? json_encode($_SESSION['user_role']) : 'null'; ?>;
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<?php displayFlash(); ?>
<!-- Custom JS -->
<script src="/ELPROFE/assets/js/main.js"></script>

<!-- Tabla mejorada (sin DataTables) -->
<script src="/ELPROFE/assets/js/table_enhancer.js"></script>

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
<script>
    // Limpieza de Service Workers viejos para evitar 503 falsos en rutas dinámicas.
    (function() {
        if (!('serviceWorker' in navigator)) return;
        const alreadyCleaned = localStorage.getItem('elprofe_sw_cleanup_done') === '1';
        if (alreadyCleaned) return;

        navigator.serviceWorker.getRegistrations()
            .then((regs) => Promise.all(regs.map((r) => r.unregister())))
            .then(() => {
                if (!('caches' in window)) return;
                return caches.keys().then((keys) => Promise.all(
                    keys
                        .filter((k) => k.startsWith('elprofe-'))
                        .map((k) => caches.delete(k))
                ));
            })
            .finally(() => {
                localStorage.setItem('elprofe_sw_cleanup_done', '1');
            });
    })();
</script>
</body>
</html>
