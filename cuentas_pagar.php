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

// Mostrar mensaje si viene de redirección
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $mensaje = "Pago registrado exitosamente";
    $tipo_mensaje = "success";
}

// Obtener proveedores con saldo pendiente
$query_proveedores = "SELECT id, codigo, nombre, ciudad, telefono, 
                     credito_limite, saldo_actual, activo
                     FROM proveedores 
                     WHERE saldo_actual > 0 AND activo = 1
                     ORDER BY saldo_actual DESC";
$result_proveedores = $conn->query($query_proveedores);

// Verificar error en consulta
if (!$result_proveedores) {
    die("Error en la consulta: " . $conn->error);
}

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

<div class="row">
    <!-- Proveedores con saldo pendiente -->
    <div class="col-md-<?php echo $proveedor_id ? '6' : '12'; ?>">
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Proveedores con Saldo Pendiente</h5>
                <div>
                    <span class="badge bg-danger me-2">
                        <?php echo $result_proveedores->num_rows; ?> proveedores
                    </span>
                    <button class="btn btn-sm btn-outline-primary" onclick="exportarReportePagar()">
                        <i class="fas fa-file-excel"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Buscador -->
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" id="buscarProveedorPagar" 
                               placeholder="Buscar por código, nombre, ciudad o teléfono...">
                        <button class="btn btn-outline-secondary" type="button" onclick="limpiarBusqueda()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover table-sm" id="tablaProveedoresPagar">
                        <thead class="table-light">
                            <tr>
                                <th width="25%">Proveedor</th>
                                <th width="15%">Ciudad</th>
                                <th width="15%" class="text-end">Límite Crédito</th>
                                <th width="20%" class="text-end">Saldo Actual</th>
                                <th width="15%" class="text-end">Disponible</th>
                                <th width="10%">Estado</th>
                                <th width="15%">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_proveedores->num_rows > 0): 
                                $total_deuda = 0;
                                $total_limite = 0;
                                $total_disponible = 0;
                                $total_proveedores = 0;
                            ?>
                                <?php while ($proveedor = $result_proveedores->fetch_assoc()): 
                                    $disponible = $proveedor['credito_limite'] - $proveedor['saldo_actual'];
                                    $porcentaje_uso = $proveedor['credito_limite'] > 0 ? 
                                        ($proveedor['saldo_actual'] / $proveedor['credito_limite']) * 100 : 0;
                                    
                                    $total_deuda += $proveedor['saldo_actual'];
                                    $total_limite += $proveedor['credito_limite'];
                                    $total_disponible += $disponible;
                                    $total_proveedores++;
                                ?>
                                <tr class="<?php echo $porcentaje_uso > 100 ? 'table-danger' : ($porcentaje_uso > 80 ? 'table-warning' : ''); ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-primary text-white me-2">
                                                <?php echo strtoupper(substr($proveedor['nombre'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong class="d-block"><?php echo htmlspecialchars($proveedor['codigo']); ?></strong>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($proveedor['nombre']); ?></small>
                                                <?php if ($proveedor['telefono']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone fa-xs me-1"></i><?php echo htmlspecialchars($proveedor['telefono']); ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($proveedor['ciudad']); ?></td>
                                    <td class="text-end"><?php echo formatearMoneda($proveedor['credito_limite']); ?></td>
                                    <td class="text-end">
                                        <span class="fw-bold text-danger">
                                            <?php echo formatearMoneda($proveedor['saldo_actual']); ?>
                                        </span>
                                        <?php if ($proveedor['credito_limite'] > 0): ?>
                                        <div class="progress mt-1" style="height: 5px;">
                                            <div class="progress-bar bg-danger" 
                                                 style="width: <?php echo min($porcentaje_uso, 100); ?>%"
                                                 title="<?php echo number_format($porcentaje_uso, 1); ?>% de uso">
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo number_format($porcentaje_uso, 1); ?>%
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-<?php echo $disponible > 0 ? 'success' : 'danger'; ?> fw-bold">
                                            <?php echo formatearMoneda($disponible); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($proveedor['activo'] == 1): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactivo</span>
                                        <?php endif; ?>
                                        <?php if ($porcentaje_uso > 100): ?>
                                            <div class="mt-1">
                                                <span class="badge bg-danger">EXCEDIDO</span>
                                            </div>
                                        <?php elseif ($porcentaje_uso > 80): ?>
                                            <div class="mt-1">
                                                <span class="badge bg-warning">ALTO</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="cuentas_pagar.php?proveedor_id=<?php echo $proveedor['id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Ver historial">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="registrar_pago_proveedor.php?proveedor_id=<?php echo $proveedor['id']; ?>" 
                                               class="btn btn-outline-success"
                                               title="Registrar pago">
                                                <i class="fas fa-credit-card"></i>
                                            </a>
                                            <a href="proveedores.php?action=edit&id=<?php echo $proveedor['id']; ?>" 
                                               class="btn btn-outline-info"
                                               title="Editar proveedor">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted p-5">
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
                        <tfoot class="table-active">
                            <tr>
                                <td><strong>TOTALES:</strong></td>
                                <td></td>
                                <td class="text-end"><strong><?php echo formatearMoneda($total_limite); ?></strong></td>
                                <td class="text-end">
                                    <strong class="text-danger"><?php echo formatearMoneda($total_deuda); ?></strong>
                                </td>
                                <td class="text-end">
                                    <strong class="text-success"><?php echo formatearMoneda($total_disponible); ?></strong>
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Historial del proveedor seleccionado -->
    <?php if ($proveedor_id && $proveedor_info): ?>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">
                        <i class="fas fa-building me-2"></i>
                        <?php echo htmlspecialchars($proveedor_info['nombre']); ?>
                        <small class="fs-6 opacity-75">(<?php echo htmlspecialchars($proveedor_info['codigo']); ?>)</small>
                    </h5>
                    <small class="opacity-75">
                        <i class="fas fa-city me-1"></i><?php echo htmlspecialchars($proveedor_info['ciudad']); ?> | 
                        <i class="fas fa-phone fa-xs me-1"></i><?php echo htmlspecialchars($proveedor_info['telefono'] ?: 'Sin teléfono'); ?>
                    </small>
                </div>
                <div>
                    <a href="cuentas_pagar.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Resumen del proveedor -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="card bg-light">
                            <div class="card-body p-3">
                                <h6>Resumen de Cuenta</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Límite Crédito:</span>
                                    <strong><?php echo formatearMoneda($proveedor_info['credito_limite']); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Saldo Actual:</span>
                                    <strong class="text-danger"><?php echo formatearMoneda($proveedor_info['saldo_actual']); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Disponible:</span>
                                    <strong class="text-success">
                                        <?php echo formatearMoneda($proveedor_info['credito_limite'] - $proveedor_info['saldo_actual']); ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card bg-light">
                            <div class="card-body p-3">
                                <h6>Acciones</h6>
                                <div class="d-grid gap-2">
                                    <a href="registrar_pago_proveedor.php?proveedor_id=<?php echo $proveedor_id; ?>" 
                                       class="btn btn-success">
                                        <i class="fas fa-credit-card me-2"></i>Registrar Pago
                                    </a>
                                    <button class="btn btn-outline-primary" onclick="imprimirExtracto(<?php echo $proveedor_id; ?>)">
                                        <i class="fas fa-print me-2"></i>Imprimir Extracto
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de historial -->
                <div class="mb-4">
                    <h6 class="mb-3">
                        <i class="fas fa-history me-2"></i>
                        Historial de Movimientos
                        <?php if ($historial_proveedor && $historial_proveedor->num_rows > 0): ?>
                        <span class="badge bg-secondary ms-2"><?php echo $historial_proveedor->num_rows; ?></span>
                        <?php endif; ?>
                    </h6>
                    
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th class="text-end">Compra</th>
                                    <th class="text-end">A Cuenta</th>
                                    <th class="text-end">Adelanto</th>
                                    <th class="text-end">Saldo</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($historial_proveedor && $historial_proveedor->num_rows > 0): 
                                    $total_compras = 0;
                                    $total_a_cuenta = 0;
                                    $total_adelanto = 0;
                                    $saldo_actual = 0;
                                ?>
                                    <?php while ($movimiento = $historial_proveedor->fetch_assoc()): 
                                        $total_compras += $movimiento['compra'];
                                        $total_a_cuenta += $movimiento['a_cuenta'];
                                        $total_adelanto += $movimiento['adelanto'];
                                        $saldo_actual = $movimiento['saldo'];
                                    ?>
                                    <tr>
                                        <td><?php echo formatearFecha($movimiento['fecha']); ?></td>
                                        <td>
                                            <small><?php echo htmlspecialchars($movimiento['descripcion']); ?></small>
                                            <?php if ($movimiento['referencia']): ?>
                                                <br><small class="text-muted">Ref: <?php echo htmlspecialchars($movimiento['referencia']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($movimiento['compra'] > 0): ?>
                                                <span class="text-danger"><?php echo formatearMoneda($movimiento['compra']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted"><?php echo formatearMoneda($movimiento['compra']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($movimiento['a_cuenta'] > 0): ?>
                                                <span class="text-danger"><?php echo formatearMoneda($movimiento['a_cuenta']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted"><?php echo formatearMoneda($movimiento['a_cuenta']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($movimiento['adelanto'] > 0): ?>
                                                <span class="text-success"><?php echo formatearMoneda($movimiento['adelanto']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted"><?php echo formatearMoneda($movimiento['adelanto']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <span class="fw-bold <?php echo $movimiento['saldo'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo formatearMoneda($movimiento['saldo']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($movimiento['usuario_nombre'] ?: 'Usuario ' . $movimiento['usuario_id']); ?></small>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <!-- Totales del proveedor -->
                                    <tr class="table-active">
                                        <td colspan="2"><strong>TOTALES:</strong></td>
                                        <td class="text-end">
                                            <strong class="text-danger"><?php echo formatearMoneda($total_compras); ?></strong>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-danger"><?php echo formatearMoneda($total_a_cuenta); ?></strong>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-success"><?php echo formatearMoneda($total_adelanto); ?></strong>
                                        </td>
                                        <td class="text-end">
                                            <strong class="<?php echo $saldo_actual > 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo formatearMoneda($saldo_actual); ?>
                                            </strong>
                                        </td>
                                        <td></td>
                                    </tr>
                                <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted p-3">
                                        No hay movimientos para este proveedor
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Resumen de movimientos -->
                <?php
                $query_resumen = "SELECT 
                                 SUM(compra) as total_compras,
                                 SUM(a_cuenta) as total_a_cuenta,
                                 SUM(adelanto) as total_pagos,
                                 MAX(saldo) as saldo_actual
                                 FROM proveedores_estado_cuentas 
                                 WHERE proveedor_id = ?";
                $stmt_resumen = $conn->prepare($query_resumen);
                $stmt_resumen->bind_param("i", $proveedor_id);
                $stmt_resumen->execute();
                $result_resumen = $stmt_resumen->get_result();
                $resumen = $result_resumen->fetch_assoc();
                ?>
                <div class="row mt-4">
                    <div class="col-md-4 mb-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center p-3">
                                <h6>Total Compras</h6>
                                <h4><?php echo formatearMoneda($resumen['total_compras'] ?? 0); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center p-3">
                                <h6>Total Pagado</h6>
                                <h4><?php echo formatearMoneda($resumen['total_pagos'] ?? 0); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center p-3">
                                <h6>Saldo Actual</h6>
                                <h4><?php echo formatearMoneda($resumen['saldo_actual'] ?? 0); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Resumen general por ciudad -->
<?php if (!$proveedor_id): ?>
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-map-marker-alt me-2"></i>
                    Resumen por Ciudad
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Ciudad</th>
                                <th class="text-center">Proveedores</th>
                                <th class="text-end">Total Límite</th>
                                <th class="text-end">Total Deuda</th>
                                <th class="text-end">Disponible</th>
                                <th class="text-center">% Uso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query_ciudades = "SELECT ciudad,
                                             COUNT(*) as total_proveedores,
                                             SUM(credito_limite) as total_limite,
                                             SUM(saldo_actual) as total_deuda,
                                             SUM(credito_limite - saldo_actual) as disponible
                                             FROM proveedores 
                                             WHERE saldo_actual > 0 AND activo = 1
                                             GROUP BY ciudad
                                             ORDER BY total_deuda DESC";
                            $result_ciudades = $conn->query($query_ciudades);
                            
                            if ($result_ciudades && $result_ciudades->num_rows > 0):
                                $ciudad_total_proveedores = 0;
                                $ciudad_total_limite = 0;
                                $ciudad_total_deuda = 0;
                                $ciudad_total_disponible = 0;
                                
                                while ($ciudad = $result_ciudades->fetch_assoc()):
                                    $porcentaje = $ciudad['total_limite'] > 0 ? 
                                        ($ciudad['total_deuda'] / $ciudad['total_limite']) * 100 : 0;
                                    
                                    $ciudad_total_proveedores += $ciudad['total_proveedores'];
                                    $ciudad_total_limite += $ciudad['total_limite'];
                                    $ciudad_total_deuda += $ciudad['total_deuda'];
                                    $ciudad_total_disponible += $ciudad['disponible'];
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($ciudad['ciudad']); ?></strong></td>
                                <td class="text-center"><?php echo $ciudad['total_proveedores']; ?></td>
                                <td class="text-end"><?php echo formatearMoneda($ciudad['total_limite']); ?></td>
                                <td class="text-end">
                                    <span class="text-danger"><?php echo formatearMoneda($ciudad['total_deuda']); ?></span>
                                </td>
                                <td class="text-end">
                                    <span class="text-success"><?php echo formatearMoneda($ciudad['disponible']); ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo $porcentaje > 80 ? 'danger' : ($porcentaje > 50 ? 'warning' : 'success'); ?>" 
                                                 style="width: <?php echo min($porcentaje, 100); ?>%">
                                            </div>
                                        </div>
                                        <span class="text-muted"><?php echo number_format($porcentaje, 1); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <!-- Totales por ciudad -->
                            <tr class="table-active">
                                <td><strong>TOTAL GENERAL:</strong></td>
                                <td class="text-center"><strong><?php echo $ciudad_total_proveedores; ?></strong></td>
                                <td class="text-end"><strong><?php echo formatearMoneda($ciudad_total_limite); ?></strong></td>
                                <td class="text-end">
                                    <strong class="text-danger"><?php echo formatearMoneda($ciudad_total_deuda); ?></strong>
                                </td>
                                <td class="text-end">
                                    <strong class="text-success"><?php echo formatearMoneda($ciudad_total_disponible); ?></strong>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    $porcentaje_total = $ciudad_total_limite > 0 ? ($ciudad_total_deuda / $ciudad_total_limite) * 100 : 0;
                                    ?>
                                    <strong class="text-<?php echo $porcentaje_total > 80 ? 'danger' : ($porcentaje_total > 50 ? 'warning' : 'success'); ?>">
                                        <?php echo number_format($porcentaje_total, 1); ?>%
                                    </strong>
                                </td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted p-4">
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
// Buscar en la tabla
function buscarProveedoresPagar() {
    var input = document.getElementById('buscarProveedorPagar');
    var filter = input.value.toUpperCase();
    var table = document.getElementById('tablaProveedoresPagar');
    var tr = table.getElementsByTagName('tr');
    var visibleCount = 0;
    
    for (var i = 1; i < tr.length; i++) {
        var mostrar = false;
        var tds = tr[i].getElementsByTagName('td');
        
        for (var j = 0; j < tds.length; j++) {
            if (tds[j]) {
                var txtValue = tds[j].textContent || tds[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    mostrar = true;
                    break;
                }
            }
        }
        
        if (mostrar) {
            tr[i].style.display = '';
            visibleCount++;
        } else {
            tr[i].style.display = 'none';
        }
    }
    
    // Actualizar contador
    var counterBadge = document.querySelector('.card-header .badge.bg-danger');
    if (counterBadge) {
        counterBadge.textContent = visibleCount + ' proveedores';
    }
}

// Configurar buscador
document.getElementById('buscarProveedorPagar').addEventListener('keyup', buscarProveedoresPagar);

// Limpiar búsqueda
function limpiarBusqueda() {
    document.getElementById('buscarProveedorPagar').value = '';
    buscarProveedoresPagar();
}

// Imprimir extracto del proveedor
function imprimirExtracto(proveedorId) {
    window.open('generar_extracto_proveedor.php?id=' + proveedorId, '_blank');
}

// Exportar reporte a Excel
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
    html += '.text-success { color: #28a745; }';
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
        if (cells.length > 6) {
            cells[6].innerHTML = ''; // Limpiar columna de acciones
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
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<style>
/* Estilos personalizados */
.avatar-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.025);
}

.table-danger {
    background-color: rgba(220, 53, 69, 0.05) !important;
}

.table-warning {
    background-color: rgba(255, 193, 7, 0.05) !important;
}

.progress {
    background-color: #e9ecef;
    border-radius: 3px;
}

/* Responsive */
@media (max-width: 768px) {
    .btn-group-sm {
        display: flex;
        flex-direction: column;
    }
    
    .btn-group-sm .btn {
        margin-bottom: 2px;
        border-radius: 4px !important;
    }
    
    .table-responsive {
        font-size: 13px;
    }
    
    .avatar-circle {
        width: 28px;
        height: 28px;
        font-size: 12px;
    }
    
    .card-body.p-3 {
        padding: 1rem !important;
    }
}
</style>

<?php require_once 'footer.php'; ?>