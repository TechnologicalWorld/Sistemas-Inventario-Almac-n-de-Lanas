<?php

session_start();


$titulo_pagina = "Registrar Pago a Proveedor";
$icono_titulo = "fas fa-credit-card";
$breadcrumb = [
    ['text' => 'Cuentas por Pagar', 'link' => 'cuentas_pagar.php', 'active' => false],
    ['text' => 'Registrar Pago', 'link' => '#', 'active' => true]
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

// Procesar pago
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $proveedor_id = intval($_POST['proveedor_id']);
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
        
        // Obtener información del proveedor
        $query_proveedor = "SELECT codigo, nombre, saldo_actual FROM proveedores WHERE id = ? AND activo = 1";
        $stmt_proveedor = $conn->prepare($query_proveedor);
        $stmt_proveedor->bind_param("i", $proveedor_id);
        $stmt_proveedor->execute();
        $result_proveedor = $stmt_proveedor->get_result();
        
        if ($result_proveedor->num_rows === 0) {
            throw new Exception("Proveedor no encontrado o inactivo");
        }
        
        $proveedor = $result_proveedor->fetch_assoc();
        $saldo_actual = floatval($proveedor['saldo_actual']);
        
        // si la tabla proveedores tiene cero (posible inconsistencia) recomputamos
        if ($saldo_actual == 0) {
            $query_calc = "SELECT COALESCE(SUM(compra - a_cuenta - adelanto),0) as saldo 
                           FROM proveedores_estado_cuentas WHERE proveedor_id = ?";
            $stmt_calc = $conn->prepare($query_calc);
            $stmt_calc->bind_param("i", $proveedor_id);
            $stmt_calc->execute();
            $res_calc = $stmt_calc->get_result();
            if ($row_calc = $res_calc->fetch_assoc()) {
                $saldo_actual = floatval($row_calc['saldo']);
            }
        }
        
        // Validar que el monto no exceda el saldo
        if ($monto > $saldo_actual) {
            throw new Exception("El monto no puede ser mayor al saldo actual del proveedor (Bs. " . number_format($saldo_actual, 2) . ")");
        }
        
        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // 1. Registrar pago en pagos_proveedores
            $query_pago = "INSERT INTO pagos_proveedores 
                          (proveedor_id, monto, metodo_pago, referencia, fecha, usuario_id, observaciones)
                          VALUES (?, ?, ?, ?, CURDATE(), ?, ?)";
            $stmt_pago = $conn->prepare($query_pago);
            $stmt_pago->bind_param("idssis", $proveedor_id, $monto, $metodo_pago, 
                                  $referencia, $_SESSION['usuario_id'], $observaciones);
            
            if (!$stmt_pago->execute()) {
                throw new Exception("Error al registrar pago: " . $stmt_pago->error);
            }
            
            // Las actualizaciones de saldo, movimientos de cuenta y registro en caja
            // están cubiertas por el trigger after_pago_proveedor_insert en la base
            // de datos. No duplicamos la lógica aquí.
            
            $conn->commit();
            
            $mensaje = "Pago registrado exitosamente";
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

// Obtener información del proveedor si se especificó
$proveedor_info = null;
if ($proveedor_id) {
    $query_proveedor = "SELECT id, codigo, nombre, saldo_actual, credito_limite, ciudad, telefono 
                       FROM proveedores WHERE id = ? AND activo = 1";
    $stmt_proveedor = $conn->prepare($query_proveedor);
    $stmt_proveedor->bind_param("i", $proveedor_id);
    $stmt_proveedor->execute();
    $result_proveedor = $stmt_proveedor->get_result();
    
    if ($result_proveedor->num_rows > 0) {
        $proveedor_info = $result_proveedor->fetch_assoc();
    } else {
        $mensaje = "Proveedor no encontrado o inactivo";
        $tipo_mensaje = "warning";
        $proveedor_id = null;
    }
}

// Obtener lista de proveedores con saldo pendiente
$query_proveedores = "SELECT id, codigo, nombre, saldo_actual, ciudad 
                     FROM proveedores 
                     WHERE saldo_actual > 0 AND activo = 1
                     ORDER BY nombre";
$result_proveedores = $conn->query($query_proveedores);
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
                    <i class="fas fa-credit-card me-2"></i>
                    Registrar Pago
                </h5>
            </div>
            <div class="card-body">
                <!-- Formulario de pago -->
                <form method="POST" action="" id="formRegistrarPago">
                    <div class="mb-3">
                        <label class="form-label">Proveedor *</label>
                        <?php if ($proveedor_info): ?>
                        <input type="hidden" name="proveedor_id" value="<?php echo $proveedor_info['id']; ?>">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-building"></i>
                            </span>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($proveedor_info['codigo'] . ' - ' . $proveedor_info['nombre']); ?>" 
                                   readonly>
                        </div>
                        <small class="text-muted">
                            Ciudad: <?php echo htmlspecialchars($proveedor_info['ciudad']); ?> | 
                            Tel: <?php echo htmlspecialchars($proveedor_info['telefono'] ?: 'No especificado'); ?>
                        </small>
                        <?php else: ?>
                        <select class="form-select" name="proveedor_id" id="selectProveedor" required 
                                onchange="cargarInfoProveedor(this.value)">
                            <option value="">Seleccionar proveedor...</option>
                            <?php if ($result_proveedores && $result_proveedores->num_rows > 0): 
                                while ($proveedor = $result_proveedores->fetch_assoc()): ?>
                            <option value="<?php echo $proveedor['id']; ?>" 
                                    data-saldo="<?php echo $proveedor['saldo_actual']; ?>"
                                    data-ciudad="<?php echo htmlspecialchars($proveedor['ciudad']); ?>">
                                <?php echo htmlspecialchars($proveedor['codigo']); ?> - <?php echo htmlspecialchars($proveedor['nombre']); ?> 
                                (Saldo: <?php echo formatearMoneda($proveedor['saldo_actual']); ?>)
                            </option>
                            <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Información del proveedor -->
                    <div id="infoProveedor" style="display: <?php echo $proveedor_info ? 'block' : 'none'; ?>;">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Saldo Actual</label>
                                <div class="input-group">
                                    <span class="input-group-text">Bs.</span>
                                    <input type="text" class="form-control" id="saldoActual" 
                                           value="<?php echo $proveedor_info ? formatearMoneda($proveedor_info['saldo_actual']) : '0.00'; ?>" 
                                           readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Límite Crédito</label>
                                <div class="input-group">
                                    <span class="input-group-text">Bs.</span>
                                    <input type="text" class="form-control" id="limiteCredito" 
                                           value="<?php echo $proveedor_info ? formatearMoneda($proveedor_info['credito_limite']) : '0.00'; ?>" 
                                           readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="progress" style="height: 20px;">
                                    <?php if ($proveedor_info): 
                                        $porcentaje = $proveedor_info['credito_limite'] > 0 ? 
                                            ($proveedor_info['saldo_actual'] / $proveedor_info['credito_limite']) * 100 : 0;
                                    ?>
                                    <div class="progress-bar bg-<?php echo $porcentaje > 80 ? 'danger' : ($porcentaje > 50 ? 'warning' : 'success'); ?>" 
                                         style="width: <?php echo min($porcentaje, 100); ?>%"
                                         role="progressbar">
                                        <?php echo number_format($porcentaje, 1); ?>%
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    Porcentaje de uso del crédito
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">A cuenta (Bs) *</label>
                        <div class="input-group">
                            <span class="input-group-text">Bs.</span>
                            <input type="number" class="form-control" name="monto" 
                                   id="montoPago" step="0.01" min="0.01" required 
                                   oninput="validarMonto()">
                        </div>
                        <div class="invalid-feedback" id="errorMontoPago">
                            Ingrese un monto válido
                        </div>
                        <div class="form-text" id="mensajeMonto"></div>
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
                                <option value="cheque">Cheque</option>
                            </select>
                            <div class="invalid-feedback" id="errorMetodoPago">
                                Seleccione un método de pago
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Referencia</label>
                            <input type="text" class="form-control" name="referencia" 
                                   id="referenciaPago"
                                   placeholder="N° transferencia, voucher, cheque, etc.">
                            <small class="text-muted">Opcional para seguimiento</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" rows="3" 
                                  id="observacionesPago"
                                  placeholder="Detalles adicionales del pago..."></textarea>
                        <small class="text-muted">Opcional</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <small>
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            El pago se registrará en la caja como un gasto y reducirá el saldo del proveedor.
                        </small>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-credit-card me-2"></i>Registrar Pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <!-- Información del proveedor seleccionado -->
        <?php if ($proveedor_info): ?>
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-building me-2"></i>
                    Información del Proveedor
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Código:</strong><br>
                            <span class="text-primary"><?php echo htmlspecialchars($proveedor_info['codigo']); ?></span>
                        </p>
                        <p><strong>Nombre:</strong><br>
                            <?php echo htmlspecialchars($proveedor_info['nombre']); ?>
                        </p>
                        <p><strong>Ciudad:</strong><br>
                            <?php echo htmlspecialchars($proveedor_info['ciudad']); ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Saldo (Deuda)</h6>
                                <h3 class="text-danger fw-bold">
                                    <?php echo formatearMoneda($proveedor_info['saldo_actual']); ?>
                                </h3>
                                <hr>
                                <h6 class="text-muted">Límite Crédito</h6>
                                <h5 class="text-primary">
                                    <?php echo formatearMoneda($proveedor_info['credito_limite']); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Historial reciente -->
                <hr>
                <h6 class="mb-3">
                    <i class="fas fa-history me-2"></i>
                    Últimos Movimientos
                </h6>
                <?php
                $query_movimientos = "SELECT pec.*, u.nombre as usuario_nombre
                                     FROM proveedores_estado_cuentas pec
                                     LEFT JOIN usuarios u ON pec.usuario_id = u.id
                                     WHERE pec.proveedor_id = ? 
                                     ORDER BY pec.fecha DESC, pec.creado_en DESC 
                                     LIMIT 5";
                $stmt_movimientos = $conn->prepare($query_movimientos);
                $stmt_movimientos->bind_param("i", $proveedor_id);
                $stmt_movimientos->execute();
                $result_movimientos = $stmt_movimientos->get_result();
                
                if ($result_movimientos && $result_movimientos->num_rows > 0):
                ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Descripción</th>
                                <th class="text-end">Monto</th>
                                <th class="text-end">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($movimiento = $result_movimientos->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo formatearFecha($movimiento['fecha']); ?></td>
                                <td>
                                    <small><?php echo htmlspecialchars($movimiento['descripcion']); ?></small>
                                    <?php if ($movimiento['referencia']): ?>
                                        <br><small class="text-muted">Ref: <?php echo htmlspecialchars($movimiento['referencia']); ?></small>
                                    <?php endif; ?>
                                    <?php if ($movimiento['usuario_nombre']): ?>
                                        <br><small class="text-muted">
                                            <i class="fas fa-user fa-xs me-1"></i><?php echo htmlspecialchars($movimiento['usuario_nombre']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($movimiento['compra'] > 0): ?>
                                        <span class="text-danger"><?php echo formatearMoneda($movimiento['compra']); ?></span>
                                    <?php elseif ($movimiento['adelanto'] > 0): ?>
                                        <span class="text-success"><?php echo formatearMoneda($movimiento['adelanto']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold <?php echo $movimiento['saldo'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo formatearMoneda($movimiento['saldo']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end mt-2">
                    <a href="cuentas_pagar.php?proveedor_id=<?php echo $proveedor_id; ?>" 
                       class="text-decoration-none small">
                        <i class="fas fa-external-link-alt me-1"></i> Ver historial completo
                    </a>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No hay movimientos registrados para este proveedor
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Historial de pagos recientes -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Últimos Pagos Registrados
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group" style="max-height: 300px; overflow-y: auto;">
                    <?php
                    $query_ultimos_pagos = "SELECT pp.*, p.codigo as proveedor_codigo, p.nombre as proveedor_nombre,
                                           u.nombre as usuario_registro
                                           FROM pagos_proveedores pp
                                           JOIN proveedores p ON pp.proveedor_id = p.id
                                           JOIN usuarios u ON pp.usuario_id = u.id
                                           ORDER BY pp.fecha DESC, pp.id DESC
                                           LIMIT 10";
                    $result_pagos = $conn->query($query_ultimos_pagos);
                    
                    if ($result_pagos && $result_pagos->num_rows > 0):
                        while ($pago = $result_pagos->fetch_assoc()):
                    ?>
                    <div class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <div>
                                <h6 class="mb-1">
                                    <span class="badge bg-success"><?php echo formatearMoneda($pago['monto']); ?></span>
                                    <?php echo htmlspecialchars($pago['proveedor_codigo']); ?>
                                </h6>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($pago['proveedor_nombre']); ?>
                                </small>
                            </div>
                            <small class="text-muted text-end">
                                <?php echo formatearFecha($pago['fecha']); ?><br>
                                <i class="fas fa-user fa-xs me-1"></i><?php echo htmlspecialchars($pago['usuario_registro']); ?>
                            </small>
                        </div>
                        <p class="mb-1 mt-2">
                            <small class="text-muted">
                                <i class="fas fa-credit-card me-1"></i><?php echo ucfirst($pago['metodo_pago']); ?>
                                <?php if ($pago['referencia']): ?>
                                    | <i class="fas fa-hashtag me-1"></i>Ref: <?php echo htmlspecialchars($pago['referencia']); ?>
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
                        <p class="mb-0">No hay pagos registrados</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="cuentas_pagar.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-arrow-left me-1"></i> Volver a Cuentas por Pagar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales
var pagoData = {
    proveedorId: null,
    saldoActual: 0
};

// Cargar información del proveedor
function cargarInfoProveedor(proveedorId) {
    if (!proveedorId) {
        document.getElementById('infoProveedor').style.display = 'none';
        pagoData.saldoActual = 0;
        return;
    }
    
    var select = document.getElementById('selectProveedor');
    var selectedOption = select.options[select.selectedIndex];
    var saldo = parseFloat(selectedOption.getAttribute('data-saldo')) || 0;
    var ciudad = selectedOption.getAttribute('data-ciudad') || '';
    
    pagoData.proveedorId = proveedorId;
    pagoData.saldoActual = saldo;
    
    // Actualizar campos
    document.getElementById('saldoActual').value = formatearMonedaLocal(saldo);
    document.getElementById('infoProveedor').style.display = 'block';
    
    // Actualizar validación de monto
    validarMonto();
}

// Validar monto en tiempo real
function validarMonto() {
    var montoInput = document.getElementById('montoPago');
    var monto = parseFloat(montoInput.value) || 0;
    var saldoActual = pagoData.saldoActual;
    var errorDiv = document.getElementById('errorMontoPago');
    var mensajeDiv = document.getElementById('mensajeMonto');
    
    // Limpiar errores
    montoInput.classList.remove('is-invalid');
    errorDiv.textContent = '';
    mensajeDiv.innerHTML = '';
    
    if (monto <= 0) {
        montoInput.classList.add('is-invalid');
        errorDiv.textContent = 'El monto debe ser mayor a cero';
        return false;
    }
    
    if (monto > saldoActual) {
        montoInput.classList.add('is-invalid');
        errorDiv.textContent = 'El monto no puede ser mayor al saldo actual (Bs. ' + saldoActual.toFixed(2) + ')';
        return false;
    }
    
    // Mostrar nuevo saldo
    var nuevoSaldo = saldoActual - monto;
    mensajeDiv.innerHTML = '<span class="text-success">Nuevo saldo después del pago: ' + formatearMonedaLocal(nuevoSaldo) + '</span>';
    
    return true;
}

// Validar formulario completo
document.getElementById('formRegistrarPago').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validar monto
    if (!validarMonto()) {
        return false;
    }
    
    // Validar método de pago
    var metodoPago = this.querySelector('select[name="metodo_pago"]');
    var errorMetodo = document.getElementById('errorMetodoPago');
    
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
    if (!confirm('¿Está seguro de registrar este pago?')) {
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
    <?php if ($proveedor_info): ?>
    // Si hay proveedor seleccionado, inicializar datos
    pagoData.proveedorId = <?php echo $proveedor_id ?: 'null'; ?>;
    pagoData.saldoActual = <?php echo $proveedor_info ? $proveedor_info['saldo_actual'] : '0'; ?>;
    validarMonto();
    <?php endif; ?>
    
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
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
}

.list-group-item-action:hover {
    background-color: #f8f9fa;
}

.progress {
    background-color: #e9ecef;
    border-radius: 3px;
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