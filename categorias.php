<?php

session_start();

$titulo_pagina = "Categorías por Proveedor";
$icono_titulo = "fas fa-tags";
$breadcrumb = [
    ['text' => 'Proveedores', 'link' => 'proveedores.php', 'active' => false],
    ['text' => 'Categorías', 'link' => '#', 'active' => true]
];

require_once 'header.php';
require_once 'funciones.php';

// Verificar que sea administrador
verificarRol(['administrador']);

$proveedor_id = $_GET['proveedor_id'] ?? null;
$mensaje = '';
$tipo_mensaje = '';

// Procesar acción de cambiar estado (si viene por GET)
if (isset($_GET['cambiar_estado'])) {
    $id = intval($_GET['cambiar_estado']);
    $nuevo_estado = isset($_GET['nuevo_estado']) ? intval($_GET['nuevo_estado']) : 0;
    
    $query = "UPDATE categorias SET activo = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $nuevo_estado, $id);
    
    if ($stmt->execute()) {
        $accion = $nuevo_estado == 1 ? 'activada' : 'desactivada';
        $mensaje = "Categoría $accion exitosamente";
        $tipo_mensaje = "success";
    } else {
        $mensaje = "Error al cambiar estado de la categoría";
        $tipo_mensaje = "danger";
    }
}

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'crear':
            $proveedor_id_post = intval($_POST['proveedor_id']);
            $nombre = limpiar($_POST['nombre']);
            $subpaquetes_por_paquete = intval($_POST['subpaquetes_por_paquete']);
            $descripcion = limpiar($_POST['descripcion']);
            
            // Verificar si la categoría ya existe para este proveedor
            $check_query = "SELECT id FROM categorias WHERE proveedor_id = ? AND nombre = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("is", $proveedor_id_post, $nombre);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $mensaje = "Error: Ya existe una categoría con ese nombre para este proveedor";
                $tipo_mensaje = "danger";
            } else {
                $query = "INSERT INTO categorias 
                         (proveedor_id, nombre, subpaquetes_por_paquete, descripcion) 
                         VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("isis", $proveedor_id_post, $nombre, $subpaquetes_por_paquete, $descripcion);
                
                if ($stmt->execute()) {
                    $mensaje = "Categoría creada exitosamente";
                    $tipo_mensaje = "success";
                    // Redirigir al proveedor si fue creado desde su vista
                    if ($proveedor_id_post && !$proveedor_id) {
                        $proveedor_id = $proveedor_id_post;
                    }
                } else {
                    $mensaje = "Error al crear categoría: " . $stmt->error;
                    $tipo_mensaje = "danger";
                }
            }
            break;
            
        case 'editar':
            $id = intval($_POST['id']);
            $nombre = limpiar($_POST['nombre']);
            $subpaquetes_por_paquete = intval($_POST['subpaquetes_por_paquete']);
            $descripcion = limpiar($_POST['descripcion']);
            $activo = isset($_POST['activo']) ? 1 : 0;
            
            // Obtener proveedor_id actual para validación
            $query_prov = "SELECT proveedor_id FROM categorias WHERE id = ?";
            $stmt_prov = $conn->prepare($query_prov);
            $stmt_prov->bind_param("i", $id);
            $stmt_prov->execute();
            $result_prov = $stmt_prov->get_result();
            $categoria_actual = $result_prov->fetch_assoc();
            
            // Verificar si el nombre ya existe para el mismo proveedor (excluyendo la actual)
            $check_query = "SELECT id FROM categorias WHERE proveedor_id = ? AND nombre = ? AND id != ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("isi", $categoria_actual['proveedor_id'], $nombre, $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $mensaje = "Error: Ya existe otra categoría con ese nombre para este proveedor";
                $tipo_mensaje = "danger";
            } else {
                $query = "UPDATE categorias SET 
                         nombre = ?, subpaquetes_por_paquete = ?, descripcion = ?, activo = ?
                         WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sisii", $nombre, $subpaquetes_por_paquete, $descripcion, $activo, $id);
                
                if ($stmt->execute()) {
                    $mensaje = "Categoría actualizada exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al actualizar categoría: " . $stmt->error;
                    $tipo_mensaje = "danger";
                }
            }
            break;
    }
}

// Obtener lista de proveedores para el selector
$query_proveedores = "SELECT id, codigo, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre";
$result_proveedores = $conn->query($query_proveedores);

