<?php
session_start();

$titulo_pagina = "Productos Varios";
$icono_titulo = "fas fa-box-open";
$breadcrumb = [
    ['text' => 'Productos Varios', 'link' => '#', 'active' => true]
];

require_once 'config.php';
require_once 'funciones.php';
require_once 'header.php';

// Verificar permisos
if ($_SESSION['usuario_rol'] == 'vendedor') {
    $solo_lectura = true;
} else {
    $solo_lectura = false;
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones (solo administrador para crear/editar, pero permitir ajuste de stock para vendedores)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Verificar permisos para cada acción
    switch ($action) {
        case 'crear':
        case 'editar':
        case 'cambiar_estado':
            if ($solo_lectura) {
                $mensaje = "No tiene permisos para realizar esta acción";
                $tipo_mensaje = "danger";
                break;
            }
            // Procesar acciones de admin
            if ($action == 'crear') {
                $codigo = limpiar($_POST['codigo']);
                $nombre = limpiar($_POST['nombre']);
                $categoria = limpiar($_POST['categoria']);
                $unidad = limpiar($_POST['unidad']);
                $precio_compra = floatval($_POST['precio_compra'] ?? 0);
                $precio_venta = floatval($_POST['precio_venta']);
                $stock = intval($_POST['stock'] ?? 0);
                $stock_minimo = intval($_POST['stock_minimo'] ?? 5);
                $observaciones = limpiar($_POST['observaciones']);
                
                // Validar código único
                $check_query = "SELECT id FROM otros_productos WHERE codigo = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("s", $codigo);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $mensaje = "Ya existe un producto con el código '$codigo'";
                    $tipo_mensaje = "danger";
                    break;
                }
                
                $query = "INSERT INTO otros_productos 
                         (codigo, nombre, categoria, unidad, precio_compra, precio_venta, 
                          stock, stock_minimo, observaciones, activo) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssddiis", $codigo, $nombre, $categoria, $unidad, 
                                 $precio_compra, $precio_venta, $stock, $stock_minimo, $observaciones);
                
                if ($stmt->execute()) {
                    $mensaje = "Producto creado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al crear producto: " . $stmt->error;
                    $tipo_mensaje = "danger";
                }
            } elseif ($action == 'editar') {
                $id = intval($_POST['id']);
                $codigo = limpiar($_POST['codigo']);
                $nombre = limpiar($_POST['nombre']);
                $categoria = limpiar($_POST['categoria']);
                $unidad = limpiar($_POST['unidad']);
                $precio_compra = floatval($_POST['precio_compra'] ?? 0);
                $precio_venta = floatval($_POST['precio_venta']);
                $stock = intval($_POST['stock']);
                $stock_minimo = intval($_POST['stock_minimo']);
                $activo = isset($_POST['activo']) ? 1 : 0;
                $observaciones = limpiar($_POST['observaciones']);
                
                // Validar código único excepto para el mismo producto
                $check_query = "SELECT id FROM otros_productos WHERE codigo = ? AND id != ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("si", $codigo, $id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $mensaje = "Ya existe otro producto con el código '$codigo'";
                    $tipo_mensaje = "danger";
                    break;
                }
                
                $query = "UPDATE otros_productos SET 
                         codigo = ?, nombre = ?, categoria = ?, unidad = ?, 
                         precio_compra = ?, precio_venta = ?, stock = ?, 
                         stock_minimo = ?, activo = ?, observaciones = ?
                         WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssddiiisi", $codigo, $nombre, $categoria, $unidad,
                                 $precio_compra, $precio_venta, $stock, $stock_minimo,
                                 $activo, $observaciones, $id);
                
                if ($stmt->execute()) {
                    $mensaje = "Producto actualizado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al actualizar producto: " . $stmt->error;
                    $tipo_mensaje = "danger";
                }
            } elseif ($action == 'cambiar_estado') {
                $id = intval($_POST['id']);
                $activo = intval($_POST['activo']);
                
                $query = "UPDATE otros_productos SET activo = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $activo, $id);
                
                if ($stmt->execute()) {
                    $mensaje = "Estado del producto actualizado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al cambiar estado: " . $stmt->error;
                    $tipo_mensaje = "danger";
                }
            }
            break;
            
        case 'ajustar_stock':
            // Permitir ajuste de stock para todos los roles
            $id = intval($_POST['id']);
            $tipo_ajuste = $_POST['tipo_ajuste'];
            $cantidad = intval($_POST['cantidad']);
            $motivo = limpiar($_POST['motivo']);
            
            $query = "SELECT stock FROM otros_productos WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $producto = $result->fetch_assoc();
            
            if (!$producto) {
                $mensaje = "Producto no encontrado";
                $tipo_mensaje = "danger";
                break;
            }
            
            $nuevo_stock = $producto['stock'];
            if ($tipo_ajuste == 'incrementar') {
                $nuevo_stock += $cantidad;
            } else {
                $nuevo_stock -= $cantidad;
                if ($nuevo_stock < 0) $nuevo_stock = 0;
            }
            
            $query_update = "UPDATE otros_productos SET stock = ? WHERE id = ?";
            $stmt_update = $conn->prepare($query_update);
            $stmt_update->bind_param("ii", $nuevo_stock, $id);
            
            if ($stmt_update->execute()) {
                $mensaje = "Stock ajustado exitosamente";
                $tipo_mensaje = "success";
                
                // Aquí podrías agregar un registro en historial de stock
            } else {
                $mensaje = "Error al ajustar stock: " . $stmt_update->error;
                $tipo_mensaje = "danger";
            }
            break;
    }
}

