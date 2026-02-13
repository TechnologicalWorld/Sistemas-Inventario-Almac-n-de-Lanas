<?php
session_start();

require_once 'config.php';
require_once 'funciones.php';

// Verificar sesión
verificarSesion();

$titulo_pagina = "Historial de Ventas";
$icono_titulo = "fas fa-history";
$breadcrumb = [
    ['text' => 'Ventas', 'link' => 'ventas.php', 'active' => false],
    ['text' => 'Historial', 'link' => '#', 'active' => true]
];

// Obtener parámetros de filtro
$filtro_fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$filtro_fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$filtro_vendedor = isset($_GET['vendedor']) ? $_GET['vendedor'] : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_cliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$filtro_tipo_pago = isset($_GET['tipo_pago']) ? $_GET['tipo_pago'] : '';

// Validar fechas
if (!strtotime($filtro_fecha_inicio)) $filtro_fecha_inicio = date('Y-m-01');
if (!strtotime($filtro_fecha_fin)) $filtro_fecha_fin = date('Y-m-d');

// Construir consulta con filtros
$where_conditions = ["v.anulado = 0"];
$params = [];
$types = "";

// Filtro por fecha
if ($filtro_fecha_inicio && $filtro_fecha_fin) {
    $where_conditions[] = "v.fecha BETWEEN ? AND ?";
    $params[] = $filtro_fecha_inicio;
    $params[] = $filtro_fecha_fin;
    $types .= "ss";
}

// Filtro por vendedor
if ($filtro_vendedor && $_SESSION['usuario_rol'] == 'administrador') {
    $where_conditions[] = "v.vendedor_id = ?";
    $params[] = $filtro_vendedor;
    $types .= "i";
} elseif ($_SESSION['usuario_rol'] == 'vendedor') {
    $where_conditions[] = "v.vendedor_id = ?";
    $params[] = $_SESSION['usuario_id'];
    $types .= "i";
}

// Filtro por estado
if ($filtro_estado && in_array($filtro_estado, ['pendiente', 'pagada', 'cancelada'])) {
    $where_conditions[] = "v.estado = ?";
    $params[] = $filtro_estado;
    $types .= "s";
}

// Filtro por cliente
if ($filtro_cliente) {
    $where_conditions[] = "(c.nombre LIKE ? OR c.codigo LIKE ? OR v.cliente_contado LIKE ?)";
    $params[] = "%$filtro_cliente%";
    $params[] = "%$filtro_cliente%";
    $params[] = "%$filtro_cliente%";
    $types .= "sss";
}

// Filtro por tipo de pago
if ($filtro_tipo_pago && in_array($filtro_tipo_pago, ['contado', 'credito', 'mixto'])) {
    $where_conditions[] = "v.tipo_pago = ?";
    $params[] = $filtro_tipo_pago;
    $types .= "s";
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Consulta principal
$query_ventas = "SELECT v.id, v.codigo_venta, v.fecha, v.hora_inicio, v.hora_fin,
                COALESCE(c.nombre, v.cliente_contado) as cliente,
                c.codigo as cliente_codigo,
                c.telefono as cliente_telefono,
                u.nombre as vendedor,
                v.tipo_pago, v.subtotal, v.descuento, v.total,
                v.pago_inicial, v.debe, v.estado,
                v.impreso, v.anulado, v.observaciones,
                (SELECT COUNT(*) FROM venta_detalles vd WHERE vd.venta_id = v.id) as total_productos
                FROM ventas v
                LEFT JOIN clientes c ON v.cliente_id = c.id
                JOIN usuarios u ON v.vendedor_id = u.id
                $where_clause
                ORDER BY v.fecha DESC, v.hora_inicio DESC
                LIMIT 1000";

// Ejecutar consulta
if (!empty($params)) {
    $stmt = $conn->prepare($query_ventas);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result_ventas = $stmt->get_result();
    } else {
        $result_ventas = $conn->query($query_ventas);
    }
} else {
    $result_ventas = $conn->query($query_ventas);
}

// Obtener vendedores para filtro
$vendedores = [];
if ($_SESSION['usuario_rol'] == 'administrador') {
    $query_vendedores = "SELECT id, nombre FROM usuarios WHERE rol = 'vendedor' AND activo = 1 ORDER BY nombre";
    $result_vendedores = $conn->query($query_vendedores);
    if ($result_vendedores) {
        while ($vendedor = $result_vendedores->fetch_assoc()) {
            $vendedores[] = $vendedor;
        }
    }
}