// Obtener categorías
if ($proveedor_id) {
    $query_categorias = "SELECT c.*, p.nombre as proveedor_nombre, p.codigo as proveedor_codigo
                        FROM categorias c 
                        JOIN proveedores p ON c.proveedor_id = p.id 
                        WHERE c.proveedor_id = ? 
                        ORDER BY c.nombre";
    $stmt = $conn->prepare($query_categorias);
    $stmt->bind_param("i", $proveedor_id);
    $stmt->execute();
    $result_categorias = $stmt->get_result();
    
    // Obtener información del proveedor seleccionado
    $query_proveedor = "SELECT nombre, codigo FROM proveedores WHERE id = ?";
    $stmt_proveedor = $conn->prepare($query_proveedor);
    $stmt_proveedor->bind_param("i", $proveedor_id);
    $stmt_proveedor->execute();
    $proveedor_seleccionado = $stmt_proveedor->get_result()->fetch_assoc();
} else {
    $query_categorias = "SELECT c.*, p.nombre as proveedor_nombre, p.codigo as proveedor_codigo
                        FROM categorias c 
                        JOIN proveedores p ON c.proveedor_id = p.id 
                        ORDER BY p.nombre, c.nombre";
    $result_categorias = $conn->query($query_categorias);
}

// Contar productos por categoría
$productos_por_categoria = [];
$query_productos = "SELECT categoria_id, COUNT(*) as total FROM productos GROUP BY categoria_id";
$result_productos = $conn->query($query_productos);
while ($row = $result_productos->fetch_assoc()) {
    $productos_por_categoria[$row['categoria_id']] = $row['total'];
}
?>

