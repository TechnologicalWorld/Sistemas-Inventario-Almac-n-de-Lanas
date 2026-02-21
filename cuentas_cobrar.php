<?php
session_start();

$titulo_pagina = "Cuentas por Cobrar";
$icono_titulo = "fas fa-hand-holding-usd";
$breadcrumb = [
    ['text' => 'Clientes', 'link' => 'clientes.php', 'active' => false],
    ['text' => 'Cuentas por Cobrar', 'link' => '#', 'active' => true]
];

require_once 'header.php';
require_once 'funciones.php';

// Verificar sesión (acceso para admin y vendedor)
verificarSesion();

// Verificar conexión a base de datos
if (!isset($conn)) {
    die("Error: No hay conexión a la base de datos");
}

$mensaje = '';
$tipo_mensaje = '';
$cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : null;
$mostrar_modal_abono = false;

// búsqueda y paginación en lista de clientes con deuda
$search = limpiar($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

$whereClientes = "c.saldo_actual > 0 AND c.activo = 1";
$paramsClientes = [];
$typesClientes = "";

if ($search !== '') {
    $whereClientes .= " AND (c.codigo LIKE ? OR c.nombre LIKE ? OR c.telefono LIKE ? )";
    $like = "%$search%";
    $paramsClientes = [$like, $like, $like];
    $typesClientes = "sss";
}

// contar totales filtrados
$count_query = "SELECT COUNT(*) as total FROM clientes c WHERE $whereClientes";
$stmt_count = $conn->prepare($count_query);
if (!empty($paramsClientes)) {
    $stmt_count->bind_param($typesClientes, ...$paramsClientes);
}
$stmt_count->execute();
$total_clientes_filtrados = $stmt_count->get_result()->fetch_assoc()['total'];

$offset = ($page - 1) * $perPage;

// Procesar abono
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'abonar') {
    try {
        $cliente_id = intval($_POST['cliente_id']);
        $venta_id = !empty($_POST['venta_id']) ? intval($_POST['venta_id']) : null;
        $monto = floatval($_POST['monto']);
        $metodo_pago = limpiar($_POST['metodo_pago']);
        $referencia = limpiar($_POST['referencia']);
        $observaciones = limpiar($_POST['observaciones']);
        
        // Validar datos
        if ($monto <= 0) {
            throw new Exception("El monto debe ser mayor a cero");
        }
        
        if (empty($metodo_pago)) {
            throw new Exception("Seleccione un método de pago");
        }
        
        // Obtener información del cliente
        $query_cliente = "SELECT saldo_actual, codigo, nombre FROM clientes WHERE id = ? AND activo = 1";
        $stmt_cliente = $conn->prepare($query_cliente);
        $stmt_cliente->bind_param("i", $cliente_id);
        $stmt_cliente->execute();
        $result_cliente = $stmt_cliente->get_result();
        
        if ($result_cliente->num_rows === 0) {
            throw new Exception("Cliente no encontrado");
        }
        
        $cliente = $result_cliente->fetch_assoc();
        $saldo_cliente = floatval($cliente['saldo_actual']);
        
        // Si es abono a venta específica, validar saldo de esa venta
        if ($venta_id) {
            $query_venta = "SELECT v.total, v.pago_inicial, v.codigo_venta,
                           COALESCE(SUM(pc.monto), 0) as abonos_previos
                           FROM ventas v
                           LEFT JOIN pagos_clientes pc ON v.id = pc.venta_id AND pc.tipo = 'abono'
                           WHERE v.id = ? AND v.cliente_id = ? AND v.estado != 'cancelada' AND v.anulado = 0
                           GROUP BY v.id";
            $stmt_venta = $conn->prepare($query_venta);
            $stmt_venta->bind_param("ii", $venta_id, $cliente_id);
            $stmt_venta->execute();
            $result_venta = $stmt_venta->get_result();
            
            if ($result_venta->num_rows === 0) {
                throw new Exception("Venta no encontrada para este cliente");
            }
            
            $venta = $result_venta->fetch_assoc();
            $saldo_venta = floatval($venta['total']) - floatval($venta['pago_inicial']) - floatval($venta['abonos_previos']);
            
            if ($monto > $saldo_venta) {
                throw new Exception("El monto excede el saldo de la venta (Bs. " . number_format($saldo_venta, 2) . ")");
            }
        } else {
            // Abono general - validar contra saldo total del cliente
            if ($monto > $saldo_cliente) {
                throw new Exception("El monto excede el saldo del cliente (Bs. " . number_format($saldo_cliente, 2) . ")");
            }
        }
        
        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // Registrar abono
            $query_abono = "INSERT INTO pagos_clientes 
                           (tipo, cliente_id, venta_id, monto, metodo_pago, referencia, 
                            fecha, hora, usuario_id, observaciones)
                           VALUES ('abono', ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?)";
            $stmt_abono = $conn->prepare($query_abono);
            
            if ($venta_id) {
                $stmt_abono->bind_param("iidssis", $cliente_id, $venta_id, $monto, $metodo_pago, 
                                       $referencia, $_SESSION['usuario_id'], $observaciones);
            } else {
                $stmt_abono->bind_param("iidssis", $cliente_id, null, $monto, $metodo_pago, 
                                       $referencia, $_SESSION['usuario_id'], $observaciones);
            }
            
            if (!$stmt_abono->execute()) {
                throw new Exception("Error al registrar abono: " . $stmt_abono->error);
            }
            
            // Actualizar saldo del cliente
            $query_actualizar_saldo = "UPDATE clientes SET saldo_actual = saldo_actual - ? WHERE id = ?";
            $stmt_actualizar = $conn->prepare($query_actualizar_saldo);
            $stmt_actualizar->bind_param("di", $monto, $cliente_id);
            
            if (!$stmt_actualizar->execute()) {
                throw new Exception("Error al actualizar saldo del cliente");
            }
            
            // Si es abono a venta específica, actualizar estado si queda pagada
            if ($venta_id) {
                // Calcular nuevo saldo de la venta
                $nuevo_saldo_venta = $saldo_venta - $monto;
                
                if ($nuevo_saldo_venta <= 0) {
                    // Marcar venta como pagada
                    $query_actualizar_venta = "UPDATE ventas SET estado = 'pagada' WHERE id = ?";
                    $stmt_actualizar_venta = $conn->prepare($query_actualizar_venta);
                    $stmt_actualizar_venta->bind_param("i", $venta_id);
                    
                    if (!$stmt_actualizar_venta->execute()) {
                        throw new Exception("Error al actualizar estado de la venta");
                    }
                }
            }
            
            // Registrar movimiento en caja
            $descripcion = "Abono cliente " . $cliente['codigo'] . " - " . $cliente['nombre'];
            if ($venta_id) {
                $descripcion .= " - Venta: " . $venta['codigo_venta'];
            }
            
            registrarMovimientoCaja('ingreso', 'abono_cliente', $monto, $descripcion, $_SESSION['usuario_id'], 
                                   $venta_id ? "Venta: " . $venta['codigo_venta'] : "Abono general");
            
            $conn->commit();
            
            $mensaje = "Abono registrado exitosamente";
            $tipo_mensaje = "success";
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = "danger";
        $mostrar_modal_abono = true; // Mantener modal abierto si hay error
    }
}

