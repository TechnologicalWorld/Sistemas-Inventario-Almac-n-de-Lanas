<?php
session_start();

require_once 'config.php';
require_once 'funciones.php';
require_once 'header.php';

verificarSesion();

// Obtener filtros
$filtro_fecha = isset($_GET['fecha']) && $_GET['fecha'] !== '' ? $_GET['fecha'] : date('Y-m-d');
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];
$types = "";

// Filtro por fecha
if ($filtro_fecha) {
    $where_conditions[] = "mc.fecha = ?";
    $params[] = $filtro_fecha;
    $types .= "s";
}

// Filtro por tipo
if ($filtro_tipo) {
    $where_conditions[] = "mc.tipo = ?";
    $params[] = $filtro_tipo;
    $types .= "s";
}

// Filtro por categoría
if ($filtro_categoria) {
    $where_conditions[] = "mc.categoria = ?";
    $params[] = $filtro_categoria;
    $types .= "s";
}

// Si es vendedor, solo ve sus movimientos
if ($_SESSION['usuario_rol'] == 'vendedor') {
    $where_conditions[] = "mc.usuario_id = ?";
    $params[] = $_SESSION['usuario_id'];
    $types .= "i";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Consulta principal
$query_movimientos = "SELECT mc.*, u.nombre as usuario_nombre, u.codigo as usuario_codigo
                     FROM movimientos_caja mc
                     JOIN usuarios u ON mc.usuario_id = u.id
                     $where_clause
                     ORDER BY mc.fecha DESC, mc.hora DESC";

$result_movimientos = null;
if (!empty($params)) {
    $stmt = $conn->prepare($query_movimientos);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result_movimientos = $stmt->get_result();
        $stmt->close();
    }
} else {
    $result_movimientos = $conn->query($query_movimientos);
}

// Estadísticas
$query_stats = "SELECT 
                COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as total_ingresos,
                COALESCE(SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END), 0) as total_gastos,
                COUNT(*) as total_movimientos,
                COUNT(DISTINCT categoria) as categorias_diferentes
                FROM movimientos_caja mc
                $where_clause";

$stats = ['total_ingresos' => 0, 'total_gastos' => 0, 'total_movimientos' => 0, 'categorias_diferentes' => 0];
if (!empty($params)) {
    $stmt_stats = $conn->prepare($query_stats);
    if ($stmt_stats) {
        $stmt_stats->bind_param($types, ...$params);
        $stmt_stats->execute();
        $result_stats = $stmt_stats->get_result();
        $stats = $result_stats->fetch_assoc();
        $stmt_stats->close();
    }
} else {
    $result_stats = $conn->query($query_stats);
    $stats = $result_stats->fetch_assoc();
}

// Obtener fechas disponibles
$query_fechas = "SELECT DISTINCT fecha FROM movimientos_caja ORDER BY fecha DESC LIMIT 30";
$result_fechas = $conn->query($query_fechas);

// Array de categorías
$categorias = [
    'venta_contado' => 'Venta Contado',
    'pago_inicial' => 'Pago Inicial',
    'abono_cliente' => 'Abono Cliente',
    'gasto_almuerzo' => 'Almuerzo',
    'gasto_varios' => 'Gastos Varios',
    'pago_proveedor' => 'Pago Proveedor',
    'otros' => 'Otros'
];

$total_ingresos = 0;
$total_gastos = 0;

// Resumen por categoría
$resumen_ingresos = [];
$resumen_gastos = [];

if ($filtro_fecha) {
    // Ingresos por categoría
    $query_ingresos_cat = "SELECT categoria, COUNT(*) as cantidad, SUM(monto) as total
                          FROM movimientos_caja 
                          WHERE tipo = 'ingreso' AND fecha = ?
                          GROUP BY categoria 
                          ORDER BY total DESC";
    
    $stmt_ingresos = $conn->prepare($query_ingresos_cat);
    if ($stmt_ingresos) {
        $stmt_ingresos->bind_param("s", $filtro_fecha);
        $stmt_ingresos->execute();
        $result_ingresos_cat = $stmt_ingresos->get_result();
        
        while ($row = $result_ingresos_cat->fetch_assoc()) {
            $resumen_ingresos[] = $row;
        }
        $stmt_ingresos->close();
    }
    
    // Gastos por categoría
    $query_gastos_cat = "SELECT categoria, COUNT(*) as cantidad, SUM(monto) as total
                        FROM movimientos_caja 
                        WHERE tipo = 'gasto' AND fecha = ?
                        GROUP BY categoria 
                        ORDER BY total DESC";
    
    $stmt_gastos = $conn->prepare($query_gastos_cat);
    if ($stmt_gastos) {
        $stmt_gastos->bind_param("s", $filtro_fecha);
        $stmt_gastos->execute();
        $result_gastos_cat = $stmt_gastos->get_result();
        
        while ($row = $result_gastos_cat->fetch_assoc()) {
            $resumen_gastos[] = $row;
        }
        $stmt_gastos->close();
    }
}

