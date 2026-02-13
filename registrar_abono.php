<?php
session_start();

$titulo_pagina = "Registrar Abono";
$icono_titulo = "fas fa-money-bill-wave";
$breadcrumb = [
    ['text' => 'Cuentas por Cobrar', 'link' => 'cuentas_cobrar.php', 'active' => false],
    ['text' => 'Registrar Abono', 'link' => '#', 'active' => true]
];

require_once 'header.php';
require_once 'funciones.php';


verificarSesion();

// Verificar conexión a base de datos
if (!isset($conn)) {
    die("Error: No hay conexión a la base de datos");
}

$mensaje = '';
$tipo_mensaje = '';
$cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : null;
$venta_id = isset($_GET['venta_id']) ? intval($_GET['venta_id']) : null;

// Procesar abono
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
        $query_cliente = "SELECT codigo, nombre, saldo_actual FROM clientes WHERE id = ? AND activo = 1";
        $stmt_cliente = $conn->prepare($query_cliente);
        $stmt_cliente->bind_param("i", $cliente_id);
        $stmt_cliente->execute();
        $result_cliente = $stmt_cliente->get_result();
        
        if ($result_cliente->num_rows === 0) {
            throw new Exception("Cliente no encontrado o inactivo");
        }
        
        $cliente = $result_cliente->fetch_assoc();
        $saldo_cliente = floatval($cliente['saldo_actual']);
        
        // Si es abono a venta específica, validar
        if ($venta_id) {
            $query_venta = "SELECT v.total, v.pago_inicial, v.codigo_venta, v.estado,
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
    }
}

// Obtener información del cliente si se especificó
$cliente_info = null;
$venta_info = null;
$deudas = [];

if ($cliente_id) {
    // Información del cliente
    $query_cliente = "SELECT id, codigo, nombre, saldo_actual, telefono FROM clientes WHERE id = ? AND activo = 1";
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
        $deudas = $stmt_deudas->get_result();
    } else {
        $mensaje = "Cliente no encontrado o inactivo";
        $tipo_mensaje = "warning";
        $cliente_id = null;
    }
}

