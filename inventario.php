<?php

session_start();

$titulo_pagina = "Inventario";
$icono_titulo = "fas fa-warehouse";
$breadcrumb = [
    ['text' => 'Inventario', 'link' => '#', 'active' => true]
];

require_once 'header.php';
require_once 'funciones.php';

verificarSesion();

// Obtener parámetros de filtro
$filtro_proveedor = $_GET['proveedor'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_stock = $_GET['stock'] ?? '';
$filtro_busqueda = $_GET['busqueda'] ?? '';
$orden = $_GET['orden'] ?? 'proveedor_codigo';
$direccion = $_GET['dir'] ?? 'asc';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = isset($_GET['por_pagina']) ? (int)$_GET['por_pagina'] : 50;

$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'actualizar_ubicacion' && $_SESSION['usuario_rol'] == 'administrador') {
        $producto_id = intval($_POST['producto_id']);
        $ubicacion = limpiar($_POST['ubicacion']);
        
        $query = "UPDATE inventario SET ubicacion = ? WHERE producto_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $ubicacion, $producto_id);
        
        if ($stmt->execute()) {
            $mensaje = "Ubicación actualizada correctamente";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al actualizar ubicación: " . $stmt->error;
            $tipo_mensaje = "danger";
        }
    }
}

// Obtener filtros disponibles
$query_proveedores = "SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre";
$result_proveedores = $conn->query($query_proveedores);

$query_categorias = "SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre";
$result_categorias = $conn->query($query_categorias);

// Construir consulta con filtros
$where_conditions = ["p.activo = 1"];
$params = [];
$types = "";

if ($filtro_proveedor) {
    $where_conditions[] = "p.proveedor_id = ?";
    $params[] = $filtro_proveedor;
    $types .= "i";
}

if ($filtro_categoria) {
    $where_conditions[] = "p.categoria_id = ?";
    $params[] = $filtro_categoria;
    $types .= "i";
}

if ($filtro_stock) {
    switch ($filtro_stock) {
        case 'bajo':
            $where_conditions[] = "i.total_subpaquetes < 50 AND i.total_subpaquetes > 0";
            break;
        case 'critico':
            $where_conditions[] = "i.total_subpaquetes < 20 AND i.total_subpaquetes > 0";
            break;
        case 'sin_stock':
            $where_conditions[] = "(i.total_subpaquetes IS NULL OR i.total_subpaquetes = 0)";
            break;
        case 'normal':
            $where_conditions[] = "i.total_subpaquetes >= 50";
            break;
    }
}

if ($filtro_busqueda) {
    $where_conditions[] = "(p.codigo LIKE ? OR p.nombre_color LIKE ? OR pr.nombre LIKE ? OR i.ubicacion LIKE ?)";
    $search_term = "%" . $filtro_busqueda . "%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= str_repeat("s", 4);
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Determinar ordenamiento
$orden_columnas = [
    'codigo' => 'p.codigo',
    'producto' => 'p.nombre_color',
    'proveedor' => 'pr.nombre',
    'categoria' => 'c.nombre',
    'total' => 'i.total_subpaquetes',
    'paquetes' => 'i.paquetes_completos',
    'sueltos' => 'i.subpaquetes_sueltos',
    'precio_menor' => 'p.precio_menor',
    'precio_mayor' => 'p.precio_mayor',
    'ubicacion' => 'i.ubicacion',
    'ultima_salida' => 'i.fecha_ultima_salida',
    'ultimo_ingreso' => 'i.fecha_ultimo_ingreso',
    'proveedor_codigo' => 'pr.nombre, p.codigo'
];

$orden_columna = $orden_columnas[$orden] ?? 'pr.nombre, p.codigo';
$orden_direccion = strtoupper($direccion) === 'DESC' ? 'DESC' : 'ASC';

// Consulta para contar total
$query_count = "SELECT COUNT(*) as total FROM productos p
               JOIN proveedores pr ON p.proveedor_id = pr.id
               JOIN categorias c ON p.categoria_id = c.id
               LEFT JOIN inventario i ON p.id = i.producto_id
               $where_clause";

if ($params) {
    $stmt_count = $conn->prepare($query_count);
    $stmt_count->bind_param($types, ...$params);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
} else {
    $result_count = $conn->query($query_count);
}

$total_registros = $result_count->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $por_pagina);
$offset = ($pagina - 1) * $por_pagina;