$titulo_pagina = "Movimientos de Caja";
$icono_titulo = "fas fa-cash-register";
$breadcrumb = [
    ['text' => 'Caja', 'link' => '#', 'active' => true]
];
?>

<!-- ========== CONTENIDO DE LA PÁGINA ========== -->
<div class="container-fluid px-4">
    <!-- Título de página -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800">
            <i class="<?php echo $icono_titulo; ?> me-2"></i>
            <?php echo $titulo_pagina; ?>
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <?php foreach ($breadcrumb as $item): ?>
                    <?php if ($item['active']): ?>
                        <li class="breadcrumb-item active"><?php echo $item['text']; ?></li>
                    <?php else: ?>
                        <li class="breadcrumb-item"><a href="<?php echo $item['link']; ?>"><?php echo $item['text']; ?></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </nav>
    </div>

    <!-- Tarjetas de estadísticas -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Ingresos
                            </div>
                            <div class="h2 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatearMoneda($stats['total_ingresos'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-down fa-2x text-success opacity-50"></i>
                        </div>
                    </div>
                    <div class="mt-2 small text-success">
                        <i class="fas fa-calendar-day me-1"></i>
                        <?php echo $filtro_fecha ? formatearFecha($filtro_fecha) : 'Todas las fechas'; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Total Gastos
                            </div>
                            <div class="h2 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatearMoneda($stats['total_gastos'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-up fa-2x text-danger opacity-50"></i>
                        </div>
                    </div>
                    <div class="mt-2 small text-danger">
                        <i class="fas fa-receipt me-1"></i>
                        <?php echo $stats['categorias_diferentes'] ?? 0; ?> categorías
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Balance
                            </div>
                            <div class="h2 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $balance = ($stats['total_ingresos'] ?? 0) - ($stats['total_gastos'] ?? 0);
                                echo formatearMoneda($balance); 
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-balance-scale fa-2x text-primary opacity-50"></i>
                        </div>
                    </div>
                    <div class="mt-2 small <?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <i class="fas fa-chart-line me-1"></i>
                        <?php 
                        $ingresos = $stats['total_ingresos'] ?? 0;
                        $porcentaje = $ingresos > 0 ? ($balance / $ingresos) * 100 : 0;
                        echo number_format($porcentaje, 1) . '% neto';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Movimientos
                            </div>
                            <div class="h2 mb-0 font-weight-bold text-gray-800">
                                <?php echo $stats['total_movimientos'] ?? 0; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exchange-alt fa-2x text-info opacity-50"></i>
                        </div>
                    </div>
                    <div class="mt-2 small text-info">
                        <i class="fas fa-user me-1"></i>
                        <?php echo $_SESSION['usuario_rol'] == 'administrador' ? 'Todos los usuarios' : 'Mis movimientos'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjeta principal -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list me-2"></i>
                Movimientos de Caja
            </h6>
            <div>
                <?php if ($_SESSION['usuario_rol'] == 'administrador'): ?>
                <button type="button" class="btn btn-success btn-sm" onclick="nuevoGasto()">
                    <i class="fas fa-plus-circle me-1"></i>Nuevo Gasto
                </button>
                <button class="btn btn-primary btn-sm ms-2" onclick="exportarMovimientos()">
                    <i class="fas fa-file-excel me-1"></i>Exportar
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <!-- Filtros -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <form method="GET" action="" class="form-inline">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="sr-only">Fecha</label>
                                <select class="form-select form-select-sm" name="fecha" onchange="this.form.submit()">
                                    <option value="<?php echo date('Y-m-d'); ?>">Hoy (<?php echo formatearFecha(date('Y-m-d')); ?>)</option>
                                    <?php 
                                    if ($result_fechas && $result_fechas->num_rows > 0):
                                        $result_fechas->data_seek(0);
                                        while ($fecha = $result_fechas->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $fecha['fecha']; ?>"
                                        <?php echo ($filtro_fecha == $fecha['fecha']) ? 'selected' : ''; ?>>
                                        <?php echo formatearFecha($fecha['fecha']); ?>
                                    </option>
                                    <?php 
                                        endwhile; 
                                    endif;
                                    ?>
                                    <option value="" <?php echo empty($filtro_fecha) ? 'selected' : ''; ?>>Todas las fechas</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="sr-only">Tipo</label>
                                <select class="form-select form-select-sm" name="tipo" onchange="this.form.submit()">
                                    <option value="">Todos</option>
                                    <option value="ingreso" <?php echo ($filtro_tipo == 'ingreso') ? 'selected' : ''; ?>>Ingresos</option>
                                    <option value="gasto" <?php echo ($filtro_tipo == 'gasto') ? 'selected' : ''; ?>>Gastos</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="sr-only">Categoría</label>
                                <select class="form-select form-select-sm" name="categoria" onchange="this.form.submit()">
                                    <option value="">Todas</option>
                                    <?php foreach($categorias as $key => $nombre): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($filtro_categoria == $key) ? 'selected' : ''; ?>>
                                        <?php echo $nombre; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-search me-1"></i>Filtrar
                                </button>
                            </div>
                            
                            <div class="col-md-2">
                                <a href="movimientos_caja.php" class="btn btn-outline-secondary btn-sm w-100">
                                    <i class="fas fa-redo me-1"></i>Limpiar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Buscador en tiempo real -->
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control" id="buscadorMovimientos" 
                               placeholder="Buscar por descripción, usuario, referencia, observaciones...">
                        <span class="input-group-text bg-white">
                            <span id="contadorMovimientos" class="badge bg-secondary">0</span>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de movimientos -->
            <div class="table-responsive">
                <table class="table table-hover table-sm" id="tablaMovimientos" width="100%" cellspacing="0">
                    <thead class="bg-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Tipo</th>
                            <th>Categoría</th>
                            <th>Descripción</th>
                            <th class="text-end">Monto</th>
                            <th>Usuario</th>
                            <th>Ref/Venta</th>
                            <?php if ($_SESSION['usuario_rol'] == 'administrador'): ?>
                            <th class="text-center">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="tbodyMovimientos">
                        <?php if ($result_movimientos && $result_movimientos->num_rows > 0): ?>
                            <?php 
                            while ($movimiento = $result_movimientos->fetch_assoc()): 
                                if ($movimiento['tipo'] == 'ingreso') {
                                    $total_ingresos += $movimiento['monto'];
                                } else {
                                    $total_gastos += $movimiento['monto'];
                                }
                            ?>
                            <tr class="movimiento-row <?php echo $movimiento['tipo'] == 'ingreso' ? 'table-success' : 'table-danger'; ?>"
                                data-id="<?php echo $movimiento['id']; ?>"
                                data-descripcion="<?php echo htmlspecialchars($movimiento['descripcion']); ?>"
                                data-usuario="<?php echo htmlspecialchars($movimiento['usuario_nombre']); ?>"
                                data-observaciones="<?php echo htmlspecialchars($movimiento['observaciones'] ?? ''); ?>"
                                data-referencia="<?php echo htmlspecialchars($movimiento['referencia_venta'] ?? ''); ?>">
                                <td class="fw-semibold">
                                    <?php echo date('d/m', strtotime($movimiento['fecha'])); ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo formatearHora($movimiento['hora']); ?></small>
                                </td>
                                <td>
                                    <?php if ($movimiento['tipo'] == 'ingreso'): ?>
                                        <span class="badge bg-success rounded-pill px-3">
                                            <i class="fas fa-arrow-down me-1"></i>INGRESO
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger rounded-pill px-3">
                                            <i class="fas fa-arrow-up me-1"></i>GASTO
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-3">
                                        <?php echo $categorias[$movimiento['categoria']] ?? $movimiento['categoria']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-medium"><?php echo htmlspecialchars($movimiento['descripcion']); ?></div>
                                    <?php if (!empty($movimiento['observaciones'])): ?>
                                    <small class="text-muted d-block" style="font-size: 0.8em;">
                                        <i class="fas fa-comment-dots me-1"></i>
                                        <?php echo substr(htmlspecialchars($movimiento['observaciones']), 0, 50); ?>
                                        <?php if (strlen($movimiento['observaciones']) > 50): ?>...<?php endif; ?>
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold <?php echo $movimiento['tipo'] == 'ingreso' ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo formatearMoneda($movimiento['monto']); ?>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($movimiento['usuario_nombre']); ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($movimiento['referencia_venta'])): ?>
                                    <span class="badge bg-info text-white"><?php echo htmlspecialchars($movimiento['referencia_venta']); ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($_SESSION['usuario_rol'] == 'administrador'): ?>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-outline-info" 
                                                onclick="verDetalleMovimiento(<?php echo $movimiento['id']; ?>)"
                                                title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($movimiento['tipo'] == 'gasto'): ?>
                                        <button class="btn btn-outline-warning" 
                                                onclick="editarGasto(<?php echo $movimiento['id']; ?>)"
                                                title="Editar gasto">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $_SESSION['usuario_rol'] == 'administrador' ? '9' : '8'; ?>" 
                                class="text-center py-5">
                                <div class="text-muted mb-3">
                                    <i class="fas fa-inbox fa-4x"></i>
                                </div>
                                <h5 class="text-muted">No hay movimientos</h5>
                                <p class="mb-0">No se encontraron movimientos con los filtros seleccionados</p>
                                <a href="movimientos_caja.php" class="btn btn-sm btn-outline-primary mt-3">
                                    <i class="fas fa-redo me-1"></i>Ver todos
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-light">
                        <tr>
                            <td colspan="4" class="fw-bold">TOTALES:</td>
                            <td class="text-end">
                                <span class="text-muted">Movimientos:</span>
                                <strong class="ms-2"><?php echo $result_movimientos ? $result_movimientos->num_rows : 0; ?></strong>
                            </td>
                            <td class="text-end">
                                <span class="text-success fw-bold"><?php echo formatearMoneda($total_ingresos); ?></span>
                                <span class="mx-2">/</span>
                                <span class="text-danger fw-bold"><?php echo formatearMoneda($total_gastos); ?></span>
                            </td>
                            <td class="text-center fw-bold">BALANCE:</td>
                            <td colspan="<?php echo $_SESSION['usuario_rol'] == 'administrador' ? '2' : '1'; ?>" 
                                class="text-center">
                                <span class="badge bg-<?php echo ($total_ingresos - $total_gastos) >= 0 ? 'success' : 'danger'; ?> p-2">
                                    <?php echo formatearMoneda($total_ingresos - $total_gastos); ?>
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Resumen por categoría -->
    <?php if ($filtro_fecha && (!empty($resumen_ingresos) || !empty($resumen_gastos))): ?>
    <div class="row">
        <?php if (!empty($resumen_ingresos)): ?>
        <div class="col-md-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-success text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-chart-pie me-2"></i>
                        Ingresos por Categoría
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless">
                            <tbody>
                                <?php foreach ($resumen_ingresos as $ingreso_cat): 
                                    $porcentaje = $total_ingresos > 0 ? ($ingreso_cat['total'] / $total_ingresos) * 100 : 0;
                                ?>
                                <tr>
                                    <td width="40%">
                                        <span class="small text-muted">
                                            <?php echo $categorias[$ingreso_cat['categoria']] ?? $ingreso_cat['categoria']; ?>
                                        </span>
                                        <span class="badge bg-secondary ms-2"><?php echo $ingreso_cat['cantidad']; ?></span>
                                    </td>
                                    <td width="50%">
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo $porcentaje; ?>%"
                                                 title="<?php echo number_format($porcentaje, 1); ?>%">
                                            </div>
                                        </div>
                                    </td>
                                    <td width="10%" class="text-end">
                                        <span class="fw-semibold small"><?php echo formatearMoneda($ingreso_cat['total']); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($resumen_gastos)): ?>
        <div class="col-md-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-danger text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-chart-pie me-2"></i>
                        Gastos por Categoría
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless">
                            <tbody>
                                <?php foreach ($resumen_gastos as $gasto_cat): 
                                    $porcentaje = $total_gastos > 0 ? ($gasto_cat['total'] / $total_gastos) * 100 : 0;
                                ?>
                                <tr>
                                    <td width="40%">
                                        <span class="small text-muted">
                                            <?php echo $categorias[$gasto_cat['categoria']] ?? $gasto_cat['categoria']; ?>
                                        </span>
                                        <span class="badge bg-secondary ms-2"><?php echo $gasto_cat['cantidad']; ?></span>
                                    </td>
                                    <td width="50%">
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar bg-danger" 
                                                 style="width: <?php echo $porcentaje; ?>%"
                                                 title="<?php echo number_format($porcentaje, 1); ?>%">
                                            </div>
                                        </div>
                                    </td>
                                    <td width="10%" class="text-end">
                                        <span class="fw-semibold small"><?php echo formatearMoneda($gasto_cat['total']); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal para ver detalle -->
