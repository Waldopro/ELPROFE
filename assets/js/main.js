// assets/js/main.js
$(document).ready(function() {
    // Theme initialization
    const htmlEl = document.documentElement;
    const themeBtn = $('#theme-toggle');
    const themeIcon = themeBtn.find('i');
    
    // Check local storage for theme
    const currentTheme = localStorage.getItem('theme') || 'dark';
    setTheme(currentTheme);

    themeBtn.on('click', function(e) {
        e.preventDefault();
        const current = htmlEl.getAttribute('data-bs-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        setTheme(next);
    });

    function setTheme(theme) {
        htmlEl.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
        if(theme === 'dark') {
            themeIcon.removeClass('fa-moon').addClass('fa-sun');
            themeBtn.removeClass('btn-outline-dark').addClass('btn-outline-light');
        } else {
            themeIcon.removeClass('fa-sun').addClass('fa-moon');
            themeBtn.removeClass('btn-outline-light').addClass('btn-outline-dark');
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
            e.preventDefault();
            themeBtn.click();
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
        
        if (!isInputFocus) {
            switch(e.key) {
                case 'F1':
                    e.preventDefault();
                    window.location.href = '/dashboard';
                    break;
                case 'F2':
                    e.preventDefault();
                    window.location.href = '/ventas';
                    break;
                case 'F3':
                    e.preventDefault();
                    window.location.href = '/inventario';
                    break;
                case 'F4':
                    e.preventDefault();
                    window.location.href = '/compras';
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
});