// Obtener lista de productos
$query = "SELECT * FROM otros_productos ORDER BY nombre";
$result = $conn->query($query);

// Estadísticas
$query_total = "SELECT COUNT(*) as total FROM otros_productos";
$result_total = $conn->query($query_total);
$total_productos = $result_total->fetch_assoc()['total'] ?? 0;

$query_valor = "SELECT COALESCE(SUM(stock * precio_compra), 0) as valor_total FROM otros_productos WHERE stock > 0";
$result_valor = $conn->query($query_valor);
$valor_stock = $result_valor->fetch_assoc()['valor_total'] ?? 0;

$query_bajo = "SELECT COUNT(*) as total FROM otros_productos WHERE stock <= stock_minimo AND stock > 0";
$result_bajo = $conn->query($query_bajo);
$stock_bajo = $result_bajo->fetch_assoc()['total'] ?? 0;

$query_sin_stock = "SELECT COUNT(*) as total FROM otros_productos WHERE stock = 0";
$result_sin_stock = $conn->query($query_sin_stock);
$sin_stock = $result_sin_stock->fetch_assoc()['total'] ?? 0;
?>

<?php if ($mensaje): ?>
<div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
    <div class="d-flex align-items-center">
        <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
        <?php echo $mensaje; ?>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Resumen de productos -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2">Total Productos</h6>
                        <h2 class="card-title mb-0"><?php echo $total_productos; ?></h2>
                    </div>
                    <i class="fas fa-boxes fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2">Valor en Stock</h6>
                        <h2 class="card-title mb-0"><?php echo formatearMoneda($valor_stock); ?></h2>
                    </div>
                    <i class="fas fa-money-bill-wave fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2">Stock Bajo</h6>
                        <h2 class="card-title mb-0"><?php echo $stock_bajo; ?></h2>
                    </div>
                    <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card bg-danger text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2">Sin Stock</h6>
                        <h2 class="card-title mb-0"><?php echo $sin_stock; ?></h2>
                    </div>
                    <i class="fas fa-times-circle fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center bg-gradient">
                <h5 class="mb-0">
                    <i class="fas fa-boxes me-2 text-primary"></i>
                    Productos Varios (No-Lanas)
                </h5>
                <?php if (!$solo_lectura): ?>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoProducto">
                    <i class="fas fa-plus-circle me-1"></i>Nuevo Producto
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4 mb-2">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control" id="buscarProducto" 
                                   placeholder="Buscar por código, nombre o categoría..." 
                                   onkeyup="buscarProductos()">
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <select class="form-select" id="filtroCategoria" onchange="filtrarProductos()">
                            <option value=""> Todas las categorías</option>
                            <?php
                            $query_categorias = "SELECT DISTINCT categoria FROM otros_productos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria";
                            $result_categorias = $conn->query($query_categorias);
                            if ($result_categorias && $result_categorias->num_rows > 0) {
                                while ($categoria = $result_categorias->fetch_assoc()):
                                ?>
                                <option value="<?php echo htmlspecialchars($categoria['categoria']); ?>">
                                    <?php echo htmlspecialchars($categoria['categoria']); ?>
                                </option>
                                <?php 
                                endwhile;
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <select class="form-select" id="filtroStock" onchange="filtrarProductos()">
                            <option value="">Todos los stocks</option>
                            <option value="bajo"> Stock Bajo</option>
                            <option value="normal">Stock Normal</option>
                            <option value="sin_stock"> Sin Stock</option>
                        </select>
                    </div>
                </div>               
                <div class="table-responsive">
                    <table class="table table-hover" id="tablaProductos">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Unidad</th>
                                <th class="text-end">P. Compra</th>
                                <th class="text-end">P. Venta</th>
                                <th class="text-center">Stock</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($producto = $result->fetch_assoc()): 
                                    $stock_bajo = $producto['stock'] <= $producto['stock_minimo'] && $producto['stock'] > 0;
                                    $clase_fila = $producto['activo'] == 0 ? 'table-secondary' : ($stock_bajo ? 'table-warning' : '');
                                ?>
                                <tr class="<?php echo $clase_fila; ?>"
                                    data-categoria="<?php echo htmlspecialchars($producto['categoria'] ?? ''); ?>"
                                    data-stock="<?php echo $producto['stock']; ?>"
                                    data-stock-minimo="<?php echo $producto['stock_minimo']; ?>">
                                    <td>
                                        <span class="fw-medium"><?php echo htmlspecialchars($producto['codigo']); ?></span>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($producto['nombre']); ?></div>
                                        <?php if (!empty($producto['observaciones'])): ?>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-comment-dots me-1"></i>
                                            <?php echo htmlspecialchars(substr($producto['observaciones'], 0, 50)); ?>
                                            <?php if (strlen($producto['observaciones']) > 50): ?>...<?php endif; ?>
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $producto['categoria'] ? htmlspecialchars($producto['categoria']) : '<span class="text-muted">General</span>'; ?></td>
                                    <td>
                                        <?php 
                                        $unidades = [
                                            'unidad' => 'Unidad',
                                            'paquete' => 'Paquete',
                                            'docena' => 'Docena'
                                        ];
                                        echo $unidades[$producto['unidad']] ?? $producto['unidad'];
                                        ?>
                                    </td>
                                    <td class="text-end"><?php echo formatearMoneda($producto['precio_compra']); ?></td>
                                    <td class="text-end">
                                        <span class="fw-bold text-primary"><?php echo formatearMoneda($producto['precio_venta']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($producto['stock'] > 0): ?>
                                            <span class="badge bg-<?php echo $stock_bajo ? 'warning text-dark' : 'success'; ?> px-3 py-2">
                                                <?php echo $producto['stock']; ?>
                                            </span>
                                            <?php if ($stock_bajo): ?>
                                            <br><small class="text-danger">Mín: <?php echo $producto['stock_minimo']; ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-danger px-3 py-2">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($producto['activo'] == 1): ?>
                                            <span class="badge bg-success px-3 py-2">
                                                <i class="fas fa-check-circle me-1"></i>Activo
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger px-3 py-2">
                                                <i class="fas fa-ban me-1"></i>Inactivo
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if (!$solo_lectura): ?>
                                            <button class="btn btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEditarProducto"
                                                    onclick='cargarDatosProducto(<?php echo json_encode($producto); ?>)'
                                                    title="Editar producto">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <!-- Botón de ajustar stock disponible para todos -->
                                            <button class="btn btn-outline-info"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalAjustarStock"
                                                    onclick="cargarProductoAjuste(<?php echo $producto['id']; ?>, '<?php echo htmlspecialchars(addslashes($producto['nombre'])); ?>')"
                                                    title="Ajustar stock">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                            
                                            <?php if (!$solo_lectura): ?>
                                            <button class="btn btn-outline-<?php echo $producto['activo'] ? 'danger' : 'success'; ?>"
                                                    onclick="cambiarEstadoProducto(<?php echo $producto['id']; ?>, <?php echo $producto['activo']; ?>, '<?php echo htmlspecialchars(addslashes($producto['nombre'])); ?>')"
                                                    title="<?php echo $producto['activo'] ? 'Desactivar' : 'Activar'; ?> producto">
                                                <i class="fas fa-<?php echo $producto['activo'] ? 'ban' : 'check-circle'; ?>"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-box-open fa-4x mb-3"></i>
                                        <h5>No hay productos registrados</h5>
                                        <?php if (!$solo_lectura): ?>
                                        <p>Haga clic en "Nuevo Producto" para comenzar.</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuevo Producto (solo admin) -->
<?php if (!$solo_lectura): ?>
<div class="modal fade" id="modalNuevoProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" id="formNuevoProducto" novalidate>
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>
                        Nuevo Producto
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="crear">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Código <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="codigo" required 
                                   placeholder="Ej: PAL-001, AGU-001">
                            <div class="invalid-feedback">El código es requerido</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Nombre del Producto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre" required>
                            <div class="invalid-feedback">El nombre es requerido</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Categoría</label>
                            <input type="text" class="form-control" name="categoria" 
                                   placeholder="Ej: Herramientas, Accesorios">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Unidad <span class="text-danger">*</span></label>
                            <select class="form-select" name="unidad" required>
                                <option value="">Seleccionar...</option>
                                <option value="unidad">Unidad</option>
                                <option value="paquete">Paquete</option>
                                <option value="docena">Docena</option>
                            </select>
                            <div class="invalid-feedback">Seleccione una unidad</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Precio de Compra (Bs)</label>
                            <div class="input-group">
                                <span class="input-group-text">Bs</span>
                                <input type="number" class="form-control" name="precio_compra" 
                                       step="0.01" min="0" value="0" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Precio de Venta (Bs) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Bs</span>
                                <input type="number" class="form-control" name="precio_venta" 
                                       step="0.01" min="0" required placeholder="0.00">
                                <div class="invalid-feedback">Ingrese un precio válido</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Stock Inicial</label>
                            <input type="number" class="form-control" name="stock" 
                                   min="0" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Stock Mínimo</label>
                            <input type="number" class="form-control" name="stock_minimo" 
                                   min="0" value="5">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>Crear Producto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Producto -->
<div class="modal fade" id="modalEditarProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" id="formEditarProducto" novalidate>
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        Editar Producto
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="editar">
                    <input type="hidden" name="id" id="editProductoId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Código <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="codigo" id="editCodigo" required>
                            <div class="invalid-feedback">El código es requerido</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Nombre del Producto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre" id="editNombre" required>
                            <div class="invalid-feedback">El nombre es requerido</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Categoría</label>
                            <input type="text" class="form-control" name="categoria" id="editCategoria"
                                   placeholder="Ej: Herramientas, Accesorios">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Unidad <span class="text-danger">*</span></label>
                            <select class="form-select" name="unidad" id="editUnidad" required>
                                <option value="unidad">Unidad</option>
                                <option value="paquete">Paquete</option>
                                <option value="docena">Docena</option>
                            </select>
                            <div class="invalid-feedback">Seleccione una unidad</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Precio de Compra (Bs)</label>
                            <div class="input-group">
                                <span class="input-group-text">Bs</span>
                                <input type="number" class="form-control" name="precio_compra" 
                                       id="editPrecioCompra" step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Precio de Venta (Bs) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Bs</span>
                                <input type="number" class="form-control" name="precio_venta" 
                                       id="editPrecioVenta" step="0.01" min="0" required placeholder="0.00">
                                <div class="invalid-feedback">Ingrese un precio válido</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Stock Actual</label>
                            <input type="number" class="form-control" name="stock" 
                                   id="editStock" min="0" readonly class="bg-light">
                            <small class="text-muted">Use el módulo de ajuste de stock</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Stock Mínimo</label>
                            <input type="number" class="form-control" name="stock_minimo" 
                                   id="editStockMinimo" min="0" value="5">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Estado</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="activo" id="editActivo" value="1" checked>
                                <label class="form-check-label" for="editActivo">
                                    Producto Activo
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Observaciones</label>
                            <textarea class="form-control" name="observaciones" id="editObservaciones" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Ajustar Stock (SIEMPRE visible para todos los roles) -->
<div class="modal fade" id="modalAjustarStock" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="formAjustarStock" novalidate>
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-exchange-alt me-2"></i>
                        Ajustar Stock
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="ajustar_stock">
                    <input type="hidden" name="id" id="ajusteProductoId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Producto</label>
                        <input type="text" class="form-control bg-light" id="ajusteProductoNombre" readonly>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tipo de Ajuste <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipo_ajuste" id="tipoAjuste" required>
                                <option value="incrementar">Incrementar Stock</option>
                                <option value="decrementar">Decrementar Stock</option>
                            </select>
                            <div class="invalid-feedback">Seleccione el tipo de ajuste</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Cantidad <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="cantidad" 
                                   id="ajusteCantidad" min="1" required value="1">
                            <div class="invalid-feedback">Ingrese una cantidad válida</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Motivo del Ajuste <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="motivo" rows="3" required 
                                  placeholder="Ej: Compra a proveedor, Venta sin registrar, Inventario físico..."></textarea>
                        <div class="invalid-feedback">Describa el motivo del ajuste</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-check-circle me-1"></i>Aplicar Ajuste
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Función para mostrar loading
function mostrarLoading(mensaje = 'Procesando...') {
    let loading = document.getElementById('loadingOverlay');
    if (!loading) {
        loading = document.createElement('div');
        loading.id = 'loadingOverlay';
        loading.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 99999;
        `;
        loading.innerHTML = `
            <div class="text-center text-white">
                <div class="spinner-border mb-3" style="width: 3rem; height: 3rem;"></div>
                <h6>${mensaje}</h6>
            </div>
        `;
        document.body.appendChild(loading);
    }
}

function ocultarLoading() {
    const loading = document.getElementById('loadingOverlay');
    if (loading) loading.remove();
}

// Función para cambiar estado
function cambiarEstadoProducto(id, estadoActual, nombre) {
    <?php if ($solo_lectura): ?>
    alert('No tiene permisos para realizar esta acción');
    return;
    <?php endif; ?>
    
    const nuevoEstado = estadoActual == 1 ? 0 : 1;
    const accion = nuevoEstado == 1 ? 'ACTIVAR' : 'DESACTIVAR';
    
    if (confirm(` ¿Está seguro de ${accion} el producto?\n\n"${nombre}"\n\nEsta acción cambiará su disponibilidad en ventas.`)) {
        mostrarLoading('Cambiando estado...');
        
        const formData = new FormData();
        formData.append('action', 'cambiar_estado');
        formData.append('id', id);
        formData.append('activo', nuevoEstado);
        
        fetch('otros_productos.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(() => {
            ocultarLoading();
            location.reload();
        })
        .catch(error => {
            ocultarLoading();
            console.error('Error:', error);
            alert(' Error al cambiar el estado del producto');
        });
    }
}

// Función para cargar datos en el modal de edición
function cargarDatosProducto(producto) {
    <?php if ($solo_lectura): ?>
    alert('No tiene permisos para editar productos');
    return;
    <?php endif; ?>
    
    document.getElementById('editProductoId').value = producto.id;
    document.getElementById('editCodigo').value = producto.codigo || '';
    document.getElementById('editNombre').value = producto.nombre || '';
    document.getElementById('editCategoria').value = producto.categoria || '';
    document.getElementById('editUnidad').value = producto.unidad || 'unidad';
    document.getElementById('editPrecioCompra').value = producto.precio_compra || 0;
    document.getElementById('editPrecioVenta').value = producto.precio_venta || 0;
    document.getElementById('editStock').value = producto.stock || 0;
    document.getElementById('editStockMinimo').value = producto.stock_minimo || 5;
    document.getElementById('editObservaciones').value = producto.observaciones || '';
    document.getElementById('editActivo').checked = producto.activo == 1;
}

// Función para cargar datos en el modal de ajuste de stock
function cargarProductoAjuste(id, nombre) {
    document.getElementById('ajusteProductoId').value = id;
    document.getElementById('ajusteProductoNombre').value = nombre;
    document.getElementById('ajusteCantidad').value = 1;
    document.getElementById('tipoAjuste').value = 'incrementar';
}

// Función de búsqueda mejorada
function buscarProductos() {
    const input = document.getElementById('buscarProducto');
    const filter = input.value.toUpperCase();
    const table = document.getElementById('tablaProductos');
    const tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        let mostrar = false;
        const tds = tr[i].getElementsByTagName('td');
        
        for (let j = 0; j < tds.length - 1; j++) {
            if (tds[j]) {
                const txtValue = tds[j].textContent || tds[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    mostrar = true;
                    break;
                }
            }
        }
        
        tr[i].style.display = mostrar ? '' : 'none';
    }
}

// Función de filtrado mejorada
function filtrarProductos() {
    const categoria = document.getElementById('filtroCategoria').value;
    const stock = document.getElementById('filtroStock').value;
    const table = document.getElementById('tablaProductos');
    const tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        let mostrar = true;
        const fila = tr[i];
        
        // Filtrar por categoría
        if (categoria) {
            const categoriaFila = fila.getAttribute('data-categoria') || '';
            if (categoriaFila !== categoria) {
                mostrar = false;
            }
        }
        
        // Filtrar por stock
        if (mostrar && stock) {
            const stockFila = parseInt(fila.getAttribute('data-stock')) || 0;
            const stockMinimo = parseInt(fila.getAttribute('data-stock-minimo')) || 5;
            
            switch (stock) {
                case 'bajo':
                    if (stockFila === 0 || stockFila > stockMinimo) mostrar = false;
                    break;
                case 'sin_stock':
                    if (stockFila > 0) mostrar = false;
                    break;
                case 'normal':
                    if (stockFila === 0 || stockFila <= stockMinimo) mostrar = false;
                    break;
            }
        }
        
        tr[i].style.display = mostrar ? '' : 'none';
    }
}

// Validación de formularios con Bootstrap 5
(function() {
    'use strict';
    
    // Validar formulario nuevo producto (solo si existe)
    const formNuevo = document.getElementById('formNuevoProducto');
    if (formNuevo) {
        formNuevo.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        }, false);
    }
    
    // Validar formulario editar producto (solo si existe)
    const formEditar = document.getElementById('formEditarProducto');
    if (formEditar) {
        formEditar.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        }, false);
    }
    
    // Validar formulario ajustar stock (siempre existe)
    const formAjuste = document.getElementById('formAjustarStock');
    if (formAjuste) {
        formAjuste.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        }, false);
    }
})();
</script>

<style>
.bg-gradient {
    background: linear-gradient(45deg, #f8f9fc, #e9ecef);
}

.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,0.02) !important;
}

.table-warning {
    background-color: rgba(255, 193, 7, 0.05) !important;
}

.table-secondary {
    background-color: rgba(108, 117, 125, 0.05) !important;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.opacity-50 {
    opacity: 0.5;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.9em;
    }
    
    .btn-group-sm .btn {
        padding: 0.2rem 0.4rem;
    }
}
</style>

<?php 
// Liberar resultados
if (isset($result)) $result->free();
if (isset($result_categorias)) $result_categorias->free();
if (isset($result_total)) $result_total->free();
if (isset($result_valor)) $result_valor->free();
if (isset($result_bajo)) $result_bajo->free();
if (isset($result_sin_stock)) $result_sin_stock->free();

require_once 'footer.php'; 
?>