<div class="modal fade" id="modalDetalleMovimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-2"></i>
                    Detalle del Movimiento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoDetalleMovimiento">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2 text-muted">Cargando detalle...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para nuevo/editar gasto -->
<div class="modal fade" id="modalGasto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formGasto" method="POST" action="ajax_guardar_gasto.php">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="modalGastoTitle">
                        <i class="fas fa-edit me-2"></i>
                        Editar Gasto
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="gastoId">
                    <input type="hidden" name="action" value="guardar_gasto">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Descripción <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="descripcion" id="gastoDescripcion" required
                               placeholder="Ej: Compra de materiales">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Monto (Bs) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Bs</span>
                                <input type="number" class="form-control" name="monto" id="gastoMonto" 
                                       step="0.01" min="0.01" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Categoría <span class="text-danger">*</span></label>
                            <select class="form-select" name="categoria" id="gastoCategoria" required>
                                <option value="">Seleccionar...</option>
                                <option value="gasto_almuerzo">Almuerzo</option>
                                <option value="gasto_varios">Gastos Varios</option>
                                <option value="pago_proveedor">Pago Proveedor</option>
                                <option value="otros">Otros</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Referencia</label>
                        <input type="text" class="form-control" name="referencia_venta" id="gastoReferencia" 
                               placeholder="N° de factura, ticket, etc.">
                        <small class="text-muted">Opcional, para identificar el comprobante</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Observaciones</label>
                        <textarea class="form-control" name="observaciones" id="gastoObservaciones" rows="3" 
                                  placeholder="Detalles adicionales del gasto..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-warning" id="btnGuardarGasto">
                        <i class="fas fa-save me-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.border-left-success { border-left: 0.25rem solid #28a745 !important; }
