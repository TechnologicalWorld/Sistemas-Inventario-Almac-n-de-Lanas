<?php
session_start();

$titulo_pagina = "Cuentas por Pagar";
$icono_titulo = "fas fa-file-invoice-dollar";
$breadcrumb = [
    ['text' => 'Proveedores', 'link' => 'proveedores.php', 'active' => false],
    ['text' => 'Cuentas por Pagar', 'link' => '#', 'active' => true]
];

require_once 'header.php';
require_once 'funciones.php';

// Verificar que sea administrador
verificarRol(['administrador']);

// Verificar conexión a base de datos
if (!isset($conn)) {
    die("Error: No hay conexión a la base de datos");
}

$proveedor_id = isset($_GET['proveedor_id']) ? intval($_GET['proveedor_id']) : null;
$mensaje = '';
$tipo_mensaje = '';

// Parámetros de búsqueda
$search = limpiar($_GET['search'] ?? '');

$whereProv = "saldo_actual > 0 AND activo = 1";
$paramsProv = [];
$typesProv = "";

if ($search !== '') {
    $whereProv .= " AND (codigo LIKE ? OR nombre LIKE ? OR ciudad LIKE ? OR telefono LIKE ? )";
    $like = "%$search%";
    $paramsProv = [$like, $like, $like, $like];
    $typesProv = "ssss";
}

// Mostrar mensaje si viene de redirección
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $mensaje = "Pago registrado exitosamente";
    $tipo_mensaje = "success";
}

// Obtener proveedores con saldo pendiente (SIN PAGINACIÓN)
$query_proveedores = "SELECT id, codigo, nombre, ciudad, telefono, 
                     credito_limite, saldo_actual, activo
                     FROM proveedores 
                     WHERE $whereProv
                     ORDER BY saldo_actual DESC";

if (!empty($paramsProv)) {
    $stmt_proveedores = $conn->prepare($query_proveedores);
    $stmt_proveedores->bind_param($typesProv, ...$paramsProv);
    $stmt_proveedores->execute();
    $result_proveedores = $stmt_proveedores->get_result();
} else {
    $result_proveedores = $conn->query($query_proveedores);
}

$total_proveedores_filtrados = $result_proveedores->num_rows;

// Obtener historial de un proveedor específico
$historial_proveedor = [];
$proveedor_info = null;

if ($proveedor_id) {
    // Información del proveedor
    $query_proveedor = "SELECT * FROM proveedores WHERE id = ? AND activo = 1";
    $stmt_proveedor = $conn->prepare($query_proveedor);
    $stmt_proveedor->bind_param("i", $proveedor_id);
    $stmt_proveedor->execute();
    $result_proveedor = $stmt_proveedor->get_result();
    
    if ($result_proveedor->num_rows > 0) {
        $proveedor_info = $result_proveedor->fetch_assoc();
        
        // Obtener historial del proveedor
        $query_historial = "SELECT pec.*, u.nombre as usuario_nombre 
                           FROM proveedores_estado_cuentas pec
                           LEFT JOIN usuarios u ON pec.usuario_id = u.id
                           WHERE pec.proveedor_id = ? 
                           ORDER BY pec.fecha DESC, pec.creado_en DESC";
        $stmt_historial = $conn->prepare($query_historial);
        $stmt_historial->bind_param("i", $proveedor_id);
        $stmt_historial->execute();
        $historial_proveedor = $stmt_historial->get_result();
    } else {
        $mensaje = "Proveedor no encontrado o inactivo";
        $tipo_mensaje = "warning";
        $proveedor_id = null;
    }
}
?>

