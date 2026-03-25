<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
restrictAdmin();
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-boxes-stacked me-2"></i> Inventario</h2>
    <div>
        <a href="/compras" class="btn btn-outline-primary"><i class="fa-solid fa-plus me-1"></i> Añadir Stock (Via Compras)</a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th width="8%" class="text-center">Foto</th>
                        <th width="12%">Código</th>
                        <th width="30%">Producto</th>
                        <th width="15%" class="text-center">Stock</th>
                        <th width="15%" class="text-end">Costo PROM (USD)</th>
                        <th width="15%" class="text-end">Venta (USD)</th>
                        <th width="5%" class="text-center"><i class="fa-solid fa-tools"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT id, codigo_barras, nombre, foto, stock_actual, costo_promedio_usd, precio_venta_usd FROM productos ORDER BY nombre ASC");
                    $productos = $stmt->fetchAll();
                    
                    if (count($productos) > 0) {
                        foreach($productos as $p) {
                            $stockBadge = $p['stock_actual'] <= 5 ? 'bg-danger' : 'bg-success';
                            $imgSrc = $p['foto'] ? "/assets/img/productos/{$p['foto']}" : "https://ui-avatars.com/api/?name=".urlencode($p['nombre'])."&background=random&size=50";
                            
                            echo "<tr>
                                    <td class='text-center'><img src='".e($imgSrc)."' class='rounded shadow-sm' style='width: 40px; height: 40px; object-fit: cover;'></td>
                                    <td><kbd>".e($p['codigo_barras'])."</kbd></td>
                                    <td><span class='fw-bold'>".e($p['nombre'])."</span></td>
                                    <td class='text-center'><span class='badge {$stockBadge} px-3 py-2'>".floatval($p['stock_actual'])."</span></td>
                                    <td class='text-end'>$".formatMoney($p['costo_promedio_usd'])."</td>
                                    <td class='text-end fw-bold text-primary'>$".formatMoney($p['precio_venta_usd'])."</td>
                                    <td class='text-center'>
                                        <button class='btn btn-sm btn-outline-secondary btn-editar-foto' data-id='".e($p['id'])."' data-nombre='".e($p['nombre'])."' title='Editar Foto'>
                                            <i class='fa-solid fa-camera'></i>
                                        </button>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center text-muted py-5'><i class='fa-solid fa-box-open fa-3x mb-3 text-light'></i><h5 class='mb-0'>No hay productos en inventario</h5></td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Foto Producto -->
<div class="modal fade" id="modalFoto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Actualizar Foto: <span id="producto-nombre-modal" class="text-primary"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="form-foto" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_foto">
            <input type="hidden" name="producto_id" id="modal-producto-id">
            <!-- Fetch csrf field para ajax o form submit -->
            <input type="hidden" name="csrf_token" id="csrf_token_foto" value="">
            
            <div class="mb-3 text-center">
                <i class="fa-solid fa-cloud-arrow-up fa-4x text-muted mb-3"></i>
                <input class="form-control form-control-lg" id="fotoFile" type="file" name="foto" accept="image/jpeg, image/png, image/webp" required>
                <div class="form-text mt-2">Formatos permitidos: JPG, PNG, WEBP. Max: 2MB.</div>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btn-upload"><i class="fa-solid fa-save"></i> Guardar Foto</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    // Tomar token CSRF para inyectarlo en formulareios no renderizados via PHP (simulado)
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    $('#csrf_token_foto').val(csrfToken);

    $('.btn-editar-foto').on('click', function() {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        
        $('#modal-producto-id').val(id);
        $('#producto-nombre-modal').text(nombre);
        $('#fotoFile').val('');
        
        var modal = new bootstrap.Modal(document.getElementById('modalFoto'));
        modal.show();
    });
    
    $('#btn-upload').click(function() {
        if(!$('#fotoFile').val()) {
            Swal.fire('Error', 'Seleccione una imagen primero.', 'error');
            return;
        }
        
        var formData = new FormData($('#form-foto')[0]);
        
        $.ajax({
            url: '/api/producto.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            beforeSend: function() {
                Swal.fire({title: 'Subiendo...', allowOutsideClick: false});
                Swal.showLoading();
            },
            success: function(res) {
                if(res.success) {
                    Swal.fire('¡Éxito!', 'Foto actualizada correctamente.', 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', res.message || 'Error al subir', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Error de red o permisos incorrectos.', 'error');
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
