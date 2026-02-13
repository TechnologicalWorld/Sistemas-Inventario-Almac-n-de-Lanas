<?php
// ajax_historial_ventas.php - Manejo AJAX para historial de ventas
require_once 'config.php';
require_once 'funciones.php';

// Verificar sesión
verificarSesion();

header('Content-Type: application/json');

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Obtener usuario actual
$usuario_id = $_SESSION['usuario_id'];
$usuario_rol = $_SESSION['usuario_rol'];

$response = ['success' => false, 'message' => 'Acción no válida'];

try {
    switch ($action) {
        case 'detalle_venta':
            $venta_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            
            if ($venta_id <= 0) {
                $response = ['success' => false, 'message' => 'ID de venta inválido'];
                break;
            }
            
            // Verificar permisos
            $query_permiso = "SELECT v.id FROM ventas v WHERE v.id = ?";
            if ($usuario_rol == 'vendedor') {
                $query_permiso .= " AND v.vendedor_id = ?";
            }
            
            $stmt_permiso = $conn->prepare($query_permiso);
            if ($usuario_rol == 'vendedor') {
                $stmt_permiso->bind_param("ii", $venta_id, $usuario_id);
            } else {
                $stmt_permiso->bind_param("i", $venta_id);
            }
            $stmt_permiso->execute();
            $result_permiso = $stmt_permiso->get_result();
            
            if ($result_permiso->num_rows === 0) {
                $response = ['success' => false, 'message' => 'No tiene permiso para ver esta venta'];
                break;
            }
            
            // Obtener información de la venta
            $query_venta = "SELECT v.*, c.nombre as cliente_nombre, c.codigo as cliente_codigo, 
                           c.telefono as cliente_telefono, c.tipo_documento, c.numero_documento,
                           u.nombre as vendedor, u.codigo as vendedor_codigo
                           FROM ventas v
                           LEFT JOIN clientes c ON v.cliente_id = c.id
                           JOIN usuarios u ON v.vendedor_id = u.id
                           WHERE v.id = ?";
            
            $stmt_venta = $conn->prepare($query_venta);
            $stmt_venta->bind_param("i", $venta_id);
            $stmt_venta->execute();
            $result_venta = $stmt_venta->get_result();
            $venta = $result_venta->fetch_assoc();
            
            // Obtener detalles de la venta
            $query_detalles = "SELECT vd.*, p.codigo, p.nombre_color, 
                              pr.nombre as proveedor, c.nombre as categoria,
                              (vd.cantidad_subpaquetes * vd.precio_unitario) as subtotal_item
                              FROM venta_detalles vd
                              JOIN productos p ON vd.producto_id = p.id
                              JOIN proveedores pr ON p.proveedor_id = pr.id
                              JOIN categorias c ON p.categoria_id = c.id
                              WHERE vd.venta_id = ?
                              ORDER BY vd.id";
            
            $stmt_detalles = $conn->prepare($query_detalles);
            $stmt_detalles->bind_param("i", $venta_id);
            $stmt_detalles->execute();
            $detalles = $stmt_detalles->get_result();
            
            // Obtener pagos registrados
            $query_pagos = "SELECT pc.*, u.nombre as usuario_registro
                           FROM pagos_clientes pc
                           JOIN usuarios u ON pc.usuario_id = u.id
                           WHERE pc.venta_id = ?
                           ORDER BY pc.fecha, pc.hora";
            
            $stmt_pagos = $conn->prepare($query_pagos);
            $stmt_pagos->bind_param("i", $venta_id);
            $stmt_pagos->execute();
            $pagos = $stmt_pagos->get_result();
            
            // Generar HTML del detalle
            ob_start();
            ?>
            <div class="row">
                <div class="col-md-12">
                    <input type="hidden" name="venta_id" value="<?php echo $venta_id; ?>">
                    
                    <!-- Información principal -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">Información de la Venta</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Código:</strong><br>
                                    <h4><?php echo $venta['codigo_venta']; ?></h4>
                                </div>
                                <div class="col-md-3">
                                    <strong>Fecha y Hora:</strong><br>
                                    <?php echo formatearFecha($venta['fecha']); ?> - <?php echo formatearHora($venta['hora_inicio']); ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Vendedor:</strong><br>
                                    <?php echo $venta['vendedor']; ?> (<?php echo $venta['vendedor_codigo']; ?>)
                                </div>
                                <div class="col-md-3">
                                    <strong>Estado:</strong><br>
                                    <?php 
                                    $estado_badge = [
                                        'pendiente' => 'warning',
                                        'pagada' => 'success',
                                        'cancelada' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $estado_badge[$venta['estado']]; ?>">
                                        <?php echo strtoupper($venta['estado']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <strong>Cliente:</strong><br>
                                    <?php echo $venta['cliente_nombre'] ?: $venta['cliente_contado']; ?>
                                    <?php if ($venta['cliente_codigo']): ?>
                                    <br><small class="text-muted"><?php echo $venta['cliente_codigo']; ?></small>
                                    <?php endif; ?>
                                    <?php if ($venta['cliente_telefono']): ?>
                                    <br><small class="text-muted"><i class="fas fa-phone"></i> <?php echo $venta['cliente_telefono']; ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Documento:</strong><br>
                                    <?php if ($venta['tipo_documento'] && $venta['numero_documento']): ?>
                                    <?php echo $venta['tipo_documento']; ?>: <?php echo $venta['numero_documento']; ?>
                                    <?php else: ?>
                                    <span class="text-muted">No especificado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($venta['observaciones']): ?>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <strong>Observaciones:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($venta['observaciones'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Detalles de productos -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">Productos Vendidos</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Código</th>
                                            <th>Producto</th>
                                            <th>Proveedor</th>
                                            <th class="text-center">Cantidad</th>
                                            <th class="text-end">Precio Unit.</th>
                                            <th class="text-end">Subtotal</th>
                                            <th class="text-center">Hora Extracción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $contador = 1;
                                        $total_cantidad = 0;
                                        while ($detalle = $detalles->fetch_assoc()): 
                                            $total_cantidad += $detalle['cantidad_subpaquetes'];
                                        ?>
                                        <tr>
                                            <td><?php echo $contador++; ?></td>
                                            <td><strong><?php echo $detalle['codigo']; ?></strong></td>
                                            <td><?php echo $detalle['nombre_color']; ?></td>
                                            <td><?php echo $detalle['proveedor']; ?></td>
                                            <td class="text-center"><?php echo $detalle['cantidad_subpaquetes']; ?></td>
                                            <td class="text-end"><?php echo formatearMoneda($detalle['precio_unitario']); ?></td>
                                            <td class="text-end"><?php echo formatearMoneda($detalle['subtotal_item']); ?></td>
                                            <td class="text-center"><?php echo formatearHora($detalle['hora_extraccion']); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Totales:</strong></td>
                                            <td class="text-center"><strong><?php echo $total_cantidad; ?></strong></td>
                                            <td colspan="3"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Resumen financiero -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">Resumen Financiero</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <td>Subtotal:</td>
                                            <td class="text-end"><?php echo formatearMoneda($venta['subtotal']); ?></td>
                                        </tr>
                                        <tr>
                                            <td>Descuento:</td>
                                            <td class="text-end text-danger">-<?php echo formatearMoneda($venta['descuento']); ?></td>
                                        </tr>
                                        <tr class="table-active">
                                            <td><strong>TOTAL VENTA:</strong></td>
                                            <td class="text-end"><strong><?php echo formatearMoneda($venta['total']); ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td>Tipo de Pago:</td>
                                            <td class="text-end">
                                                <span class="badge bg-<?php echo $venta['tipo_pago'] == 'contado' ? 'success' : ($venta['tipo_pago'] == 'credito' ? 'warning' : 'info'); ?>">
                                                    <?php echo strtoupper($venta['tipo_pago']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Pago Inicial:</td>
                                            <td class="text-end"><?php echo formatearMoneda($venta['pago_inicial']); ?></td>
                                        </tr>
                                        <tr class="<?php echo $venta['debe'] > 0 ? 'table-warning' : 'table-success'; ?>">
                                            <td><strong>SALDO PENDIENTE:</strong></td>
                                            <td class="text-end"><strong><?php echo formatearMoneda($venta['debe']); ?></strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Historial de pagos -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">Historial de Pagos</h6>
                                </div>
                                <div class="card-body p-0">
                                    <?php if ($pagos->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Tipo</th>
                                                    <th class="text-end">Monto</th>
                                                    <th>Método</th>
                                                    <th>Usuario</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($pago = $pagos->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo formatearFecha($pago['fecha']); ?><br>
                                                        <small><?php echo formatearHora($pago['hora']); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $pago['tipo'] == 'pago_inicial' ? 'primary' : ($pago['tipo'] == 'abono' ? 'warning' : 'success'); ?>">
                                                            <?php echo $pago['tipo']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end fw-bold"><?php echo formatearMoneda($pago['monto']); ?></td>
                                                    <td><?php echo ucfirst($pago['metodo_pago']); ?></td>
                                                    <td><small><?php echo $pago['usuario_registro']; ?></small></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-money-bill-wave fa-2x mb-3"></i>
                                        <p>No hay pagos registrados</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            $html = ob_get_clean();
            
            // Cambiar a texto plano para la respuesta
            $response = ['success' => true, 'html' => $html];
            break;
            
        case 'info_abono':
            $venta_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            
            if ($venta_id <= 0) {
                $response = ['success' => false, 'message' => 'ID de venta inválido'];
                break;
            }
            
            // Obtener información para el abono
            $query = "SELECT v.id, v.codigo_venta, v.total, v.debe, 
                      COALESCE(c.nombre, v.cliente_contado) as cliente_nombre,
                      c.id as cliente_id, c.telefono as cliente_telefono
                      FROM ventas v
                      LEFT JOIN clientes c ON v.cliente_id = c.id
                      WHERE v.id = ? AND v.estado = 'pendiente' AND v.anulado = 0";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $venta_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $response = ['success' => false, 'message' => 'Venta no encontrada o no está pendiente'];
                break;
            }
            
            $venta = $result->fetch_assoc();
            $response = [
                'success' => true,
                'codigo_venta' => $venta['codigo_venta'],
                'cliente_nombre' => $venta['cliente_nombre'],
                'cliente_id' => $venta['cliente_id'],
                'cliente_telefono' => $venta['cliente_telefono'],
                'total' => floatval($venta['total']),
                'debe' => floatval($venta['debe'])
            ];
            break;
            
        case 'info_venta':
            $venta_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            
            if ($venta_id <= 0) {
                $response = ['success' => false, 'message' => 'ID de venta inválido'];
                break;
            }
            
            $query = "SELECT v.codigo_venta, 
                      COALESCE(c.nombre, v.cliente_contado) as cliente_nombre
                      FROM ventas v
                      LEFT JOIN clientes c ON v.cliente_id = c.id
                      WHERE v.id = ? AND v.estado != 'cancelada' AND v.anulado = 0";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $venta_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $response = ['success' => false, 'message' => 'Venta no encontrada o ya cancelada'];
                break;
            }
            
            $venta = $result->fetch_assoc();
            $response = [
                'success' => true,
                'codigo_venta' => $venta['codigo_venta'],
                'cliente_nombre' => $venta['cliente_nombre']
            ];
            break;
            
        case 'registrar_abono':
            // Solo administradores pueden registrar abonos
            if ($usuario_rol != 'administrador') {
                $response = ['success' => false, 'message' => 'No autorizado'];
                break;
            }
            
            $venta_id = isset($_POST['venta_id']) ? intval($_POST['venta_id']) : 0;
            $monto = isset($_POST['monto']) ? floatval($_POST['monto']) : 0;
            $metodo_pago = isset($_POST['metodo_pago']) ? trim($_POST['metodo_pago']) : '';
            $referencia = isset($_POST['referencia']) ? trim($_POST['referencia']) : '';
            $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';
            
            if ($venta_id <= 0 || $monto <= 0 || empty($metodo_pago)) {
                $response = ['success' => false, 'message' => 'Datos inválidos'];
                break;
            }
            
            // Iniciar transacción
            $conn->begin_transaction();
            
            try {
                // 1. Obtener información actual de la venta
                $query_venta = "SELECT debe, cliente_id FROM ventas WHERE id = ? FOR UPDATE";
                $stmt_venta = $conn->prepare($query_venta);
                $stmt_venta->bind_param("i", $venta_id);
                $stmt_venta->execute();
                $result_venta = $stmt_venta->get_result();
                $venta = $result_venta->fetch_assoc();
                
                if ($venta['debe'] < $monto) {
                    throw new Exception('El monto del abono no puede ser mayor al saldo pendiente');
                }
                
                // 2. Registrar el pago
                $query_pago = "INSERT INTO pagos_clientes 
                              (tipo, cliente_id, venta_id, monto, metodo_pago, 
                               referencia, fecha, hora, usuario_id, observaciones)
                              VALUES ('abono', ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?)";
                
                $stmt_pago = $conn->prepare($query_pago);
                $stmt_pago->bind_param("iidssis", 
                    $venta['cliente_id'],
                    $venta_id,
                    $monto,
                    $metodo_pago,
                    $referencia,
                    $usuario_id,
                    $observaciones
                );
                $stmt_pago->execute();
                
                // 3. Actualizar saldo de la venta
                $nuevo_debe = $venta['debe'] - $monto;
                $estado = ($nuevo_debe <= 0) ? 'pagada' : 'pendiente';
                
                $query_update_venta = "UPDATE ventas SET debe = ?, estado = ? WHERE id = ?";
                $stmt_update = $conn->prepare($query_update_venta);
                $stmt_update->bind_param("dsi", $nuevo_debe, $estado, $venta_id);
                $stmt_update->execute();
                
                // 4. Actualizar saldo del cliente
                $query_update_cliente = "UPDATE clientes SET saldo_actual = saldo_actual - ? WHERE id = ?";
                $stmt_update_cliente = $conn->prepare($query_update_cliente);
                $stmt_update_cliente->bind_param("di", $monto, $venta['cliente_id']);
                $stmt_update_cliente->execute();
                
                // 5. Registrar movimiento de caja
                $query_caja = "INSERT INTO movimientos_caja 
                              (tipo, categoria, monto, descripcion, referencia_venta,
                               fecha, hora, usuario_id, observaciones)
                              VALUES ('ingreso', 'abono_cliente', ?, ?, 
                                      (SELECT codigo_venta FROM ventas WHERE id = ?),
                                      CURDATE(), CURTIME(), ?, ?)";
                
                $descripcion = "Abono cliente";
                $obs_caja = "Método: $metodo_pago" . ($referencia ? " - Ref: $referencia" : "");
                $stmt_caja = $conn->prepare($query_caja);
                $stmt_caja->bind_param("dsiss", $monto, $descripcion, $venta_id, $usuario_id, $obs_caja);
                $stmt_caja->execute();
                
                // 6. Actualizar estado de cuenta por cobrar si se pagó totalmente
                if ($nuevo_debe <= 0) {
                    $query_update_cobrar = "UPDATE clientes_cuentas_cobrar 
                                           SET estado = 'pagada', saldo_pendiente = 0 
                                           WHERE venta_id = ?";
                    $stmt_cobrar = $conn->prepare($query_update_cobrar);
                    $stmt_cobrar->bind_param("i", $venta_id);
                    $stmt_cobrar->execute();
                } else {
                    $query_update_cobrar = "UPDATE clientes_cuentas_cobrar 
                                           SET saldo_pendiente = saldo_pendiente - ? 
                                           WHERE venta_id = ?";
                    $stmt_cobrar = $conn->prepare($query_update_cobrar);
                    $stmt_cobrar->bind_param("di", $monto, $venta_id);
                    $stmt_cobrar->execute();
                }
                
                $conn->commit();
                $response = ['success' => true, 'message' => 'Abono registrado exitosamente'];
                
            } catch (Exception $e) {
                $conn->rollback();
                $response = ['success' => false, 'message' => $e->getMessage()];
            }
            break;
            
        case 'cancelar_venta':
            // Solo administradores pueden cancelar ventas
            if ($usuario_rol != 'administrador') {
                $response = ['success' => false, 'message' => 'No autorizado'];
                break;
            }
            
            $venta_id = isset($_POST['venta_id']) ? intval($_POST['venta_id']) : 0;
            $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';
            
            if ($venta_id <= 0 || empty($motivo)) {
                $response = ['success' => false, 'message' => 'Datos inválidos'];
                break;
            }
            
            // Iniciar transacción
            $conn->begin_transaction();
            
            try {
                // 1. Obtener información de la venta
                $query_venta = "SELECT v.*, c.id as cliente_id FROM ventas v 
                               LEFT JOIN clientes c ON v.cliente_id = c.id 
                               WHERE v.id = ? FOR UPDATE";
                $stmt_venta = $conn->prepare($query_venta);
                $stmt_venta->bind_param("i", $venta_id);
                $stmt_venta->execute();
                $result_venta = $stmt_venta->get_result();
                $venta = $result_venta->fetch_assoc();
                
                if (!$venta) {
                    throw new Exception('Venta no encontrada');
                }
                
                // 2. Devolver stock de productos
                $query_detalles = "SELECT producto_id, cantidad_subpaquetes FROM venta_detalles WHERE venta_id = ?";
                $stmt_detalles = $conn->prepare($query_detalles);
                $stmt_detalles->bind_param("i", $venta_id);
                $stmt_detalles->execute();
                $detalles = $stmt_detalles->get_result();
                
                while ($detalle = $detalles->fetch_assoc()) {
                    $query_update_inventario = "UPDATE inventario 
                                               SET total_subpaquetes = total_subpaquetes + ?,
                                                   fecha_ultimo_ingreso = CURDATE()
                                               WHERE producto_id = ?";
                    $stmt_update = $conn->prepare($query_update_inventario);
                    $stmt_update->bind_param("ii", $detalle['cantidad_subpaquetes'], $detalle['producto_id']);
                    $stmt_update->execute();
                    
                    // Registrar en historial
                    $query_historial = "INSERT INTO historial_inventario 
                                       (producto_id, tipo_movimiento, diferencia, referencia,
                                        fecha_hora, usuario_id, observaciones)
                                       VALUES (?, 'devolucion', ?, ?, NOW(), ?, ?)";
                    $referencia = "CANCELACION-VENTA-$venta_id";
                    $obs = "Cancelación venta $venta_id - Devolución de {$detalle['cantidad_subpaquetes']} unidades";
                    $stmt_historial = $conn->prepare($query_historial);
                    $stmt_historial->bind_param("iisis", 
                        $detalle['producto_id'],
                        $detalle['cantidad_subpaquetes'],
                        $referencia,
                        $usuario_id,
                        $obs
                    );
                    $stmt_historial->execute();
                }
                
                // 3. Actualizar saldo del cliente si había crédito
                if ($venta['cliente_id'] && $venta['debe'] > 0) {
                    $query_update_cliente = "UPDATE clientes 
                                           SET saldo_actual = saldo_actual - ?,
                                               compras_realizadas = compras_realizadas - 1,
                                               total_comprado = total_comprado - ?
                                           WHERE id = ?";
                    $stmt_update_cliente = $conn->prepare($query_update_cliente);
                    $stmt_update_cliente->bind_param("ddi", $venta['debe'], $venta['total'], $venta['cliente_id']);
                    $stmt_update_cliente->execute();
                }
                
                // 4. Actualizar estado de cuenta por cobrar
                if ($venta['debe'] > 0) {
                    $query_update_cobrar = "UPDATE clientes_cuentas_cobrar 
                                           SET estado = 'cancelada' 
                                           WHERE venta_id = ?";
                    $stmt_cobrar = $conn->prepare($query_update_cobrar);
                    $stmt_cobrar->bind_param("i", $venta_id);
                    $stmt_cobrar->execute();
                }
                
                // 5. Cancelar la venta
                $query_cancelar = "UPDATE ventas 
                                  SET estado = 'cancelada', 
                                      anulado = 1,
                                      motivo_anulacion = ?
                                  WHERE id = ?";
                $stmt_cancelar = $conn->prepare($query_cancelar);
                $stmt_cancelar->bind_param("si", $motivo, $venta_id);
                $stmt_cancelar->execute();
                
                // 6. Registrar movimiento de caja si hubo pago inicial
                if ($venta['pago_inicial'] > 0) {
                    $query_caja_gasto = "INSERT INTO movimientos_caja 
                                        (tipo, categoria, monto, descripcion, referencia_venta,
                                         fecha, hora, usuario_id, observaciones)
                                        VALUES ('gasto', 'otros', ?, ?, ?, CURDATE(), CURTIME(), ?, ?)";
                    
                    $descripcion = "Devolución por cancelación de venta";
                    $obs_caja = "Cancelación venta $venta_id - Motivo: " . substr($motivo, 0, 100);
                    $stmt_caja = $conn->prepare($query_caja_gasto);
                    $stmt_caja->bind_param("dssis", 
                        $venta['pago_inicial'],
                        $descripcion,
                        $venta['codigo_venta'],
                        $usuario_id,
                        $obs_caja
                    );
                    $stmt_caja->execute();
                }
                
                $conn->commit();
                $response = ['success' => true, 'message' => 'Venta cancelada exitosamente'];
                
            } catch (Exception $e) {
                $conn->rollback();
                $response = ['success' => false, 'message' => $e->getMessage()];
            }
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida'];
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}

// Si se solicita HTML directamente
if (isset($_GET['action']) && $_GET['action'] == 'detalle_venta') {
    if (isset($response['html'])) {
        echo $response['html'];
    } else {
        echo '<div class="alert alert-danger">Error al cargar los detalles</div>';
    }
} else {
    echo json_encode($response);
}
?>