// Obtener clientes con deuda (aplicando filtros/paginación)
$query_clientes_deuda = "SELECT c.id, c.codigo, c.nombre, c.telefono, 
                        c.limite_credito, c.saldo_actual,
                        COUNT(DISTINCT v.id) as ventas_pendientes,
                        COALESCE(SUM(v.debe), 0) as total_debe
                        FROM clientes c
                        LEFT JOIN ventas v ON c.id = v.cliente_id AND v.estado = 'pendiente' AND v.anulado = 0
                        WHERE $whereClientes
                        GROUP BY c.id
                        ORDER BY c.saldo_actual DESC
                        LIMIT ?, ?";
$stmt_clientes = $conn->prepare($query_clientes_deuda);
if (!empty($paramsClientes)) {
    $bindTypes = $typesClientes . "ii";
    $allParams = array_merge($paramsClientes, [$offset, $perPage]);
    $stmt_clientes->bind_param($bindTypes, ...$allParams);
} else {
    $stmt_clientes->bind_param("ii", $offset, $perPage);
}
$stmt_clientes->execute();
$result_clientes_deuda = $stmt_clientes->get_result();

// Verificar error en consulta
if (!$result_clientes_deuda) {
    die("Error en la consulta: " . $conn->error);
}

// Obtener deudas detalladas de un cliente específico
$deudas_detalladas = [];
$cliente_info = null;

