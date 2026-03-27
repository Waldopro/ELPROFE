/**
 * Compartir PNG generado con html2canvas.
 * Limitación: https://wa.me/?text= solo admite texto; no hay API web oficial para adjuntar imágenes.
 * En móvil (Chrome Android / Safari iOS) navigator.share({ files }) suele abrir el selector y permite WhatsApp con imagen.
 */
async function elprofeTryShareImageFile(file, title, text) {
    if (!navigator.share) return false;
    // Web Share con archivos requiere contexto seguro (HTTPS) en la mayoría de navegadores móviles.
    // Excepción típica: localhost. Si estás entrando por IP en HTTP, casi seguro fallará y caerá a texto/link.
    window.__elprofeShareInsecure = false;
    if (typeof window !== 'undefined' && window.isSecureContext === false) {
        window.__elprofeShareInsecure = true;
        return false;
    }
    const canShareFn = navigator.canShare;
    // Algunos navegadores reportan `canShare({ files }) = false`
    // aun cuando el share-sheet sí abre. Por eso intentamos variantes
    // sin "short-circuit" por canShare.
    const tries = [
        // 1) Solo archivo (más compatible)
        { files: [file] },
        // 2) Archivo + título
        { files: [file], title: title || '' },
        // 3) Archivo + título + texto
        { files: [file], title: title || '', text: text || '' }
    ];
    for (const data of tries) {
        try {
            if (canShareFn && !canShareFn(data)) {
                // No hacemos continue: algunos navegadores tienen falsos negativos.
            }
            await navigator.share(data);
            return true;
        } catch (e) {
            if (e && e.name === 'AbortError') return true;
        }
    }
    return false;
}

function elprofeFallbackShareComprobante(canvas, id, tipo, onlineUrl) {
    const fname = `${tipo}_${id}.png`;
    const hostBad = /localhost|127\.0\.0\.1/i.test(window.location.hostname || '');
    const urlBad = onlineUrl && /localhost|127\.0\.0\.1/i.test(onlineUrl);

    const textoRecibo = `Comprobante ELPROFE #${id}\n${onlineUrl || ''}`.trim();

    const insecureHint = (window.__elprofeShareInsecure)
        ? '<p class="text-start small"><strong>Importante:</strong> para compartir la imagen como archivo desde el navegador necesitas abrir el sistema por <strong>HTTPS</strong> (o instalarlo como PWA). En <code>http://</code> por IP el teléfono suele bloquear el envío de archivos y solo permite texto.</p>'
        : '';

    const html = insecureHint + '<p class="text-start small">' +
        'WhatsApp por navegador <strong>no permite</strong> enviar una imagen como adjunto con un simple enlace: solo texto. ' +
        'Por eso antes se abría un mensaje con URL (y con <code>localhost</code> el otro no ve la imagen).</p>' +
        '<p class="text-start small mb-0">' +
        (hostBad || urlBad
            ? '<strong>En producción</strong> use la IP o dominio público del servidor si quiere compartir un enlace descargable. ' +
              'Lo fiable es <strong>descargar el PNG</strong> y adjuntarlo en WhatsApp.</p>'
            : 'Pulse <strong>Descargar PNG</strong> y adjunte el archivo en WhatsApp (móvil o WhatsApp Web).</p>');

    Swal.fire({
        title: 'Compartir imagen en WhatsApp',
        html: html,
        icon: 'info',
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: 'Descargar PNG',
        denyButtonText: 'Abrir WhatsApp App',
        cancelButtonText: 'WhatsApp Web',
        confirmButtonColor: '#0d6efd',
        denyButtonColor: '#25D366'
    }).then((result) => {
        if (result.isConfirmed) {
            const a = document.createElement('a');
            a.download = fname;
            a.href = canvas.toDataURL('image/png');
            a.click();
        } else if (result.isDenied) {
            // En móvil, wa.me suele abrir la app si está instalada.
            const wa = 'https://wa.me/?text=' + encodeURIComponent(textoRecibo);
            window.open(wa, '_blank', 'noopener');
        } else {
            window.open('https://web.whatsapp.com', '_blank', 'noopener');
        }
    });
}
