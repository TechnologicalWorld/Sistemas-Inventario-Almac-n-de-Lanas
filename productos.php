<?php

session_start();

$titulo_pagina = "Gestión de Productos";
$icono_titulo = "fas fa-palette";
$breadcrumb = [
    ['text' => 'Proveedores', 'link' => 'proveedores.php', 'active' => false],
    ['text' => 'Productos', 'link' => '#', 'active' => true]
];

require_once 'header.php';
require_once 'funciones.php';

// Verificar que sea administrador
verificarRol(['administrador']);

$categoria_id = $_GET['categoria_id'] ?? null;
$proveedor_id = $_GET['proveedor_id'] ?? null;
$mensaje = '';
$tipo_mensaje = '';

// Procesar acción de cambiar estado (si viene por GET)
if (isset($_GET['cambiar_estado'])) {
    $id = intval($_GET['cambiar_estado']);
    $nuevo_estado = isset($_GET['nuevo_estado']) ? intval($_GET['nuevo_estado']) : 0;
    
    $query = "UPDATE productos SET activo = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $nuevo_estado, $id);
    
    if ($stmt->execute()) {
        $accion = $nuevo_estado == 1 ? 'activado' : 'desactivado';
        $mensaje = "Producto $accion exitosamente";
        $tipo_mensaje = "success";
    } else {
        $mensaje = "Error al cambiar estado del producto";
        $tipo_mensaje = "danger";
    }
}

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'crear':
            $codigo = limpiar($_POST['codigo']);
            $nombre_color = limpiar($_POST['nombre_color']);
            $proveedor_id_post = intval($_POST['proveedor_id']);
            $categoria_id_post = intval($_POST['categoria_id']);
            $precio_menor = floatval($_POST['precio_menor']);
            $precio_mayor = floatval($_POST['precio_mayor']);
            $tiene_stock = isset($_POST['tiene_stock']) ? 1 : 0;
            
            // Verificar si el código ya existe para este proveedor y categoría
            $check_query = "SELECT id FROM productos WHERE proveedor_id = ? AND codigo = ? AND categoria_id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("isi", $proveedor_id_post, $codigo, $categoria_id_post);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $mensaje = "Error: Ya existe un producto con ese código en esta categoría";
                $tipo_mensaje = "danger";
            } else {
                $query = "INSERT INTO productos 
                         (codigo, nombre_color, proveedor_id, categoria_id, precio_menor, precio_mayor, tiene_stock) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssiiddi", $codigo, $nombre_color, $proveedor_id_post, $categoria_id_post, 
                                 $precio_menor, $precio_mayor, $tiene_stock);
                
                if ($stmt->execute()) {
                    $producto_id = $stmt->insert_id;
                    
                    // Crear registro de inventario inicial si tiene_stock
                    if ($tiene_stock) {
                        // Obtener subpaquetes por paquete de la categoría
                        $query_categoria = "SELECT subpaquetes_por_paquete FROM categorias WHERE id = ?";
                        $stmt_cat = $conn->prepare($query_categoria);
                        $stmt_cat->bind_param("i", $categoria_id_post);
                        $stmt_cat->execute();
                        $result_cat = $stmt_cat->get_result();
                        $categoria_info = $result_cat->fetch_assoc();
                        $subpaquetes_por_paquete = $categoria_info['subpaquetes_por_paquete'] ?? 10;
                        
                        $query_inventario = "INSERT INTO inventario 
                                            (producto_id, paquetes_completos, subpaquetes_sueltos, 
                                             subpaquetes_por_paquete, usuario_registro)
                                            VALUES (?, 0, 0, ?, ?)";
                        $stmt_inv = $conn->prepare($query_inventario);
                        $stmt_inv->bind_param("iii", $producto_id, $subpaquetes_por_paquete, $_SESSION['usuario_id']);
                        $stmt_inv->execute();
                    }
                    
                    $mensaje = "Producto creado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al crear producto: " . $stmt->error;
                    $tipo_mensaje = "danger";
                }
            }
            break;
            
        case 'editar':
            $id = intval($_POST['id']);
            $codigo = limpiar($_POST['codigo']);
            $nombre_color = limpiar($_POST['nombre_color']);
            $precio_menor = floatval($_POST['precio_menor']);
            $precio_mayor = floatval($_POST['precio_mayor']);
            $activo = isset($_POST['activo']) ? 1 : 0;
            $tiene_stock = isset($_POST['tiene_stock']) ? 1 : 0;
            
            // Obtener información actual del producto para validación
            $query_actual = "SELECT proveedor_id, categoria_id FROM productos WHERE id = ?";
            $stmt_actual = $conn->prepare($query_actual);
            $stmt_actual->bind_param("i", $id);
            $stmt_actual->execute();
            $result_actual = $stmt_actual->get_result();
            $producto_actual = $result_actual->fetch_assoc();
            
            // Verificar si el código ya existe (excluyendo el actual)
            $check_query = "SELECT id FROM productos WHERE proveedor_id = ? AND codigo = ? AND categoria_id = ? AND id != ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("isii", $producto_actual['proveedor_id'], $codigo, $producto_actual['categoria_id'], $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $mensaje = "Error: Ya existe otro producto con ese código en esta categoría";
                $tipo_mensaje = "danger";
            } else {
                $query = "UPDATE productos SET 
                         codigo = ?, nombre_color = ?, precio_menor = ?, precio_mayor = ?, 
                         activo = ?, tiene_stock = ?
                         WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssddiii", $codigo, $nombre_color, $precio_menor, $precio_mayor, 
                                 $activo, $tiene_stock, $id);
                
                if ($stmt->execute()) {
                    // Verificar si necesita crear inventario
                    if ($tiene_stock) {
                        $query_check_inv = "SELECT id FROM inventario WHERE producto_id = ?";
                        $stmt_check = $conn->prepare($query_check_inv);
                        $stmt_check->bind_param("i", $id);
                        $stmt_check->execute();
                        $result_check = $stmt_check->get_result();
                        
                        if ($result_check->num_rows == 0) {
                            // Obtener subpaquetes por paquete de la categoría
                            $query_categoria = "SELECT subpaquetes_por_paquete FROM categorias WHERE id = ?";
                            $stmt_cat = $conn->prepare($query_categoria);
                            $stmt_cat->bind_param("i", $producto_actual['categoria_id']);
                            $stmt_cat->execute();
                            $result_cat = $stmt_cat->get_result();
                            $categoria_info = $result_cat->fetch_assoc();
                            $subpaquetes_por_paquete = $categoria_info['subpaquetes_por_paquete'] ?? 10;
                            
                            $query_inventario = "INSERT INTO inventario 
                                                (producto_id, paquetes_completos, subpaquetes_sueltos, 
                                                 subpaquetes_por_paquete, usuario_registro)
                                                VALUES (?, 0, 0, ?, ?)";
                            $stmt_inv = $conn->prepare($query_inventario);
                            $stmt_inv->bind_param("iii", $id, $subpaquetes_por_paquete, $_SESSION['usuario_id']);
                            $stmt_inv->execute();
                        }
                    }
                    
                    $mensaje = "Producto actualizado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al actualizar producto: " . $stmt->error;
                    $tipo_mensaje = "danger";
                }
            }
            break;
    }
}

