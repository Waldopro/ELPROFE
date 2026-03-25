// assets/js/pos.js
const POS = (() => {
    let cart = [];
    let currentClienteId = 0;
    
    const elements = {
        buscador: $('#buscador-producto'),
        resultados: $('#resultado-busqueda'),
        tabla: $('#lista-productos'),
        totalUSD: $('#gran-total-usd'),
        totalBS: $('#gran-total-bs'),
        filaVacia: $('#fila-vacia'),
        clienteCedula: $('#cliente-cedula'),
        clienteNombre: $('#cliente-nombre')
    };

    const tasa = parseFloat($('#tasa-actual').text().replace(/,/g, ''));

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
                    <td class="fw-bold">${item.nombre} <br><small class="text-muted">${item.codigo}</small></td>
                    <td class="text-center">
                        <div class="input-group input-group-sm w-75 mx-auto">
                            <button class="btn btn-outline-secondary btn-minus" data-index="${index}">-</button>
                            <input type="number" class="form-control text-center input-cantidad" value="${item.cantidad}" data-index="${index}" min="1">
                            <button class="btn btn-outline-secondary btn-plus" data-index="${index}">+</button>
                        </div>
                    </td>
                    <td class="text-end">$${item.precio.toFixed(2)}</td>
                    <td class="text-end fw-bold text-primary">$${subtotal.toFixed(2)}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-danger btn-remove" data-index="${index}"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>`;
                elements.tabla.append(tr);
            });
        }
        
        elements.totalUSD.text('$' + granTotal.toFixed(2));
        elements.totalBS.text((granTotal * tasa).toFixed(2));
    }

    function addToCart(producto) {
        const exist = cart.find(p => p.presentacion_id === producto.presentacion_id);
        if (exist) {
            exist.cantidad++;
        } else {
            cart.push({
                presentacion_id: producto.presentacion_id,
                codigo: producto.codigo_barras,
                nombre: producto.nombre_completo,
                precio: parseFloat(producto.precio_venta_usd),
                cantidad: 1,
                 factor_conversion: parseFloat(producto.factor_conversion),
                stock_unidades: parseFloat(producto.stock_actual)
            });
        }
        renderCart();
    }

    function initEvents() {
        // Escáner Global o Búsqueda Manual
        elements.buscador.on('keyup change', function(e) {
            const q = $(this).val();
            if (q.length >= 2) {
                $.get('/ELPROFE/api/ventas.php', { action: 'buscar_producto', q: q }, function(res) {
                    elements.resultados.empty().show();
                    if(res.length > 0) {
                        res.forEach(prod => {
                            let maxPresentaciones = Math.floor(parseFloat(prod.stock_actual) / parseFloat(prod.factor_conversion));
                            elements.resultados.append(`<a href="#" class="list-group-item list-group-item-action product-result" data-prod='${JSON.stringify(prod)}'>
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">${prod.nombre_completo}</h6>
                                    <small class="text-primary fw-bold">$${parseFloat(prod.precio_venta_usd).toFixed(2)}</small>
                                </div>
                                <small>Código: ${prod.codigo_barras} | Disponible: ${maxPresentaciones} (Unds Global: ${parseFloat(prod.stock_actual)})</small>
                            </a>`);
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
            const prod = $(this).data('prod');
            addToCart(prod);
            elements.resultados.hide();
            elements.buscador.val('').focus();
        });
        
        // Escuchar el evento de escaner global
        $(document).on('barcodeScanned', function(e, code) {
            if(code.length >= 2) {
                $.get('/ELPROFE/api/ventas.php', { action: 'buscar_producto', q: code }, function(res) {
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
        });
        
        $(document).on('click', '.btn-minus', function() {
            let item = cart[$(this).data('index')];
            if(item.cantidad > 1) item.cantidad--;
            renderCart();
        });
        
        $(document).on('click', '.btn-remove', function() {
            cart.splice($(this).data('index'), 1);
            renderCart();
        });
        
        // Ocultar dropdown clic afuera
        $(document).click(function(e) {
            if(!$(e.target).closest('#buscador-producto, #resultado-busqueda').length) {
                elements.resultados.hide();
            }
        });
        
        // Modal Cobro Mixto Logic
        let totalUSDRequerido = 0;
        
        $('#modalPago').on('show.bs.modal', function() {
            if(cart.length === 0) {
                Swal.fire('Atención', 'El carrito está vacío', 'warning'); return false;
            }
            totalUSDRequerido = cart.reduce((acc, p) => acc + (p.precio * p.cantidad), 0);
            $('#modal-pagar-usd').text('$' + totalUSDRequerido.toFixed(2));
            $('#modal-resta-usd').text('$' + totalUSDRequerido.toFixed(2));
            $('.input-monto-usd, .input-monto-bs').val('');
            $('#btn-procesar-mixto').prop('disabled', true);
            setTimeout(() => { $('.input-monto-usd').first().focus(); }, 500);
        });

        // Recalcular saldo adeudado dinámicamente
        $('.input-monto-usd, .input-monto-bs').on('keyup change', function() {
            let pagadoUSD = 0;
            $('.metodo-row').each(function() {
                let u = parseFloat($(this).find('.input-monto-usd').val()) || 0;
                let b = parseFloat($(this).find('.input-monto-bs').val()) || 0;
                pagadoUSD += u + (b / tasa);
            });
            
            let resta = totalUSDRequerido - pagadoUSD;
            if(resta < 0) resta = 0; // Mostrar vuelto positivo si pasa, pero cobró completo
            
            $('#modal-resta-usd').text('$' + resta.toFixed(2));
            $('#modal-resta-usd').toggleClass('text-success', resta <= 0.05).toggleClass('text-danger', resta > 0.05);
            
            // Tolerancia de 5 centavos para activar botón
            $('#btn-procesar-mixto').prop('disabled', resta > 0.05);
        });

        $('#btn-procesar-mixto').click(function() {
            let pagosMultiples = [];
            $('.metodo-row').each(function() {
                let id = parseInt($(this).data('id'));
                let u = parseFloat($(this).find('.input-monto-usd').val()) || 0;
                let b = parseFloat($(this).find('.input-monto-bs').val()) || 0;
                if(u > 0 || b > 0) {
                    pagosMultiples.push({id: id, monto_usd: u, monto_bs: b});
                }
            });
            $('#modalPago').modal('hide');
            procesarVenta('CONTADO', pagosMultiples);
        });

        // Boton Fiado Directo
        $('#btn-fiado').click(function() { procesarVenta('FIADO'); });
        $('#btn-anular').click(function() { cart = []; renderCart(); elements.buscador.focus(); });
        
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
    }

    function procesarVenta(tipo, arrayPagos = []) {
        if(cart.length === 0) {
            Swal.fire('Atención', 'El carrito está vacío', 'warning'); return;
        }
        
        $.post('/ELPROFE/api/ventas.php', {
            action: 'procesar_proforma',
            tipo: tipo,
            cliente_id: currentClienteId,
            productos: JSON.stringify(cart),
            pagos: JSON.stringify(arrayPagos)
        }, function(res) {
            if(res.success) {
                Swal.fire({
                    title: '¡Operación Exitosa!',
                    text: 'Proforma registrada correctamente. ID #' + res.proforma_id,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    cart = [];
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

    return { init: () => { renderCart(); initEvents(); elements.buscador.focus(); } };
})();

$(document).ready(function() { POS.init(); });