<?php if ($mensaje): ?>
<div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
    <div class="d-flex align-items-center">
        <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $mensaje; ?>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="fas fa-tags me-2"></i>
                        <?php if ($proveedor_id): ?>
                            Categorías de: <span class="text-success"><?php echo htmlspecialchars($proveedor_seleccionado['nombre']); ?></span>
                        <?php else: ?>
                            Todas las Categorías
                        <?php endif; ?>
                    </h5>
                    <?php if ($proveedor_id): ?>
                        <small class="text-muted">Proveedor: <?php echo htmlspecialchars($proveedor_seleccionado['codigo']); ?></small>
                    <?php endif; ?>
                </div>
                <div class="btn-group">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNuevaCategoria">
                        <i class="fas fa-plus me-2"></i>Nueva Categoría
                    </button>
                    <a href="proveedores.php" class="btn btn-outline-primary ms-2">
                        <i class="fas fa-arrow-left me-2"></i>Volver a Proveedores
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Filtros y buscador -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control" id="buscarCategoria" 
                                   placeholder="Buscar categoría...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-filter"></i>
                            </span>
                            <select class="form-select" id="filtroEstado" onchange="filtrarCategorias()">
                                <option value="">Todos los estados</option>
                                <option value="1">Activas</option>
                                <option value="0">Inactivas</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-truck"></i>
                            </span>
                            <select class="form-select" id="filtroProveedor" onchange="filtrarCategorias()">
                                <option value="">Todos los proveedores</option>
                                <?php 
                                $result_proveedores_filtro = $conn->query($query_proveedores);
                                while ($proveedor = $result_proveedores_filtro->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $proveedor['id']; ?>"
                                    <?php echo ($proveedor_id == $proveedor['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($proveedor['nombre']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Tarjetas de resumen -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 opacity-75">Total Categorías</h6>
                                        <h2 class="card-title mb-0">
                                            <?php
                                            $query_total = "SELECT COUNT(*) as total FROM categorias" . 
                                                          ($proveedor_id ? " WHERE proveedor_id = $proveedor_id" : "");
                                            $result_total = $conn->query($query_total);
                                            echo number_format($result_total->fetch_assoc()['total']);
                                            ?>
                                        </h2>
                                    </div>
                                    <i class="fas fa-tags fa-2x opacity-25"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 opacity-75">Categorías Activas</h6>
                                        <h2 class="card-title mb-0">
                                            <?php
                                            $query_activas = "SELECT COUNT(*) as total FROM categorias WHERE activo = 1" . 
                                                            ($proveedor_id ? " AND proveedor_id = $proveedor_id" : "");
                                            $result_activas = $conn->query($query_activas);
                                            echo number_format($result_activas->fetch_assoc()['total']);
                                            ?>
                                        </h2>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x opacity-25"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 opacity-75">Categorías Inactivas</h6>
                                        <h2 class="card-title mb-0">
                                            <?php
                                            $query_inactivas = "SELECT COUNT(*) as total FROM categorias WHERE activo = 0" . 
                                                              ($proveedor_id ? " AND proveedor_id = $proveedor_id" : "");
                                            $result_inactivas = $conn->query($query_inactivas);
                                            echo number_format($result_inactivas->fetch_assoc()['total']);
                                            ?>
                                        </h2>
                                    </div>
                                    <i class="fas fa-times-circle fa-2x opacity-25"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 opacity-75">Productos Asociados</h6>
                                        <h2 class="card-title mb-0">
                                            <?php
                                            $query_productos_total = "SELECT COUNT(*) as total FROM productos" . 
                                                                    ($proveedor_id ? " WHERE proveedor_id = $proveedor_id" : "");
                                            $result_productos_total = $conn->query($query_productos_total);
                                            echo number_format($result_productos_total->fetch_assoc()['total']);
                                            ?>
                                        </h2>
                                    </div>
                                    <i class="fas fa-palette fa-2x opacity-25"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de categorías -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="tablaCategorias">
                        <thead class="table-light">
                            <tr>
                                <th width="200">Categoría</th>
                                <th width="150">Proveedor</th>
                                <th width="120" class="text-center">Subp. x Paq.</th>
                                <th>Descripción</th>
                                <th width="80" class="text-center">Productos</th>
                                <th width="100">Estado</th>
                                <th width="140" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_categorias->num_rows > 0): ?>
                                <?php while ($categoria = $result_categorias->fetch_assoc()): 
                                    $productos_count = $productos_por_categoria[$categoria['id']] ?? 0;
                                ?>
                                <tr data-proveedor-id="<?php echo $categoria['proveedor_id']; ?>"
                                    data-estado="<?php echo $categoria['activo']; ?>"
                                    data-busqueda="<?php echo strtolower(htmlspecialchars($categoria['nombre'] . ' ' . $categoria['proveedor_nombre'] . ' ' . $categoria['descripcion'])); ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                <i class="fas fa-tag"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($categoria['nombre']); ?></strong>
                                                <?php if (!$proveedor_id): ?>
                                                <div class="small text-muted">
                                                    <i class="fas fa-truck me-1"></i>
                                                    <?php echo htmlspecialchars($categoria['proveedor_codigo']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!$proveedor_id): ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-industry me-1"></i>
                                            <?php echo htmlspecialchars($categoria['proveedor_nombre']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary" title="Subpaquetes por paquete">
                                            <i class="fas fa-boxes me-1"></i>
                                            <?php echo $categoria['subpaquetes_por_paquete']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($categoria['descripcion']): ?>
                                            <small class="text-muted" title="<?php echo htmlspecialchars($categoria['descripcion']); ?>">
                                                <?php echo substr($categoria['descripcion'], 0, 50); ?>
                                                <?php echo strlen($categoria['descripcion']) > 50 ? '...' : ''; ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted small">Sin descripción</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $productos_count > 0 ? 'success' : 'secondary'; ?>">
                                            <i class="fas fa-palette me-1"></i>
                                            <?php echo $productos_count; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($categoria['activo'] == 1): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i>Activa
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times-circle me-1"></i>Inactiva
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEditarCategoria"
                                                    onclick="cargarDatosCategoria(<?php echo htmlspecialchars(json_encode($categoria)); ?>)"
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="productos.php?categoria_id=<?php echo $categoria['id']; ?>" 
                                               class="btn btn-outline-success"
                                               title="Ver Productos">
                                                <i class="fas fa-palette"></i>
                                            </a>
                                            <button class="btn btn-outline-<?php echo $categoria['activo'] ? 'danger' : 'success'; ?>"
                                                    onclick="cambiarEstadoCategoria(<?php echo $categoria['id']; ?>, <?php echo $categoria['activo']; ?>, '<?php echo addslashes($categoria['nombre']); ?>')"
                                                    title="<?php echo $categoria['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                                <i class="fas fa-<?php echo $categoria['activo'] ? 'ban' : 'check'; ?>"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                                            <h5>No hay categorías registradas</h5>
                                            <p class="text-muted">
                                                <?php if ($proveedor_id): ?>
                                                    Este proveedor no tiene categorías aún.
                                                <?php else: ?>
                                                    No se encontraron categorías en el sistema.
                                                <?php endif; ?>
                                            </p>
                                            <button class="btn btn-success mt-2" data-bs-toggle="modal" data-bs-target="#modalNuevaCategoria">
                                                <i class="fas fa-plus me-2"></i>Crear primera categoría
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="7" class="text-muted small">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-info-circle me-1"></i>
                                            Mostrando <?php echo $result_categorias->num_rows; ?> categorías
                                        </div>
                                        <div class="text-end">
                                            <button class="btn btn-sm btn-outline-secondary" onclick="exportarTabla()">
                                                <i class="fas fa-file-excel me-1"></i>Exportar
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary ms-1" onclick="imprimirTabla()">
                                                <i class="fas fa-print me-1"></i>Imprimir
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Categoría -->
<div class="modal fade" id="modalNuevaCategoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="formNuevaCategoria">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Nueva Categoría</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="crear">
                    
                    <div class="mb-3">
                        <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                        <select class="form-select" name="proveedor_id" required>
                            <option value="">Seleccionar proveedor...</option>
                            <?php 
                            $result_proveedores_modal = $conn->query($query_proveedores);
                            while ($proveedor = $result_proveedores_modal->fetch_assoc()):
                            ?>
                            <option value="<?php echo $proveedor['id']; ?>" 
                                <?php echo ($proveedor_id == $proveedor['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($proveedor['codigo'] . ' - ' . $proveedor['nombre']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre de la Categoría <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-tag"></i>
                            </span>
                            <input type="text" class="form-control" name="nombre" required 
                                   placeholder="Ej: Acuarela, Bebé, Premium">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subpaquetes por Paquete <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-boxes"></i>
                                </span>
                                <input type="number" class="form-control" name="subpaquetes_por_paquete" 
                                       value="10" min="1" max="100" required>
                            </div>
                            <div class="form-text">Generalmente 10 subpaquetes por paquete</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado Inicial</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="activo_inicial" 
                                       id="activoInicial" value="1" checked role="switch">
                                <label class="form-check-label" for="activoInicial">
                                    Categoría Activa
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3" 
                                  placeholder="Características de esta categoría..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>Crear Categoría
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Categoría -->
<div class="modal fade" id="modalEditarCategoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="formEditarCategoria">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Categoría</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="editar">
                    <input type="hidden" name="id" id="editCategoriaId">
                    
                    <div class="mb-3">
                        <label class="form-label">Proveedor</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-industry"></i>
                            </span>
                            <input type="text" class="form-control" id="editProveedor" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre de la Categoría <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-tag"></i>
                            </span>
                            <input type="text" class="form-control" name="nombre" id="editNombre" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subpaquetes por Paquete <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-boxes"></i>
                                </span>
                                <input type="number" class="form-control" name="subpaquetes_por_paquete" 
                                       id="editSubpaquetes" min="1" max="100" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="activo" 
                                       id="editActivo" value="1" role="switch">
                                <label class="form-check-label" for="editActivo">
                                    <span id="estadoCategoria">Categoría Activa</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="editDescripcion" rows="3"></textarea>
                    </div>
                    
                    <div class="alert alert-light">
                        <i class="fas fa-info-circle me-2"></i>
                        <small class="text-muted">Al desactivar una categoría, los productos asociados no se mostrarán en ventas.</small>
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

<script>
function cargarDatosCategoria(categoria) {
    document.getElementById('editCategoriaId').value = categoria.id;
    document.getElementById('editProveedor').value = categoria.proveedor_codigo + ' - ' + categoria.proveedor_nombre;
    document.getElementById('editNombre').value = categoria.nombre;
    document.getElementById('editSubpaquetes').value = categoria.subpaquetes_por_paquete;
    document.getElementById('editDescripcion').value = categoria.descripcion || '';
    
    const activoCheckbox = document.getElementById('editActivo');
    const estadoLabel = document.getElementById('estadoCategoria');
    
    activoCheckbox.checked = categoria.activo == 1;
    estadoLabel.textContent = categoria.activo == 1 ? 'Categoría Activa' : 'Categoría Inactiva';
}

function cambiarEstadoCategoria(id, estadoActual, nombre) {
    const nuevoEstado = estadoActual == 1 ? 0 : 1;
    const accion = nuevoEstado == 1 ? 'activar' : 'desactivar';
    const mensaje = estadoActual == 1 ? 
        `¿Desactivar la categoría "${nombre}"?\n\nLos productos de esta categoría no se mostrarán en ventas hasta que sea reactivada.` :
        `¿Activar la categoría "${nombre}"?\n\nLos productos de esta categoría estarán disponibles nuevamente.`;
    
    if (confirm(mensaje)) {
        window.location.href = `categorias.php?cambiar_estado=${id}&nuevo_estado=${nuevoEstado}<?php echo $proveedor_id ? '&proveedor_id=' . $proveedor_id : ''; ?>`;
    }
}

function buscarCategorias() {
    const filter = document.getElementById('buscarCategoria').value.toLowerCase();
    const rows = document.querySelectorAll('#tablaCategorias tbody tr');
    
    rows.forEach(row => {
        const searchText = row.getAttribute('data-busqueda') || '';
        if (searchText.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filtrarCategorias() {
    const proveedorId = document.getElementById('filtroProveedor').value;
    const estado = document.getElementById('filtroEstado').value;
    const rows = document.querySelectorAll('#tablaCategorias tbody tr');
    
    rows.forEach(row => {
        const rowProveedorId = row.getAttribute('data-proveedor-id');
        const rowEstado = row.getAttribute('data-estado');
        let mostrar = true;
        
        if (proveedorId && rowProveedorId !== proveedorId) {
            mostrar = false;
        }
        
        if (estado !== '' && rowEstado !== estado) {
            mostrar = false;
        }
        
        row.style.display = mostrar ? '' : 'none';
    });
}

function exportarTabla() {
    alert('Funcionalidad de exportación en desarrollo.\nSe generará un archivo Excel con los datos de categorías.');
}

function imprimirTabla() {
    const printContent = document.getElementById('tablaCategorias').outerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <html>
            <head>
                <title>Lista de Categorías - <?php echo EMPRESA_NOMBRE; ?></title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    @media print {
                        .no-print { display: none !important; }
                        body { padding: 20px; }
                        table { font-size: 12px; }
                        .badge { border: 1px solid #000 !important; }
                    }
                    h1 { margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                    .summary { margin-bottom: 20px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1 class="text-center">Lista de Categorías</h1>
                    <div class="summary">
                        <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i'); ?></p>
                        <p><strong>Sistema:</strong> <?php echo SISTEMA_NOMBRE; ?></p>
                        <?php if ($proveedor_id): ?>
                        <p><strong>Proveedor:</strong> <?php echo htmlspecialchars($proveedor_seleccionado['nombre']); ?></p>
                        <?php endif; ?>
                    </div>
                    ${printContent}
                    <div class="mt-4 text-center text-muted">
                        <small>Documento generado automáticamente</small>
                    </div>
                </div>
            </body>
        </html>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

// Inicializar eventos
document.addEventListener('DOMContentLoaded', function() {
    // Configurar tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Evento para buscar
    document.getElementById('buscarCategoria').addEventListener('keyup', buscarCategorias);
    
    // Si hay proveedor seleccionado, actualizar el filtro
    <?php if ($proveedor_id): ?>
    document.getElementById('filtroProveedor').value = '<?php echo $proveedor_id; ?>';
    <?php endif; ?>
});

// Validación de formularios
document.getElementById('formNuevaCategoria').addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});

document.getElementById('formEditarCategoria').addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});
</script>

<style>
.avatar-sm {
    width: 36px;
    height: 36px;
    font-size: 14px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.table th {
    font-weight: 600;
    background-color: #f8f9fa;
    position: sticky;
    top: 0;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
    transform: translateY(-1px);
    transition: transform 0.2s;
}

.btn-group .btn {
    border-radius: 4px !important;
    margin-right: 2px;
    padding: 0.25rem 0.5rem;
}

.card {
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-2px);
}

.input-group-text {
    background-color: #f8f9fa;
    border-right: none;
}

.form-control:focus + .input-group-text {
    border-color: #86b7fe;
    background-color: #e7f1ff;
}

.form-switch .form-check-input {
    height: 1.5em;
    width: 3em;
}

.badge {
    font-weight: 500;
}
</style>

<?php require_once 'footer.php'; ?>