.border-left-danger { border-left: 0.25rem solid #dc3545 !important; }
.border-left-primary { border-left: 0.25rem solid #007bff !important; }
.border-left-info { border-left: 0.25rem solid #17a2b8 !important; }

.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,0.03) !important;
}

.table-success {
    background-color: rgba(40, 167, 69, 0.05) !important;
}

.table-danger {
    background-color: rgba(220, 53, 69, 0.05) !important;
}

.progress {
    background-color: #e9ecef;
    border-radius: 0.25rem;
}

.progress-bar {
    transition: width 0.6s ease;
}

.opacity-50 { opacity: 0.5; }

#buscadorMovimientos:focus {
    border-color: #4dabf7;
    box-shadow: 0 0 0 0.2rem rgba(77, 171, 247, 0.25);
}

.loading-overlay {
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
}

.alert-flotante {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 99999;
    min-width: 300px;
    max-width: 500px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

@media print {
    .btn, .modal, .card-header .btn-group { display: none; }
}
</style>

<script>
// Variables globales
let modalDetalle, modalGasto;

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar modales
    if (typeof bootstrap !== 'undefined') {
        const modalDetalleEl = document.getElementById('modalDetalleMovimiento');
        const modalGastoEl = document.getElementById('modalGasto');
        
        if (modalDetalleEl) modalDetalle = new bootstrap.Modal(modalDetalleEl);
        if (modalGastoEl) modalGasto = new bootstrap.Modal(modalGastoEl);
    }
    
    // Inicializar buscador
    initBuscador();
    
    // Inicializar formulario
    initFormularioGasto();
    
    // Auto-focus en el buscador
    setTimeout(() => {
        const buscador = document.getElementById('buscadorMovimientos');
        if (buscador) buscador.focus();
    }, 100);
    
    // Actualizar contador de movimientos
    actualizarContadorMovimientos();
});

