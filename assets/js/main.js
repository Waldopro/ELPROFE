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
            themeBtn.attr('title', theme === 'dark' ? 'Cambiar a modo claro (Alt+T)' : 'Cambiar a modo oscuro (Alt+T)');
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
        
        if (!isInputFocus && window.isLoggedIn) {
            switch(e.key) {
                case 'F1':
                    e.preventDefault();
                    window.location.href = '/ELPROFE/dashboard';
                    break;
                case 'F2':
                    e.preventDefault();
                    window.location.href = '/ELPROFE/ventas';
                    break;
                case 'F3':
                    e.preventDefault();
                    window.location.href = '/ELPROFE/inventario';
                    break;
                case 'F4':
                    e.preventDefault();
                    window.location.href = '/ELPROFE/compras';
                    break;
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

    // Alertas globales en tiempo real (inventario/fiados)
    const stockAlertBtn = $('#stock-alert-btn');
    const stockAlertCount = $('#stock-alert-count');
    let lastAlertSignature = '';

    async function refreshGlobalAlerts(showToastOnChange = false) {
        if (!window.isLoggedIn || stockAlertBtn.length === 0) return;
        try {
            const res = await fetch('/ELPROFE/api/monitor.php?action=resumen');
            const data = await res.json();
            if (!data || !data.success) return;

            const sinStock = Number(data.stock?.sin_stock || 0);
            const bajo = Number(data.stock?.stock_bajo || 0);
            const total = sinStock + bajo;
            const fiados = Number(data.fiados_pendientes || 0);

            if (total > 0) {
                stockAlertCount.text(total).removeClass('d-none');
                stockAlertBtn.removeClass('btn-outline-warning').addClass('btn-warning text-dark');
            } else {
                stockAlertCount.text('0').addClass('d-none');
                stockAlertBtn.removeClass('btn-warning text-dark').addClass('btn-outline-warning');
            }

            const sig = `${sinStock}-${bajo}-${fiados}`;
            if (showToastOnChange && sig !== lastAlertSignature && lastAlertSignature !== '') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: total > 0 ? 'warning' : 'success',
                    title: total > 0
                        ? `Inventario: ${sinStock} sin stock, ${bajo} con stock bajo`
                        : 'Inventario sin alertas',
                    showConfirmButton: false,
                    timer: 2400
                });
            }
            lastAlertSignature = sig;

            stockAlertBtn.off('click').on('click', function() {
                Swal.fire({
                    title: 'Alertas en Tiempo Real',
                    html: `
                        <div class="text-start">
                            <p class="mb-2"><strong>Sin stock:</strong> ${sinStock}</p>
                            <p class="mb-2"><strong>Stock bajo (&lt;5):</strong> ${bajo}</p>
                            <p class="mb-0"><strong>Fiados pendientes:</strong> ${fiados}</p>
                        </div>
                    `,
                    icon: total > 0 ? 'warning' : 'info',
                    confirmButtonText: 'Entendido'
                });
            });
        } catch (e) {}
    }

    refreshGlobalAlerts(false);
    setInterval(() => refreshGlobalAlerts(true), 30000);
});
