// assets/js/pos.js
const POS = (() => {
    let cart = [];
    let currentClienteId = 0;
    let reservationId = parseInt(localStorage.getItem('elprofe_reservation_id') || '0', 10) || 0;
    let deviceId = localStorage.getItem('elprofe_device_id') || '';
    
    const elements = {
        buscador: $('#buscador-producto'),
        resultados: $('#resultado-busqueda'),
        tabla: $('#lista-productos'),
        totalUSD: $('#gran-total-usd'),
        totalBS: $('#gran-total-bs'),
        filaVacia: $('#fila-vacia'),
        clienteCedula: $('#cliente-cedula'),
        clienteNombre: $('#cliente-nombre'),
        holdCount: $('#hold-count')
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function sanitizeNumber(value, fallback = 0) {
        const n = Number(value);
        return Number.isFinite(n) ? n : fallback;
    }

    // La tasa es crítica para cálculos en Bs. Evitamos NaN por selectores inexistentes.
    let tasa = parseFloat(($('#tasa-actual').text() || '').toString().replace(/,/g, ''));
    if (!isFinite(tasa) || tasa <= 0) tasa = 0;
    let hold_bills = JSON.parse(localStorage.getItem('hold_bills')) || [];
    let reservaTimer = null;

    function ensureDeviceId() {
        if (deviceId) return deviceId;
        deviceId = (crypto?.randomUUID?.() || ('dev_' + Date.now() + '_' + Math.random().toString(16).slice(2)));
        deviceId = deviceId.replace(/[^a-zA-Z0-9_-]/g, '');
        localStorage.setItem('elprofe_device_id', deviceId);
        return deviceId;
    }

    function cartToReservaItems() {
        return cart.map((c) => ({ presentacion_id: c.presentacion_id, cantidad: c.cantidad }));
    }

    function syncReserva(estado = 'ACTIVE') {
        clearTimeout(reservaTimer);
        reservaTimer = setTimeout(() => {
            const items = cartToReservaItems();
            const csrf = $('meta[name="csrf-token"]').attr('content') || '';

            if (items.length === 0) {
                if (reservationId > 0) {
                    $.ajax({
                        url: '/ELPROFE/api/reservas.php',
                        type: 'POST',
                        dataType: 'json',
                        headers: { 'X-CSRF-Token': csrf },
                        data: { action: 'delete', reservation_id: reservationId, device_id: ensureDeviceId() }
                    }).always(() => {
                        reservationId = 0;
                        localStorage.setItem('elprofe_reservation_id', '0');
                    });
                }
                return;
            }

            $.ajax({
                url: '/ELPROFE/api/reservas.php',
                type: 'POST',
                dataType: 'json',
                headers: { 'X-CSRF-Token': csrf },
                data: {
                    action: 'upsert',
                    reservation_id: reservationId,
                    device_id: ensureDeviceId(),
                    estado: estado,
                    ttl_seconds: 240,
                    items: JSON.stringify(items)
                }
            }).done((res) => {
                if (res && res.success && res.reservation_id) {
                    reservationId = parseInt(res.reservation_id, 10) || 0;
                    localStorage.setItem('elprofe_reservation_id', String(reservationId));
                }
            });
        }, 250);
    }

    function renderCart() {
        elements.tabla.empty();
        let granTotal = 0;
        
        if (cart.length === 0) {
            elements.tabla.append(elements.filaVacia);
        } else {
            cart.forEach((item, index) => {
                const subtotal = item.precio * item.cantidad;
                granTotal += subtotal;
                
                const tr = `<tr>
                    <td class="text-center">${index + 1}</td>
                    <td class="fw-bold">${escapeHtml(item.nombre)} <br>
                        <small class="text-muted">Cód. Único: ${escapeHtml(item.codigo_interno || 'N/A')}</small><br>
                        <small class="text-secondary" style="font-size:0.75rem;">Barras: ${escapeHtml(item.codigo || 'S/B')}</small>
                    </td>
                    <td class="text-center">
                        <div class="input-group input-group-sm w-75 mx-auto">
                            <button class="btn btn-outline-secondary btn-minus" data-index="${index}">-</button>
                            <input type="number" class="form-control text-center input-cantidad" value="${item.cantidad}" data-index="${index}" min="1">
                            <button class="btn btn-outline-secondary btn-plus" data-index="${index}">+</button>
                        </div>
                    </td>
                    <td class="text-end">$${sanitizeNumber(item.precio).toFixed(2)}</td>
                    <td class="text-end fw-bold text-primary">$${sanitizeNumber(subtotal).toFixed(2)}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-danger btn-remove" data-index="${index}"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>`;
                elements.tabla.append(tr);
            });
        }
        
        elements.totalUSD.text('$' + granTotal.toFixed(2));
        elements.totalBS.text(tasa > 0 ? (granTotal * tasa).toFixed(2) : '0.00');
    }

    function updateHoldCount() {
        elements.holdCount.text(hold_bills.length);
        if (hold_bills.length > 0) {
            elements.holdCount.removeClass('bg-secondary bg-danger').addClass('bg-warning text-dark');
        } else {
            elements.holdCount.removeClass('bg-warning text-dark').addClass('bg-secondary text-white');
        }
    }

    function addToCart(producto) {
        // Validación de disponibilidad (si viene del API con reservas)
        if (producto && typeof producto.stock_disponible_presentaciones !== 'undefined') {
            const disp = parseFloat(producto.stock_disponible_presentaciones);
            if (isFinite(disp) && disp <= 0) {
                Swal.fire({toast: true, position: 'top-end', icon: 'warning', title: 'No disponible (reservado o sin stock)', showConfirmButton: false, timer: 1600});
                return;
            }
        }
        const exist = cart.find(p => p.presentacion_id === producto.presentacion_id);
        if (exist) {
            exist.cantidad++;
        } else {
            cart.push({
                presentacion_id: producto.presentacion_id,
                codigo: producto.codigo_barras,
                codigo_interno: producto.codigo_interno,
                nombre: producto.nombre_completo,
                precio: parseFloat(producto.precio_venta_usd),
                cantidad: 1,
                 factor_conversion: parseFloat(producto.factor_conversion),
                stock_unidades: parseFloat(producto.stock_actual)
            });
        }
        renderCart();
        syncReserva('ACTIVE');
    }

    function initEvents() {
        // Escáner Global o Búsqueda Manual
        elements.buscador.on('keyup change', function(e) {
            const q = $(this).val();
            if (q.length >= 2) {
                $.get('/ELPROFE/api/ventas.php', { action: 'buscar_producto', q: q, reservation_id: reservationId }, function(res) {
                    elements.resultados.empty().show();
                    if(res.length > 0) {
                        res.forEach(prod => {
                            let maxPresentaciones = parseInt(prod.stock_disponible_presentaciones ?? 0, 10);
                            const disabled = maxPresentaciones <= 0 ? ' disabled opacity-50' : '';
                            const safeProd = {
                                presentacion_id: parseInt(prod.presentacion_id ?? 0, 10) || 0,
                                codigo_barras: String(prod.codigo_barras ?? ''),
                                codigo_interno: String(prod.codigo_interno ?? ''),
                                nombre_completo: String(prod.nombre_completo ?? ''),
                                precio_venta_usd: sanitizeNumber(prod.precio_venta_usd, 0),
                                factor_conversion: sanitizeNumber(prod.factor_conversion, 1),
                                stock_actual: sanitizeNumber(prod.stock_actual, 0),
                                stock_disponible_presentaciones: sanitizeNumber(prod.stock_disponible_presentaciones, 0),
                            };
                            const $item = $(`<a href="#" class="list-group-item list-group-item-action product-result${disabled}">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">${escapeHtml(safeProd.nombre_completo)}</h6>
                                    <small class="text-primary fw-bold">$${sanitizeNumber(safeProd.precio_venta_usd).toFixed(2)}</small>
                                </div>
                                <small>ID: <b>${escapeHtml(safeProd.codigo_interno)}</b> | Barras: <b>${escapeHtml(safeProd.codigo_barras || 'S/B')}</b> | Disponible: ${maxPresentaciones}</small>
                            </a>`);
                            $item.data('prod', safeProd);
                            elements.resultados.append($item);
                        });
                    } else {
                        elements.resultados.append('<div class="list-group-item text-muted">No se encontró producto en catálogo de presentaciones</div>');
                    }
                });
            } else {
                elements.resultados.hide();
            }
        });

        // Seleccionar de la lista de resultados
        $(document).on('click', '.product-result', function(e) {
            e.preventDefault();
            if ($(this).hasClass('disabled')) return;
            const prod = $(this).data('prod');
            addToCart(prod);
            elements.resultados.hide();
            elements.buscador.val('').focus();
        });
        
        // Escuchar el evento de escaner global
        $(document).on('barcodeScanned', function(e, code) {
            if(code.length >= 2) {
                $.get('/ELPROFE/api/ventas.php', { action: 'buscar_producto', q: code, reservation_id: reservationId }, function(res) {
                    if(res.length === 1) {
                        addToCart(res[0]); // Autoseleccionar si hay match exacto
                        elements.buscador.val('');
                        elements.resultados.hide();
                    } else if (res.length > 1) {
                        elements.buscador.trigger('keyup'); // Mostrar lista
                    } else {
                        Swal.fire({toast: true, position: 'top-end', icon: 'error', title: 'Código inválido o producto sin stock', showConfirmButton: false, timer: 1500});
                        elements.buscador.val('');
                    }
                });
            }
        });

        // Controles Tabla
        $(document).on('click', '.btn-plus', function() {
            cart[$(this).data('index')].cantidad++;
            renderCart();
            syncReserva('ACTIVE');
        });
        
        $(document).on('click', '.btn-minus', function() {
            let item = cart[$(this).data('index')];
            if(item.cantidad > 1) item.cantidad--;
            renderCart();
            syncReserva('ACTIVE');
        });
        
        $(document).on('click', '.btn-remove', function() {
            cart.splice($(this).data('index'), 1);
            renderCart();
            syncReserva('ACTIVE');
        });
        
        // Ocultar dropdown clic afuera
        $(document).click(function(e) {
            if(!$(e.target).closest('#buscador-producto, #resultado-busqueda').length) {
                elements.resultados.hide();
            }
        });
        
        // Modal Cobro Logic (USD / Bs / Mixto + Crédito parcial)
        let totalUSDRequerido = 0;
        let totalBSRequerido = 0;
        let currentPaymentIntent = 'CONTADO';

        function getPagosPorModo() {
            const modo = $('#modal-payment-mode').val();
            const pagos = [];

            if (modo === 'USD') {
                const metodoId = parseInt($('#single-usd-metodo').val() || '0', 10);
                const montoUsd = parseFloat($('#single-usd-monto').val()) || 0;
                if (metodoId > 0 && montoUsd > 0) pagos.push({ id: metodoId, monto_usd: montoUsd, monto_bs: 0 });
            } else if (modo === 'BS') {
                const metodoId = parseInt($('#single-bs-metodo').val() || '0', 10);
                const montoBs = parseFloat($('#single-bs-monto').val()) || 0;
                if (metodoId > 0 && montoBs > 0) pagos.push({ id: metodoId, monto_usd: 0, monto_bs: montoBs });
            } else if (modo === 'MIXTO') {
                $('.metodo-row').each(function() {
                    const id = parseInt($(this).data('id') || '0', 10);
                    const u = parseFloat($(this).find('.input-monto-usd').val()) || 0;
                    const b = parseFloat($(this).find('.input-monto-bs').val()) || 0;
                    if (u > 0 || b > 0) pagos.push({ id: id, monto_usd: u, monto_bs: b });
                });
            }
            return pagos;
        }

        function recalcularSaldoModal() {
            const pagos = getPagosPorModo();
            let pagadoUSD = 0;
            pagos.forEach((pg) => {
                pagadoUSD += (parseFloat(pg.monto_usd) || 0) + ((parseFloat(pg.monto_bs) || 0) / tasa);
            });

            let resta = totalUSDRequerido - pagadoUSD;
            if (resta < 0) resta = 0;
            const restaBs = resta * tasa;

            $('#modal-resta-usd').text('$' + resta.toFixed(2));
            $('#modal-resta-bs').text('Bs ' + restaBs.toFixed(2));
            $('#modal-resta-usd').toggleClass('text-success', resta <= 0.05).toggleClass('text-danger', resta > 0.05);

            const modo = $('#modal-payment-mode').val();
            if (currentPaymentIntent === 'FIADO') {
                // En crédito, el abono inicial es opcional.
                $('#btn-procesar-mixto').prop('disabled', false);
            } else {
                const completo = resta <= 0.05;
                $('#btn-procesar-mixto').prop('disabled', !(modo && completo));
            }
        }

        function aplicarModoPago(modo) {
            $('#panel-pago-usd, #panel-pago-bs, #panel-pago-mixto').addClass('d-none');
            if (modo === 'USD') $('#panel-pago-usd').removeClass('d-none');
            if (modo === 'BS') $('#panel-pago-bs').removeClass('d-none');
            if (modo === 'MIXTO') $('#panel-pago-mixto').removeClass('d-none');
            recalcularSaldoModal();
        }
        
        $('#modalPago').on('show.bs.modal', function() {
            if(cart.length === 0) {
                Swal.fire('Atención', 'El carrito está vacío', 'warning'); return false;
            }
            // refrescar tasa desde el modal/DOM por si fue actualizada por admin sin recargar
            const tasaModal = parseFloat(($('#modal-tasa-actual').text() || '').toString().replace(/,/g, ''));
            if (isFinite(tasaModal) && tasaModal > 0) tasa = tasaModal;
            totalUSDRequerido = cart.reduce((acc, p) => acc + (p.precio * p.cantidad), 0);
            totalBSRequerido = totalUSDRequerido * tasa;
            $('#modal-pagar-usd').text('$' + totalUSDRequerido.toFixed(2));
            $('#modal-pagar-bs').text('Bs ' + totalBSRequerido.toFixed(2));
            $('#modal-resta-usd').text('$' + totalUSDRequerido.toFixed(2));
            $('#modal-resta-bs').text('Bs ' + totalBSRequerido.toFixed(2));
            $('.input-monto-usd, .input-monto-bs').val('');
            $('#single-usd-monto, #single-bs-monto').val('');
            $('#modal-payment-mode').val('');
            aplicarModoPago('');

            // Crédito siempre emite PROFORMA y permite abono parcial opcional.
            if (currentPaymentIntent === 'FIADO') {
                $('#modal-tipo-doc').val('PROFORMA').prop('disabled', true);
                $('#btn-procesar-mixto').html('<i class="fa-solid fa-handshake-angle"></i> Registrar Crédito');
                $('.modal-title', this).html('<i class="fa-solid fa-handshake-angle"></i> Registrar Crédito');
            } else {
                $('#modal-tipo-doc').prop('disabled', false);
                $('#btn-procesar-mixto').html('<i class="fa-solid fa-check"></i> Emitir Documento');
                $('.modal-title', this).html('<i class="fa-solid fa-money-bill-transfer"></i> Procesar Pago');
            }

            $('.metodo-row').each(function() {
                const tipo = String($(this).data('tipo') || 'BS');
                const $usd = $(this).find('.input-monto-usd');
                const $bs = $(this).find('.input-monto-bs');
                if (tipo === 'USD') {
                    $usd.prop('disabled', false).attr('placeholder', '0.00');
                    $bs.prop('disabled', true).val('').attr('placeholder', 'No aplica');
                } else {
                    $usd.prop('disabled', true).val('').attr('placeholder', 'No aplica');
                    $bs.prop('disabled', false).attr('placeholder', '0.00');
                }
            });
            $('#btn-procesar-mixto').prop('disabled', currentPaymentIntent !== 'FIADO');
            setTimeout(() => {
                const $firstEnabled = $('#single-usd-monto, #single-bs-monto, .input-monto-usd:not(:disabled), .input-monto-bs:not(:disabled)').filter(':visible').first();
                if ($firstEnabled.length) $firstEnabled.focus();
            }, 500);
        });

        $('#modal-payment-mode').on('change', function() { aplicarModoPago($(this).val()); });
        $('.input-monto-usd, .input-monto-bs, #single-usd-monto, #single-bs-monto').on('keyup change', recalcularSaldoModal);

        $('#btn-procesar-mixto').click(function() {
            const pagosMultiples = getPagosPorModo();
            let docType = $('#modal-tipo-doc').val() || 'PROFORMA';
            if (currentPaymentIntent === 'FIADO') docType = 'PROFORMA';

            if (currentPaymentIntent === 'CONTADO' && !$('#modal-payment-mode').val()) {
                Swal.fire('Atención', 'Seleccione una modalidad de cobro.', 'warning');
                return;
            }

            $('#modalPago').modal('hide');
            procesarVenta(currentPaymentIntent, pagosMultiples, docType);
        });

        // Boton Fiado Directo
        $('#btn-fiado').click(function() { 
            if(cart.length === 0) {
                Swal.fire('Atención', 'El carrito está vacío', 'warning'); return;
            }
            Swal.fire({
                title: 'Crédito / Fiado',
                text: '¿Desea registrar un abono inicial ahora?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, registrar abono',
                cancelButtonText: 'No, dejar deuda completa'
            }).then((r) => {
                if (r.isConfirmed) {
                    currentPaymentIntent = 'FIADO';
                    $('#modalPago').modal('show');
                } else if (r.dismiss === Swal.DismissReason.cancel) {
                    procesarVenta('FIADO', [], 'PROFORMA');
                }
            });
        });
        $('#btn-cobrar-pre').on('click', function() { currentPaymentIntent = 'CONTADO'; });
        $('#btn-anular').click(function() { cart = []; renderCart(); syncReserva('ACTIVE'); elements.buscador.focus(); });
        
        // Buscar Cliente al salir del input cedula
        elements.clienteCedula.on('blur', function() {
            const rif = $(this).val();
            if(rif && rif !== 'V-00000000') {
                $.get('/ELPROFE/api/ventas.php', {action: 'buscar_cliente', q: rif}, function(res) {
                    if(res.success) {
                        currentClienteId = res.cliente.id;
                        elements.clienteNombre.val(res.cliente.nombre + ' ' + res.cliente.apellido).prop('disabled', true);
                    } else {
                        currentClienteId = 0;
                        elements.clienteNombre.val('').prop('disabled', false).attr('placeholder', 'Cliente Nuevo (Regístrelo en Módulo Cliente)');
                        Swal.fire({toast: true, position: 'top-end', icon: 'warning', title: 'Cliente no registrado', showConfirmButton: false, timer: 1500});
                    }
                });
            } else {
                currentClienteId = 0;
                elements.clienteNombre.val('Consumidor Final').prop('disabled', false);
            }
        });

        // --- EVENTOS FACTURAS EN ESPERA (HOLD BILLS) ---
        $('#btn-hold').click(function() {
            if (cart.length === 0) {
                Swal.fire('Atención', 'El carrito está vacío, no hay nada que poner en espera.', 'warning');
                return;
            }
            
            const ticketName = 'Cliente: ' + elements.clienteNombre.val() + ' | ' + new Date().toLocaleTimeString();
            
            hold_bills.push({
                id: Date.now(),
                label: ticketName,
                reservation_id: reservationId,
                cart: JSON.parse(JSON.stringify(cart)),
                clienteId: currentClienteId,
                clienteCedula: elements.clienteCedula.val(),
                clienteNombre: elements.clienteNombre.val()
            });
            
            localStorage.setItem('hold_bills', JSON.stringify(hold_bills));
            updateHoldCount();
            
            // Limpiar
            cart = [];
            // Mantener reserva como HOLD y resetear la actual
            if (reservationId > 0) {
                syncReserva('HOLD');
                reservationId = 0;
                localStorage.setItem('elprofe_reservation_id', '0');
            }
            currentClienteId = 0;
            elements.clienteCedula.val('V-00000000');
            elements.clienteNombre.val('Consumidor Final').prop('disabled', false);
            renderCart();
            elements.buscador.focus();
            
            Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'Ticket guardado en espera', showConfirmButton: false, timer: 2000});
        });

        $('#btn-restore-hold').click(function() {
            if (hold_bills.length === 0) {
                Swal.fire({toast: true, position: 'top-end', icon: 'info', title: 'No hay tickets en espera', showConfirmButton: false, timer: 1500});
                return;
            }
            
            // Construir HTML para seleccionar
            let htmlOptions = '';
            hold_bills.forEach((bill, i) => {
                            let totalItems = bill.cart.reduce((s, p) => s + sanitizeNumber(p.cantidad, 0), 0);
                            let totalUSD = bill.cart.reduce((s, p) => s + (sanitizeNumber(p.precio, 0) * sanitizeNumber(p.cantidad, 0)), 0);
                            htmlOptions += `<button type="button" class="list-group-item list-group-item-action fw-bold fs-6 py-3 select-hold-bill" data-idx="${i}">
                                <div class="d-flex w-100 justify-content-between">
                                  <span><i class="fa-solid fa-clock text-warning"></i> ${escapeHtml(bill.label)}</span>
                                  <span class="text-primary">$${sanitizeNumber(totalUSD).toFixed(2)}</span>
                                </div>
                                <small class="text-muted">${sanitizeNumber(totalItems)} artículos en carrito</small>
                            </button>`;
                        });
            
            Swal.fire({
                title: 'Recuperar Ticket en Espera',
                html: `<div class="list-group list-group-flush text-start shadow-sm border mt-3">${htmlOptions}</div>`,
                showConfirmButton: false,
                showCancelButton: true,
                cancelButtonText: 'Cerrar',
                didOpen: () => {
                    $('.select-hold-bill').click(function() {
                        let idx = $(this).data('idx');
                        let billToRestore = hold_bills[idx];
                        
                        let doRestore = () => {
                            cart = billToRestore.cart;
                            currentClienteId = billToRestore.clienteId;
                            elements.clienteCedula.val(billToRestore.clienteCedula);
                            elements.clienteNombre.val(billToRestore.clienteNombre);
                            if (currentClienteId > 0) elements.clienteNombre.prop('disabled', true);

                            reservationId = parseInt(billToRestore.reservation_id || '0', 10) || 0;
                            localStorage.setItem('elprofe_reservation_id', String(reservationId));
                            
                            // Remover del array
                            hold_bills.splice(idx, 1);
                            localStorage.setItem('hold_bills', JSON.stringify(hold_bills));
                            updateHoldCount();
                            renderCart();
                            syncReserva('ACTIVE');
                            Swal.close();
                            elements.buscador.focus();
                        };
                        
                        if (cart.length > 0) {
                            Swal.fire({
                                title: 'Carrito Actual No Vacío',
                                text: 'El carrito actual se enviará a tickets en espera automáticamente.',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Sí, continuar'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $('#btn-hold').click(); // Auto-hold
                                    setTimeout(doRestore, 300); // Wait for the auto-hold toast
                                }
                            });
                        } else {
                            doRestore();
                        }
                    });
                }
            });
        });

        // Modal catálogo de productos
        function cargarCatalogo(q = '') {
            $.get('/ELPROFE/api/ventas.php', { action: 'catalogo_productos', q: q, limit: 120 }, function(res) {
                const body = $('#catalogo-body');
                body.empty();
                const rows = (res && res.rows) ? res.rows : [];
                if (!rows.length) {
                    body.append('<tr><td colspan="6" class="text-center text-muted py-4">No hay productos para mostrar</td></tr>');
                    return;
                }
                rows.forEach((p) => {
                    const disp = parseInt(p.stock_disponible_presentaciones || 0, 10);
                    const badge = disp <= 0 ? '<span class="badge bg-danger">Sin stock</span>' : (disp < 5 ? '<span class="badge bg-warning text-dark">Stock bajo</span>' : '<span class="badge bg-success">Disponible</span>');
                    const disabled = disp <= 0 ? 'disabled' : '';
                    body.append(`
                        <tr>
                            <td>${escapeHtml(p.nombre_completo || '')}</td>
                            <td>${escapeHtml(p.codigo_interno || '')}</td>
                            <td>${escapeHtml(p.codigo_barras || 'S/B')}</td>
                            <td class="text-end">$${sanitizeNumber(p.precio_venta_usd).toFixed(2)}</td>
                            <td class="text-center">${badge} <span class="ms-1">${disp}</span></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-primary btn-add-catalog" ${disabled}>Agregar</button>
                            </td>
                        </tr>
                    `);
                    body.find('tr:last .btn-add-catalog').data('prod', {
                        presentacion_id: parseInt(p.presentacion_id || 0, 10),
                        codigo_barras: String(p.codigo_barras || ''),
                        codigo_interno: String(p.codigo_interno || ''),
                        nombre_completo: String(p.nombre_completo || ''),
                        precio_venta_usd: sanitizeNumber(p.precio_venta_usd, 0),
                        factor_conversion: sanitizeNumber(p.factor_conversion, 1),
                        stock_actual: sanitizeNumber(p.stock_actual, 0),
                        stock_disponible_presentaciones: sanitizeNumber(p.stock_disponible_presentaciones, 0)
                    });
                });
            }, 'json');
        }

        $('#btn-catalogo-productos').on('click', function() {
            $('#catalogo-buscar').val('');
            cargarCatalogo('');
            new bootstrap.Modal(document.getElementById('modalCatalogoProductos')).show();
        });

        $('#catalogo-buscar').on('keyup change', function() {
            cargarCatalogo($(this).val());
        });

        $(document).on('click', '.btn-add-catalog', function() {
            const prod = $(this).data('prod');
            if (prod) addToCart(prod);
        });

        // Alertas de stock en tiempo real (POS)
        let lastStockSig = '';
        function refrescarAlertasStockVentas() {
            $.get('/ELPROFE/api/ventas.php', { action: 'stock_alertas' }, function(res) {
                if (!res || !res.success) return;
                const sinStock = parseInt(res.sin_stock || 0, 10);
                const bajo = parseInt(res.stock_bajo || 0, 10);
                const total = sinStock + bajo;
                const sig = `${sinStock}-${bajo}`;
                const alertBox = $('#ventas-stock-alert');
                if (total > 0) {
                    alertBox.removeClass('d-none').html(`<i class="fa-solid fa-triangle-exclamation me-2"></i> Alertas en inventario: <strong>${sinStock}</strong> sin stock y <strong>${bajo}</strong> con stock bajo.`);
                } else {
                    alertBox.addClass('d-none').empty();
                }
                if (lastStockSig && lastStockSig !== sig) {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: total > 0 ? 'warning' : 'success',
                        title: total > 0 ? `Inventario actualizado: ${sinStock} sin stock, ${bajo} bajo` : 'Inventario estabilizado',
                        showConfirmButton: false,
                        timer: 2200
                    });
                }
                lastStockSig = sig;
            }, 'json');
        }
        refrescarAlertasStockVentas();
        setInterval(refrescarAlertasStockVentas, 30000);
    }

    function procesarVenta(tipo, arrayPagos = [], tipoDoc = 'PROFORMA') {
        if(cart.length === 0) {
            Swal.fire('Atención', 'El carrito está vacío', 'warning'); return;
        }
        
        $.post('/ELPROFE/api/ventas.php', {
            action: 'procesar_proforma',
            tipo: tipo,
            tipo_doc: tipoDoc,
            cliente_id: currentClienteId,
            productos: JSON.stringify(cart),
            pagos: JSON.stringify(arrayPagos),
            reservation_id: reservationId,
            device_id: ensureDeviceId()
        }, function(res) {
            if(res.success) {
                // Generar URLs para comprobantes
                const shareToken = res.share_token || '';
                const urlTicket = `/ELPROFE/pages/ticket.php?id=${res.proforma_id}&share=${encodeURIComponent(shareToken)}`;
                const urlWa = `/ELPROFE/pages/ticket.php?id=${res.proforma_id}&share=${encodeURIComponent(shareToken)}&wa=1`;
                const urlPdf = `/ELPROFE/pages/nota_entrega.php?id=${res.proforma_id}&share=${encodeURIComponent(shareToken)}`;
                const esFiado = (tipo === 'FIADO');
                const labelDocA4 = (String(tipoDoc || 'PROFORMA') === 'FACTURA')
                    ? 'Ver Factura PDF (A4)'
                    : 'Ver Nota de Entrega (A4)';

                let accionesHtml = '';
                if (esFiado) {
                    accionesHtml = `
                        <div class="d-grid gap-2">
                            <a href="${urlPdf}" target="_blank" class="btn btn-warning fw-bold py-2 shadow-sm">
                                <i class="fa-solid fa-file-lines me-2"></i> Ver Nota de Entrega (A4)
                            </a>
                            <a href="/ELPROFE/proformas" class="btn btn-primary fw-bold py-2 shadow-sm">
                                <i class="fa-solid fa-hand-holding-dollar me-2"></i> Ir a Cobranza / Fiados
                            </a>
                        </div>
                    `;
                } else {
                    accionesHtml = `
                        <div class="d-grid gap-2">
                            <a href="${urlTicket}&print=1" target="_blank" class="btn btn-primary fw-bold py-2 shadow-sm">
                                <i class="fa-solid fa-receipt me-2"></i> Imprimir Ticket Térmico
                            </a>
                            <a href="${urlWa}" target="_blank" class="btn btn-success fw-bold py-2 shadow-sm">
                                <i class="fa-brands fa-whatsapp me-2"></i> Compartir WhatsApp
                            </a>
                            <a href="${urlPdf}" target="_blank" class="btn btn-warning fw-bold py-2 shadow-sm">
                                <i class="fa-solid fa-file-pdf me-2"></i> ${labelDocA4}
                            </a>
                        </div>
                    `;
                }
                
                Swal.fire({
                    title: '¡Operación Exitosa!',
                    html: `
                        <p class="mb-4">${esFiado ? 'Crédito registrado como Proforma.' : 'Proforma registrada correctamente.'} ID #<strong>${res.proforma_id}</strong></p>
                        ${accionesHtml}
                    `,
                    icon: 'success',
                    showConfirmButton: true,
                    confirmButtonText: '<i class="fa-solid fa-cart-plus"></i> Nueva Venta',
                    confirmButtonColor: '#6c757d',
                    allowOutsideClick: false
                }).then(() => {
                    cart = [];
                    reservationId = 0;
                    localStorage.setItem('elprofe_reservation_id', '0');
                    currentClienteId = 0;
                    elements.clienteCedula.val('V-00000000');
                    elements.clienteNombre.val('Consumidor Final').prop('disabled', false);
                    renderCart();
                    elements.buscador.focus();
                });
            } else {
                Swal.fire('Error Operativo', res.message, 'error');
            }
        });
    }

    return { init: () => { ensureDeviceId(); renderCart(); updateHoldCount(); initEvents(); elements.buscador.focus(); if (cart.length > 0) syncReserva('ACTIVE'); } };
})();

$(document).ready(function() { POS.init(); });