<?php if ($mensaje): ?>
<div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($mensaje); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<style>
/* Estilos mejorados para el historial */
.historial-card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.historial-header {
    background: linear-gradient(135deg, #42b346 0%, #165032 100%);
    color: white;
    border-radius: 10px 10px 0 0 !important;
}

.movimiento-item {
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.movimiento-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

.movimiento-compra {
    border-left-color: #dc3545;
}

.movimiento-pago {
    border-left-color: #28a745;
}

.movimiento-adelanto {
    border-left-color: #ffc107;
}

.badge-movimiento {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.total-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
}

.total-card .cantidad {
    font-size: 1.5rem;
    font-weight: 700;
}

.total-card .etiqueta {
    font-size: 0.9rem;
    color: #6c757d;
}

.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 16px;
}

/* Scroll personalizado */
.historial-scroll {
    max-height: 500px;
    overflow-y: auto;
    padding-right: 5px;
}

.historial-scroll::-webkit-scrollbar {
    width: 6px;
}

.historial-scroll::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.historial-scroll::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
}

.historial-scroll::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Timeline */
.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 30px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
    padding-left: 60px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: 24px;
    top: 20px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: white;
    border: 3px solid;
}

.timeline-item.compra::before {
    border-color: #dc3545;
}

.timeline-item.pago::before {
    border-color: #28a745;
}

.timeline-item.adelanto::before {
    border-color: #ffc107;
}