// Estadísticas
$query_stats = "SELECT 
                COUNT(*) as total_ventas,
                COALESCE(SUM(v.total), 0) as total_monto,
                COALESCE(SUM(v.pago_inicial), 0) as total_pagado,
                COALESCE(SUM(v.debe), 0) as total_debe,
                SUM(CASE WHEN v.estado = 'pagada' THEN 1 ELSE 0 END) as ventas_pagadas,
                SUM(CASE WHEN v.estado = 'pendiente' THEN 1 ELSE 0 END) as ventas_pendientes,
                SUM(CASE WHEN v.estado = 'cancelada' THEN 1 ELSE 0 END) as ventas_canceladas,
                SUM(CASE WHEN v.tipo_pago = 'contado' THEN v.total ELSE 0 END) as monto_contado,
                SUM(CASE WHEN v.tipo_pago = 'credito' THEN v.total ELSE 0 END) as monto_credito,
                SUM(CASE WHEN v.tipo_pago = 'mixto' THEN v.total ELSE 0 END) as monto_mixto
                FROM ventas v
                LEFT JOIN clientes c ON v.cliente_id = c.id
                $where_clause";

$stats = [
    'total_ventas' => 0,
    'total_monto' => 0,
    'total_pagado' => 0,
    'total_debe' => 0,
    'ventas_pagadas' => 0,
    'ventas_pendientes' => 0,
    'ventas_canceladas' => 0,
    'monto_contado' => 0,
    'monto_credito' => 0,
    'monto_mixto' => 0
];

if (!empty($params)) {
    $stmt_stats = $conn->prepare($query_stats);
    if ($stmt_stats) {
        $stmt_stats->bind_param($types, ...$params);
        $stmt_stats->execute();
        $result_stats = $stmt_stats->get_result();
        if ($result_stats) {
            $stats = $result_stats->fetch_assoc() ?: $stats;
        }
    }
} else {
    $result_stats = $conn->query($query_stats);
    if ($result_stats) {
        $stats = $result_stats->fetch_assoc() ?: $stats;
    }
}

// Asegurar valores numéricos
foreach ($stats as $key => $value) {
    $stats[$key] = floatval($value);
}

include 'header.php';
?>

