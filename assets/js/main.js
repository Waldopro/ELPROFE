// assets/js/main.js
$(document).ready(function() {
    // Theme initialization
    const htmlEl = document.documentElement;
    const themeBtn = $('#theme-toggle');
    const themeIcon = themeBtn.find('i');
    
    // Check local storage for theme
    const currentTheme = localStorage.getItem('theme') || htmlEl.getAttribute('data-bs-theme') || 'dark';
    setTheme(currentTheme);

    themeBtn.on('click', function(e) {
        e.preventDefault();
        const current = htmlEl.getAttribute('data-bs-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        setTheme(next);
    });

    function setTheme(theme) {
        theme = (theme === 'light') ? 'light' : 'dark';
        htmlEl.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
        if (themeBtn.length) {
            const isMobile = window.innerWidth < 992;
            const hint = isMobile ? '' : ' (Alt+T)';
            themeBtn.attr('title', theme === 'dark' ? 'Cambiar a modo claro' + hint : 'Cambiar a modo oscuro' + hint);
            themeBtn.attr('aria-label', theme === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
        }
        if(theme === 'dark') {
            themeIcon.removeClass('fa-moon').addClass('fa-sun');
        } else {
            themeIcon.removeClass('fa-sun').addClass('fa-moon');
        }
    }

    // Configurar AJAX para incluir CSRF
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Barcode scanner buffer global
    let barcodeBuffer = '';
    let barcodeTimer = null;

    // Keyboard Shortcuts & Barcode scanner global listener
    $(document).on('keydown', function(e) {
        // Alt + T: Toggle Theme
        if (e.altKey && e.key.toLowerCase() === 't') {
            if (window.isLoggedIn) {
                e.preventDefault();
                themeBtn.click();
            }
        }
        
        // Barcode Scanner Listener:
        // Los escáneres escriben muy rápido y terminan con Enter.
        const isInputFocus = $(e.target).is('input, textarea, select');
        
        // Si no es un atajo especial, capturamos para el barcode si es que escribe
        if (!e.altKey && !e.ctrlKey && !e.metaKey && e.key.length === 1) {
            barcodeBuffer += e.key;
            clearTimeout(barcodeTimer);
            barcodeTimer = setTimeout(() => { barcodeBuffer = ''; }, 100); // 100ms timeout para reiniciar buffer
        }
        
        if (e.key === 'Enter' && barcodeBuffer.length >= 3) {
            // Un barcode entero fue leido por el lector
            e.preventDefault();
            const scannedCode = barcodeBuffer;
            barcodeBuffer = ''; // reset
            clearTimeout(barcodeTimer);
            
            // Si estamos en ventas o compras y hay un input de busqueda
            const buscador = $('#buscador-producto');
            if (buscador.length > 0) {
                buscador.val(scannedCode).trigger('change');
                // Trigger evento personalizado para que ventas.js lo capture
                $(document).trigger('barcodeScanned', [scannedCode]);
            }
        }
        
        if (window.isLoggedIn) {
            const k = String(e.key || '').toUpperCase();
            const isAdmin = String(window.userRole || '') === 'ADMIN';
            if (k === 'F1') { e.preventDefault(); window.location.href = '/ELPROFE/dashboard'; return; }
            if (k === 'F2') { e.preventDefault(); window.location.href = '/ELPROFE/ventas'; return; }
            if (k === 'F6') { e.preventDefault(); window.location.href = '/ELPROFE/clientes'; return; }
            if (k === 'F7') { e.preventDefault(); window.location.href = '/ELPROFE/proformas'; return; }
            if (k === 'F10') { e.preventDefault(); window.location.href = '/ELPROFE/mi_caja'; return; }
            if (k === 'F8') {
                e.preventDefault();
                if (notifyBtn.length) notifyBtn.trigger('click');
                return;
            }
            if (k === 'F3') {
                e.preventDefault();
                if (isAdmin) window.location.href = '/ELPROFE/inventario';
                else Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Inventario solo para administradores', showConfirmButton: false, timer: 1800 });
                return;
            }
            if (k === 'F4') {
                e.preventDefault();
                if (isAdmin) window.location.href = '/ELPROFE/compras';
                else Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Compras solo para administradores', showConfirmButton: false, timer: 1800 });
                return;
            }
            if (e.ctrlKey && k === 'K') {
                e.preventDefault();
                const target = document.querySelector('#buscador-producto, #catalogo-buscar, #clientes-buscar, input[type=\"search\"], .dataTables_filter input');
                if (target) target.focus();
                return;
            }
            if (e.altKey && k === 'N') {
                e.preventDefault();
                if (notifyBtn.length) notifyBtn.trigger('click');
                return;
            }
        }
    });

    // Tasa actual updater wrapper para scope global
    window.actualizarTasa = function() {
        Swal.fire({
            title: 'Actualizar Tasa (USD a Bs)',
            input: 'text',
            inputLabel: 'Ingrese la nueva tasa del día',
            inputValue: $('#tasa-actual').text().replace(/,/g, ''),
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            preConfirm: (value) => {
                if(!value || isNaN(value) || parseFloat(value) <= 0) {
                    Swal.showValidationMessage('Ingrese un valor numérico válido.');
                    return false;
                }
                return value;
            }
        }).then((result) => {
            if(result.isConfirmed) {
                const nuevaTasa = parseFloat(result.value);
                $.post('/ELPROFE/api/config.php', { action: 'update_tasa', tasa: nuevaTasa }, function(res) {
                    if(res.success) {
                        $('#tasa-actual').text(nuevaTasa.toFixed(2));
                        Swal.fire('Tasa Actualizada', 'La tasa de cambio se actualizó correctamente.', 'success').then(() => {
                            // Reload page to reflect changes if necessary
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', res.message || 'No se pudo actualizar.', 'error');
                    }
                }, 'json').fail(function() {
                    Swal.fire('Error', 'Error de red o de servidor.', 'error');
                });
            }
        });
    };

    // Centro de notificaciones profesional (inventario/cobranza)
    const notifyBtn = $('#notifications-btn');
    const notifyCount = $('#notifications-count');
    const notifyList = $('#notifications-list');
    const notifyTime = $('#notifications-time');
    const notifyRefreshBtn = $('#notifications-refresh');
    const notifyMarkReadBtn = $('#notifications-mark-read');
    const notifyMenu = $('#notifications-menu');
    let lastFeedSignature = '';
    let readIds = [];
    try {
        readIds = JSON.parse(localStorage.getItem('elprofe_notify_read_ids') || '[]');
        if (!Array.isArray(readIds)) readIds = [];
    } catch (e) {
        readIds = [];
    }

    function persistReadIds() {
        const trimmed = readIds.slice(-120);
        localStorage.setItem('elprofe_notify_read_ids', JSON.stringify(trimmed));
    }

    function renderNotifyItem(item) {
        const icon = item.tipo === 'inventario'
            ? 'fa-box-open'
            : (item.tipo === 'seguridad' ? 'fa-shield-halved' : 'fa-file-invoice-dollar');
        const href = item.tipo === 'inventario'
            ? '/ELPROFE/inventario'
            : (item.tipo === 'seguridad' ? '/ELPROFE/bitacora' : '/ELPROFE/proformas');
        const isNew = !readIds.includes(item.id);
        const when = item.fecha || '';
        return `
            <a href="${href}" class="elprofe-notify-item ${isNew ? 'is-new' : ''}" data-notify-id="${item.id}">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="d-flex align-items-start gap-2">
                        <i class="fa-solid ${icon} mt-1 text-primary"></i>
                        <div>
                            <div class="fw-semibold">${item.titulo} ${isNew ? '<span class="elprofe-notify-dot" title="Nueva"></span>' : ''}</div>
                            <div class="small text-muted">${item.mensaje}</div>
                            <div class="small text-muted mt-1">${when}</div>
                        </div>
                    </div>
                    <span class="elprofe-notify-tag ${item.nivel}">${item.nivel}</span>
                </div>
            </a>
        `;
    }

    async function refreshNotifications(showToastOnChange = false) {
        if (!window.isLoggedIn || notifyBtn.length === 0) return;
        try {
            const res = await fetch('/ELPROFE/api/monitor.php?action=feed');
            const data = await res.json();
            if (!data || !data.success) return;

            const items = Array.isArray(data.items) ? data.items : [];
            const total = Number(data.count || 0);
            const sig = items.map((it) => it.id).join('|');
            const unseen = items.filter((it) => !readIds.includes(it.id)).length;

            notifyTime.text((data.server_time || '').slice(11, 16) || '--:--');
            if (unseen > 0) notifyCount.text(unseen > 99 ? '99+' : String(unseen)).removeClass('d-none');
            else notifyCount.text('0').addClass('d-none');

            if (items.length === 0) {
                notifyList.html('<div class="px-3 py-3 text-muted small">Sin notificaciones pendientes.</div>');
            } else {
                notifyList.html(items.map(renderNotifyItem).join(''));
            }

            if (showToastOnChange && lastFeedSignature && sig !== lastFeedSignature && total > 0) {
                const top = items[0];
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: top.nivel === 'critico' ? 'error' : (top.nivel === 'alerta' ? 'warning' : 'info'),
                    title: top.titulo,
                    text: top.mensaje,
                    showConfirmButton: false,
                    timer: 2600
                });
            }
            lastFeedSignature = sig;
        } catch (e) {}
    }

    notifyMenu.on('click', '.elprofe-notify-item', function() {
        const id = $(this).data('notify-id');
        if (id && !readIds.includes(id)) {
            readIds.push(id);
            persistReadIds();
            refreshNotifications(false);
        }
    });

    notifyRefreshBtn.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        refreshNotifications(false);
    });

    notifyMarkReadBtn.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const ids = notifyList.find('.elprofe-notify-item').map(function() {
            return $(this).data('notify-id');
        }).get().filter(Boolean);
        ids.forEach((id) => {
            if (!readIds.includes(id)) readIds.push(id);
        });
        persistReadIds();
        refreshNotifications(false);
    });

    notifyBtn.on('click', function() {
        setTimeout(() => refreshNotifications(false), 120);
    });

    refreshNotifications(false);
    setInterval(() => refreshNotifications(true), 30000);

    // Strip keyboard shortcuts from titles and placeholders on mobile
    if (window.innerWidth < 992) {
        $('[title*="(Alt+"], [title*="(Ctrl+"], [title*="(F"]').each(function() {
            let title = $(this).attr('title');
            if (title) {
                title = title.replace(/\s?\(Alt\+[A-Z0-9]\)/gi, '');
                title = title.replace(/\s?\(Ctrl\+[A-Z0-9]\)/gi, '');
                title = title.replace(/\s?\(F[0-9]+\)/gi, '');
                $(this).attr('title', title.trim());
            }
        });
        
        $('input[placeholder*="(F"], input[placeholder*="(Ctrl+"]').each(function() {
            let ph = $(this).attr('placeholder');
            if (ph) {
                ph = ph.replace(/\s?\(F[0-9]+\)/gi, '');
                ph = ph.replace(/\s?\(Ctrl\+[A-Z0-9]\)/gi, '');
                $(this).attr('placeholder', ph.trim());
            }
        });
    }
});