if ($cliente_id) {
    // Verificar que el cliente existe y está activo
    $query_cliente = "SELECT * FROM clientes WHERE id = ? AND activo = 1";
    $stmt_cliente = $conn->prepare($query_cliente);
    $stmt_cliente->bind_param("i", $cliente_id);
    $stmt_cliente->execute();
    $result_cliente = $stmt_cliente->get_result();
    
    if ($result_cliente->num_rows > 0) {
        $cliente_info = $result_cliente->fetch_assoc();
        
        // Obtener ventas pendientes del cliente
        $query_deudas = "SELECT v.id as venta_id, v.codigo_venta, v.fecha, v.total,
                        v.pago_inicial, v.debe, v.estado,
                        COALESCE(SUM(pc.monto), 0) as abonos_realizados
                        FROM ventas v
                        LEFT JOIN pagos_clientes pc ON v.id = pc.venta_id AND pc.tipo = 'abono'
                        WHERE v.cliente_id = ? AND v.estado = 'pendiente' AND v.anulado = 0
                        GROUP BY v.id
                        ORDER BY v.fecha DESC";
        $stmt_deudas = $conn->prepare($query_deudas);
        $stmt_deudas->bind_param("i", $cliente_id);
        $stmt_deudas->execute();
        $deudas_detalladas = $stmt_deudas->get_result();
    } else {
        $mensaje = "Cliente no encontrado o inactivo";
        $tipo_mensaje = "warning";
        $cliente_id = null;
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
    <!-- Lista de clientes con deuda -->
    <div class="col-md-<?php echo $cliente_id ? '6' : '12'; ?>">
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Clientes con Deuda Pendiente</h5>
                <div>
                    <span class="badge bg-danger me-2">
                        <?php echo $total_clientes_filtrados; ?> clientes
                    </span>
                    <button class="btn btn-sm btn-outline-primary" onclick="exportarReporteDeudas()">
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Buscador (envía mediante GET) -->
                <form method="GET" class="mb-3">
                    <?php if ($cliente_id): ?>
                        <input type="hidden" name="cliente_id" value="<?php echo $cliente_id; ?>">
                    <?php endif; ?>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" name="search" class="form-control" id="buscarClienteDeuda"
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Buscar por código, nombre o teléfono...">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <div class="table-responsive">
                    <table class="table table-hover table-sm" id="tablaClientesDeuda">
                        <thead class="table-light">
                            <tr>
                                <th width="25%">Cliente</th>
                                <th width="15%" class="text-end">Límite Crédito</th>
                                <th width="20%" class="text-end">Saldo Actual</th>
                                <th width="10%" class="text-center">Ventas Pend.</th>
                                <th width="15%" class="text-end">Total Pendiente</th>
                                <th width="10%">Estado</th>
                                <th width="15%">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_clientes_deuda->num_rows > 0): 
                                $total_deuda = 0;
                                $total_limite = 0;
                                $total_clientes = 0;
                            ?>
                                <?php while ($cliente = $result_clientes_deuda->fetch_assoc()): 
                                    $porcentaje_uso = $cliente['limite_credito'] > 0 ? 
                                        ($cliente['saldo_actual'] / $cliente['limite_credito']) * 100 : 0;
                                    $estado_credito = '';
                                    $color_estado = '';
                                    $clase_fila = '';
                                    
                                    if ($porcentaje_uso > 100) {
                                        $estado_credito = 'EXCEDIDO';
                                        $color_estado = 'danger';
                                        $clase_fila = 'table-danger';
                                    } elseif ($porcentaje_uso > 80) {
                                        $estado_credito = 'ALTO';
                                        $color_estado = 'warning';
                                        $clase_fila = 'table-warning';
                                    } elseif ($porcentaje_uso > 0) {
                                        $estado_credito = 'DEUDA';
                                        $color_estado = 'info';
                                    } else {
                                        $estado_credito = 'AL DÍA';
                                        $color_estado = 'success';
                                    }
                                    
                                    $total_deuda += $cliente['saldo_actual'];
                                    $total_limite += $cliente['limite_credito'];
                                    $total_clientes++;
                                ?>
                                <tr class="<?php echo $clase_fila; ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-primary text-white me-2">
                                                <?php echo strtoupper(substr($cliente['nombre'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong class="d-block"><?php echo htmlspecialchars($cliente['codigo']); ?></strong>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($cliente['nombre']); ?></small>
                                                <?php if ($cliente['telefono']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone fa-xs me-1"></i><?php echo htmlspecialchars($cliente['telefono']); ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end"><?php echo formatearMoneda($cliente['limite_credito']); ?></td>
                                    <td class="text-end">
                                        <span class="fw-bold text-danger">
                                            <?php echo formatearMoneda($cliente['saldo_actual']); ?>
                                        </span>
                                        <?php if ($cliente['limite_credito'] > 0): ?>
                                        <div class="progress mt-1" style="height: 5px;">
                                            <div class="progress-bar bg-<?php echo $color_estado; ?>" 
                                                 style="width: <?php echo min($porcentaje_uso, 100); ?>%"
                                                 title="<?php echo number_format($porcentaje_uso, 1); ?>%">
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo number_format($porcentaje_uso, 1); ?>%
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?php echo $cliente['ventas_pendientes']; ?></span>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold">
                                            <?php echo formatearMoneda($cliente['total_debe']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $color_estado; ?>">
                                            <?php echo $estado_credito; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="cuentas_cobrar.php?cliente_id=<?php echo $cliente['id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Ver detalles">
                                                <i class="fas fa-eye"></i>
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
                                        <p class="mb-0">No hay clientes con deuda pendiente</p>
                                        <small>Todos los clientes están al día con sus pagos</small>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php if ($result_clientes_deuda->num_rows > 0): ?>
                        <tfoot class="table-active">
                            <tr>
                                <td><strong>TOTALES:</strong></td>
                                <td class="text-end"><strong><?php echo formatearMoneda($total_limite); ?></strong></td>
                                <td class="text-end">
                                    <strong class="text-danger"><?php echo formatearMoneda($total_deuda); ?></strong>
                                </td>
                                <td class="text-center"><strong><?php echo $total_clientes; ?></strong></td>
                                <td class="text-end"><strong><?php echo formatearMoneda($total_deuda); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                    <?php if (isset($total_clientes_filtrados) && $total_clientes_filtrados > $perPage): ?>
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php
                            $pages = ceil($total_clientes_filtrados / $perPage);
                            for ($p = 1; $p <= $pages; $p++):
                                $active = $p == $page ? ' active' : '';
                                $params = http_build_query([
                                    'search' => $search,
                                    'page' => $p
                                ] + ($cliente_id ? ['cliente_id'=>$cliente_id] : []));
                            ?>
                            <li class="page-item<?php echo $active; ?>">
                                <a class="page-link" href="?<?php echo $params; ?>"><?php echo $p; ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detalles de deudas de cliente específico -->
    <?php if ($cliente_id && $cliente_info): ?>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">
                        <i class="fas fa-user me-2"></i>
                        <?php echo htmlspecialchars($cliente_info['nombre']); ?>
                        <small class="fs-6 opacity-75">(<?php echo htmlspecialchars($cliente_info['codigo']); ?>)</small>
                    </h5>
                    <small class="opacity-75">
                        <i class="fas fa-phone fa-xs me-1"></i><?php echo htmlspecialchars($cliente_info['telefono'] ?: 'Sin teléfono'); ?>
                    </small>
                </div>
                <div>
                    <a href="cuentas_cobrar.php" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Resumen del cliente -->
                <div class="row mb-4">
                    <div class="col-md-12 mb-3">
                        <div class="card bg-light">
                            <div class="card-body p-3">
                                <h6>Resumen de Deuda</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Límite Crédito:</span>
                                    <strong><?php echo formatearMoneda($cliente_info['limite_credito']); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Saldo Actual:</span>
                                    <strong class="text-danger"><?php echo formatearMoneda($cliente_info['saldo_actual']); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Disponible:</span>
                                    <strong class="text-success">
                                        <?php echo formatearMoneda(max(0, $cliente_info['limite_credito'] - $cliente_info['saldo_actual'])); ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                
                <!-- Tabla de deudas detalladas -->
                <div class="mb-4">
                    <h6 class="mb-3">
                        <i class="fas fa-file-invoice-dollar me-2"></i>
                        Deudas Pendientes
                        <?php if ($deudas_detalladas && $deudas_detalladas->num_rows > 0): ?>
                        <span class="badge bg-warning ms-2"><?php echo $deudas_detalladas->num_rows; ?></span>
                        <?php endif; ?>
                    </h6>
                    
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Venta (Código)</th>
                                    <th>Fecha</th>
                                    <th class="text-end">Total (Venta)</th>
                                    <th class="text-end">Pagado (A Cuenta)</th>
                                    <th class="text-end">Debe (Saldo)</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($deudas_detalladas && $deudas_detalladas->num_rows > 0): 
                                    $total_general = 0;
                                    $total_abonos = 0;
                                    $total_pendiente = 0;
                                ?>
                                    <?php while ($deuda = $deudas_detalladas->fetch_assoc()): 
                                        $abonos = floatval($deuda['abonos_realizados']);
                                        $saldo_pendiente = floatval($deuda['debe']);
                                        
                                        $total_general += floatval($deuda['total']);
                                        $total_abonos += $abonos;
                                        $total_pendiente += $saldo_pendiente;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($deuda['codigo_venta']); ?></td>
                                        <td><?php echo formatearFecha($deuda['fecha']); ?></td>
                                        <td class="text-end"><?php echo formatearMoneda($deuda['total']); ?></td>
                                        <td class="text-end"><?php echo formatearMoneda($abonos); ?></td>
                                        <td class="text-end">
                                            <span class="fw-bold text-danger">
                                                <?php echo formatearMoneda($saldo_pendiente); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-success"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalRegistrarAbono"
                                                    onclick="cargarDeudaAbono(<?php echo $cliente_id; ?>, <?php echo $deuda['venta_id']; ?>, '<?php echo addslashes($deuda['codigo_venta']); ?>', <?php echo $saldo_pendiente; ?>)"
                                                    title="Abonar a esta venta">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <!-- Totales por cliente -->
                                    <tr class="table-active">
                                        <td colspan="2"><strong>TOTALES:</strong></td>
                                        <td class="text-end">
                                            <strong><?php echo formatearMoneda($total_general); ?></strong>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-success"><?php echo formatearMoneda($total_abonos); ?></strong>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-danger"><?php echo formatearMoneda($total_pendiente); ?></strong>
                                        </td>
                                        <td></td>
                                    </tr>
                                <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted p-3">
                                        No hay deudas pendientes para este cliente
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Historial de abonos recientes -->
                <div class="mt-4">
                    <h6 class="mb-3">
                        <i class="fas fa-history me-2"></i>
                        Últimos Abonos
                    </h6>
                    <?php
                    $query_abonos = "SELECT pc.*, v.codigo_venta
                                    FROM pagos_clientes pc
                                    JOIN ventas v ON pc.venta_id = v.id
                                    WHERE pc.cliente_id = ? AND pc.tipo = 'abono'
                                    ORDER BY pc.fecha DESC, pc.hora DESC
                                    LIMIT 5";
                    $stmt_abonos = $conn->prepare($query_abonos);
                    $stmt_abonos->bind_param("i", $cliente_id);
                    $stmt_abonos->execute();
                    $result_abonos = $stmt_abonos->get_result();
                    
                    if ($result_abonos->num_rows > 0):
                    ?>
                    <div class="list-group">
                        <?php while ($abono = $result_abonos->fetch_assoc()): ?>
                        <div class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($abono['codigo_venta']); ?></h6>
                                <small><?php echo formatearFecha($abono['fecha']); ?> <?php echo formatearHora($abono['hora']); ?></small>
                            </div>
                            <p class="mb-1">
                                <span class="badge bg-success"><?php echo formatearMoneda($abono['monto']); ?></span>
                                - <?php echo ucfirst($abono['metodo_pago']); ?>
                                <?php if ($abono['referencia']): ?>
                                    <br><small class="text-muted">Ref: <?php echo htmlspecialchars($abono['referencia']); ?></small>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        No hay abonos registrados para este cliente
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal para registrar abono -->
<div class="modal fade" id="modalRegistrarAbono" tabindex="-1" <?php echo $mostrar_modal_abono ? 'data-bs-backdrop="static" data-bs-keyboard="false"' : ''; ?>>
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="formRegistrarAbono">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-money-bill-wave me-2"></i>
                        Registrar Abono
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="abonar">
                    <input type="hidden" name="cliente_id" id="abonoClienteId">
                    <input type="hidden" name="venta_id" id="abonoVentaId">
                    
                    <div class="mb-3">
                        <label class="form-label">Cliente</label>
                        <input type="text" class="form-control" id="abonoClienteNombre" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Venta</label>
                        <input type="text" class="form-control" id="abonoVentaCodigo" readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Debe </label>
                            <div class="input-group">
                                <span class="input-group-text">Bs.</span>
                                <input type="text" class="form-control" id="abonoSaldoPendiente" readonly>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">A cuenta (Bs) *</label>
                            <div class="input-group">
                                <span class="input-group-text">Bs.</span>
                                <input type="number" class="form-control" name="monto" 
                                       id="abonoMonto" step="0.01" min="0.01" required
                                       oninput="validarMontoAbono()">
                            </div>
                            <div class="invalid-feedback" id="errorMontoAbono">
                                Ingrese un monto válido
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Método de Pago *</label>
                            <select class="form-select" name="metodo_pago" required>
                                <option value="">Seleccionar...</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="QR">QR</option>
                            </select>
                            <div class="invalid-feedback" id="errorMetodoAbono">
                                Seleccione un método de pago
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Referencia</label>
                            <input type="text" class="form-control" name="referencia" 
                                   placeholder="N° transferencia, voucher, etc.">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" rows="2" 
                                  placeholder="Detalles adicionales del abono..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning mt-3" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <small>El monto no puede exceder el saldo pendiente.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Registrar Abono</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Variables globales
var abonoData = {
    clienteId: null,
    ventaId: null,
    saldoPendiente: 0
};

// Cargar datos para abono general del cliente
function cargarClienteAbono(cliente) {
    abonoData.clienteId = cliente.id;
    abonoData.ventaId = null;
    abonoData.saldoPendiente = parseFloat(cliente.saldo_actual) || 0;
    
    document.getElementById('abonoClienteId').value = cliente.id;
    document.getElementById('abonoClienteNombre').value = cliente.codigo + ' - ' + cliente.nombre;
    document.getElementById('abonoVentaId').value = '';
    document.getElementById('abonoVentaCodigo').value = 'Todas las ventas pendientes';
    document.getElementById('abonoSaldoPendiente').value = formatearMonedaLocal(cliente.saldo_actual);
    document.getElementById('abonoMonto').value = '';
    document.getElementById('abonoMonto').max = cliente.saldo_actual;
    document.getElementById('abonoMonto').min = '0.01';
    document.getElementById('abonoMonto').step = '0.01';
    
    // Limpiar validaciones
    limpiarValidacionesAbono();
    
    // Enfocar en el monto
    setTimeout(function() {
        document.getElementById('abonoMonto').focus();
    }, 300);
}

// Cargar datos para abono específico de venta
function cargarDeudaAbono(clienteId, ventaId, codigoVenta, saldoPendiente) {
    abonoData.clienteId = clienteId;
    abonoData.ventaId = ventaId;
    abonoData.saldoPendiente = parseFloat(saldoPendiente) || 0;
    
    // Buscar nombre del cliente en la tabla
    var nombreCliente = '';
    var table = document.getElementById('tablaClientesDeuda');
    var rows = table.getElementsByTagName('tr');
    
    for (var i = 1; i < rows.length; i++) {
        var cells = rows[i].getElementsByTagName('td');
        if (cells.length > 0) {
            var codigoElement = cells[0].querySelector('strong');
            var nombreElement = cells[0].querySelector('.text-muted.d-block');
            if (codigoElement && nombreElement) {
                nombreCliente = codigoElement.textContent.trim() + ' - ' + nombreElement.textContent.trim();
                break;
            }
        }
    }
    
    document.getElementById('abonoClienteId').value = clienteId;
    document.getElementById('abonoClienteNombre').value = nombreCliente || 'Cliente ID: ' + clienteId;
    document.getElementById('abonoVentaId').value = ventaId;
    document.getElementById('abonoVentaCodigo').value = codigoVenta;
    document.getElementById('abonoSaldoPendiente').value = formatearMonedaLocal(saldoPendiente);
    document.getElementById('abonoMonto').value = '';
    document.getElementById('abonoMonto').max = saldoPendiente;
    document.getElementById('abonoMonto').min = '0.01';
    document.getElementById('abonoMonto').step = '0.01';
    
    // Limpiar validaciones
    limpiarValidacionesAbono();
    
    // Enfocar en el monto
    setTimeout(function() {
        document.getElementById('abonoMonto').focus();
    }, 300);
}

// Validar monto en tiempo real
function validarMontoAbono() {
    var montoInput = document.getElementById('abonoMonto');
    var monto = parseFloat(montoInput.value) || 0;
    var saldoPendiente = abonoData.saldoPendiente;
    var errorDiv = document.getElementById('errorMontoAbono');
    
    // Limpiar error
    montoInput.classList.remove('is-invalid');
    errorDiv.textContent = '';
    
    if (monto <= 0) {
        montoInput.classList.add('is-invalid');
        errorDiv.textContent = 'El monto debe ser mayor a cero';
        return false;
    }
    
    if (monto > saldoPendiente) {
        montoInput.classList.add('is-invalid');
        errorDiv.textContent = 'El monto no puede ser mayor al saldo pendiente (Bs. ' + saldoPendiente.toFixed(2) + ')';
        return false;
    }
    
    return true;
}

// Limpiar validaciones
function limpiarValidacionesAbono() {
    var form = document.getElementById('formRegistrarAbono');
    form.classList.remove('was-validated');
    
    var inputs = form.querySelectorAll('.is-invalid');
    inputs.forEach(function(input) {
        input.classList.remove('is-invalid');
    });
    
    var feedbacks = form.querySelectorAll('.invalid-feedback');
    feedbacks.forEach(function(feedback) {
        feedback.textContent = '';
    });
}

// Validar formulario completo
document.getElementById('formRegistrarAbono').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validar monto
    if (!validarMontoAbono()) {
        return false;
    }
    
    // Validar método de pago
    var metodoPago = this.querySelector('select[name="metodo_pago"]');
    var errorMetodo = document.getElementById('errorMetodoAbono');
    
    if (!metodoPago.value) {
        metodoPago.classList.add('is-invalid');
        errorMetodo.textContent = 'Seleccione un método de pago';
        return false;
    } else {
        metodoPago.classList.remove('is-invalid');
    }
    
    // Validar formulario completo
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return false;
    }
    
    // Confirmar
    if (!confirm('¿Está seguro de registrar este abono?')) {
        return false;
    }
    
    // Enviar formulario
    this.submit();
});

// Buscar en la tabla
// El filtrado de clientes con deuda ahora se realiza en el servidor mediante
// parámetros GET. Ya no se necesita código JavaScript para recorrer la tabla.
// Exportar reporte a Excel
function exportarReporteDeudas() {
    var html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    html += '<title>Reporte de Cuentas por Cobrar</title>';
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
    
    html += '<h1>Reporte de Cuentas por Cobrar</h1>';
    html += '<p><strong>Generado:</strong> ' + new Date().toLocaleDateString('es-BO') + ' ' + new Date().toLocaleTimeString('es-BO') + '</p>';
    
    // Copiar tabla sin botones
    var table = document.getElementById('tablaClientesDeuda').cloneNode(true);
    
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
    a.download = 'cuentas_por_cobrar_' + fecha + '.xls';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Función para formatear moneda local
function formatearMonedaLocal(numero) {
    if (isNaN(numero)) numero = 0;
    return 'Bs. ' + numero.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Inicializar tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Mostrar modal automáticamente si hay error en abono
    <?php if ($mostrar_modal_abono): ?>
    var modalElement = document.getElementById('modalRegistrarAbono');
    if (modalElement) {
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
    }
    <?php endif; ?>
});

// Función para recargar la página después de abono exitoso
<?php if ($mensaje && $tipo_mensaje == 'success'): ?>
// Recargar la página después de 1.5 segundos para mostrar datos actualizados
setTimeout(function() {
    <?php if ($cliente_id): ?>
    window.location.href = 'cuentas_cobrar.php?cliente_id=<?php echo $cliente_id; ?>';
    <?php else: ?>
    window.location.href = 'cuentas_cobrar.php';
    <?php endif; ?>
}, 1500);
<?php endif; ?>
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
}
</style>

<?php require_once 'footer.php'; ?>