.timeline-content {
    background: white;
    border-radius: 10px;
    padding: 15px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.timeline-date {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 5px;
}

.timeline-title {
    font-weight: 600;
    margin-bottom: 10px;
}

.timeline-amounts {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.amount-badge {
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 0.9rem;
}

.amount-badge.compra {
    background: #fee2e2;
    color: #dc3545;
}

.amount-badge.pago {
    background: #d4edda;
    color: #28a745;
}

.amount-badge.adelanto {
    background: #fff3cd;
    color: #856404;
}
</style>

<div class="row">
    <!-- Proveedores con saldo pendiente -->
    <div class="col-md-<?php echo $proveedor_id ? '5' : '12'; ?>">
        <div class="card shadow mb-4">
            <div class="card-header bg-gradient-warning text-white d-flex justify-content-between align-items-center py-3">
                <div>
                    <h5 class="mb-0">
                        <i class="fas fa-truck me-2"></i>Proveedores con Saldo Pendiente
                    </h5>
                    <small class="opacity-75">Total: <?php echo $total_proveedores_filtrados; ?> proveedores</small>
                </div>
                <div>
                    <button class="btn btn-sm btn-outline-light" onclick="exportarReportePagar()">
                        <i class="fas fa-file-excel me-1"></i>Exportar
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Buscador -->
                <form method="GET" class="mb-4">
                    <?php if ($proveedor_id): ?>
                        <input type="hidden" name="proveedor_id" value="<?php echo $proveedor_id; ?>">
                    <?php endif; ?>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-primary"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" 
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Buscar por código, nombre, ciudad o teléfono...">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search me-2"></i>Buscar
                        </button>
                        <?php if ($search): ?>
                        <a href="cuentas_pagar.php<?php echo $proveedor_id ? '?proveedor_id=' . $proveedor_id : ''; ?>" 
                           class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <!-- Tabla de proveedores con scroll -->
                <div class="table-responsive" style="max-height: 600px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px;">
                    <table class="table table-hover mb-0" id="tablaProveedoresPagar">
                        <thead class="table-light" style="position: sticky; top: 0; background: white; z-index: 10;">
                            <tr>
                                <th width="30%">Proveedor</th>
                                <th width="20%">Ciudad</th>
                                <th width="20%" class="text-end">Saldo Actual (Deuda)</th>
                                <th width="15%">Estado</th>
                                <th width="15%" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_proveedores->num_rows > 0): 
                                $total_deuda = 0;
                                $total_proveedores = 0;
                            ?>
                                <?php while ($proveedor = $result_proveedores->fetch_assoc()): 
                                    $total_deuda += $proveedor['saldo_actual'];
                                    $total_proveedores++;
                                ?>
                                <tr class="align-middle">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-primary text-white me-3">
                                                <?php echo strtoupper(substr($proveedor['nombre'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong class="d-block text-primary"><?php echo htmlspecialchars($proveedor['codigo']); ?></strong>
                                                <span class="d-block fw-bold"><?php echo htmlspecialchars($proveedor['nombre']); ?></span>
                                                <?php if ($proveedor['telefono']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone fa-xs me-1"></i><?php echo htmlspecialchars($proveedor['telefono']); ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-25 text-dark">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($proveedor['ciudad']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold text-danger fs-5">
                                            <?php echo formatearMoneda($proveedor['saldo_actual']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($proveedor['activo'] == 1): ?>
                                            <span class="badge bg-success rounded-pill px-3 py-2">
                                                <i class="fas fa-check-circle me-1"></i>Activo
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger rounded-pill px-3 py-2">
                                                <i class="fas fa-ban me-1"></i>Inactivo
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="cuentas_pagar.php?proveedor_id=<?php echo $proveedor['id']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                                           class="btn btn-sm btn-outline-primary <?php echo $proveedor_id == $proveedor['id'] ? 'active' : ''; ?>"
                                           title="Ver historial">
                                            <i class="fas fa-eye me-1"></i>Ver
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted p-5">
                                    <div class="py-4">
                                        <i class="fas fa-check-circle fa-4x mb-3 text-success opacity-50"></i>
                                        <h5 class="text-success">¡Excelente!</h5>
                                        <p class="mb-0">No hay proveedores con saldo pendiente</p>
                                        <small>Todas las cuentas están al día</small>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php if ($result_proveedores->num_rows > 0): ?>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="2">TOTAL GENERAL:</td>
                                <td class="text-end text-danger fs-5"><?php echo formatearMoneda($total_deuda); ?></td>
                                <td colspan="2" class="text-center"><?php echo $total_proveedores; ?> proveedores</td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Historial del proveedor seleccionado - VERSIÓN MEJORADA -->
    <?php if ($proveedor_id && $proveedor_info): ?>
    <div class="col-md-7">
        <div class="card shadow historial-card">
            <div class="card-header historial-header py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">
                            <i class="fas fa-history me-2"></i>
                            Historial de Movimientos
                        </h5>
                        <h4 class="mb-0 fw-bold"><?php echo htmlspecialchars($proveedor_info['nombre']); ?></h4>
                        <small class="opacity-75">
                            <i class="fas fa-code me-1"></i><?php echo htmlspecialchars($proveedor_info['codigo']); ?> | 
                            <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($proveedor_info['ciudad']); ?> | 
                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($proveedor_info['telefono'] ?: 'Sin teléfono'); ?>
                        </small>
                    </div>
                    <div>
                        <a href="cuentas_pagar.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>" 
                           class="btn btn-outline-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Volver
                        </a>
                        <a href="registrar_pago_proveedor.php?proveedor_id=<?php echo $proveedor_id; ?>" 
                           class="btn btn-success btn-sm ms-2">
                            <i class="fas fa-credit-card me-1"></i>Nuevo Abono
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Resumen de saldos - Tarjetas mejoradas -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="total-card text-center">
                            <div class="etiqueta">
                                <i class="fas fa-shopping-cart me-1 text-danger"></i>Total Compras
                            </div>
                            <div class="cantidad text-danger">
                                <?php 
                                $total_compras = 0;
                                if ($historial_proveedor) {
                                    $historial_proveedor->data_seek(0);
                                    while ($mov = $historial_proveedor->fetch_assoc()) {
                                        $total_compras += $mov['compra'];
                                    }
                                    $historial_proveedor->data_seek(0);
                                }
                                echo formatearMoneda($total_compras);
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="total-card text-center">
                            <div class="etiqueta">
                                <i class="fas fa-credit-card me-1 text-success"></i>Total Pagado
                            </div>
                            <div class="cantidad text-success">
                                <?php 
                                $total_pagado = 0;
                                if ($historial_proveedor) {
                                    $historial_proveedor->data_seek(0);
                                    while ($mov = $historial_proveedor->fetch_assoc()) {
                                        $total_pagado += $mov['a_cuenta'] + $mov['adelanto'];
                                    }
                                    $historial_proveedor->data_seek(0);
                                }
                                echo formatearMoneda($total_pagado);
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="total-card text-center">
                            <div class="etiqueta">
                                <i class="fas fa-clock me-1 text-warning"></i>Saldo Actual (Deuda)
                            </div>
                            <div class="cantidad text-warning">
                                <?php echo formatearMoneda($proveedor_info['saldo_actual']); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Historial de movimientos con timeline mejorado -->
                <h6 class="mb-3">
                    <i class="fas fa-list-alt me-2 text-primary"></i>
                    Detalle de Movimientos
                    <?php if ($historial_proveedor && $historial_proveedor->num_rows > 0): ?>
                    <span class="badge bg-primary ms-2"><?php echo $historial_proveedor->num_rows; ?> registros</span>
                    <?php endif; ?>
                </h6>
                
                <div class="historial-scroll">
                    <?php if ($historial_proveedor && $historial_proveedor->num_rows > 0): ?>
                        <div class="timeline">
                            <?php 
                            $contador = 0;
                            while ($movimiento = $historial_proveedor->fetch_assoc()): 
                                $tipo_clase = '';
                                $tipo_icono = '';
                                
                                if ($movimiento['compra'] > 0) {
                                    $tipo_clase = 'compra';
                                    $tipo_icono = 'fa-cart-plus';
                                } elseif ($movimiento['adelanto'] > 0) {
                                    $tipo_clase = 'adelanto';
                                    $tipo_icono = 'fa-hand-holding-usd';
                                } elseif ($movimiento['a_cuenta'] > 0) {
                                    $tipo_clase = 'pago';
                                    $tipo_icono = 'fa-credit-card';
                                }
                            ?>
                            <div class="timeline-item <?php echo $tipo_clase; ?>">
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="badge-movimiento <?php echo $tipo_clase; ?>">
                                                <i class="fas <?php echo $tipo_icono; ?> me-1"></i>
                                                <?php 
                                                if ($movimiento['compra'] > 0) echo 'COMPRA';
                                                elseif ($movimiento['adelanto'] > 0) echo 'ADELANTO';
                                                elseif ($movimiento['a_cuenta'] > 0) echo 'PAGO';
                                                ?>
                                            </span>
                                            <div class="timeline-date">
                                                <i class="far fa-calendar-alt me-1"></i>
                                                <?php echo formatearFecha($movimiento['fecha']); ?>
                                                <span class="mx-2">|</span>
                                                <i class="far fa-clock me-1"></i>
                                                <?php echo date('H:i', strtotime($movimiento['creado_en'])); ?>
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($movimiento['usuario_nombre'] ?: 'Usuario ' . $movimiento['usuario_id']); ?>
                                        </small>
                                    </div>
                                    
                                    <div class="timeline-title">
                                        <?php echo htmlspecialchars($movimiento['descripcion']); ?>
                                        <?php if ($movimiento['referencia']): ?>
                                        <br><small class="text-muted">Ref: <?php echo htmlspecialchars($movimiento['referencia']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="timeline-amounts">
                                        <?php if ($movimiento['compra'] > 0): ?>
                                        <div class="amount-badge compra">
                                            <i class="fas fa-plus-circle me-1"></i>
                                            Compra: <?php echo formatearMoneda($movimiento['compra']); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($movimiento['a_cuenta'] > 0): ?>
                                        <div class="amount-badge pago">
                                            <i class="fas fa-minus-circle me-1"></i>
                                            Pago: <?php echo formatearMoneda($movimiento['a_cuenta']); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($movimiento['adelanto'] > 0): ?>
                                        <div class="amount-badge adelanto">
                                            <i class="fas fa-minus-circle me-1"></i>
                                            Adelanto: <?php echo formatearMoneda($movimiento['adelanto']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mt-2 text-end">
                                        <small class="text-muted">Saldo después del movimiento:</small>
                                        <span class="fw-bold <?php echo $movimiento['saldo'] > 0 ? 'text-danger' : 'text-success'; ?> ms-2">
                                            <?php echo formatearMoneda($movimiento['saldo']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted p-5">
                            <i class="fas fa-history fa-4x mb-3 opacity-25"></i>
                            <h5 class="text-muted">No hay movimientos para este proveedor</h5>
                            <p class="mb-0">Los movimientos aparecerán cuando se registren compras o pagos</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Resumen general por ciudad (solo cuando no hay proveedor seleccionado) -->
<?php if (!$proveedor_id): ?>
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header bg-gradient-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-map-marked-alt me-2"></i>
                    Resumen de Deudas por Ciudad
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Ciudad</th>
                                <th class="text-center">Proveedores</th>
                                <th class="text-end">Total Deuda</th>
                                <th class="text-center">% del Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query_ciudades = "SELECT ciudad,
                                             COUNT(*) as total_proveedores,
                                             SUM(saldo_actual) as total_deuda
                                             FROM proveedores 
                                             WHERE saldo_actual > 0 AND activo = 1
                                             GROUP BY ciudad
                                             ORDER BY total_deuda DESC";
                            $result_ciudades = $conn->query($query_ciudades);
                            
                            if ($result_ciudades && $result_ciudades->num_rows > 0):
                                $total_general_deuda = 0;
                                $total_general_proveedores = 0;
                                
                                while ($ciudad = $result_ciudades->fetch_assoc()):
                                    $total_general_deuda += $ciudad['total_deuda'];
                                    $total_general_proveedores += $ciudad['total_proveedores'];
                            ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?php echo htmlspecialchars($ciudad['ciudad'] ?: 'Sin ciudad'); ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info rounded-pill px-3">
                                        <?php echo $ciudad['total_proveedores']; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold text-danger">
                                        <?php echo formatearMoneda($ciudad['total_deuda']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    $porcentaje = $total_general_deuda > 0 ? ($ciudad['total_deuda'] / $total_general_deuda) * 100 : 0;
                                    ?>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 10px;">
                                            <div class="progress-bar bg-info" 
                                                 style="width: <?php echo $porcentaje; ?>%">
                                            </div>
                                        </div>
                                        <span class="text-muted"><?php echo number_format($porcentaje, 1); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <!-- Totales -->
                            <tr class="table-active fw-bold">
                                <td>TOTAL GENERAL:</td>
                                <td class="text-center"><?php echo $total_general_proveedores; ?></td>
                                <td class="text-end text-danger fs-5"><?php echo formatearMoneda($total_general_deuda); ?></td>
                                <td class="text-center">100%</td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted p-4">
                                    No hay datos de proveedores por ciudad
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
<?php endif; ?>

<script>
// Función para exportar reporte a Excel
function exportarReportePagar() {
    var html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    html += '<title>Reporte de Cuentas por Pagar</title>';
    html += '<style>';
    html += 'body { font-family: Arial, sans-serif; margin: 20px; }';
    html += 'h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }';
    html += 'table { border-collapse: collapse; width: 100%; margin-top: 20px; }';
    html += 'th { background-color: #f8f9fa; color: #495057; text-align: left; padding: 12px; border: 1px solid #dee2e6; }';
    html += 'td { padding: 10px; border: 1px solid #dee2e6; }';
    html += '.text-end { text-align: right; }';
    html += '.text-center { text-align: center; }';
    html += '.text-danger { color: #dc3545; }';
    html += '.table-active { background-color: #e9ecef; font-weight: bold; }';
    html += '</style>';
    html += '</head><body>';
    
    html += '<h1>Reporte de Cuentas por Pagar</h1>';
    html += '<p><strong>Generado:</strong> ' + new Date().toLocaleDateString('es-BO') + ' ' + new Date().toLocaleTimeString('es-BO') + '</p>';
    
    // Copiar tabla sin botones
    var table = document.getElementById('tablaProveedoresPagar').cloneNode(true);
    
    // Remover botones de acciones
    var rows = table.getElementsByTagName('tr');
    for (var i = 0; i < rows.length; i++) {
        var cells = rows[i].getElementsByTagName('td');
        if (cells.length > 4) {
            if (cells[4]) {
                cells[4].innerHTML = ''; // Limpiar columna de acciones
            }
        }
    }
    
    html += table.outerHTML;
    html += '</body></html>';
    
    var blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    var fecha = new Date().toISOString().split('T')[0];
    a.download = 'cuentas_por_pagar_' + fecha + '.xls';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Inicializar tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php require_once 'footer.php'; ?>