// Consulta principal con paginación
$query_inventario = "SELECT p.id, p.codigo, p.nombre_color, 
                    pr.nombre as proveedor, pr.id as proveedor_id,
                    c.nombre as categoria, c.id as categoria_id,
                    i.paquetes_completos, i.subpaquetes_sueltos, 
                    i.total_subpaquetes, i.ubicacion, i.costo_paquete,
                    p.precio_menor, p.precio_mayor, p.tiene_stock,
                    i.fecha_ultima_salida, i.fecha_ultimo_ingreso,
                    (SELECT SUM(vd.cantidad_subpaquetes) 
                     FROM venta_detalles vd 
                     JOIN ventas v ON vd.venta_id = v.id 
                     WHERE vd.producto_id = p.id 
                     AND v.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as ventas_30_dias
                    FROM productos p
                    JOIN proveedores pr ON p.proveedor_id = pr.id
                    JOIN categorias c ON p.categoria_id = c.id
                    LEFT JOIN inventario i ON p.id = i.producto_id
                    $where_clause
                    ORDER BY $orden_columna $orden_direccion
                    LIMIT ? OFFSET ?";

// Agregar parámetros de paginación
$params_paginacion = array_merge($params, [$por_pagina, $offset]);
$types_paginacion = $types . "ii";

if ($params_paginacion) {
    $stmt = $conn->prepare($query_inventario);
    $stmt->bind_param($types_paginacion, ...$params_paginacion);
    $stmt->execute();
    $result_inventario = $stmt->get_result();
} else {
    $query_simple = $query_inventario . " LIMIT $por_pagina OFFSET $offset";
    $result_inventario = $conn->query($query_simple);
}

// Estadísticas completas
$query_stats = "SELECT 
                COUNT(*) as total_productos,
                SUM(CASE WHEN i.total_subpaquetes IS NULL OR i.total_subpaquetes = 0 THEN 1 ELSE 0 END) as sin_stock,
                SUM(CASE WHEN i.total_subpaquetes < 20 THEN 1 ELSE 0 END) as critico,
                SUM(CASE WHEN i.total_subpaquetes < 50 AND i.total_subpaquetes > 0 THEN 1 ELSE 0 END) as bajo,
                SUM(CASE WHEN i.total_subpaquetes >= 50 THEN 1 ELSE 0 END) as normal,
                SUM(i.total_subpaquetes) as total_subpaquetes,
                SUM(i.paquetes_completos) as total_paquetes,
                SUM(i.total_subpaquetes * p.precio_menor) as valor_menor,
                SUM(i.total_subpaquetes * p.precio_mayor) as valor_mayor
                FROM productos p
                LEFT JOIN inventario i ON p.id = i.producto_id
                WHERE p.activo = 1";
$result_stats = $conn->query($query_stats);
$stats = $result_stats->fetch_assoc();
?>

<?php if ($mensaje): ?>
<div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
    <?php echo $mensaje; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row mb-4">
    <!-- Estadísticas principales -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-success border-4 shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-success text-uppercase mb-1">
                            Total Productos
                        </div>
                        <div class="h5 mb-0 fw-bold"><?php echo number_format($stats['total_productos']); ?></div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span class="text-success me-2">
                                <i class="fas fa-arrow-up"></i> 
                                <?php echo $stats['normal']; ?> normal
                            </span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-boxes fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-primary border-4 shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                            Stock Total
                        </div>
                        <div class="h5 mb-0 fw-bold"><?php echo number_format($stats['total_subpaquetes']); ?></div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <?php echo number_format($stats['total_paquetes']); ?> paquetes
                            <span class="text-primary ms-2">
                                <?php echo number_format($stats['total_subpaquetes'] - ($stats['total_paquetes'] * 10)); ?> sueltos
                            </span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-layer-group fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-warning border-4 shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                            Stock Bajo/Crítico
                        </div>
                        <div class="h5 mb-0 fw-bold">
                            <span class="text-warning"><?php echo $stats['bajo']; ?></span> /
                            <span class="text-danger"><?php echo $stats['critico']; ?></span>
                        </div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span class="text-warning me-2">
                                <i class="fas fa-exclamation-triangle"></i> Bajo: < 50
                            </span>
                            <span class="text-danger">
                                <i class="fas fa-exclamation-circle"></i> Crítico: < 20
                            </span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-danger border-4 shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-danger text-uppercase mb-1">
                            Valor del Inventario
                        </div>
                        <div class="h5 mb-0 fw-bold"><?php echo formatearMoneda($stats['valor_menor']); ?></div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span class="text-danger me-2">
                                Menor: <?php echo formatearMoneda($stats['valor_menor']); ?>
                            </span>
                            <br>
                            <span class="text-success">
                                Mayor: <?php echo formatearMoneda($stats['valor_mayor']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header bg-gradient-primary text-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="fas fa-warehouse me-2"></i>Inventario Completo
                            <span class="badge bg-white text-primary ms-2"><?php echo number_format($total_registros); ?> productos</span>
                        </h5>
                    </div>
                    <div class="btn-group">
                        <?php if ($_SESSION['usuario_rol'] == 'administrador'): ?>
                        <a href="ingresar_stock.php" class="btn btn-success btn-sm">
                            <i class="fas fa-plus me-1"></i>Ingresar Stock
                        </a>
                        <a href="ajustar_inventario.php" class="btn btn-warning btn-sm ms-1">
                            <i class="fas fa-adjust me-1"></i>Ajustar
                        </a>
                        <?php endif; ?>
                        <button class="btn btn-outline-light btn-sm ms-1" onclick="exportarInventarioExcel()">
                            <i class="fas fa-file-excel me-1"></i>Excel
                        </button>
                        <button class="btn btn-outline-light btn-sm ms-1" onclick="imprimirInventario()">
                            <i class="fas fa-print me-1"></i>Imprimir
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Filtros avanzados -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-primary">
                            <div class="card-header bg-light py-2">
                                <h6 class="mb-0">
                                    <i class="fas fa-filter me-1 text-primary"></i>Filtros Avanzados
                                    <button class="btn btn-sm btn-outline-primary float-end" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#filtrosAvanzados">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </h6>
                            </div>
                            <div class="collapse show" id="filtrosAvanzados">
                                <div class="card-body">
                                    <form method="GET" action="" class="row g-2" id="formFiltros">
                                        <input type="hidden" name="pagina" value="1">
                                        
                                        <div class="col-lg-2 col-md-4">
                                            <label class="form-label small fw-bold">Proveedor</label>
                                            <select class="form-select form-select-sm" name="proveedor" onchange="this.form.submit()">
                                                <option value="">Todos</option>
                                                <?php 
                                                $result_proveedores->data_seek(0);
                                                while ($proveedor = $result_proveedores->fetch_assoc()): 
                                                ?>
                                                <option value="<?php echo $proveedor['id']; ?>"
                                                    <?php echo ($filtro_proveedor == $proveedor['id']) ? 'selected' : ''; ?>>
                                                    <?php echo $proveedor['nombre']; ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-lg-2 col-md-4">
                                            <label class="form-label small fw-bold">Categoría</label>
                                            <select class="form-select form-select-sm" name="categoria" onchange="this.form.submit()">
                                                <option value="">Todas</option>
                                                <?php 
                                                $result_categorias->data_seek(0);
                                                while ($categoria = $result_categorias->fetch_assoc()): 
                                                ?>
                                                <option value="<?php echo $categoria['id']; ?>"
                                                    <?php echo ($filtro_categoria == $categoria['id']) ? 'selected' : ''; ?>>
                                                    <?php echo $categoria['nombre']; ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-lg-2 col-md-4">
                                            <label class="form-label small fw-bold">Estado Stock</label>
                                            <select class="form-select form-select-sm" name="stock" onchange="this.form.submit()">
                                                <option value="">Todos</option>
                                                <option value="sin_stock" <?php echo ($filtro_stock == 'sin_stock') ? 'selected' : ''; ?>>
                                                    Sin Stock
                                                </option>
                                                <option value="critico" <?php echo ($filtro_stock == 'critico') ? 'selected' : ''; ?>>
                                                    Crítico (< 20)
                                                </option>
                                                <option value="bajo" <?php echo ($filtro_stock == 'bajo') ? 'selected' : ''; ?>>
                                                    Bajo (< 50)
                                                </option>
                                                <option value="normal" <?php echo ($filtro_stock == 'normal') ? 'selected' : ''; ?>>
                                                    Normal (>= 50)
                                                </option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-lg-3 col-md-6">
                                            <label class="form-label small fw-bold">Ordenar por</label>
                                            <div class="input-group input-group-sm">
                                                <select class="form-select" name="orden">
                                                    <?php foreach ($orden_columnas as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>"
                                                        <?php echo ($orden == $key) ? 'selected' : ''; ?>>
                                                        <?php echo ucfirst(str_replace('_', ' ', $key)); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn btn-outline-secondary" type="button" 
                                                        onclick="toggleDireccion()" title="Cambiar dirección">
                                                    <i class="fas fa-sort-amount-<?php echo strtolower($direccion) === 'desc' ? 'down' : 'up'; ?>"></i>
                                                </button>
                                                <input type="hidden" name="dir" value="<?php echo $direccion; ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-3 col-md-6">
                                            <label class="form-label small fw-bold">Buscar</label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control" name="busqueda" 
                                                       value="<?php echo htmlspecialchars($filtro_busqueda); ?>"
                                                       placeholder="Código, producto, proveedor...">
                                                <button class="btn btn-primary" type="submit">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                                <?php if ($filtro_proveedor || $filtro_categoria || $filtro_stock || $filtro_busqueda): ?>
                                                <a href="inventario.php" class="btn btn-outline-secondary">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-4 col-md-8 mt-2">
                                            <div class="d-flex align-items-center">
                                                <label class="form-label small fw-bold me-2 mb-0">Mostrar:</label>
                                                <select class="form-select form-select-sm w-auto" name="por_pagina" onchange="this.form.submit()">
                                                    <option value="20" <?php echo ($por_pagina == 20) ? 'selected' : ''; ?>>20</option>
                                                    <option value="50" <?php echo ($por_pagina == 50) ? 'selected' : ''; ?>>50</option>
                                                    <option value="100" <?php echo ($por_pagina == 100) ? 'selected' : ''; ?>>100</option>
                                                    <option value="200" <?php echo ($por_pagina == 200) ? 'selected' : ''; ?>>200</option>
                                                </select>
                                                <span class="ms-2 small text-muted">
                                                    <?php echo number_format($offset + 1); ?> - 
                                                    <?php echo number_format(min($offset + $por_pagina, $total_registros)); ?> 
                                                    de <?php echo number_format($total_registros); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contadores rápidos -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="d-flex flex-wrap gap-2">
                            <a href="?stock=sin_stock" class="btn btn-sm btn-danger">
                                <i class="fas fa-times-circle me-1"></i>Sin Stock: <?php echo $stats['sin_stock']; ?>
                            </a>
                            <a href="?stock=critico" class="btn btn-sm btn-danger">
                                <i class="fas fa-exclamation-circle me-1"></i>Crítico: <?php echo $stats['critico']; ?>
                            </a>
                            <a href="?stock=bajo" class="btn btn-sm btn-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>Bajo: <?php echo $stats['bajo']; ?>
                            </a>
                            <a href="?stock=normal" class="btn btn-sm btn-success">
                                <i class="fas fa-check-circle me-1"></i>Normal: <?php echo $stats['normal']; ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de inventario -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped table-bordered" id="tablaInventario">
                        <thead class="table-dark">
                            <tr>
                                <th width="8%" class="text-center">
                                    <a href="<?php echo generarUrlOrden('codigo'); ?>" class="text-white text-decoration-none">
                                        Código
                                        <?php if ($orden == 'codigo'): ?>
                                        <i class="fas fa-sort-<?php echo strtolower($direccion) === 'desc' ? 'down' : 'up'; ?> ms-1"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th width="15%">
                                    <a href="<?php echo generarUrlOrden('producto'); ?>" class="text-white text-decoration-none">
                                        Producto
                                        <?php if ($orden == 'producto'): ?>
                                        <i class="fas fa-sort-<?php echo strtolower($direccion) === 'desc' ? 'down' : 'up'; ?> ms-1"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th width="12%">
                                    <a href="<?php echo generarUrlOrden('proveedor'); ?>" class="text-white text-decoration-none">
                                        Proveedor
                                        <?php if ($orden == 'proveedor'): ?>
                                        <i class="fas fa-sort-<?php echo strtolower($direccion) === 'desc' ? 'down' : 'up'; ?> ms-1"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th width="10%">
                                    <a href="<?php echo generarUrlOrden('categoria'); ?>" class="text-white text-decoration-none">
                                        Categoría
                                        <?php if ($orden == 'categoria'): ?>
                                        <i class="fas fa-sort-<?php echo strtolower($direccion) === 'desc' ? 'down' : 'up'; ?> ms-1"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th width="8%" class="text-center">
                                    <a href="<?php echo generarUrlOrden('paquetes'); ?>" class="text-white text-decoration-none">
                                        Paquetes
                                        <?php if ($orden == 'paquetes'): ?>
                                        <i class="fas fa-sort-<?php echo strtolower($direccion) === 'desc' ? 'down' : 'up'; ?> ms-1"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th width="8%" class="text-center">
                                    <a href="<?php echo generarUrlOrden('sueltos'); ?>" class="text-white text-decoration-none">
                                        Sueltos
                                        <?php if ($orden == 'sueltos'): ?>
                                        <i class="fas fa-sort-<?php echo strtolower($direccion) === 'desc' ? 'down' : 'up'; ?> ms-1"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th width="8%" class="text-center">
                                    <a href="<?php echo generarUrlOrden('total'); ?>" class="text-white text-decoration-none">
                                        Total
                                        <?php if ($orden == 'total'): ?>
                                        <i class="fas fa-sort-<?php echo strtolower($direccion) === 'desc' ? 'down' : 'up'; ?> ms-1"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th width="12%">
                                    <a href="<?php echo generarUrlOrden('ubicacion'); ?>" class="text-white text-decoration-none">
                                        Ubicación
                                        <?php if ($orden == 'ubicacion'): ?>
                                        <i class="fas fa-sort-<?php echo strtolower($direccion) === 'desc' ? 'down' : 'up'; ?> ms-1"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th width="8%" class="text-end">
                                    <a href="<?php echo generarUrlOrden('precio_menor'); ?>" class="text-white text-decoration-none">
                                        P. Menor
                                        <?php if ($orden == 'precio_menor'): ?>
                                        <i class="fas fa-sort-<?php echo strtolower($direccion) === 'desc' ? 'down' : 'up'; ?> ms-1"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th width="8%" class="text-end">
                                    <a href="<?php echo generarUrlOrden('precio_mayor'); ?>" class="text-white text-decoration-none">
                                        P. Mayor
                                        <?php if ($orden == 'precio_mayor'): ?>
                                        <i class="fas fa-sort-<?php echo strtolower($direccion) === 'desc' ? 'down' : 'up'; ?> ms-1"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th width="6%" class="text-center">Estado</th>
                                <th width="5%" class="text-center">Ventas 30d</th>
                                <?php if ($_SESSION['usuario_rol'] == 'administrador'): ?>
                                <th width="8%" class="text-center">Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_inventario->num_rows > 0): ?>
                                <?php while ($item = $result_inventario->fetch_assoc()): 
                                    // Determinar nivel de stock
                                    $stock_level = 'normal';
                                    $stock_class = 'success';
                                    $stock_text = 'NORMAL';
                                    
                                    if ($item['total_subpaquetes'] === null || $item['total_subpaquetes'] == 0) {
                                        $stock_level = 'sin-stock';
                                        $stock_class = 'danger';
                                        $stock_text = 'AGOTADO';
                                    } elseif ($item['total_subpaquetes'] < 20) {
                                        $stock_level = 'critico';
                                        $stock_class = 'danger';
                                        $stock_text = 'CRÍTICO';
                                    } elseif ($item['total_subpaquetes'] < 50) {
                                        $stock_level = 'bajo';
                                        $stock_class = 'warning';
                                        $stock_text = 'BAJO';
                                    }
                                    
                                    // Determinar si tiene stock disponible para venta
                                    $tiene_stock = $item['tiene_stock'] && ($item['total_subpaquetes'] ?? 0) > 0;
                                ?>
                                <tr class="align-middle stock-<?php echo $stock_level; ?>" 
                                    data-codigo="<?php echo htmlspecialchars($item['codigo']); ?>"
                                    data-nombre="<?php echo htmlspecialchars($item['nombre_color']); ?>"
                                    data-proveedor="<?php echo htmlspecialchars($item['proveedor']); ?>"
                                    data-stock="<?php echo $item['total_subpaquetes'] ?? 0; ?>">
                                    <td class="text-center">
                                        <span class="fw-bold text-primary" data-bs-toggle="tooltip" 
                                              title="ID: <?php echo $item['id']; ?>">
                                            <?php echo $item['codigo']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="color-indicator me-2" 
                                                 style="background-color: <?php echo generarColor($item['nombre_color']); ?>"></div>
                                            <span><?php echo $item['nombre_color']; ?></span>
                                            <?php if (!$tiene_stock): ?>
                                            <i class="fas fa-ban text-danger ms-2" data-bs-toggle="tooltip" title="No disponible para venta"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-25 text-dark border border-info">
                                            <?php echo $item['proveedor']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo $item['categoria']; ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary">
                                            <?php echo $item['paquetes_completos'] ?? 0; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">
                                            <?php echo $item['subpaquetes_sueltos'] ?? 0; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $stock_class; ?> fs-6">
                                            <?php echo $item['total_subpaquetes'] ?? 0; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="<?php echo empty($item['ubicacion']) ? 'text-muted fst-italic' : ''; ?>">
                                                <?php echo $item['ubicacion'] ?: 'Sin ubicación'; ?>
                                            </span>
                                            <?php if ($_SESSION['usuario_rol'] == 'administrador'): ?>
                                            <button class="btn btn-sm btn-outline-secondary py-0 px-1" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalUbicacion"
                                                    data-producto-id="<?php echo $item['id']; ?>"
                                                    data-ubicacion-actual="<?php echo htmlspecialchars($item['ubicacion']); ?>"
                                                    title="Cambiar ubicación">
                                                <i class="fas fa-edit fa-xs"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold text-success">
                                            <?php echo formatearMoneda($item['precio_menor']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold text-primary">
                                            <?php echo formatearMoneda($item['precio_mayor']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $stock_class; ?>" data-bs-toggle="tooltip" 
                                              title="<?php echo $item['total_subpaquetes'] ?? 0; ?> subpaquetes">
                                            <?php echo $stock_text; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($item['ventas_30_dias'] > 0): ?>
                                        <span class="badge bg-info" data-bs-toggle="tooltip" 
                                              title="Ventas últimos 30 días">
                                            <?php echo $item['ventas_30_dias']; ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($_SESSION['usuario_rol'] == 'administrador'): ?>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="ingresar_stock.php?producto_id=<?php echo $item['id']; ?>" 
                                               class="btn btn-outline-success" 
                                               data-bs-toggle="tooltip" title="Ingresar stock">
                                                <i class="fas fa-plus"></i>
                                            </a>
                                            <a href="ajustar_inventario.php?producto_id=<?php echo $item['id']; ?>" 
                                               class="btn btn-outline-warning"
                                               data-bs-toggle="tooltip" title="Ajustar inventario">
                                                <i class="fas fa-adjust"></i>
                                            </a>
                                            <a href="productos.php?proveedor_id=<?php echo $item['proveedor_id']; ?>&categoria_id=<?php echo $item['categoria_id']; ?>" 
                                               class="btn btn-outline-info"
                                               data-bs-toggle="tooltip" title="Ver similares">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="<?php echo $_SESSION['usuario_rol'] == 'administrador' ? '13' : '12'; ?>" 
                                    class="text-center text-muted p-5">
                                    <div class="py-5">
                                        <i class="fas fa-box-open fa-4x mb-3 opacity-25"></i>
                                        <h5 class="text-muted">No se encontraron productos</h5>
                                        <p class="mb-4">Intente con otros filtros de búsqueda</p>
                                        <a href="inventario.php" class="btn btn-primary">
                                            <i class="fas fa-redo me-2"></i>Limpiar filtros
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                <div class="row mt-4">
                    <div class="col-md-12">
                        <nav aria-label="Paginación">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo generarUrlPagina(1); ?>">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo generarUrlPagina($pagina - 1); ?>">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </li>
                                
                                <?php
                                $inicio = max(1, $pagina - 2);
                                $fin = min($total_paginas, $pagina + 2);
                                
                                if ($inicio > 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                
                                for ($i = $inicio; $i <= $fin; $i++):
                                ?>
                                <li class="page-item <?php echo $i == $pagina ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo generarUrlPagina($i); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                if ($fin < $total_paginas) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                ?>
                                
                                <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo generarUrlPagina($pagina + 1); ?>">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo generarUrlPagina($total_paginas); ?>">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar ubicación -->
<div class="modal fade" id="modalUbicacion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-map-marker-alt me-2"></i>Cambiar Ubicación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="actualizar_ubicacion">
                    <input type="hidden" name="producto_id" id="modalProductoId">
                    
                    <div class="mb-3">
                        <label class="form-label">Ubicación Actual</label>
                        <input type="text" class="form-control" id="modalUbicacionActual" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nueva Ubicación</label>
                        <select class="form-select" name="ubicacion" id="modalNuevaUbicacion">
                            <option value="">Seleccionar ubicación...</option>
                            <option value="Estantería A1">Estantería A1</option>
                            <option value="Estantería A2">Estantería A2</option>
                            <option value="Estantería B1">Estantería B1</option>
                            <option value="Estantería B2">Estantería B2</option>
                            <option value="Almacén Principal">Almacén Principal</option>
                            <option value="Almacén Secundario">Almacén Secundario</option>
                            <option value="Mostrador">Mostrador</option>
                            <option value="Vitrina">Vitrina</option>
                            <option value="Bodega">Bodega</option>
                        </select>
                        <div class="form-text">
                            O ingrese una ubicación personalizada:
                        </div>
                        <input type="text" class="form-control mt-2" id="modalUbicacionPersonalizada" 
                               placeholder="Ej: Pasillo 3, Estante Superior">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// Función para generar color basado en nombre
function generarColor($nombre) {
    $hash = md5($nombre);
    return '#' . substr($hash, 0, 6);
}

// Función para generar URL con ordenamiento
function generarUrlOrden($columna) {
    global $filtro_proveedor, $filtro_categoria, $filtro_stock, $filtro_busqueda, $orden, $direccion, $pagina, $por_pagina;
    
    $url = "inventario.php?";
    $params = [];
    
    if ($filtro_proveedor) $params[] = "proveedor=" . urlencode($filtro_proveedor);
    if ($filtro_categoria) $params[] = "categoria=" . urlencode($filtro_categoria);
    if ($filtro_stock) $params[] = "stock=" . urlencode($filtro_stock);
    if ($filtro_busqueda) $params[] = "busqueda=" . urlencode($filtro_busqueda);
    if ($pagina > 1) $params[] = "pagina=" . $pagina;
    if ($por_pagina != 50) $params[] = "por_pagina=" . $por_pagina;
    
    // Determinar dirección de ordenamiento
    $nueva_direccion = ($orden == $columna && $direccion == 'asc') ? 'desc' : 'asc';
    $params[] = "orden=" . urlencode($columna);
    $params[] = "dir=" . urlencode($nueva_direccion);
    
    return $url . implode('&', $params);
}

// Función para generar URL con página específica
function generarUrlPagina($num_pagina) {
    global $filtro_proveedor, $filtro_categoria, $filtro_stock, $filtro_busqueda, $orden, $direccion, $por_pagina;
    
    $url = "inventario.php?";
    $params = [];
    
    if ($filtro_proveedor) $params[] = "proveedor=" . urlencode($filtro_proveedor);
    if ($filtro_categoria) $params[] = "categoria=" . urlencode($filtro_categoria);
    if ($filtro_stock) $params[] = "stock=" . urlencode($filtro_stock);
    if ($filtro_busqueda) $params[] = "busqueda=" . urlencode($filtro_busqueda);
    if ($num_pagina > 1) $params[] = "pagina=" . $num_pagina;
    if ($por_pagina != 50) $params[] = "por_pagina=" . $por_pagina;
    if ($orden != 'proveedor_codigo') $params[] = "orden=" . urlencode($orden);
    if ($direccion != 'asc') $params[] = "dir=" . urlencode($direccion);
    
    return $url . implode('&', $params);
}
?>

<style>
/* Estilos para la tabla */
.table-hover tbody tr:hover {
    background-color: rgba(40, 167, 69, 0.1) !important;
    transform: scale(1.002);
    transition: all 0.2s ease;
}

.stock-critico {
    background-color: #ffe6e6 !important;
    border-left: 4px solid #dc3545 !important;
}

.stock-bajo {
    background-color: #fff3cd !important;
    border-left: 4px solid #ffc107 !important;
}

.stock-sin-stock {
    background-color: #f8d7da !important;
    border-left: 4px solid #dc3545 !important;
}

.stock-normal {
    background-color: #ffffff !important;
    border-left: 4px solid #28a745 !important;
}

.color-indicator {
    width: 15px;
    height: 15px;
    border-radius: 3px;
    border: 1px solid #dee2e6;
    flex-shrink: 0;
}

.badge {
    font-weight: 500;
}

.pagination .page-item.active .page-link {
    background-color: #28a745;
    border-color: #28a745;
}

.card-header {
    border-bottom: none;
}

.table thead th {
    position: sticky;
    top: 0;
    background-color: #2c3e50;
    z-index: 10;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 14px;
    }
    
    .table td, .table th {
        padding: 0.5rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
    }
}

/* Estilos para impresión */
@media print {
    .card-header, .card-footer, .btn, .modal, 
    .collapse, .pagination, .input-group {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .table {
        font-size: 11px !important;
    }
    
    .stock-critico, .stock-bajo, .stock-sin-stock, .stock-normal {
        border-left: none !important;
        -webkit-print-color-adjust: exact !important;
    }
}
</style>

<script>
// Inicializar tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips de Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Inicializar modal de ubicación
    const modalUbicacion = document.getElementById('modalUbicacion');
    if (modalUbicacion) {
        modalUbicacion.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const productoId = button.getAttribute('data-producto-id');
            const ubicacionActual = button.getAttribute('data-ubicacion-actual');
            
            document.getElementById('modalProductoId').value = productoId;
            document.getElementById('modalUbicacionActual').value = ubicacionActual;
            
            // Configurar select de ubicación
            const select = document.getElementById('modalNuevaUbicacion');
            const personalizada = document.getElementById('modalUbicacionPersonalizada');
            
            if (ubicacionActual && !select.querySelector(`option[value="${ubicacionActual}"]`)) {
                select.value = '';
                personalizada.value = ubicacionActual;
            } else {
                select.value = ubicacionActual || '';
                personalizada.value = '';
            }
            
            // Mostrar/ocultar campo personalizado
            personalizada.addEventListener('input', function() {
                if (this.value) {
                    select.value = '';
                }
            });
            
            select.addEventListener('change', function() {
                if (this.value) {
                    personalizada.value = '';
                }
            });
        });
    }
    
    // Configurar búsqueda en tiempo real
    const buscador = document.querySelector('input[name="busqueda"]');
    if (buscador) {
        let timeout;
        buscador.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                document.querySelector('input[name="pagina"]').value = '1';
                document.getElementById('formFiltros').submit();
            }, 500);
        });
    }
});