// Obtener proveedores activos
$query_proveedores = "SELECT id, codigo, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre";
$result_proveedores = $conn->query($query_proveedores);

// Obtener categorías según proveedor seleccionado
$categorias_disponibles = [];
if ($proveedor_id) {
    $query_categorias = "SELECT id, nombre FROM categorias 
                        WHERE proveedor_id = ? AND activo = 1 
                        ORDER BY nombre";
    $stmt_categorias = $conn->prepare($query_categorias);
    $stmt_categorias->bind_param("i", $proveedor_id);
    $stmt_categorias->execute();
    $result_categorias = $stmt_categorias->get_result();
    
    while ($cat = $result_categorias->fetch_assoc()) {
        $categorias_disponibles[] = $cat;
    }
}

// Obtener productos según filtros
$where_conditions = [];
$params = [];
$types = "";

if ($categoria_id) {
    $where_conditions[] = "p.categoria_id = ?";
    $params[] = $categoria_id;
    $types .= "i";
}

if ($proveedor_id) {
    $where_conditions[] = "p.proveedor_id = ?";
    $params[] = $proveedor_id;
    $types .= "i";
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

$query_productos = "SELECT p.*, pr.nombre as proveedor_nombre, pr.codigo as proveedor_codigo,
                   c.nombre as categoria_nombre, c.subpaquetes_por_paquete,
                   i.total_subpaquetes, i.paquetes_completos, i.subpaquetes_sueltos, 
                   i.subpaquetes_por_paquete as inv_subpaquetes, i.ubicacion,
                   i.fecha_ultimo_ingreso, i.fecha_ultima_salida
                   FROM productos p
                   JOIN proveedores pr ON p.proveedor_id = pr.id
                   JOIN categorias c ON p.categoria_id = c.id
                   LEFT JOIN inventario i ON p.id = i.producto_id
                   $where_clause
                   ORDER BY pr.nombre, p.codigo";

if ($params) {
    $stmt = $conn->prepare($query_productos);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result_productos = $stmt->get_result();
} else {
    $result_productos = $conn->query($query_productos);
}

// Obtener información del filtro actual
$filtro_actual = "";
if ($categoria_id) {
    $query_cat_info = "SELECT c.nombre, p.nombre as proveedor 
                      FROM categorias c 
                      JOIN proveedores p ON c.proveedor_id = p.id 
                      WHERE c.id = ?";
    $stmt_cat = $conn->prepare($query_cat_info);
    $stmt_cat->bind_param("i", $categoria_id);
    $stmt_cat->execute();
    $cat_info = $stmt_cat->get_result()->fetch_assoc();
    $filtro_actual = "Categoría: <strong>" . htmlspecialchars($cat_info['nombre']) . "</strong> (Proveedor: " . htmlspecialchars($cat_info['proveedor']) . ")";
} elseif ($proveedor_id) {
    $query_prov_info = "SELECT nombre, codigo FROM proveedores WHERE id = ?";
    $stmt_prov = $conn->prepare($query_prov_info);
    $stmt_prov->bind_param("i", $proveedor_id);
    $stmt_prov->execute();
    $prov_info = $stmt_prov->get_result()->fetch_assoc();
    $filtro_actual = "Proveedor: <strong>" . htmlspecialchars($prov_info['codigo'] . " - " . $prov_info['nombre']) . "</strong>";
}

// Estadísticas
$query_stats = "SELECT 
                COUNT(*) as total_productos,
                SUM(CASE WHEN p.activo = 1 THEN 1 ELSE 0 END) as activos,
                SUM(CASE WHEN p.activo = 0 THEN 1 ELSE 0 END) as inactivos,
                SUM(CASE WHEN p.tiene_stock = 1 THEN 1 ELSE 0 END) as con_stock,
                SUM(CASE WHEN i.total_subpaquetes IS NULL OR i.total_subpaquetes = 0 THEN 1 ELSE 0 END) as sin_stock,
                COALESCE(SUM(i.total_subpaquetes), 0) as total_stock
                FROM productos p
                LEFT JOIN inventario i ON p.id = i.producto_id";
                
if ($where_clause) {
    $query_stats .= " $where_clause";
}

if ($params) {
    $stmt_stats = $conn->prepare($query_stats);
    $stmt_stats->bind_param($types, ...$params);
    $stmt_stats->execute();
    $result_stats = $stmt_stats->get_result();
} else {
    $result_stats = $conn->query($query_stats);
}
$stats = $result_stats->fetch_assoc();
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
                    <h5 class="mb-0"><i class="fas fa-palette me-2"></i>Gestión de Productos</h5>
                    <?php if ($filtro_actual): ?>
                        <small class="text-muted"><?php echo $filtro_actual; ?></small>
                    <?php endif; ?>
                </div>
                <div class="btn-group">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNuevoProducto">
                        <i class="fas fa-plus me-2"></i>Nuevo Producto
                    </button>
                    <a href="proveedores.php" class="btn btn-outline-primary ms-2">
                        <i class="fas fa-truck me-2"></i>Proveedores
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Tarjetas de estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-2 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 opacity-75">Total Productos</h6>
                                <h2 class="card-title mb-0"><?php echo number_format($stats['total_productos']); ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 opacity-75">Activos</h6>
                                <h2 class="card-title mb-0"><?php echo number_format($stats['activos']); ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 opacity-75">Inactivos</h6>
                                <h2 class="card-title mb-0"><?php echo number_format($stats['inactivos']); ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 opacity-75">Con Stock</h6>
                                <h2 class="card-title mb-0"><?php echo number_format($stats['con_stock']); ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 opacity-75">Stock Total</h6>
                                <h2 class="card-title mb-0"><?php echo number_format($stats['total_stock']); ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 opacity-75">Sin Stock</h6>
                                <h2 class="card-title mb-0"><?php echo number_format($stats['sin_stock']); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros y buscador -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control" id="buscarProducto" 
                                   placeholder="Buscar producto...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-truck"></i>
                            </span>
                            <select class="form-select" id="filtroProveedor" onchange="filtrarProductos()">
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
                    <div class="col-md-2">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-filter"></i>
                            </span>
                            <select class="form-select" id="filtroEstado" onchange="filtrarProductos()">
                                <option value="">Todos</option>
                                <option value="1">Activos</option>
                                <option value="0">Inactivos</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-boxes"></i>
                            </span>
                            <select class="form-select" id="filtroStock" onchange="filtrarProductos()">
                                <option value="">Stock</option>
                                <option value="con">Con Stock</option>
                                <option value="sin">Sin Stock</option>
                                <option value="bajo">Stock Bajo</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de productos -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="tablaProductos">
                        <thead class="table-light">
                            <tr>
                                <th width="100">Código</th>
                                <th>Producto/Color</th>
                                <th width="120">Proveedor</th>
                                <th width="120">Categoría</th>
                                <th width="100" class="text-end">P. Menor</th>
                                <th width="100" class="text-end">P. Mayor</th>
                                <th width="140" class="text-center">Stock</th>
                                <th width="100">Últ. Mov.</th>
                                <th width="90" class="text-center">Stock?</th>
                                <th width="80">Estado</th>
                                <th width="150" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_productos->num_rows > 0): ?>
                                <?php while ($producto = $result_productos->fetch_assoc()): 
                                    $total_stock = $producto['total_subpaquetes'] ?? 0;
                                    $paquetes = $producto['paquetes_completos'] ?? 0;
                                    $subpaquetes = $producto['subpaquetes_sueltos'] ?? 0;
                                    $subpaquetes_por_paquete = $producto['inv_subpaquetes'] ?? $producto['subpaquetes_por_paquete'] ?? 10;
                                    $stock_level = '';
                                    
                                    if ($total_stock == 0) {
                                        $stock_class = 'danger';
                                        $stock_level = 'Sin stock';
                                    } elseif ($total_stock < 20) {
                                        $stock_class = 'warning';
                                        $stock_level = 'Bajo';
                                    } else {
                                        $stock_class = 'success';
                                        $stock_level = 'Normal';
                                    }
                                    
                                    $ultimo_movimiento = '';
                                    if ($producto['fecha_ultimo_ingreso']) {
                                        $ultimo_movimiento = date('d/m/y', strtotime($producto['fecha_ultimo_ingreso']));
                                    } elseif ($producto['fecha_ultima_salida']) {
                                        $ultimo_movimiento = date('d/m/y', strtotime($producto['fecha_ultima_salida']));
                                    }
                                ?>
                                <tr data-proveedor-id="<?php echo $producto['proveedor_id']; ?>"
                                    data-categoria-id="<?php echo $producto['categoria_id']; ?>"
                                    data-estado="<?php echo $producto['activo']; ?>"
                                    data-tiene-stock="<?php echo $producto['tiene_stock']; ?>"
                                    data-stock="<?php echo $total_stock; ?>"
                                    data-busqueda="<?php echo strtolower(htmlspecialchars($producto['codigo'] . ' ' . $producto['nombre_color'] . ' ' . $producto['proveedor_nombre'] . ' ' . $producto['categoria_nombre'] . ' ' . $producto['ubicacion'])); ?>">
                                    <td>
                                        <span class="badge bg-dark"><?php echo htmlspecialchars($producto['codigo']); ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="color-indicator me-2" style="background-color: <?php echo generarColorHex($producto['codigo']); ?>"></div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($producto['nombre_color']); ?></strong>
                                                <?php if ($producto['ubicacion']): ?>
                                                <div class="small text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars($producto['ubicacion']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <i class="fas fa-industry me-1"></i>
                                            <?php echo htmlspecialchars($producto['proveedor_codigo']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($producto['categoria_nombre']); ?></small>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold text-success"><?php echo formatearMoneda($producto['precio_menor']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold text-primary"><?php echo formatearMoneda($producto['precio_mayor']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex flex-column align-items-center">
                                            <?php if ($producto['tiene_stock'] == 1): ?>
                                                <div class="badge bg-<?php echo $stock_class; ?> mb-1">
                                                    <?php echo $total_stock; ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <?php echo $paquetes; ?>p <?php echo $subpaquetes; ?>s
                                                </div>
                                                <?php if ($stock_level != 'Normal'): ?>
                                                <small class="text-<?php echo $stock_class; ?>">
                                                    <i class="fas fa-exclamation-circle me-1"></i><?php echo $stock_level; ?>
                                                </small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No aplica</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo $ultimo_movimiento ?: 'Nunca'; ?></small>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($producto['tiene_stock'] == 1): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-times"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($producto['activo'] == 1): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i>Activo
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times-circle me-1"></i>Inactivo
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEditarProducto"
                                                    onclick="cargarDatosProducto(<?php echo htmlspecialchars(json_encode($producto)); ?>)"
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($producto['tiene_stock'] == 1): ?>
                                            <a href="ingresar_stock.php?producto_id=<?php echo $producto['id']; ?>" 
                                               class="btn btn-outline-success"
                                               title="Ingresar Stock">
                                                <i class="fas fa-plus-circle"></i>
                                            </a>
                                            <a href="ajustar_inventario.php?producto_id=<?php echo $producto['id']; ?>" 
                                               class="btn btn-outline-warning"
                                               title="Ajustar Stock">
                                                <i class="fas fa-adjust"></i>
                                            </a>
                                            <?php endif; ?>
                                            <button class="btn btn-outline-<?php echo $producto['activo'] ? 'danger' : 'success'; ?>"
                                                    onclick="cambiarEstadoProducto(<?php echo $producto['id']; ?>, <?php echo $producto['activo']; ?>, '<?php echo addslashes($producto['nombre_color']); ?>')"
                                                    title="<?php echo $producto['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                                <i class="fas fa-<?php echo $producto['activo'] ? 'ban' : 'check'; ?>"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-palette fa-3x text-muted mb-3"></i>
                                            <h5>No hay productos registrados</h5>
                                            <p class="text-muted">
                                                <?php if ($filtro_actual): ?>
                                                    No se encontraron productos con los filtros aplicados.
                                                <?php else: ?>
                                                    No hay productos en el sistema.
                                                <?php endif; ?>
                                            </p>
                                            <button class="btn btn-success mt-2" data-bs-toggle="modal" data-bs-target="#modalNuevoProducto">
                                                <i class="fas fa-plus me-2"></i>Crear primer producto
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="11" class="text-muted small">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-info-circle me-1"></i>
                                            Mostrando <?php echo $result_productos->num_rows; ?> productos
                                        </div>
                                        <div class="text-end">
                                            
                                            <button class="btn btn-sm btn-outline-secondary ms-1" onclick="imprimirProductos()">
                                                <i class="fas fa-print me-1"></i>Imprimir
                                            </button>
                                            <?php if ($filtro_actual): ?>
                                            <a href="productos.php" class="btn btn-sm btn-outline-secondary ms-1">
                                                <i class="fas fa-redo me-1"></i>Limpiar filtros
                                            </a>
                                            <?php endif; ?>
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

<!-- Modal Nuevo Producto -->
<div class="modal fade" id="modalNuevoProducto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="formNuevoProducto">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Nuevo Producto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="crear">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Código <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-hashtag"></i>
                                </span>
                                <input type="text" class="form-control" name="codigo" required 
                                       placeholder="Ej: 1050, 2050">
                            </div>
                            <div class="form-text">Identificador único del color</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre/Color <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-tint"></i>
                                </span>
                                <input type="text" class="form-control" name="nombre_color" required 
                                       placeholder="Ej: Blanco, Perla, Rojo Intenso">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-truck"></i>
                                </span>
                                <select class="form-select" name="proveedor_id" id="nuevoProveedor" required
                                        onchange="cargarCategoriasNuevo(this.value)">
                                    <option value="">Seleccionar...</option>
                                    <?php 
                                    $result_proveedores = $conn->query($query_proveedores);
                                    while ($proveedor = $result_proveedores->fetch_assoc()):
                                    ?>
                                    <option value="<?php echo $proveedor['id']; ?>">
                                        <?php echo htmlspecialchars($proveedor['codigo'] . ' - ' . $proveedor['nombre']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Categoría <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-tags"></i>
                                </span>
                                <select class="form-select" name="categoria_id" id="nuevoCategoria" required>
                                    <option value="">Primero seleccione proveedor</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio Menor (Bs) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-money-bill-wave"></i>
                                </span>
                                <input type="number" class="form-control" name="precio_menor" 
                                       step="0.01" min="0" required>
                            </div>
                            <div class="form-text">Precio para venta al por menor</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio Mayor (Bs) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-money-bills"></i>
                                </span>
                                <input type="number" class="form-control" name="precio_mayor" 
                                       step="0.01" min="0" required>
                            </div>
                            <div class="form-text">Precio para venta al por mayor</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Control de Stock</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="tiene_stock" 
                                       id="tieneStock" value="1" checked role="switch">
                                <label class="form-check-label" for="tieneStock">
                                    Este producto tiene control de inventario
                                </label>
                            </div>
                            <div class="form-text">Si no tiene stock, no se controlará inventario</div>
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
<div class="modal fade" id="modalEditarProducto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="formEditarProducto">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Producto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="editar">
                    <input type="hidden" name="id" id="editProductoId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Código <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-hashtag"></i>
                                </span>
                                <input type="text" class="form-control" name="codigo" id="editCodigo" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre/Color <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-tint"></i>
                                </span>
                                <input type="text" class="form-control" name="nombre_color" id="editNombre" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio Menor (Bs) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-money-bill-wave"></i>
                                </span>
                                <input type="number" class="form-control" name="precio_menor" 
                                       id="editPrecioMenor" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio Mayor (Bs) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-money-bills"></i>
                                </span>
                                <input type="number" class="form-control" name="precio_mayor" 
                                       id="editPrecioMayor" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Control de Stock</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="tiene_stock" 
                                       id="editTieneStock" value="1" role="switch">
                                <label class="form-check-label" for="editTieneStock">
                                    <span id="labelTieneStock">Control de inventario</span>
                                </label>
                            </div>
                            <div class="form-text" id="textoTieneStock"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stock Actual</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-boxes"></i>
                                </span>
                                <input type="text" class="form-control" id="editStock" readonly>
                            </div>
                            <small id="detalleStock" class="text-muted"></small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="activo" 
                                       id="editActivo" value="1" role="switch">
                                <label class="form-check-label" for="editActivo">
                                    <span id="estadoProducto">Producto Activo</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Proveedor/Categoría</label>
                            <div class="alert alert-light">
                                <small id="infoProveedorCategoria"></small>
                            </div>
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

<script>
function cargarDatosProducto(producto) {
    document.getElementById('editProductoId').value = producto.id;
    document.getElementById('editCodigo').value = producto.codigo;
    document.getElementById('editNombre').value = producto.nombre_color;
    document.getElementById('editPrecioMenor').value = parseFloat(producto.precio_menor).toFixed(2);
    document.getElementById('editPrecioMayor').value = parseFloat(producto.precio_mayor).toFixed(2);
    
    const tieneStockCheckbox = document.getElementById('editTieneStock');
    const activoCheckbox = document.getElementById('editActivo');
    const estadoLabel = document.getElementById('estadoProducto');
    const stockLabel = document.getElementById('labelTieneStock');
    const stockText = document.getElementById('textoTieneStock');
    
    tieneStockCheckbox.checked = producto.tiene_stock == 1;
    activoCheckbox.checked = producto.activo == 1;
    estadoLabel.textContent = producto.activo == 1 ? 'Producto Activo' : 'Producto Inactivo';
    stockLabel.textContent = producto.tiene_stock == 1 ? 'Control de inventario' : 'Sin control de inventario';
    
    if (producto.tiene_stock == 1) {
        const totalStock = producto.total_subpaquetes || 0;
        const paquetes = producto.paquetes_completos || 0;
        const subpaquetes = producto.subpaquetes_sueltos || 0;
        const porPaquete = producto.inv_subpaquetes || producto.subpaquetes_por_paquete || 10;
        
        document.getElementById('editStock').value = totalStock + ' unidades';
        document.getElementById('detalleStock').textContent = `${paquetes} paquetes, ${subpaquetes} subpaquetes (${porPaquete} subp/paq)`;
        stockText.textContent = 'El producto tiene control de inventario físico';
    } else {
        document.getElementById('editStock').value = 'No aplica';
        document.getElementById('detalleStock').textContent = '';
        stockText.textContent = 'El producto no tiene control de inventario físico';
    }
    
    // Información de proveedor y categoría
    document.getElementById('infoProveedorCategoria').innerHTML = 
        `<i class="fas fa-truck me-1"></i>${producto.proveedor_nombre}<br>
         <i class="fas fa-tags me-1"></i>${producto.categoria_nombre}`;
}

function cambiarEstadoProducto(id, estadoActual, nombre) {
    const nuevoEstado = estadoActual == 1 ? 0 : 1;
    const accion = nuevoEstado == 1 ? 'activar' : 'desactivar';
    const mensaje = estadoActual == 1 ? 
        `¿Desactivar el producto "${nombre}"?\n\nEl producto no se mostrará en ventas hasta que sea reactivado.` :
        `¿Activar el producto "${nombre}"?\n\nEl producto estará disponible nuevamente para ventas.`;
    
    if (confirm(mensaje)) {
        window.location.href = `productos.php?cambiar_estado=${id}&nuevo_estado=${nuevoEstado}<?php echo $proveedor_id ? '&proveedor_id=' . $proveedor_id : ''; ?><?php echo $categoria_id ? '&categoria_id=' . $categoria_id : ''; ?>`;
    }
}

function cargarCategoriasNuevo(proveedorId) {
    if (proveedorId) {
        fetch('ajax_cargar_categorias.php?proveedor_id=' + proveedorId)
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('nuevoCategoria');
                select.innerHTML = '<option value="">Seleccionar categoría</option>';
                
                data.forEach(function(categoria) {
                    const option = document.createElement('option');
                    option.value = categoria.id;
                    option.textContent = categoria.nombre;
                    select.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error al cargar categorías:', error);
                document.getElementById('nuevoCategoria').innerHTML = '<option value="">Error al cargar categorías</option>';
            });
    }
}

function buscarProductos() {
    const filter = document.getElementById('buscarProducto').value.toLowerCase();
    const rows = document.querySelectorAll('#tablaProductos tbody tr');
    
    rows.forEach(row => {
        const searchText = row.getAttribute('data-busqueda') || '';
        if (searchText.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filtrarProductos() {
    const proveedorId = document.getElementById('filtroProveedor').value;
    const estado = document.getElementById('filtroEstado').value;
    const stock = document.getElementById('filtroStock').value;
    const rows = document.querySelectorAll('#tablaProductos tbody tr');
    
    rows.forEach(row => {
        const rowProveedorId = row.getAttribute('data-proveedor-id');
        const rowEstado = row.getAttribute('data-estado');
        const rowTieneStock = row.getAttribute('data-tiene-stock');
        const rowStock = parseInt(row.getAttribute('data-stock')) || 0;
        let mostrar = true;
        
        if (proveedorId && rowProveedorId !== proveedorId) {
            mostrar = false;
        }
        
        if (estado !== '' && rowEstado !== estado) {
            mostrar = false;
        }
        
        if (stock === 'con' && rowTieneStock !== '1') {
            mostrar = false;
        }
        
        if (stock === 'sin' && rowTieneStock === '1') {
            mostrar = false;
        }
        
        if (stock === 'bajo' && (rowTieneStock !== '1' || rowStock >= 20)) {
            mostrar = false;
        }
        
        row.style.display = mostrar ? '' : 'none';
    });
}

function exportarProductos() {
    alert('Funcionalidad de exportación en desarrollo.\nSe generará un archivo Excel con el listado de productos.');
}

function imprimirProductos() {
    const printContent = document.getElementById('tablaProductos').outerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <html>
            <head>
                <title>Lista de Productos - <?php echo EMPRESA_NOMBRE; ?></title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    @media print {
                        .no-print { display: none !important; }
                        body { padding: 20px; }
                        table { font-size: 10px; }
                        .badge { border: 1px solid #000 !important; }
                    }
                    h1 { margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                    .summary { margin-bottom: 20px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1 class="text-center">Lista de Productos</h1>
                    <div class="summary">

                        <p><strong>Sistema:</strong> <?php echo SISTEMA_NOMBRE; ?></p>
                        <?php if ($filtro_actual): ?>
                        <p><strong>Filtro:</strong> <?php echo strip_tags($filtro_actual); ?></p>
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
    document.getElementById('buscarProducto').addEventListener('keyup', buscarProductos);
    
    // Cargar categorías si hay proveedor seleccionado en filtros
    const filtroProveedor = document.getElementById('filtroProveedor');
    if (filtroProveedor.value) {
        filtrarProductos();
    }
    
    // Pre-seleccionar proveedor si hay uno en GET
    <?php if ($proveedor_id): ?>
    document.getElementById('filtroProveedor').value = '<?php echo $proveedor_id; ?>';
    <?php endif; ?>
    
    // Auto-completar campos si viene de categoría específica
    <?php if ($categoria_id && $proveedor_id): ?>
    const nuevoProveedor = document.getElementById('nuevoProveedor');
    if (nuevoProveedor) {
        nuevoProveedor.value = '<?php echo $proveedor_id; ?>';
        // Simular cambio para cargar categorías
        setTimeout(() => {
            cargarCategoriasNuevo('<?php echo $proveedor_id; ?>');
            // Seleccionar la categoría después de cargar
            setTimeout(() => {
                const nuevoCategoria = document.getElementById('nuevoCategoria');
                if (nuevoCategoria) {
                    nuevoCategoria.value = '<?php echo $categoria_id; ?>';
                }
            }, 500);
        }, 100);
    }
    <?php endif; ?>
});

// Validación de formularios
document.getElementById('formNuevoProducto').addEventListener('submit', function(e) {
    const precioMenor = parseFloat(this.precio_menor.value);
    const precioMayor = parseFloat(this.precio_mayor.value);
    
    if (precioMenor <= precioMayor) {
        e.preventDefault();
        alert('El precio por mayor debe ser MENOR que el precio por mayor');
        return false;
    }
    
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});

document.getElementById('formEditarProducto').addEventListener('submit', function(e) {
    const precioMenor = parseFloat(this.precio_menor.value);
    const precioMayor = parseFloat(this.precio_mayor.value);
    
    if (precioMenor <= precioMayor) {
        e.preventDefault();
        alert('El precio por mayor debe ser MENOR que el precio por mayor');
        return false;
    }
    
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

.color-indicator {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid #ddd;
}

.badge {
    font-weight: 500;
}

.small-text {
    font-size: 0.85rem;
}

.card-body h2 {
    font-size: 1.5rem;
}
</style>

<?php require_once 'footer.php'; ?>

<?php
// Función para generar un color HEX a partir del código del producto
function generarColorHex($codigo) {
    $hash = md5($codigo);
    return '#' . substr($hash, 0, 6);
}
?>