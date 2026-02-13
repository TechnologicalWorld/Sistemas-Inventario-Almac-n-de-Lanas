<?php

$titulo_pagina = "Finalizar Venta";
$icono_titulo = "fas fa-check-circle";
$breadcrumb = [
    ['text' => 'Ventas', 'link' => 'ventas.php', 'active' => false],
    ['text' => 'Carrito', 'link' => 'carrito.php', 'active' => false],
    ['text' => 'Finalizar Venta', 'link' => '#', 'active' => true]
];

require_once 'header.php';
require_once 'funciones.php';

// Verificar sesión (acceso para admin y vendedor)
verificarSesion();

// Verificar que haya productos en el carrito
if (empty($_SESSION['carrito'])) {
    header("Location: carrito.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';
$venta_exitosa = false;
$codigo_venta = '';
$venta_id = 0;

// Procesar finalización de venta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'finalizar') {
    $tipo_pago = limpiar($_POST['tipo_pago']);
    $metodo_pago = limpiar($_POST['metodo_pago']);
    $referencia_pago = limpiar($_POST['referencia_pago']);
    $descuento = floatval($_POST['descuento']);
    $observaciones = limpiar($_POST['observaciones']);
    
    // Calcular totales del carrito
    $subtotal = 0;
    $total_items = 0;
    foreach ($_SESSION['carrito'] as $item) {
        $subtotal += $item['subtotal'];
        $total_items += $item['cantidad'];
    }
    
    $total = $subtotal - $descuento;
    
    // Obtener cliente si está en sesión
    $cliente_id = $_SESSION['cliente_venta'] ?? null;
    $cliente_contado = $cliente_id ? null : 'Cliente Contado';
    
    // Calcular pago inicial según tipo de pago
    $pago_inicial = 0;
    if ($tipo_pago == 'contado') {
        $pago_inicial = $total;
    } elseif ($tipo_pago == 'mixto') {
        $pago_inicial = floatval($_POST['pago_inicial']);
        if ($pago_inicial > $total) {
            $pago_inicial = $total;
        }
    }
    
    // Verificar límite de crédito si es crédito o mixto
    if (($tipo_pago == 'credito' || $tipo_pago == 'mixto') && $cliente_id) {
        $query_cliente = "SELECT limite_credito, saldo_actual FROM clientes WHERE id = ?";
        $stmt_cliente = $conn->prepare($query_cliente);
        $stmt_cliente->bind_param("i", $cliente_id);
        $stmt_cliente->execute();
        $result_cliente = $stmt_cliente->get_result();
        
        if ($result_cliente->num_rows > 0) {
            $cliente = $result_cliente->fetch_assoc();
            $nueva_deuda = ($total - $pago_inicial);
            $total_deuda = $cliente['saldo_actual'] + $nueva_deuda;
            
            if ($total_deuda > $cliente['limite_credito']) {
                $mensaje = "El cliente excede su límite de crédito. Límite: " . 
                          formatearMoneda($cliente['limite_credito']) . 
                          " | Deuda actual: " . formatearMoneda($cliente['saldo_actual']);
                $tipo_mensaje = "danger";
            }
        }
    }
    
    // Si no hay errores, proceder con la venta
    if (empty($mensaje)) {
        // Generar código de venta
        $codigo_venta = generarCodigoVenta();
        
        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // Insertar venta
            $query_venta = "INSERT INTO ventas 
                           (codigo_venta, cliente_id, cliente_contado, vendedor_id, 
                            tipo_pago, subtotal, descuento, total, pago_inicial,
                            metodo_pago_inicial, referencia_pago_inicial, fecha, hora_inicio, hora_fin)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), CURTIME())";
            
            $stmt_venta = $conn->prepare($query_venta);
            $stmt_venta->bind_param("sisisddddsss", $codigo_venta, $cliente_id, $cliente_contado, 
                                   $_SESSION['usuario_id'], $tipo_pago, $subtotal, $descuento, 
                                   $total, $pago_inicial, $metodo_pago, $referencia_pago);
            
            if ($stmt_venta->execute()) {
                $venta_id = $stmt_venta->insert_id;
                
                // Insertar detalles de venta
                foreach ($_SESSION['carrito'] as $item) {
                    $query_detalle = "INSERT INTO venta_detalles 
                                     (venta_id, producto_id, cantidad_subpaquetes, 
                                      precio_unitario, subtotal, hora_extraccion, usuario_extraccion)
                                     VALUES (?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt_detalle = $conn->prepare($query_detalle);
                    $stmt_detalle->bind_param("iiiddsi", $venta_id, $item['producto_id'], 
                                            $item['cantidad'], $item['precio'], 
                                            $item['subtotal'], $item['hora_extraccion'],
                                            $_SESSION['usuario_id']);
                    
                    if (!$stmt_detalle->execute()) {
                        throw new Exception("Error al insertar detalle: " . $stmt_detalle->error);
                    }
                    
                    // Actualizar inventario (se hace automáticamente con trigger)
                }
                
                // Registrar pago inicial en movimientos de caja si hay
                if ($pago_inicial > 0) {
                    $query_movimiento = "INSERT INTO movimientos_caja 
                                        (tipo, categoria, monto, descripcion, referencia_venta,
                                         fecha, hora, usuario_id, observaciones)
                                        VALUES ('ingreso', 'pago_inicial', ?, ?, ?,
                                                CURDATE(), CURTIME(), ?, ?)";
                    
                    $descripcion = "Pago inicial venta " . $codigo_venta . 
                                  ($cliente_id ? " - Cliente: " . $cliente['nombre'] : "");
                    
                    $stmt_movimiento = $conn->prepare($query_movimiento);
                    $stmt_movimiento->bind_param("dssis", $pago_inicial, $descripcion, 
                                                $codigo_venta, $_SESSION['usuario_id'], $observaciones);
                    $stmt_movimiento->execute();
                }
                
                // Confirmar transacción
                $conn->commit();
                
                $venta_exitosa = true;
                $mensaje = "Venta registrada exitosamente. Código: " . $codigo_venta;
                $tipo_mensaje = "success";
                
                // Limpiar carrito y cliente de sesión
                unset($_SESSION['carrito']);
                unset($_SESSION['cliente_venta']);
                
            } else {
                throw new Exception("Error al registrar venta: " . $stmt_venta->error);
            }
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $conn->rollback();
            $mensaje = "Error al procesar la venta: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
}

// Calcular totales del carrito
$subtotal_carrito = 0;
$total_items = 0;
foreach ($_SESSION['carrito'] as $item) {
    $subtotal_carrito += $item['subtotal'];
    $total_items += $item['cantidad'];
}
$descuento_carrito = 0;
$total_carrito = $subtotal_carrito - $descuento_carrito;

// Obtener información del cliente si existe
$cliente_info = null;
if (isset($_SESSION['cliente_venta'])) {
    $query_cliente = "SELECT * FROM clientes WHERE id = ?";
    $stmt = $conn->prepare($query_cliente);
    $stmt->bind_param("i", $_SESSION['cliente_venta']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $cliente_info = $result->fetch_assoc();
    }
}
?>

<?php if ($mensaje): ?>
<div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
    <?php echo $mensaje; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($venta_exitosa): ?>
<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header bg-success text-white text-center">
                <h4 class="mb-0"><i class="fas fa-check-circle me-2"></i>¡Venta Exitosa!</h4>
            </div>
            <div class="card-body text-center">
                <div class="mb-4">
                    <i class="fas fa-receipt fa-5x text-success mb-3"></i>
                    <h2><?php echo $codigo_venta; ?></h2>
                    <p class="lead">Venta registrada correctamente</p>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6>Detalles de la Venta</h6>
                                <p class="mb-1"><strong>Total:</strong> <?php echo formatearMoneda($total_carrito); ?></p>
                                <p class="mb-1"><strong>Items:</strong> <?php echo $total_items; ?></p>
                                <p class="mb-1"><strong>Fecha:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
                                <p class="mb-0"><strong>Vendedor:</strong> <?php echo $_SESSION['usuario_nombre']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6>Acciones</h6>
                                <div class="d-grid gap-2">
                                    <a href="exportar_pdf.php?tipo=recibo&venta_id=<?php echo $venta_id; ?>" 
                                       class="btn btn-outline-primary" target="_blank">
                                        <i class="fas fa-print me-2"></i>Imprimir Recibo
                                    </a>
                                    <a href="ventas.php" class="btn btn-outline-success">
                                        <i class="fas fa-cash-register me-2"></i>Nueva Venta
                                    </a>
                                    <a href="historial_ventas.php" class="btn btn-outline-info">
                                        <i class="fas fa-history me-2"></i>Ver Historial
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <a href="ventas.php" class="btn btn-success btn-lg">
                        <i class="fas fa-plus-circle me-2"></i>Realizar Nueva Venta
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Resumen de la Venta</h5>
            </div>
            <div class="card-body">
                <!-- Resumen del carrito -->
                <div class="table-responsive mb-4">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Precio Unit.</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['carrito'] as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $item['codigo']; ?></strong> - <?php echo $item['nombre']; ?><br>
                                    <small class="text-muted"><?php echo $item['proveedor']; ?></small>
                                </td>
                                <td class="text-center"><?php echo $item['cantidad']; ?></td>
                                <td class="text-end"><?php echo formatearMoneda($item['precio']); ?></td>
                                <td class="text-end"><?php echo formatearMoneda($item['subtotal']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Totales -->
                <div class="row">
                    <div class="col-md-6 offset-md-6">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Subtotal:</strong></td>
                                <td class="text-end"><?php echo formatearMoneda($subtotal_carrito); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Descuento:</strong></td>
                                <td class="text-end text-danger">-<?php echo formatearMoneda($descuento_carrito); ?></td>
                            </tr>
                            <tr class="table-success">
                                <td><strong>TOTAL A PAGAR:</strong></td>
                                <td class="text-end"><h4 class="mb-0"><?php echo formatearMoneda($total_carrito); ?></h4></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Información del cliente -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h6>Información del Cliente</h6>
                        <?php if ($cliente_info): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Cliente:</strong> <?php echo $cliente_info['nombre']; ?></p>
                                <p class="mb-1"><strong>Código:</strong> <?php echo $cliente_info['codigo']; ?></p>
                                <p class="mb-1"><strong>Teléfono:</strong> <?php echo $cliente_info['telefono']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-info">
                                    <small>
                                        <strong>Límite de crédito:</strong> <?php echo formatearMoneda($cliente_info['limite_credito']); ?><br>
                                        <strong>Saldo actual:</strong> <?php echo formatearMoneda($cliente_info['saldo_actual']); ?><br>
                                        <strong>Disponible:</strong> <?php echo formatearMoneda($cliente_info['limite_credito'] - $cliente_info['saldo_actual']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-user-slash fa-2x mb-2"></i>
                            <p class="mb-0">Venta Rápida - Cliente no registrado</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Formulario de pago -->
                <div class="card">
                    <div class="card-body">
                        <h6>Información de Pago</h6>
                        <form method="POST" action="" id="formFinalizarVenta">
                            <input type="hidden" name="action" value="finalizar">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tipo de Pago *</label>
                                    <select class="form-select" name="tipo_pago" id="tipoPago" required 
                                            onchange="actualizarOpcionesPago()">
                                        <option value="">Seleccionar...</option>
                                        <option value="contado">Contado Completo</option>
                                        <option value="credito">Crédito Completo</option>
                                        <option value="mixto">Mixto (Parte contado, parte crédito)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Método de Pago *</label>
                                    <select class="form-select" name="metodo_pago" id="metodoPago" required>
                                        <option value="efectivo">Efectivo</option>
                                        <option value="transferencia">Transferencia Bancaria</option>
                                        <option value="QR">QR/Pago Móvil</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Sección para pago mixto -->
                            <div class="row mb-3" id="seccionPagoInicial" style="display: none;">
                                <div class="col-md-6">
                                    <label class="form-label">Pago Inicial (Bs) *</label>
                                    <input type="number" class="form-control" name="pago_inicial" 
                                           id="pagoInicial" step="0.01" min="0" 
                                           max="<?php echo $total_carrito; ?>"
                                           onchange="calcularSaldo()">
                                    <div class="form-text">Monto que paga al momento</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Saldo Pendiente</label>
                                    <input type="text" class="form-control" id="saldoPendiente" readonly>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Referencia de Pago</label>
                                    <input type="text" class="form-control" name="referencia_pago" 
                                           placeholder="N° de transacción, depósito, etc.">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Descuento Adicional (Bs)</label>
                                    <input type="number" class="form-control" name="descuento" 
                                           id="descuento" step="0.01" min="0" 
                                           max="<?php echo $subtotal_carrito; ?>" 
                                           value="0" onchange="recalcularTotales()">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Observaciones</label>
                                    <textarea class="form-control" name="observaciones" rows="2" 
                                              placeholder="Notas adicionales sobre la venta..."></textarea>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-success btn-lg" id="btnFinalizar">
                                            <i class="fas fa-check-circle me-2"></i>Finalizar Venta
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Resumen de pago -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">Resumen de Pago</h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <h2 id="totalPagar"><?php echo formatearMoneda($total_carrito); ?></h2>
                    <p class="mb-0">Total a pagar</p>
                </div>
                
                <div class="alert alert-info" id="infoPago">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        Seleccione el tipo de pago para continuar
                    </small>
                </div>
                
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-warning" onclick="recalcularPrecios()">
                        <i class="fas fa-calculator me-2"></i>Recalcular Totales
                    </button>
                    <a href="carrito.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Volver al Carrito
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Historial reciente del cliente -->
        <?php if ($cliente_info): ?>
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">Historial del Cliente</h6>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php
                    $query_historial = "SELECT v.codigo_venta, v.fecha, v.total, v.debe, v.estado
                                       FROM ventas v
                                       WHERE v.cliente_id = ? AND v.anulado = 0
                                       ORDER BY v.fecha DESC
                                       LIMIT 5";
                    $stmt = $conn->prepare($query_historial);
                    $stmt->bind_param("i", $cliente_info['id']);
                    $stmt->execute();
                    $result_historial = $stmt->get_result();
                    
                    if ($result_historial->num_rows > 0):
                        while ($venta = $result_historial->fetch_assoc()):
                    ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo $venta['codigo_venta']; ?></h6>
                            <small><?php echo formatearFecha($venta['fecha']); ?></small>
                        </div>
                        <p class="mb-1">
                            <?php echo formatearMoneda($venta['total']); ?>
                            <?php if ($venta['debe'] > 0): ?>
                                <span class="badge bg-warning float-end">Debe: <?php echo formatearMoneda($venta['debe']); ?></span>
                            <?php endif; ?>
                        </p>
                        <small class="text-muted">Estado: <?php echo ucfirst($venta['estado']); ?></small>
                    </div>
                    <?php
                        endwhile;
                    else:
                    ?>
                    <div class="text-center text-muted p-3">
                        No hay ventas anteriores
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Consejos para la venta -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0">Recomendaciones</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <small>
                        <i class="fas fa-check-circle me-1"></i>
                        <strong>Verifique:</strong>
                        <ul class="mb-0">
                            <li>Productos correctos</li>
                            <li>Cantidades exactas</li>
                            <li>Precios actualizados</li>
                            <li>Datos del cliente</li>
                        </ul>
                    </small>
                </div>
                <div class="alert alert-warning">
                    <small>
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Importante:</strong> Una vez finalizada la venta, no se podrán realizar cambios.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
// Actualizar opciones de pago según tipo seleccionado
function actualizarOpcionesPago() {
    var tipoPago = document.getElementById('tipoPago').value;
    var seccionPagoInicial = document.getElementById('seccionPagoInicial');
    var infoPago = document.getElementById('infoPago');
    var pagoInicialInput = document.getElementById('pagoInicial');
    
    // Mostrar/ocultar sección de pago inicial
    if (tipoPago === 'mixto') {
        seccionPagoInicial.style.display = 'block';
        pagoInicialInput.required = true;
        infoPago.innerHTML = '<small><i class="fas fa-info-circle me-1"></i>Ingrese el monto que paga al momento. El saldo restante quedará como crédito.</small>';
        calcularSaldo();
    } else {
        seccionPagoInicial.style.display = 'none';
        pagoInicialInput.required = false;
        pagoInicialInput.value = '';
        
        if (tipoPago === 'contado') {
            infoPago.innerHTML = '<small><i class="fas fa-info-circle me-1"></i>El cliente pagará el monto total al momento de la venta.</small>';
        } else if (tipoPago === 'credito') {
            infoPago.innerHTML = '<small><i class="fas fa-info-circle me-1"></i>El total de la venta quedará como crédito. El cliente pagará posteriormente.</small>';
        } else {
            infoPago.innerHTML = '<small><i class="fas fa-info-circle me-1"></i>Seleccione el tipo de pago para continuar</small>';
        }
    }
}

// Calcular saldo pendiente
function calcularSaldo() {
    var total = <?php echo $total_carrito; ?>;
    var pagoInicial = parseFloat(document.getElementById('pagoInicial').value) || 0;
    var descuento = parseFloat(document.getElementById('descuento').value) || 0;
    
    // Asegurar que el pago inicial no sea mayor al total
    if (pagoInicial > total) {
        pagoInicial = total;
        document.getElementById('pagoInicial').value = total;
    }
    
    var saldoPendiente = total - pagoInicial;
    document.getElementById('saldoPendiente').value = 'Bs ' + saldoPendiente.toFixed(2);
}

// Recalcular totales con descuento
function recalcularTotales() {
    var subtotal = <?php echo $subtotal_carrito; ?>;
    var descuento = parseFloat(document.getElementById('descuento').value) || 0;
    
    // Asegurar que el descuento no sea mayor al subtotal
    if (descuento > subtotal) {
        descuento = subtotal;
        document.getElementById('descuento').value = subtotal;
    }
    
    var total = subtotal - descuento;
    document.getElementById('totalPagar').textContent = 'Bs ' + total.toFixed(2);
    
    // Actualizar máximo del pago inicial
    document.getElementById('pagoInicial').max = total;
    calcularSaldo();
}

// Recalcular precios
function recalcularPrecios() {
    if (confirm('¿Recalcular precios según cantidades actuales?')) {
        window.location.href = 'carrito.php';
    }
}

// Validar formulario antes de enviar
document.getElementById('formFinalizarVenta').addEventListener('submit', function(e) {
    var tipoPago = document.getElementById('tipoPago').value;
    var pagoInicial = document.getElementById('pagoInicial').value;
    
    if (tipoPago === 'mixto' && (!pagoInicial || parseFloat(pagoInicial) <= 0)) {
        e.preventDefault();
        alert('Ingrese el monto del pago inicial para venta mixta');
        return false;
    }
    
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    this.classList.add('was-validated');
    
    // Mostrar confirmación final
    if (this.checkValidity()) {
        var total = <?php echo $total_carrito; ?>;
        var confirmacion = '¿Finalizar venta por ' + formatearMoneda(total) + '?';
        
        if (!confirm(confirmacion)) {
            e.preventDefault();
            return false;
        }
        
        // Mostrar loading
        mostrarLoading();
    }
});

// Función para formatear moneda
function formatearMoneda(monto) {
    return 'Bs ' + parseFloat(monto).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    recalcularTotales();
});
</script>

<?php require_once 'footer.php'; ?>