// Función para cambiar dirección de ordenamiento
function toggleDireccion() {
    const dirInput = document.querySelector('input[name="dir"]');
    const dirButton = document.querySelector('button[onclick="toggleDireccion()"]');
    const icon = dirButton.querySelector('i');
    
    const nuevaDir = dirInput.value === 'asc' ? 'desc' : 'asc';
    dirInput.value = nuevaDir;
    
    icon.className = nuevaDir === 'asc' ? 'fas fa-sort-amount-up' : 'fas fa-sort-amount-down';
    
    // Enviar formulario automáticamente
    document.getElementById('formFiltros').submit();
}

// Función para exportar a Excel
function exportarInventarioExcel() {
    // Crear datos para exportación
    let csv = [];
    let headers = [];
    
    // Obtener encabezados
    document.querySelectorAll('#tablaInventario thead th').forEach(th => {
        headers.push(th.textContent.trim().replace(/\n/g, ' '));
    });
    csv.push(headers.join(','));
    
    // Obtener datos de las filas visibles
    document.querySelectorAll('#tablaInventario tbody tr:not([style*="display: none"])').forEach(tr => {
        let row = [];
        tr.querySelectorAll('td').forEach(td => {
            let text = td.textContent.trim().replace(/,/g, ';').replace(/\n/g, ' ');
            // Remover iconos y elementos innecesarios
            text = text.replace(/\s+/g, ' ');
            row.push(`"${text}"`);
        });
        csv.push(row.join(','));
    });
    
    // Crear y descargar archivo
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (navigator.msSaveBlob) {
        navigator.msSaveBlob(blob, `inventario_${new Date().toISOString().split('T')[0]}.csv`);
    } else {
        link.href = URL.createObjectURL(blob);
        link.setAttribute('download', `inventario_${new Date().toISOString().split('T')[0]}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    // Mostrar notificación
    mostrarNotificacion('Inventario exportado exitosamente', 'success');
}

// Función para imprimir inventario
function imprimirInventario() {
    const contenidoOriginal = document.body.innerHTML;
    const contenidoImpresion = document.querySelector('.card').outerHTML;
    
    document.body.innerHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Inventario - <?php echo date('Y-m-d'); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .table { width: 100%; border-collapse: collapse; font-size: 12px; }
                .table th, .table td { border: 1px solid #ddd; padding: 6px; }
                .table th { background-color: #2c3e50; color: white; }
                .text-center { text-align: center; }
                .text-end { text-align: right; }
                .badge { padding: 2px 6px; border-radius: 3px; font-size: 11px; }
                .bg-success { background-color: #28a745; color: white; }
                .bg-warning { background-color: #ffc107; color: black; }
                .bg-danger { background-color: #dc3545; color: white; }
                .bg-secondary { background-color: #6c757d; color: white; }
                h5 { color: #2c3e50; }
                .header-print { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2c3e50; padding-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="header-print">
                <h3>INVENTARIO - <?php echo EMPRESA_NOMBRE ?? 'TIENDA DE LANAS'; ?></h3>
                <p>Fecha de impresión: ${new Date().toLocaleString()}</p>
                <p>Filtros aplicados: 
                    <?php 
                    $filtros_texto = [];
                    if ($filtro_proveedor) $filtros_texto[] = 'Proveedor: ' . obtenerNombreProveedor($filtro_proveedor);
                    if ($filtro_categoria) $filtros_texto[] = 'Categoría: ' . obtenerNombreCategoria($filtro_categoria);
                    if ($filtro_stock) $filtros_texto[] = 'Stock: ' . ucfirst(str_replace('_', ' ', $filtro_stock));
                    if ($filtro_busqueda) $filtros_texto[] = 'Búsqueda: ' . $filtro_busqueda;
                    echo implode(', ', $filtros_texto) ?: 'Todos los productos';
                    ?>
                </p>
            </div>
            ${contenidoImpresion}
        </body>
        </html>
    `;
    
    window.print();
    document.body.innerHTML = contenidoOriginal;
    window.location.reload();
}

// Función para mostrar notificaciones
function mostrarNotificacion(mensaje, tipo = 'info') {
    const notificacion = document.createElement('div');
    notificacion.className = `alert alert-${tipo} alert-dismissible fade show position-fixed`;
    notificacion.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
    notificacion.innerHTML = `
        <i class="fas fa-${tipo === 'success' ? 'check-circle' : (tipo === 'danger' ? 'exclamation-circle' : 'info-circle')} me-2"></i>
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notificacion);
    
    // Auto-remover después de 3 segundos
    setTimeout(() => {
        if (notificacion.parentNode) {
            notificacion.remove();
        }
    }, 3000);
}

// Función para búsqueda rápida en la tabla (cliente-side)
function buscarEnTablaRapido() {
    const input = document.getElementById('buscadorRapido');
    if (!input) return;
    
    const filter = input.value.toUpperCase();
    const table = document.getElementById('tablaInventario');
    const tr = table.getElementsByTagName('tr');
    
    let encontrados = 0;
    
    for (let i = 1; i < tr.length; i++) {
        const tds = tr[i].getElementsByTagName('td');
        let mostrar = false;
        
        for (let j = 0; j < tds.length; j++) {
            if (tds[j]) {
                const txtValue = tds[j].textContent || tds[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    mostrar = true;
                    break;
                }
            }
        }
        
        tr[i].style.display = mostrar ? '' : 'none';
        if (mostrar) encontrados++;
    }
    
    // Mostrar contador
    const contador = document.getElementById('contadorResultadosRapido');
    if (contador) {
        contador.textContent = encontrados + ' productos encontrados';
        contador.style.display = 'block';
        
        if (encontrados === 0 && filter) {
            contador.innerHTML = '<span class="text-danger">No se encontraron productos</span>';
        }
    }
}

// Función para agregar búsqueda rápida dinámica
function agregarBuscadorRapido() {
    const tablaContainer = document.querySelector('.table-responsive');
    if (!tablaContainer) return;
    
    // Crear contenedor de búsqueda rápida
    const buscadorContainer = document.createElement('div');
    buscadorContainer.className = 'mb-3';
    buscadorContainer.innerHTML = `
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" class="form-control" id="buscadorRapido" 
                   placeholder="Buscar dentro de la tabla (código, producto, proveedor, ubicación)..."
                   onkeyup="buscarEnTablaRapido()">
            <span class="input-group-text">
                <small id="contadorResultadosRapido" style="display: none;"></small>
            </span>
        </div>
    `;
    
    // Insertar antes de la tabla
    tablaContainer.parentNode.insertBefore(buscadorContainer, tablaContainer);
}

// Inicializar buscador rápido
document.addEventListener('DOMContentLoaded', function() {
    agregarBuscadorRapido();
});

// Función para resaltar filas según stock
function resaltarFilas() {
    const filas = document.querySelectorAll('#tablaInventario tbody tr');
    filas.forEach(fila => {
        const stock = parseInt(fila.getAttribute('data-stock') || 0);
        
        if (stock === 0) {
            fila.classList.add('stock-sin-stock');
        } else if (stock < 20) {
            fila.classList.add('stock-critico');
        } else if (stock < 50) {
            fila.classList.add('stock-bajo');
        } else {
            fila.classList.add('stock-normal');
        }
    });
}

// Inicializar resaltado
resaltarFilas();

// Función para copiar información del producto
function copiarInformacionProducto(codigo, nombre, proveedor, stock) {
    const texto = `Código: ${codigo}\nProducto: ${nombre}\nProveedor: ${proveedor}\nStock: ${stock} subpaquetes`;
    
    navigator.clipboard.writeText(texto).then(function() {
        mostrarNotificacion('Información copiada al portapapeles', 'info');
    }).catch(function(err) {
        console.error('Error al copiar: ', err);
        mostrarNotificacion('Error al copiar información', 'danger');
    });
}

// Agregar eventos de doble clic para copiar información
document.addEventListener('DOMContentLoaded', function() {
    const filas = document.querySelectorAll('#tablaInventario tbody tr');
    filas.forEach(fila => {
        fila.addEventListener('dblclick', function() {
            const codigo = this.getAttribute('data-codigo');
            const nombre = this.getAttribute('data-nombre');
            const proveedor = this.getAttribute('data-proveedor');
            const stock = this.getAttribute('data-stock');
            
            if (confirm('¿Copiar información del producto?')) {
                copiarInformacionProducto(codigo, nombre, proveedor, stock);
            }
        });
    });
});
</script>

<?php
// Función auxiliar para obtener nombre del proveedor
function obtenerNombreProveedor($id) {
    global $conn;
    $query = "SELECT nombre FROM proveedores WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0 ? $result->fetch_assoc()['nombre'] : 'Desconocido';
}

// Función auxiliar para obtener nombre de la categoría
function obtenerNombreCategoria($id) {
    global $conn;
    $query = "SELECT nombre FROM categorias WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0 ? $result->fetch_assoc()['nombre'] : 'Desconocida';
}
?>

<?php require_once 'footer.php'; ?>