<div class="row mb-4">
    <!-- Estadísticas -->
    <div class="col-xl-3 col-lg-6 mb-4">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 opacity-75">Total Ventas</h6>
                        <h2 class="card-title mb-1"><?php echo number_format($stats['total_ventas']); ?></h2>
                        <p class="card-text mb-0 fw-bold"><?php echo formatearMoneda($stats['total_monto']); ?></p>
                    </div>
                    <i class="fas fa-shopping-cart fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 mb-4">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 opacity-75">Pagadas</h6>
                        <h2 class="card-title mb-1"><?php echo number_format($stats['ventas_pagadas']); ?></h2>
                        <p class="card-text mb-0 fw-bold"><?php echo formatearMoneda($stats['total_pagado']); ?></p>
                    </div>
                    <i class="fas fa-check-circle fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 mb-4">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 opacity-75">Pendientes</h6>
                        <h2 class="card-title mb-1"><?php echo number_format($stats['ventas_pendientes']); ?></h2>
                        <p class="card-text mb-0 fw-bold"><?php echo formatearMoneda($stats['total_debe']); ?></p>
                    </div>
                    <i class="fas fa-clock fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 mb-4">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 opacity-75">Período</h6>
                        <div class="small">
                            <?php echo date('d/m/Y', strtotime($filtro_fecha_inicio)); ?><br>
                            <?php echo date('d/m/Y', strtotime($filtro_fecha_fin)); ?>
                        </div>
                    </div>
                    <i class="fas fa-calendar fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 d-flex align-items-center">
                    <i class="fas fa-history me-2 text-primary"></i>Historial de Ventas
                    <span class="badge bg-primary ms-2"><?php echo $result_ventas ? $result_ventas->num_rows : 0; ?></span>
                </h5>
            </div>
            <div class="card-body p-3">
                <!-- Filtros -->
                <!--<div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-0 bg-light">
                            <div class="card-body p-3">
                                <form method="GET" action="" class="row g-2">
                                    <div class="col-md-2 col-sm-6">
                                        <label class="form-label small fw-bold">Fecha Desde</label>
                                        <input type="date" class="form-control form-control-sm" name="fecha_inicio" 
                                               value="<?php echo $filtro_fecha_inicio; ?>">
                                    </div>
                                    <div class="col-md-2 col-sm-6">
                                        <label class="form-label small fw-bold">Fecha Hasta</label>
                                        <input type="date" class="form-control form-control-sm" name="fecha_fin" 
                                               value="<?php echo $filtro_fecha_fin; ?>">
                                    </div>
                                    
                                    <?php if ($_SESSION['usuario_rol'] == 'administrador'): ?>
                                    <div class="col-md-2 col-sm-6">
                                        <label class="form-label small fw-bold">Vendedor</label>
                                        <select class="form-select form-select-sm" name="vendedor">
                                            <option value="">Todos</option>
                                            <?php foreach ($vendedores as $vendedor): ?>
                                            <option value="<?php echo $vendedor['id']; ?>"
                                                <?php echo ($filtro_vendedor == $vendedor['id']) ? 'selected' : ''; ?>>
                                                <?php echo $vendedor['nombre']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="col-md-2 col-sm-6">
                                        <label class="form-label small fw-bold">Estado</label>
                                        <select class="form-select form-select-sm" name="estado">
                                            <option value="">Todos</option>
                                            <option value="pendiente" <?php echo ($filtro_estado == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                                            <option value="pagada" <?php echo ($filtro_estado == 'pagada') ? 'selected' : ''; ?>>Pagada</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2 col-sm-6">
                                        <label class="form-label small fw-bold">Tipo Pago</label>
                                        <select class="form-select form-select-sm" name="tipo_pago">
                                            <option value="">Todos</option>
                                            <option value="contado" <?php echo ($filtro_tipo_pago == 'contado') ? 'selected' : ''; ?>>Contado</option>
                                            <option value="credito" <?php echo ($filtro_tipo_pago == 'credito') ? 'selected' : ''; ?>>Crédito</option>
                                            <option value="mixto" <?php echo ($filtro_tipo_pago == 'mixto') ? 'selected' : ''; ?>>Mixto</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2 col-sm-6">
                                        <label class="form-label small fw-bold">Cliente</label>
                                        <input type="text" class="form-control form-control-sm" name="cliente" 
                                               value="<?php echo htmlspecialchars($filtro_cliente); ?>" 
                                               placeholder="Buscar...">
                                    </div>
                                    
                                    <div class="col-md-12 mt-2">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-filter me-1"></i>Filtrar
                                        </button>
                                        <a href="historial_ventas.php" class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-times me-1"></i>Limpiar
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div> -->
                
                <!-- Tabla de ventas -->
                <div class="table-responsive border rounded" style="max-height: 600px; overflow-y: auto;">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th width="120">Venta</th>
                                <th width="100">Fecha</th>
                                <th>Cliente</th>
                                <th width="100">Vendedor</th>
                                <th class="text-end" width="100">Total</th>
                                <th class="text-end" width="90">Pagado</th>
                                <th class="text-end" width="90">Debe</th>
                                <th class="text-center" width="90">Tipo</th>
                                <th class="text-center" width="100">Estado</th>
                                <th class="text-center" width="150">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_ventas && $result_ventas->num_rows > 0): 
                                while ($venta = $result_ventas->fetch_assoc()):
                                    $clase_fila = '';
                                    if ($venta['estado'] == 'pagada') $clase_fila = 'table-success';
                                    elseif ($venta['debe'] > 0) $clase_fila = 'table-warning';
                            ?>
                            <tr class="<?php echo $clase_fila; ?>">
                                <td>
                                    <div class="fw-bold"><?php echo $venta['codigo_venta']; ?></div>
                                    <small class="text-muted"><?php echo formatearHora($venta['hora_inicio']); ?></small>
                                </td>
                                <td><?php echo formatearFecha($venta['fecha']); ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($venta['cliente']); ?></div>
                                    <?php if ($venta['cliente_telefono']): ?>
                                    <small class="text-muted"><?php echo $venta['cliente_telefono']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $venta['vendedor']; ?></td>
                                <td class="text-end fw-bold"><?php echo formatearMoneda($venta['total']); ?></td>
                                <td class="text-end text-success"><?php echo formatearMoneda($venta['pago_inicial']); ?></td>
                                <td class="text-end <?php echo $venta['debe'] > 0 ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                    <?php echo formatearMoneda($venta['debe']); ?>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    $badge_tipo = [
                                        'contado' => 'success',
                                        'credito' => 'warning',
                                        'mixto' => 'info'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $badge_tipo[$venta['tipo_pago']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($venta['tipo_pago']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    $badge_estado = [
                                        'pagada' => 'success',
                                        'pendiente' => 'warning',
                                        'cancelada' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $badge_estado[$venta['estado']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($venta['estado']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" 
                                                onclick="verDetalleVenta(<?php echo $venta['id']; ?>)"
                                                title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="imprimir_recibo.php?venta_id=<?php echo $venta['id']; ?>" 
                                           class="btn btn-outline-success" 
                                           target="_blank"
                                           title="Imprimir">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-5">
                                    <i class="fas fa-search fa-3x mb-3 opacity-50"></i>
                                    <h5>No se encontraron ventas</h5>
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

<!-- Modal para ver detalle -->
<div class="modal fade" id="modalDetalleVenta" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-receipt me-2"></i>Detalle de Venta
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoDetalleVenta">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-3">Cargando...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success" onclick="imprimirDetalleModal()">
                    <i class="fas fa-print me-2"></i>Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let ventaActualId = null;

function verDetalleVenta(ventaId) {
    ventaActualId = ventaId;
    
    fetch(`ajax_detalle_venta.php?venta_id=${ventaId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('contenidoDetalleVenta').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalleVenta')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('contenidoDetalleVenta').innerHTML = 
                '<div class="alert alert-danger">Error al cargar los detalles</div>';
        });
}

function imprimirDetalleModal() {
    if (ventaActualId) {
        window.open(`imprimir_recibo.php?venta_id=${ventaActualId}`, '_blank');
    }
}
</script>

<?php include 'footer.php'; ?>