// Funciones de utilidad
function mostrarLoading(mensaje = 'Cargando...') {
    // Eliminar loading anterior si existe
    ocultarLoading();
    
    const loading = document.createElement('div');
    loading.id = 'loadingOverlay';
    loading.className = 'loading-overlay';
    loading.innerHTML = `
        <div class="text-center text-white">
            <div class="spinner-border mb-3" style="width: 3rem; height: 3rem;"></div>
            <h6>${mensaje}</h6>
        </div>
    `;
    document.body.appendChild(loading);
}

function ocultarLoading() {
    const loading = document.getElementById('loadingOverlay');
    if (loading) loading.remove();
}

function mostrarMensaje(tipo, mensaje) {
    // Eliminar mensajes anteriores
    const mensajesAnteriores = document.querySelectorAll('.alert-flotante');
    mensajesAnteriores.forEach(function(el) {
        el.remove();
    });
    
    const alerta = document.createElement('div');
    alerta.className = `alert alert-${tipo} alert-dismissible fade show alert-flotante`;
    
    let icono = '';
    switch(tipo) {
        case 'success': icono = 'check-circle'; break;
        case 'danger': icono = 'exclamation-circle'; break;
        case 'warning': icono = 'exclamation-triangle'; break;
        case 'info': icono = 'info-circle'; break;
    }
    
    alerta.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-${icono} me-2 fa-lg"></i>
            <span>${mensaje}</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alerta);
    setTimeout(() => alerta.remove(), 5000);
}