// Obtener información de venta específica si se especificó
if ($venta_id && !$cliente_id) {
    $query_venta = "SELECT v.*, c.id as cliente_id, c.codigo as cliente_codigo, c.nombre as cliente_nombre,
                    c.saldo_actual as cliente_saldo
                    FROM ventas v
                    LEFT JOIN clientes c ON v.cliente_id = c.id
                    WHERE v.id = ? AND v.anulado = 0";
    $stmt_venta = $conn->prepare($query_venta);
    $stmt_venta->bind_param("i", $venta_id);
    $stmt_venta->execute();
    $result_venta = $stmt_venta->get_result();
    
    if ($result_venta->num_rows > 0) {
        $venta_info = $result_venta->fetch_assoc();
        $cliente_id = $venta_info['cliente_id'];
        
        // Obtener información del cliente
        if ($cliente_id) {
            $query_cliente = "SELECT id, codigo, nombre, saldo_actual, telefono FROM clientes WHERE id = ? AND activo = 1";
            $stmt_cliente = $conn->prepare($query_cliente);
            $stmt_cliente->bind_param("i", $cliente_id);
            $stmt_cliente->execute();
            $result_cliente = $stmt_cliente->get_result();
            
            if ($result_cliente->num_rows > 0) {
                $cliente_info = $result_cliente->fetch_assoc();
                
                // Obtener información específica de la venta con abonos
                $query_venta_detalle = "SELECT v.*, 
                                       COALESCE(SUM(pc.monto), 0) as abonos_realizados
                                       FROM ventas v
                                       LEFT JOIN pagos_clientes pc ON v.id = pc.venta_id AND pc.tipo = 'abono'
                                       WHERE v.id = ?
                                       GROUP BY v.id";
                $stmt_venta_detalle = $conn->prepare($query_venta_detalle);
                $stmt_venta_detalle->bind_param("i", $venta_id);
                $stmt_venta_detalle->execute();
                $venta_info = $stmt_venta_detalle->get_result()->fetch_assoc();
            }
        }
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
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-money-bill-wave me-2"></i>
                    Registrar Abono
                </h5>
            </div>
            <div class="card-body">
                <!-- Formulario de abono -->
                <form method="POST" action="" id="formRegistrarAbono">
                    <div class="mb-3">
                        <label class="form-label">Cliente *</label>
                        <?php if ($cliente_info): ?>
                        <input type="hidden" name="cliente_id" value="<?php echo $cliente_info['id']; ?>">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($cliente_info['codigo'] . ' - ' . $cliente_info['nombre']); ?>" 
                                   readonly>
                        </div>
                        <small class="text-muted">
                            Saldo actual: <?php echo formatearMoneda($cliente_info['saldo_actual']); ?>
                        </small>
                        <?php else: ?>
                        <select class="form-select" name="cliente_id" id="selectCliente" required 
                                onchange="cargarDeudasCliente(this.value)">
                            <option value="">Seleccionar cliente...</option>
                            <?php
                            $query_clientes = "SELECT id, codigo, nombre, saldo_actual 
                                              FROM clientes 
                                              WHERE saldo_actual > 0 AND activo = 1
                                              ORDER BY nombre";
                            $result_clientes = $conn->query($query_clientes);
                            if ($result_clientes && $result_clientes->num_rows > 0):
                                while ($cliente = $result_clientes->fetch_assoc()):
                            ?>
                            <option value="<?php echo $cliente['id']; ?>">
                                <?php echo htmlspecialchars($cliente['codigo']); ?> - <?php echo htmlspecialchars($cliente['nombre']); ?> 
                                (Deuda: <?php echo formatearMoneda($cliente['saldo_actual']); ?>)
                            </option>
                            <?php 
                                endwhile;
                            endif; 
                            ?>
                        </select>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Venta</label>
                        <?php if ($venta_id && $venta_info): ?>
                        <input type="hidden" name="venta_id" value="<?php echo $venta_id; ?>">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-receipt"></i>
                            </span>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($venta_info['codigo_venta']); ?>" 
                                   readonly>
                        </div>
                        <small class="text-muted">
                            Total: <?php echo formatearMoneda($venta_info['total']); ?> | 
                            Pagado: <?php echo formatearMoneda($venta_info['pago_inicial'] + ($venta_info['abonos_realizados'] ?? 0)); ?> | 
                            Saldo: <?php echo formatearMoneda($venta_info['debe']); ?>
                        </small>
                        <?php else: ?>
                        <select class="form-select" name="venta_id" id="selectVenta">
                            <option value="">Todas las ventas pendientes (Abono general)</option>
                            <?php if ($cliente_info && $deudas && $deudas->num_rows > 0): 
                                $deudas->data_seek(0); // Reset pointer
                                while ($deuda = $deudas->fetch_assoc()): 
                                    $abonos = floatval($deuda['abonos_realizados']);
                                    $saldo_pendiente = floatval($deuda['debe']);
                            ?>
                            <option value="<?php echo $deuda['venta_id']; ?>" 
                                    data-saldo="<?php echo $saldo_pendiente; ?>">
                                <?php echo htmlspecialchars($deuda['codigo_venta']); ?> - 
                                <?php echo formatearFecha($deuda['fecha']); ?> - 
                                Saldo: <?php echo formatearMoneda($saldo_pendiente); ?>
                            </option>
                            <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Saldo Pendiente</label>
                            <div class="input-group">
                                <span class="input-group-text">Bs.</span>
                                <input type="text" class="form-control" id="saldoPendiente" readonly
                                       value="<?php 
                                       if ($venta_id && $venta_info) {
                                           echo formatearMoneda($venta_info['debe']);
                                       } elseif ($cliente_info) {
                                           echo formatearMoneda($cliente_info['saldo_actual']);
                                       }
                                       ?>">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Monto a Abonar (Bs) *</label>
                            <div class="input-group">
                                <span class="input-group-text">Bs.</span>
                                <input type="number" class="form-control" name="monto" 
                                       id="montoAbono" step="0.01" min="0.01" required
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
                                <option value="deposito">Depósito</option>
                            </select>
                            <div class="invalid-feedback" id="errorMetodoAbono">
                                Seleccione un método de pago
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Referencia</label>
                            <input type="text" class="form-control" name="referencia" 
                                   id="referenciaAbono"
                                   placeholder="N° transferencia, voucher, etc.">
                            <small class="text-muted">Opcional para seguimiento</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" rows="3" 
                                  id="observacionesAbono"
                                  placeholder="Detalles adicionales del abono..."></textarea>
                        <small class="text-muted">Opcional</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Si no selecciona una venta específica, el abono se aplicará a todas las ventas pendientes del cliente.
                        </small>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save me-2"></i>Registrar Abono
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <!-- Información del cliente -->
        <?php if ($cliente_info): ?>
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>
                    Información del Cliente
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Código:</strong><br>
                            <span class="text-primary"><?php echo htmlspecialchars($cliente_info['codigo']); ?></span>
                        </p>
                        <p><strong>Nombre:</strong><br>
                            <?php echo htmlspecialchars($cliente_info['nombre']); ?>
                        </p>
                        <?php if ($cliente_info['telefono']): ?>
                        <p><strong>Teléfono:</strong><br>
                            <?php echo htmlspecialchars($cliente_info['telefono']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Saldo Actual</h6>
                                <h3 class="text-danger fw-bold">
                                    <?php echo formatearMoneda($cliente_info['saldo_actual']); ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($deudas && $deudas->num_rows > 0): ?>
                <hr>
                <h6>
                    <i class="fas fa-file-invoice-dollar me-2"></i>
                    Ventas Pendientes
                    <span class="badge bg-warning ms-2"><?php echo $deudas->num_rows; ?></span>
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Venta</th>
                                <th>Fecha</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $deudas->data_seek(0); // Reset pointer
                            $total_saldo = 0;
                            while ($deuda = $deudas->fetch_assoc()): 
                                $saldo_pendiente = floatval($deuda['debe']);
                                $total_saldo += $saldo_pendiente;
                            ?>
                            <tr>
                                <td>
                                    <a href="javascript:void(0)" 
                                       onclick="seleccionarVenta(<?php echo $deuda['venta_id']; ?>, '<?php echo addslashes($deuda['codigo_venta']); ?>', <?php echo $saldo_pendiente; ?>)"
                                       class="text-decoration-none">
                                        <?php echo htmlspecialchars($deuda['codigo_venta']); ?>
                                    </a>
                                </td>
                                <td><?php echo formatearFecha($deuda['fecha']); ?></td>
                                <td class="text-end"><?php echo formatearMoneda($deuda['total']); ?></td>
                                <td class="text-end">
                                    <span class="text-danger fw-bold">
                                        <?php echo formatearMoneda($saldo_pendiente); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <tr class="table-active">
                                <td colspan="3" class="text-end"><strong>TOTAL SALDO:</strong></td>
                                <td class="text-end">
                                    <strong class="text-danger"><?php echo formatearMoneda($total_saldo); ?></strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Historial de abonos recientes -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Últimos Abonos Registrados
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group" style="max-height: 300px; overflow-y: auto;">
                    <?php
                    $query_ultimos_abonos = "SELECT pc.*, c.codigo as cliente_codigo, c.nombre as cliente_nombre,
                                            v.codigo_venta, u.nombre as usuario_registro
                                            FROM pagos_clientes pc
                                            JOIN clientes c ON pc.cliente_id = c.id
                                            JOIN ventas v ON pc.venta_id = v.id
                                            JOIN usuarios u ON pc.usuario_id = u.id
                                            WHERE pc.tipo = 'abono'
                                            ORDER BY pc.fecha DESC, pc.hora DESC
                                            LIMIT 10";
                    $result_abonos = $conn->query($query_ultimos_abonos);
                    
                    if ($result_abonos && $result_abonos->num_rows > 0):
                        while ($abono = $result_abonos->fetch_assoc()):
                    ?>
                    <div class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <div>
                                <h6 class="mb-1">
                                    <span class="badge bg-success"><?php echo formatearMoneda($abono['monto']); ?></span>
                                    <?php echo htmlspecialchars($abono['cliente_codigo']); ?>
                                </h6>
                                <small class="text-muted">
                                    <i class="fas fa-receipt me-1"></i><?php echo htmlspecialchars($abono['codigo_venta']); ?>
                                </small>
                            </div>
                            <small class="text-muted text-end">
                                <?php echo formatearFecha($abono['fecha']); ?><br>
                                <?php echo formatearHora($abono['hora']); ?>
                            </small>
                        </div>
                        <p class="mb-1 mt-2">
                            <small class="text-muted">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($abono['usuario_registro']); ?> | 
                                <i class="fas fa-credit-card me-1"></i><?php echo ucfirst($abono['metodo_pago']); ?>
                                <?php if ($abono['referencia']): ?>
                                    | <i class="fas fa-hashtag me-1"></i>Ref: <?php echo htmlspecialchars($abono['referencia']); ?>
                                <?php endif; ?>
                            </small>
                        </p>
                    </div>
                    <?php
                        endwhile;
                    else:
                    ?>
                    <div class="text-center text-muted p-4">
                        <i class="fas fa-info-circle fa-2x mb-3 opacity-50"></i>
                        <p class="mb-0">No hay abonos registrados</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="cuentas_cobrar.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-arrow-left me-1"></i> Volver a Cuentas por Cobrar
                    </a>
                </div>
            </div>
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

// Cargar deudas del cliente (para select dinámico)
function cargarDeudasCliente(clienteId) {
    if (!clienteId) return;
    
    // Limpiar select de ventas
    var selectVenta = document.getElementById('selectVenta');
    selectVenta.innerHTML = '<option value="">Todas las ventas pendientes (Abono general)</option>';
    document.getElementById('saldoPendiente').value = '';
    
    // Actualizar saldo pendiente con saldo del cliente
    var saldoCliente = parseFloat(document.querySelector('option[value="' + clienteId + '"]')?.textContent.match(/Deuda:\s*Bs\.\s*([\d,]+\.\d{2})/)?.[1]?.replace(/,/g, '') || 0);
    if (saldoCliente > 0) {
        document.getElementById('saldoPendiente').value = formatearMonedaLocal(saldoCliente);
        abonoData.saldoPendiente = saldoCliente;
    }
    
    // Cargar ventas pendientes del cliente via AJAX
    fetch('ajax_cargar_deudas_cliente.php?cliente_id=' + clienteId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.deudas && data.deudas.length > 0) {
                data.deudas.forEach(function(deuda) {
                    var option = document.createElement('option');
                    option.value = deuda.venta_id;
                    option.textContent = deuda.codigo_venta + ' - ' + deuda.fecha + ' - Saldo: ' + formatearMonedaLocal(deuda.saldo_pendiente);
                    option.setAttribute('data-saldo', deuda.saldo_pendiente);
                    selectVenta.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Seleccionar venta desde la tabla
function seleccionarVenta(ventaId, codigoVenta, saldoPendiente) {
    var selectVenta = document.getElementById('selectVenta');
    if (selectVenta) {
        selectVenta.value = ventaId;
        actualizarSaldoPendiente(saldoPendiente);
    }
}

// Actualizar saldo pendiente cuando se selecciona una venta
function actualizarSaldoPendiente(saldo) {
    abonoData.saldoPendiente = parseFloat(saldo) || 0;
    document.getElementById('saldoPendiente').value = formatearMonedaLocal(abonoData.saldoPendiente);
    document.getElementById('montoAbono').max = abonoData.saldoPendiente;
}

// Event listener para el select de ventas
document.getElementById('selectVenta')?.addEventListener('change', function() {
    var selectedOption = this.options[this.selectedIndex];
    var saldoPendiente = selectedOption.getAttribute('data-saldo');
    
    if (saldoPendiente) {
        actualizarSaldoPendiente(saldoPendiente);
    } else {
        // Si es abono general, usar saldo del cliente
        var clienteId = document.querySelector('select[name="cliente_id"]')?.value || 
                       document.querySelector('input[name="cliente_id"]')?.value;
        if (clienteId) {
            // Obtener saldo del cliente (esto sería mejor con AJAX en producción)
            var saldoCliente = <?php echo $cliente_info ? $cliente_info['saldo_actual'] : '0'; ?>;
            actualizarSaldoPendiente(saldoCliente);
        } else {
            document.getElementById('saldoPendiente').value = '';
            document.getElementById('montoAbono').removeAttribute('max');
        }
    }
});

// Validar monto en tiempo real
function validarMontoAbono() {
    var montoInput = document.getElementById('montoAbono');
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

// Función para formatear moneda local
function formatearMonedaLocal(numero) {
    if (isNaN(numero)) numero = 0;
    return 'Bs. ' + numero.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    // Si hay una venta específica seleccionada, configurar datos
    <?php if ($venta_id && $venta_info): ?>
    abonoData.clienteId = <?php echo $cliente_id ?: 'null'; ?>;
    abonoData.ventaId = <?php echo $venta_id ?: 'null'; ?>;
    abonoData.saldoPendiente = <?php echo $venta_info ? $venta_info['debe'] : '0'; ?>;
    <?php elseif ($cliente_info): ?>
    abonoData.clienteId = <?php echo $cliente_id ?: 'null'; ?>;
    abonoData.saldoPendiente = <?php echo $cliente_info ? $cliente_info['saldo_actual'] : '0'; ?>;
    <?php endif; ?>
    
    // Configurar máximo para monto
    var montoInput = document.getElementById('montoAbono');
    if (montoInput && abonoData.saldoPendiente > 0) {
        montoInput.max = abonoData.saldoPendiente;
    }
});
</script>

<style>
/* Estilos personalizados */
.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: none;
}

.card-header {
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.025);
    cursor: pointer;
}

.list-group-item-action:hover {
    background-color: #f8f9fa;
}

/* Responsive */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
    
    .btn-lg {
        padding: 0.75rem;
        font-size: 1rem;
    }
}
</style>

<?php require_once 'footer.php'; ?>