// Funciones principales
function verDetalleMovimiento(movimientoId) {
    if (!movimientoId) {
        mostrarMensaje('danger', 'ID de movimiento no válido');
        return;
    }
    
    mostrarLoading('Cargando detalle...');
    
    fetch('ajax_detalle_movimiento.php?id=' + movimientoId + '&t=' + new Date().getTime())
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor: ' + response.status);
            }
            return response.text();
        })
        .then(html => {
            ocultarLoading(); // Ocultar loading inmediatamente
            document.getElementById('contenidoDetalleMovimiento').innerHTML = html;
            if (modalDetalle) {
                setTimeout(() => {
                    modalDetalle.show();
                }, 100);
            } else {
                // Fallback si bootstrap no está disponible
                const modalEl = document.getElementById('modalDetalleMovimiento');
                if (modalEl) {
                    const modal = new bootstrap.Modal(modalEl);
                    setTimeout(() => {
                        modal.show();
                    }, 100);
                }
            }
        })
        .catch(error => {
            ocultarLoading(); // Ocultar loading en caso de error
            console.error('Error:', error);
            mostrarMensaje('danger', 'Error al cargar el detalle del movimiento: ' + error.message);
            document.getElementById('contenidoDetalleMovimiento').innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-exclamation-triangle text-danger fa-3x mb-3"></i>
                    <h5 class="text-danger">Error al cargar el detalle</h5>
                    <p class="text-muted">${error.message}</p>
                </div>
            `;
        });
}

function nuevoGasto() {
    if (!modalGasto) {
        const modalEl = document.getElementById('modalGasto');
        if (modalEl) {
            modalGasto = new bootstrap.Modal(modalEl);
        }
    }
    
    document.getElementById('modalGastoTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i>Nuevo Gasto';
    document.getElementById('gastoId').value = '';
    document.getElementById('gastoDescripcion').value = '';
    document.getElementById('gastoMonto').value = '';
    document.getElementById('gastoCategoria').value = '';
    document.getElementById('gastoReferencia').value = '';
    document.getElementById('gastoObservaciones').value = '';
    document.getElementById('btnGuardarGasto').innerHTML = '<i class="fas fa-save me-1"></i>Guardar Gasto';
    document.getElementById('btnGuardarGasto').className = 'btn btn-success';
    
    // Remover validación previa
    const form = document.getElementById('formGasto');
    if (form) {
        form.classList.remove('was-validated');
    }
    
    if (modalGasto) {
        modalGasto.show();
    }
}

function editarGasto(gastoId) {
    if (!gastoId) {
        mostrarMensaje('danger', 'ID de gasto no válido');
        return;
    }
    
    if (!modalGasto) {
        const modalEl = document.getElementById('modalGasto');
        if (modalEl) {
            modalGasto = new bootstrap.Modal(modalEl);
        }
    }
    
    mostrarLoading('Cargando gasto...');
    
    fetch('ajax_info_gasto.php?id=' + gastoId + '&t=' + new Date().getTime())
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            ocultarLoading(); // Ocultar loading inmediatamente
            
            if (data.error) {
                throw new Error(data.message || 'Error al cargar el gasto');
            }
            
            document.getElementById('modalGastoTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Editar Gasto';
            document.getElementById('gastoId').value = data.id || '';
            document.getElementById('gastoDescripcion').value = data.descripcion || '';
            document.getElementById('gastoMonto').value = data.monto || '';
            document.getElementById('gastoCategoria').value = data.categoria || '';
            document.getElementById('gastoReferencia').value = data.referencia_venta || '';
            document.getElementById('gastoObservaciones').value = data.observaciones || '';
            document.getElementById('btnGuardarGasto').innerHTML = '<i class="fas fa-save me-1"></i>Guardar Cambios';
            document.getElementById('btnGuardarGasto').className = 'btn btn-warning';
            
            // Remover validación previa
            const form = document.getElementById('formGasto');
            if (form) {
                form.classList.remove('was-validated');
            }
            
            if (modalGasto) {
                setTimeout(() => {
                    modalGasto.show();
                }, 100);
            }
        })
        .catch(error => {
            ocultarLoading(); // Ocultar loading en caso de error
            console.error('Error:', error);
            mostrarMensaje('danger', error.message || 'Error al cargar los datos del gasto');
        });
}

function initFormularioGasto() {
    const form = document.getElementById('formGasto');
    if (!form) return;
    
    form.onsubmit = function(e) {
        e.preventDefault();
        
        const gastoId = document.getElementById('gastoId').value;
        const descripcion = document.getElementById('gastoDescripcion').value.trim();
        const monto = document.getElementById('gastoMonto').value;
        const categoria = document.getElementById('gastoCategoria').value;
        
        // Validaciones
        if (!descripcion) {
            mostrarMensaje('warning', 'La descripción es requerida');
            return;
        }
        
        if (!monto || parseFloat(monto) <= 0) {
            mostrarMensaje('warning', 'El monto debe ser mayor a 0');
            return;
        }
        
        if (!categoria) {
            mostrarMensaje('warning', 'La categoría es requerida');
            return;
        }
        
        const esNuevo = !gastoId;
        mostrarLoading(esNuevo ? 'Guardando gasto...' : 'Actualizando gasto...');
        
        // Usar FormData para enviar
        const formData = new FormData(form);
        
        fetch('ajax_guardar_gasto.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            ocultarLoading(); // Ocultar loading inmediatamente
            
            if (data.success) {
                mostrarMensaje('success', data.message);
                if (modalGasto) {
                    modalGasto.hide();
                }
                setTimeout(() => window.location.reload(), 1500);
            } else {
                mostrarMensaje('danger', data.message || 'Error al guardar el gasto');
            }
        })
        .catch(error => {
            ocultarLoading(); // Ocultar loading en caso de error
            console.error('Error:', error);
            mostrarMensaje('danger', 'Error de conexión al guardar el gasto');
        });
    };
}

function actualizarContadorMovimientos() {
    const filas = document.querySelectorAll('.movimiento-row');
    const contador = document.getElementById('contadorMovimientos');
    if (contador) {
        contador.textContent = filas.length;
    }
}

function exportarMovimientos() {
    mostrarLoading('Generando archivo Excel...');
    
    // Crear una copia de la tabla para exportar
    const tablaOriginal = document.getElementById('tablaMovimientos');
    if (!tablaOriginal) {
        ocultarLoading();
        mostrarMensaje('danger', 'No se pudo generar el archivo');
        return;
    }
    
    const tablaExportar = tablaOriginal.cloneNode(true);
    
    // Eliminar la columna de acciones
    const ths = tablaExportar.querySelectorAll('thead tr th');
    if (ths.length > 0) {
        ths[ths.length - 1].remove();
    }
    
    // Eliminar las celdas de acciones de cada fila
    tablaExportar.querySelectorAll('tbody tr').forEach(row => {
        const cells = row.cells;
        if (cells.length > 0) {
            cells[cells.length - 1].remove();
        }
    });
    
    // Eliminar el foot
    if (tablaExportar.querySelector('tfoot')) {
        tablaExportar.querySelector('tfoot').remove();
    }
    
    // Obtener fecha actual
    const fecha = new Date();
    const fechaStr = fecha.toLocaleDateString('es-ES');
    const horaStr = fecha.toLocaleTimeString('es-ES');
    
    // Construir HTML para exportar
    const html = `
        <html>
            <head>
                <meta charset="UTF-8">
                <title>Movimientos de Caja</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    h1 { color: #333; font-size: 24px; margin-bottom: 10px; }
                    h2 { color: #666; font-size: 18px; margin-bottom: 20px; }
                    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
                    th { background-color: #007bff; color: white; padding: 10px; text-align: left; }
                    td { padding: 8px; border-bottom: 1px solid #ddd; }
                    .ingreso { color: #28a745; font-weight: bold; }
                    .gasto { color: #dc3545; font-weight: bold; }
                    .footer { margin-top: 30px; padding-top: 20px; border-top: 2px solid #333; }
                    .resumen { background-color: #f8f9fc; padding: 15px; border-radius: 5px; }
                </style>
            </head>
            <body>
                <div style="text-align: center;">
                    <h1><?php echo EMPRESA_NOMBRE ?? 'TIENDA DE LANAS'; ?></h1>
                    <h2>Reporte de Movimientos de Caja</h2>
                    <p>
                        <strong>Fecha:</strong> ${fechaStr} ${horaStr}<br>
                        <strong>Filtro:</strong> <?php echo $filtro_fecha ? formatearFecha($filtro_fecha) : 'Todas las fechas'; ?><br>
                        <strong>Usuario:</strong> <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?>
                    </p>
                </div>
                
                ${tablaExportar.outerHTML}
                
                <div class="footer">
                    <div class="resumen">
                        <h3 style="margin-top: 0;">Resumen</h3>
                        <table style="width: auto; margin-top: 10px;">
                            <tr>
                                <td><strong>Total Ingresos:</strong></td>
                                <td style="color: #28a745; font-weight: bold;"><?php echo formatearMoneda($total_ingresos); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Total Gastos:</strong></td>
                                <td style="color: #dc3545; font-weight: bold;"><?php echo formatearMoneda($total_gastos); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Balance Final:</strong></td>
                                <td style="color: <?php echo ($total_ingresos - $total_gastos) >= 0 ? '#28a745' : '#dc3545'; ?>; font-weight: bold;">
                                    <?php echo formatearMoneda($total_ingresos - $total_gastos); ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Total Movimientos:</strong></td>
                                <td><?php echo $result_movimientos ? $result_movimientos->num_rows : 0; ?></td>
                            </tr>
                        </table>
                    </div>
                    <p style="text-align: center; color: #666; margin-top: 30px;">
                        Reporte generado el ${fechaStr} a las ${horaStr}
                    </p>
                </div>
            </body>
        </html>
    `;
    
    ocultarLoading();
    
    // Descargar archivo
    try {
        const blob = new Blob([html], {type: 'application/vnd.ms-excel'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `movimientos_caja_${new Date().toISOString().split('T')[0]}.xls`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        mostrarMensaje('success', 'Archivo exportado exitosamente');
    } catch (error) {
        console.error('Error al exportar:', error);
        mostrarMensaje('danger', 'Error al exportar el archivo');
    }
}

// Buscador en tiempo real
function initBuscador() {
    const buscador = document.getElementById('buscadorMovimientos');
    const contador = document.getElementById('contadorMovimientos');
    const filas = document.querySelectorAll('.movimiento-row');
    
    if (!buscador || !contador) return;
    
    // Actualizar contador inicial
    contador.textContent = filas.length;
    
    function buscar(termino) {
        let encontrados = 0;
        const terminoLower = termino.toLowerCase().trim();
        
        filas.forEach(fila => {
            const descripcion = fila.getAttribute('data-descripcion')?.toLowerCase() || '';
            const usuario = fila.getAttribute('data-usuario')?.toLowerCase() || '';
            const observaciones = fila.getAttribute('data-observaciones')?.toLowerCase() || '';
            const referencia = fila.getAttribute('data-referencia')?.toLowerCase() || '';
            
            const coincide = terminoLower === '' || 
                descripcion.includes(terminoLower) ||
                usuario.includes(terminoLower) ||
                observaciones.includes(terminoLower) ||
                referencia.includes(terminoLower);
            
            fila.style.display = coincide ? '' : 'none';
            if (coincide) encontrados++;
        });
        
        contador.textContent = encontrados;
        
        // Mostrar mensaje si no hay resultados
        const tbody = document.getElementById('tbodyMovimientos');
        let filaVacia = tbody.querySelector('.no-results');
        
        if (terminoLower !== '' && encontrados === 0) {
            if (!filaVacia) {
                const colspan = <?php echo $_SESSION['usuario_rol'] == 'administrador' ? 9 : 8; ?>;
                const nuevaFila = document.createElement('tr');
                nuevaFila.className = 'no-results';
                nuevaFila.innerHTML = `
                    <td colspan="${colspan}" class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No se encontraron resultados</h5>
                        <p class="text-muted mb-0">No hay movimientos que coincidan con "${termino}"</p>
                    </td>
                `;
                tbody.appendChild(nuevaFila);
            }
        } else if (filaVacia) {
            filaVacia.remove();
        }
    }
    
    buscador.addEventListener('input', function() {
        buscar(this.value);
    });
    
    buscador.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            buscar('');
        }
    });
}
</script>

<?php 
// Liberar resultados
if (isset($result_movimientos)) $result_movimientos->free();
if (isset($result_fechas)) $result_fechas->free();
if (isset($result_stats)) $result_stats->free();
if (isset($result_ingresos_cat)) $result_ingresos_cat->free();
if (isset($result_gastos_cat)) $result_gastos_cat->free();

require_once 'footer.php';
?>