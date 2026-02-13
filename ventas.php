<?php
session_start();

require_once 'config.php';
require_once 'funciones.php';
verificarSesion();

$titulo_pagina = "Punto de Venta";
$icono_titulo = "fas fa-cash-register";

// Obtener productos para el buscador
$query_productos = "SELECT 
                        p.id, 
                        p.codigo, 
                        p.nombre_color, 
                        COALESCE(i.total_subpaquetes, 0) as stock,
                        p.precio_menor,
                        p.precio_mayor
                    FROM productos p
                    LEFT JOIN inventario i ON p.id = i.producto_id
                    WHERE p.activo = 1 
                    ORDER BY p.nombre_color ASC";
$result_productos = $conn->query($query_productos);
$productos = [];
while ($row = $result_productos->fetch_assoc()) {
    $productos[] = $row;
}

// Obtener clientes para el selector
$query_clientes = "SELECT id, codigo, nombre, telefono, limite_credito, saldo_actual 
                   FROM clientes 
                   WHERE activo = 1 
                   ORDER BY nombre ASC";
$result_clientes = $conn->query($query_clientes);
$clientes = [];
while ($row = $result_clientes->fetch_assoc()) {
    $clientes[] = $row;
}

// PROCESAR VENTA POR AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'finalizar_venta') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => '', 'venta_id' => null, 'codigo_venta' => ''];
    
    try {
        $conn->begin_transaction();
        
        $vendedor_id = $_SESSION['usuario_id'];
        $cliente_id = !empty($_POST['cliente_id']) ? intval($_POST['cliente_id']) : null;
        $cliente_contado = $_POST['cliente_contado'] ?? 'Cliente Contado';
        $tipo_pago = $_POST['tipo_pago']; // contado, credito, mixto
        $metodo_pago = $_POST['metodo_pago'] ?? 'efectivo';
        $referencia_pago = $_POST['referencia_pago'] ?? null;
        $pago_inicial = floatval($_POST['pago_inicial'] ?? 0);
        $subtotal = floatval($_POST['subtotal']);
        $descuento = floatval($_POST['descuento'] ?? 0);
        $total = floatval($_POST['total']);
        $productos_venta = json_decode($_POST['productos_json'], true);
        
        if (empty($productos_venta)) {
            throw new Exception("No hay productos en el carrito");
        }
        
        // Validar stock
        foreach ($productos_venta as $item) {
            if (!verificarStock($item['id'], $item['cantidad'])) {
                throw new Exception("Stock insuficiente para: " . $item['nombre']);
            }
        }
        
        // Validar crédito
        if ($tipo_pago == 'credito' || $tipo_pago == 'mixto') {
            if (!$cliente_id) {
                throw new Exception("Debe seleccionar un cliente para venta al crédito");
            }
            
            // Verificar límite de crédito
            $query_cliente = "SELECT limite_credito, saldo_actual FROM clientes WHERE id = ?";
            $stmt_cliente = $conn->prepare($query_cliente);
            $stmt_cliente->bind_param("i", $cliente_id);
            $stmt_cliente->execute();
            $cliente_data = $stmt_cliente->get_result()->fetch_assoc();
            
            $deuda_nueva = $total - $pago_inicial;
            $saldo_futuro = $cliente_data['saldo_actual'] + $deuda_nueva;
            
            if ($saldo_futuro > $cliente_data['limite_credito']) {
                throw new Exception("El cliente excede su límite de crédito");
            }
        }
        
        // Determinar tipo de venta (menor/mayor)
        $tipo_venta = 'menor';
        $cantidad_total = array_sum(array_column($productos_venta, 'cantidad'));
        if ($cantidad_total > 5) {
            $tipo_venta = 'mayor';
        }
        
        // Generar código de venta
        $codigo_venta = generarCodigoVenta();
        
        // Calcular saldo pendiente
        $saldo_pendiente = $total - $pago_inicial;
        
        // Determinar estado
        $estado = ($saldo_pendiente <= 0) ? 'pagada' : 'pendiente';
        
        // Insertar venta
        $query_venta = "INSERT INTO ventas (
            codigo_venta, cliente_id, cliente_contado, vendedor_id, 
            tipo_venta, tipo_pago, subtotal, descuento, total, 
            pago_inicial, metodo_pago_inicial, referencia_pago_inicial,
            fecha, hora_inicio, estado, es_venta_rapida
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?)";
        
        $es_venta_rapida = ($cliente_id == null) ? 1 : 0;
        
        $stmt = $conn->prepare($query_venta);
        $stmt->bind_param(
            "sissssddddsssi",
            $codigo_venta,
            $cliente_id,
            $cliente_contado,
            $vendedor_id,
            $tipo_venta,
            $tipo_pago,
            $subtotal,
            $descuento,
            $total,
            $pago_inicial,
            $metodo_pago,
            $referencia_pago,
            $estado,
            $es_venta_rapida
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error al registrar la venta: " . $stmt->error);
        }
        
        $venta_id = $stmt->insert_id;
        
        // Insertar detalles y actualizar inventario
        foreach ($productos_venta as $item) {
            $subtotal_item = $item['precio_unitario'] * $item['cantidad'];
            
            $query_detalle = "INSERT INTO venta_detalles (
                venta_id, producto_id, cantidad_subpaquetes, 
                precio_unitario, subtotal, hora_extraccion, usuario_extraccion
            ) VALUES (?, ?, ?, ?, ?, CURTIME(), ?)";
            
            $stmt_detalle = $conn->prepare($query_detalle);
            $stmt_detalle->bind_param(
                "iiiddi",
                $venta_id,
                $item['id'],
                $item['cantidad'],
                $item['precio_unitario'],
                $subtotal_item,
                $vendedor_id
            );
            
            if (!$stmt_detalle->execute()) {
                throw new Exception("Error al registrar detalle");
            }
        }
        
        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = "Venta registrada correctamente";
        $response['venta_id'] = $venta_id;
        $response['codigo_venta'] = $codigo_venta;
        
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

include 'header.php';
?>

<style>
/* ========================================
   ESTILOS PUNTO DE VENTA MEJORADO
======================================== */
:root {
    --pos-primary: #28a745;
    --pos-primary-dark: #1e7e34;
    --pos-secondary: #17a2b8;
    --pos-danger: #dc3545;
    --pos-warning: #ffc107;
    --pos-info: #17a2b8;
    --pos-dark: #2c3e50;
}

/* Layout principal */
.pos-container {
    display: grid;
    grid-template-columns: 1fr 420px;
    gap: 25px;
    margin-top: 20px;
}

/* Panel de productos */
.products-panel {
    background: white;
    border-radius: 20px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.05);
    overflow: hidden;
}

.products-header {
    background: linear-gradient(135deg, var(--pos-primary), var(--pos-primary-dark));
    color: white;
    padding: 22px 25px;
}

.products-header h3 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Buscador premium */
.search-box {
    padding: 20px 25px;
    background: white;
}

.search-input-group {
    position: relative;
}

.search-input-group i {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    font-size: 18px;
}

.search-input-group input {
    width: 100%;
    padding: 16px 20px 16px 50px;
    border: 2px solid #e9ecef;
    border-radius: 15px;
    font-size: 16px;
    transition: all 0.3s;
}

.search-input-group input:focus {
    outline: none;
    border-color: var(--pos-primary);
    box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.1);
}

/* Filtro de precio */
.price-filter {
    padding: 15px 25px;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
    border-bottom: 1px solid #e9ecef;
}

.btn-price-group {
    display: flex;
    gap: 10px;
    background: white;
    padding: 4px;
    border-radius: 12px;
    border: 1px solid #e9ecef;
}

.btn-price {
    padding: 10px 25px;
    border: none;
    background: transparent;
    border-radius: 10px;
    font-weight: 600;
    color: #6c757d;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-price.active {
    background: var(--pos-primary);
    color: white;
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
}

.btn-price i {
    font-size: 16px;
}

/* Grid de productos */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 18px;
    padding: 25px;
    max-height: 550px;
    overflow-y: auto;
    background: #f8f9fa;
}

.product-card {
    background: white;
    border: 2px solid transparent;
    border-radius: 16px;
    padding: 18px;
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}

.product-card:hover {
    border-color: var(--pos-primary);
    transform: translateY(-5px);
    box-shadow: 0 12px 25px rgba(40, 167, 69, 0.15);
}

.product-card.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f8f9fa;
    border-color: #dee2e6;
}

.product-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: var(--pos-primary);
    color: white;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.product-code {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.product-name {
    font-size: 18px;
    font-weight: 700;
    color: #212529;
    margin-bottom: 12px;
    line-height: 1.2;
}

.product-stock {
    font-size: 13px;
    color: #6c757d;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.product-stock i {
    color: var(--pos-primary);
}

.product-price {
    font-size: 24px;
    font-weight: 800;
    color: var(--pos-primary);
    margin-bottom: 5px;
    line-height: 1;
}

.price-label {
    font-size: 12px;
    color: #6c757d;
    font-weight: normal;
    display: block;
    margin-top: 4px;
}

/* Panel del carrito */
.cart-panel {
    background: white;
    border-radius: 20px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    height: fit-content;
    position: sticky;
    top: 20px;
}

.cart-header {
    background: linear-gradient(135deg, var(--pos-dark), #34495e);
    color: white;
    padding: 22px 25px;
    border-radius: 20px 20px 0 0;
}

.cart-header h3 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Items del carrito */
.cart-items {
    padding: 20px 25px;
    max-height: 350px;
    overflow-y: auto;
    background: white;
}

.cart-item {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    gap: 12px;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid #e9ecef;
}

.cart-item:last-child {
    border-bottom: none;
}

.cart-item-info h6 {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 5px 0;
    color: #212529;
}

.cart-item-info small {
    font-size: 12px;
    color: #6c757d;
    display: block;
}

.cart-item-price {
    font-weight: 700;
    color: var(--pos-primary);
    min-width: 80px;
    text-align: right;
    font-size: 16px;
}

.cart-item-quantity {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f8f9fa;
    padding: 5px;
    border-radius: 10px;
}

.btn-qty {
    width: 32px;
    height: 32px;
    border: none;
    background: white;
    border-radius: 8px;
    color: #495057;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    font-weight: bold;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.btn-qty:hover {
    background: var(--pos-primary);
    color: white;
}

.btn-remove {
    color: var(--pos-danger);
    background: none;
    border: none;
    font-size: 16px;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.2s;
}

.btn-remove:hover {
    background: rgba(220, 53, 69, 0.1);
}

/* Sección de cliente */
.client-section {
    padding: 20px 25px;
    border-top: 1px solid #e9ecef;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
}

.client-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.client-header h6 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: var(--pos-dark);
}

.quick-sale-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
    background: white;
    padding: 8px 15px;
    border-radius: 30px;
    border: 1px solid #dee2e6;
}

/* Totales */
.cart-summary {
    padding: 20px 25px;
    background: white;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    font-size: 15px;
    padding: 5px 0;
}

.summary-row.total {
    font-size: 24px;
    font-weight: 800;
    color: var(--pos-primary);
    margin-top: 15px;
    padding-top: 15px;
    border-top: 2px dashed #dee2e6;
}

.summary-row.saldo {
    font-size: 18px;
    font-weight: 700;
    color: var(--pos-danger);
    background: rgba(220, 53, 69, 0.05);
    padding: 12px;
    border-radius: 10px;
    margin-top: 10px;
}

/* Input de descuento */
.input-descuento {
    width: 80px;
    padding: 6px 12px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    text-align: right;
    font-weight: 600;
    transition: all 0.3s;
}

.input-descuento:focus {
    border-color: var(--pos-primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
}

/* Botón pagar */
.btn-pagar {
    background: linear-gradient(135deg, var(--pos-primary), var(--pos-primary-dark));
    color: white;
    border: none;
    padding: 18px;
    border-radius: 15px;
    font-weight: 700;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    transition: all 0.3s;
    margin: 0 25px 25px;
    border: 2px solid transparent;
}

.btn-pagar:hover:not(:disabled) {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(40, 167, 69, 0.4);
    border-color: white;
}

.btn-pagar:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Modal de pago mejorado */
.payment-modal .modal-content {
    border-radius: 25px;
    border: none;
    overflow: hidden;
}

.payment-modal .modal-header {
    background: linear-gradient(135deg, var(--pos-primary), var(--pos-primary-dark));
    padding: 20px 25px;
}

.payment-methods {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin: 20px 0;
}

.payment-method-card {
    border: 2px solid #e9ecef;
    border-radius: 15px;
    padding: 20px 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    background: white;
}

.payment-method-card:hover,
.payment-method-card.active {
    border-color: var(--pos-primary);
    background: rgba(40, 167, 69, 0.05);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(40, 167, 69, 0.15);
}

.payment-method-card i {
    font-size: 36px;
    color: var(--pos-primary);
    margin-bottom: 12px;
}

.payment-method-card span {
    display: block;
    font-weight: 700;
    font-size: 16px;
    color: #495057;
}

.payment-detail {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 15px;
    margin-top: 20px;
}

.payment-detail .input-group {
    border-radius: 12px;
    overflow: hidden;
    border: 2px solid #e9ecef;
}

.payment-detail .input-group-text {
    background: white;
    border: none;
    font-weight: 600;
    padding: 12px 20px;
}

.payment-detail .form-control {
    border: none;
    padding: 12px 20px;
    font-size: 16px;
}

/* Responsive */
@media (max-width: 992px) {
    .pos-container {
        grid-template-columns: 1fr;
    }
    
    .cart-panel {
        position: static;
        margin-top: 20px;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    }
}

@media (max-width: 576px) {
    .products-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .cart-item {
        grid-template-columns: 1fr auto;
    }
    
    .cart-item-price {
        grid-column: 2;
        grid-row: 1;
    }
}
</style>

<div class="pos-container">
    <!-- PANEL IZQUIERDO - PRODUCTOS -->
    <div class="products-panel">
        <div class="products-header">
            <h3>
                <i class="fas fa-boxes"></i>
                Seleccionar Productos
            </h3>
        </div>
        
        <!-- Buscador -->
        <div class="search-box">
            <div class="search-input-group">
                <i class="fas fa-search"></i>
                <input type="text" id="buscadorProductos" placeholder="Buscar por código o nombre del producto...">
            </div>
        </div>
        
        <!-- Filtro de precio -->
        <div class="price-filter d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="fas fa-tag me-2"></i>Tipo de precio:</span>
            <div class="btn-price-group" id="filtroPrecio">
                <button type="button" class="btn-price active" data-tipo="menor">
                    <i class="fas fa-shopping-bag"></i> Por Menor
                </button>
                <button type="button" class="btn-price" data-tipo="mayor">
                    <i class="fas fa-boxes"></i> Por Mayor
                </button>
            </div>
        </div>
        
        <!-- Grid de productos -->
        <div class="products-grid" id="productosGrid">
            <?php foreach ($productos as $producto): ?>
            <div class="product-card <?php echo ($producto['stock'] <= 0) ? 'disabled' : ''; ?>" 
                 data-id="<?php echo $producto['id']; ?>"
                 data-codigo="<?php echo $producto['codigo']; ?>"
                 data-nombre="<?php echo htmlspecialchars($producto['nombre_color']); ?>"
                 data-precio-menor="<?php echo $producto['precio_menor']; ?>"
                 data-precio-mayor="<?php echo $producto['precio_mayor']; ?>"
                 data-stock="<?php echo $producto['stock']; ?>"
                 data-search="<?php echo strtolower($producto['codigo'] . ' ' . $producto['nombre_color']); ?>">
                
                <?php if($producto['stock'] < 50): ?>
                <span class="product-badge bg-warning text-dark">
                    <i class="fas fa-exclamation-triangle"></i> Stock bajo
                </span>
                <?php endif; ?>
                
                <div class="product-code"><?php echo $producto['codigo']; ?></div>
                <div class="product-name"><?php echo $producto['nombre_color']; ?></div>
                <div class="product-stock">
                    <i class="fas fa-box"></i> Stock: <?php echo number_format($producto['stock']); ?> subp.
                </div>
                <div class="product-price" data-precio-menor="<?php echo $producto['precio_menor']; ?>" data-precio-mayor="<?php echo $producto['precio_mayor']; ?>">
                    <?php echo formatearMoneda($producto['precio_menor']); ?>
                    <span class="price-label">Precio por menor</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- PANEL DERECHO - CARRITO -->
    <div class="cart-panel">
        <div class="cart-header">
            <h3>
                <i class="fas fa-shopping-cart"></i>
                Carrito de Ventas
            </h3>
        </div>
        
        <!-- Items del carrito -->
        <div class="cart-items" id="carritoItems">
            <div class="text-center text-muted py-5">
                <i class="fas fa-shopping-cart fa-4x mb-4" style="opacity: 0.2;"></i>
                <p class="fw-bold">El carrito está vacío</p>
                <small>Selecciona productos para comenzar</small>
            </div>
        </div>
        
        <!-- Selección de cliente -->
        <div class="client-section">
            <div class="client-header">
                <h6><i class="fas fa-user me-2"></i>Cliente</h6>
                <div class="quick-sale-toggle">
                    <input type="checkbox" id="ventaRapida" class="form-check-input m-0">
                    <label for="ventaRapida" class="small mb-0">Venta rápida</label>
                </div>
            </div>
            
            <div id="clienteSelector">
                <select id="selectCliente" class="form-select form-select-lg">
                    <option value="">-- Seleccionar cliente --</option>
                    <?php foreach ($clientes as $cliente): ?>
                    <option value="<?php echo $cliente['id']; ?>" 
                            data-limite="<?php echo $cliente['limite_credito']; ?>"
                            data-saldo="<?php echo $cliente['saldo_actual']; ?>">
                        <?php echo $cliente['nombre']; ?> - <?php echo $cliente['telefono']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="clienteRapido" style="display: none;" class="mt-3">
                <input type="text" id="nombreClienteRapido" class="form-control form-control-lg" 
                       placeholder="Nombre del cliente" value="Cliente Contado">
            </div>
        </div>
        
        <!-- Resumen de venta -->
        <div class="cart-summary">
            <div class="summary-row">
                <span><i class="fas fa-calculator me-2"></i>Subtotal:</span>
                <span class="fw-bold" id="subtotalCarrito">Bs 0.00</span>
            </div>
            <div class="summary-row">
                <span><i class="fas fa-tag me-2"></i>Descuento:</span>
                <span>
                    <div class="input-group input-group-sm">
                        <input type="number" id="descuentoVenta" class="input-descuento" 
                               min="0" max="100" step="1" value="0">
                        <span class="input-group-text">%</span>
                    </div>
                    <span id="descuentoMonto" class="text-danger ms-2">- Bs 0.00</span>
                </span>
            </div>
            <div id="pagoInicialContainer" style="display: none;">
                <div class="summary-row">
                    <span><i class="fas fa-hand-holding-usd me-2"></i>Pago inicial:</span>
                    <span>
                        <input type="number" id="pagoInicialInput" class="input-descuento" 
                               min="0" step="0.01" value="0" placeholder="0.00">
                    </span>
                </div>
            </div>
            <div class="summary-row total">
                <span>TOTAL A PAGAR:</span>
                <span id="totalCarrito">Bs 0.00</span>
            </div>
            <div id="saldoPendienteContainer" style="display: none;">
                <div class="summary-row saldo">
                    <span><i class="fas fa-clock me-2"></i>Saldo pendiente:</span>
                    <span id="saldoPendienteTexto">Bs 0.00</span>
                </div>
            </div>
        </div>
        
        <!-- Botón pagar -->
        <button class="btn-pagar" id="btnPagar" disabled>
            <i class="fas fa-check-circle"></i>
            Pagar y Finalizar Venta
        </button>
    </div>
</div>

<!-- MODAL DE PAGO MEJORADO -->
<div class="modal fade payment-modal" id="modalPago" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white border-0">
                <h5 class="modal-title fs-4">
                    <i class="fas fa-cash-register me-2"></i>
                    Procesar Pago
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- Columna izquierda: Resumen -->
                    <div class="col-md-5">
                        <div class="bg-light p-4 rounded-4 h-100">
                            <h6 class="fw-bold mb-4">
                                <i class="fas fa-receipt me-2"></i>
                                Resumen de venta
                            </h6>
                            
                            <table class="table table-borderless">
                                <tr>
                                    <td class="ps-0">Subtotal:</td>
                                    <td class="text-end pe-0 fw-bold" id="modalSubtotal">Bs 0.00</td>
                                </tr>
                                <tr>
                                    <td class="ps-0">Descuento:</td>
                                    <td class="text-end pe-0 text-danger" id="modalDescuento">- Bs 0.00</td>
                                </tr>
                                <tr class="border-top">
                                    <td class="ps-0 pt-3 fs-5 fw-bold">TOTAL:</td>
                                    <td class="text-end pe-0 pt-3 fs-3 fw-bold text-success" id="modalTotal">Bs 0.00</td>
                                </tr>
                            </table>
                            
                            <hr class="my-4">
                            
                            <div id="modalClienteInfo" class="small">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-user text-success me-2"></i>
                                    <span id="modalClienteNombre">Cliente Contado</span>
                                </div>
                                <div id="modalCreditoInfo" style="display: none;">
                                    <div class="d-flex align-items-center mb-1">
                                        <i class="fas fa-chart-line text-warning me-2"></i>
                                        <span>Límite: <span id="modalLimiteCredito">Bs 0.00</span></span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-clock text-info me-2"></i>
                                        <span>Saldo actual: <span id="modalSaldoActual">Bs 0.00</span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Columna derecha: Métodos de pago -->
                    <div class="col-md-7">
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-credit-card me-2"></i>
                            Seleccionar método de pago
                        </h6>
                        
                        <div class="payment-methods">
                            <div class="payment-method-card active" data-metodo="contado">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>Contado</span>
                                <small class="text-muted d-block mt-1">Pago completo</small>
                            </div>
                            <div class="payment-method-card" data-metodo="credito">
                                <i class="fas fa-credit-card"></i>
                                <span>Crédito</span>
                                <small class="text-muted d-block mt-1">Pago a plazo</small>
                            </div>
                            <div class="payment-method-card" data-metodo="mixto">
                                <i class="fas fa-hand-holding-usd"></i>
                                <span>Mixto</span>
                                <small class="text-muted d-block mt-1">Inicial + crédito</small>
                            </div>
                        </div>
                        
                        <!-- Detalle de pago CONTADO -->
                        <div id="detalleContado" class="payment-detail">
                            <label class="form-label fw-bold">Método de pago</label>
                            <select id="metodoPagoContado" class="form-select mb-3">
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="QR">QR</option>
                            </select>
                            
                            <label class="form-label fw-bold">Monto recibido</label>
                            <div class="input-group mb-3">
                                <span class="input-group-text">Bs</span>
                                <input type="number" id="montoRecibido" class="form-control form-control-lg" 
                                       step="0.01" min="0" placeholder="0.00">
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded-3">
                                <span class="fw-bold">Cambio:</span>
                                <span id="cambioCalculado" class="fs-4 fw-bold text-success">Bs 0.00</span>
                            </div>
                        </div>
                        
                        <!-- Detalle de pago CREDITO -->
                        <div id="detalleCredito" class="payment-detail" style="display: none;">
                            <div class="alert alert-warning mb-4">
                                <div class="d-flex">
                                    <i class="fas fa-exclamation-triangle fs-4 me-3"></i>
                                    <div>
                                        <p class="fw-bold mb-1">Venta a crédito</p>
                                        <p class="small mb-0">No se requiere pago inicial. El cliente pagará después.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <label class="form-label fw-bold">Fecha límite (opcional)</label>
                            <input type="date" id="fechaVencimiento" class="form-control">
                            
                            <div class="mt-4 p-3 bg-white rounded-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total a crédito:</span>
                                    <span class="fw-bold" id="totalCredito">Bs 0.00</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detalle de pago MIXTO -->
                        <div id="detalleMixto" class="payment-detail" style="display: none;">
                            <label class="form-label fw-bold">Método de pago inicial</label>
                            <select id="metodoPagoMixto" class="form-select mb-3">
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="QR"> QR</option>
                            </select>
                            
                            <label class="form-label fw-bold">Pago inicial</label>
                            <div class="input-group mb-3">
                                <span class="input-group-text">Bs</span>
                                <input type="number" id="pagoInicialModal" class="form-control form-control-lg" 
                                       step="0.01" min="0" placeholder="0.00">
                            </div>
                            
                            <div class="bg-light p-3 rounded-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total venta:</span>
                                    <span class="fw-bold" id="totalMixto">Bs 0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Pago inicial:</span>
                                    <span class="fw-bold" id="pagoInicialMostrar">Bs 0.00</span>
                                </div>
                                <div class="d-flex justify-content-between pt-2 border-top">
                                    <span class="fw-bold">Saldo pendiente:</span>
                                    <span class="fw-bold text-danger" id="saldoPendienteModal">Bs 0.00</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Referencia (para todos los métodos) -->
                        <div class="mt-4">
                            <label class="form-label fw-bold">Referencia (opcional)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                <input type="text" id="referenciaPagoGlobal" class="form-control" 
                                       placeholder="N° de transferencia, QR, etc.">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-light border-0 p-4">
                <button type="button" class="btn btn-outline-secondary btn-lg px-5" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-success btn-lg px-5" id="btnConfirmarPago">
                    <i class="fas fa-check-circle me-2"></i>
                    Confirmar Venta
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE CARGA -->
<div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body text-center">
                <div class="spinner-border text-success" style="width: 4rem; height: 4rem;" role="status">
                    <span class="visually-hidden">Procesando...</span>
                </div>
                <p class="mt-3 text-white fw-bold">Procesando venta...</p>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// SISTEMA DE PUNTO DE VENTA - VERSIÓN MEJORADA
// ============================================

let carrito = [];
let precioActivo = 'menor';

// ============================================
// INICIALIZACIÓN
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    inicializarBuscador();
    inicializarFiltroPrecio();
    inicializarProductos();
    inicializarCarritoEventos();
    inicializarClienteSelector();
    inicializarModalPago();
    calcularTotales();
});

// ============================================
// 1. BUSCADOR DE PRODUCTOS
// ============================================
function inicializarBuscador() {
    const buscador = document.getElementById('buscadorProductos');
    if (!buscador) return;
    
    buscador.addEventListener('keyup', function() {
        const termino = this.value.toLowerCase().trim();
        const productos = document.querySelectorAll('.product-card');
        
        productos.forEach(producto => {
            const textoBusqueda = producto.dataset.search || '';
            if (textoBusqueda.includes(termino) || termino === '') {
                producto.style.display = 'block';
            } else {
                producto.style.display = 'none';
            }
        });
    });
}

// ============================================
// 2. FILTRO DE PRECIOS
// ============================================
function inicializarFiltroPrecio() {
    const botonesPrecio = document.querySelectorAll('.btn-price');
    
    botonesPrecio.forEach(boton => {
        boton.addEventListener('click', function() {
            botonesPrecio.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            precioActivo = this.dataset.tipo;
            actualizarPreciosProductos();
            
            // Actualizar precios en el carrito
            carrito.forEach(item => {
                item.precio_unitario = precioActivo === 'menor' ? item.precio_menor : item.precio_mayor;
            });
            actualizarCarrito();
        });
    });
}

function actualizarPreciosProductos() {
    const productos = document.querySelectorAll('.product-card');
    
    productos.forEach(producto => {
        const precioMenor = parseFloat(producto.dataset.precioMenor);
        const precioMayor = parseFloat(producto.dataset.precioMayor);
        const precioSpan = producto.querySelector('.product-price');
        
        if (precioActivo === 'menor') {
            precioSpan.innerHTML = formatearMoneda(precioMenor) + 
                '<span class="price-label">Precio por menor</span>';
        } else {
            precioSpan.innerHTML = formatearMoneda(precioMayor) + 
                '<span class="price-label">Precio por mayor</span>';
        }
    });
}

// ============================================
// 3. PRODUCTOS Y CARRITO
// ============================================
function inicializarProductos() {
    const productos = document.querySelectorAll('.product-card:not(.disabled)');
    
    productos.forEach(producto => {
        producto.addEventListener('click', function(e) {
            const id = this.dataset.id;
            const codigo = this.dataset.codigo;
            const nombre = this.dataset.nombre;
            const stock = parseInt(this.dataset.stock);
            const precioMenor = parseFloat(this.dataset.precioMenor);
            const precioMayor = parseFloat(this.dataset.precioMayor);
            const precioUnitario = precioActivo === 'menor' ? precioMenor : precioMayor;
            
            agregarAlCarrito({
                id: id,
                codigo: codigo,
                nombre: nombre,
                precio_unitario: precioUnitario,
                precio_menor: precioMenor,
                precio_mayor: precioMayor,
                cantidad: 1,
                stock: stock
            });
        });
    });
}

function agregarAlCarrito(producto) {
    const existeIndex = carrito.findIndex(item => item.id === producto.id);
    
    if (existeIndex !== -1) {
        if (carrito[existeIndex].cantidad + 1 > carrito[existeIndex].stock) {
            mostrarAlerta('Stock insuficiente', 'warning');
            return;
        }
        carrito[existeIndex].cantidad += 1;
        carrito[existeIndex].precio_unitario = producto.precio_unitario;
    } else {
        carrito.push(producto);
    }
    
    actualizarCarrito();
    habilitarBotonPagar();
    mostrarAlerta('Producto agregado al carrito', 'success');
}

function actualizarCarrito() {
    const contenedor = document.getElementById('carritoItems');
    
    if (carrito.length === 0) {
        contenedor.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="fas fa-shopping-cart fa-4x mb-4" style="opacity: 0.2;"></i>
                <p class="fw-bold">El carrito está vacío</p>
                <small>Selecciona productos para comenzar</small>
            </div>
        `;
        document.getElementById('btnPagar').disabled = true;
        return;
    }
    
    let html = '';
    carrito.forEach((item, index) => {
        const subtotal = item.precio_unitario * item.cantidad;
        html += `
            <div class="cart-item" data-index="${index}">
                <div class="cart-item-info">
                    <h6>${item.nombre}</h6>
                    <small>${item.codigo} | Stock: ${item.stock}</small>
                    <small class="text-success">P/U: ${formatearMoneda(item.precio_unitario)}</small>
                </div>
                <div class="cart-item-price">
                    ${formatearMoneda(subtotal)}
                </div>
                <div class="cart-item-quantity">
                    <button class="btn-qty" onclick="disminuirCantidad(${index})">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span style="min-width: 30px; text-align: center; font-weight: 600;">${item.cantidad}</span>
                    <button class="btn-qty" onclick="aumentarCantidad(${index})">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <button class="btn-remove" onclick="eliminarDelCarrito(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
    });
    
    contenedor.innerHTML = html;
    calcularTotales();
}

function aumentarCantidad(index) {
    if (carrito[index].cantidad < carrito[index].stock) {
        carrito[index].cantidad += 1;
        actualizarCarrito();
    } else {
        mostrarAlerta('Stock máximo alcanzado', 'warning');
    }
}

function disminuirCantidad(index) {
    if (carrito[index].cantidad > 1) {
        carrito[index].cantidad -= 1;
    } else {
        eliminarDelCarrito(index);
    }
    actualizarCarrito();
}

function eliminarDelCarrito(index) {
    carrito.splice(index, 1);
    actualizarCarrito();
    if (carrito.length === 0) {
        document.getElementById('btnPagar').disabled = true;
    }
    mostrarAlerta('Producto eliminado', 'info');
}

function vaciarCarrito() {
    carrito = [];
    actualizarCarrito();
}

// ============================================
// 4. CÁLCULOS DE TOTALES
// ============================================
function calcularTotales() {
    // Calcular subtotal
    let subtotal = 0;
    carrito.forEach(item => {
        subtotal += item.precio_unitario * item.cantidad;
    });
    
    // Calcular descuento
    const descuentoPorcentaje = parseFloat(document.getElementById('descuentoVenta').value) || 0;
    const descuentoMonto = subtotal * (descuentoPorcentaje / 100);
    const total = subtotal - descuentoMonto;
    
    // Obtener pago inicial
    let pagoInicial = 0;
    const tipoPagoActivo = document.querySelector('.payment-method-card.active')?.dataset.metodo || 'contado';
    
    if (tipoPagoActivo === 'mixto') {
        pagoInicial = parseFloat(document.getElementById('pagoInicialModal')?.value) || 0;
        if (pagoInicial > total) pagoInicial = total;
    } else if (tipoPagoActivo === 'contado') {
        pagoInicial = total;
    }
    
    const saldoPendiente = total - pagoInicial;
    
    // Actualizar UI
    document.getElementById('subtotalCarrito').textContent = formatearMoneda(subtotal);
    document.getElementById('descuentoMonto').textContent = `- ${formatearMoneda(descuentoMonto)}`;
    document.getElementById('totalCarrito').textContent = formatearMoneda(total);
    
    // Actualizar saldo pendiente si es visible
    if (document.getElementById('saldoPendienteContainer').style.display !== 'none') {
        document.getElementById('saldoPendienteTexto').textContent = formatearMoneda(saldoPendiente);
    }
    
    // Actualizar modal si está abierto
    if (document.getElementById('modalSubtotal')) {
        document.getElementById('modalSubtotal').textContent = formatearMoneda(subtotal);
        document.getElementById('modalDescuento').textContent = `- ${formatearMoneda(descuentoMonto)}`;
        document.getElementById('modalTotal').textContent = formatearMoneda(total);
        
        if (document.getElementById('totalCredito')) {
            document.getElementById('totalCredito').textContent = formatearMoneda(total);
        }
        if (document.getElementById('totalMixto')) {
            document.getElementById('totalMixto').textContent = formatearMoneda(total);
        }
        if (document.getElementById('saldoPendienteModal')) {
            document.getElementById('saldoPendienteModal').textContent = formatearMoneda(saldoPendiente);
        }
    }
}

// Evento para descuento
document.getElementById('descuentoVenta')?.addEventListener('input', function() {
    let valor = parseInt(this.value) || 0;
    if (valor < 0) valor = 0;
    if (valor > 100) valor = 100;
    this.value = valor;
    calcularTotales();
});

// ============================================
// 5. SELECCIÓN DE CLIENTE
// ============================================
function inicializarClienteSelector() {
    const ventaRapida = document.getElementById('ventaRapida');
    const clienteSelector = document.getElementById('clienteSelector');
    const clienteRapido = document.getElementById('clienteRapido');
    const selectCliente = document.getElementById('selectCliente');
    
    ventaRapida.addEventListener('change', function() {
        if (this.checked) {
            clienteSelector.style.display = 'none';
            clienteRapido.style.display = 'block';
        } else {
            clienteSelector.style.display = 'block';
            clienteRapido.style.display = 'none';
        }
    });
    
    selectCliente.addEventListener('change', function() {
        if (this.value) {
            const selected = this.options[this.selectedIndex];
            const limite = selected.dataset.limite || 0;
            const saldo = selected.dataset.saldo || 0;
            const nombre = selected.text.split(' - ')[0];
            
            document.getElementById('modalClienteNombre').textContent = nombre;
            document.getElementById('modalLimiteCredito').textContent = formatearMoneda(parseFloat(limite));
            document.getElementById('modalSaldoActual').textContent = formatearMoneda(parseFloat(saldo));
        }
    });
}

// ============================================
// 6. MODAL DE PAGO
// ============================================
function inicializarModalPago() {
    const btnPagar = document.getElementById('btnPagar');
    const modalPago = new bootstrap.Modal(document.getElementById('modalPago'));
    
    // Botón pagar
    btnPagar.addEventListener('click', function() {
        if (carrito.length === 0) {
            mostrarAlerta('No hay productos en el carrito', 'warning');
            return;
        }
        
        // Validar cliente para crédito/mixto
        const ventaRapida = document.getElementById('ventaRapida').checked;
        const selectCliente = document.getElementById('selectCliente');
        
        if (!ventaRapida && !selectCliente.value) {
            mostrarAlerta('Debe seleccionar un cliente', 'warning');
            return;
        }
        
        // Preparar modal
        prepararModalPago();
        modalPago.show();
    });
    
    // Métodos de pago
    const metodosPago = document.querySelectorAll('.payment-method-card');
    metodosPago.forEach(metodo => {
        metodo.addEventListener('click', function() {
            metodosPago.forEach(m => m.classList.remove('active'));
            this.classList.add('active');
            cambiarMetodoPago(this.dataset.metodo);
        });
    });
    
    // Calcular cambio
    document.getElementById('montoRecibido')?.addEventListener('input', calcularCambio);
    
    // Calcular saldo pendiente en mixto
    document.getElementById('pagoInicialModal')?.addEventListener('input', function() {
        const total = parseFloat(document.getElementById('modalTotal').textContent.replace(/[^0-9.-]/g, '')) || 0;
        let pago = parseFloat(this.value) || 0;
        
        if (pago > total) {
            pago = total;
            this.value = pago.toFixed(2);
        }
        
        document.getElementById('pagoInicialMostrar').textContent = formatearMoneda(pago);
        const saldo = total - pago;
        document.getElementById('saldoPendienteModal').textContent = formatearMoneda(saldo);
        document.getElementById('saldoPendienteTexto').textContent = formatearMoneda(saldo);
    });
}

function prepararModalPago() {
    const total = parseFloat(document.getElementById('totalCarrito').textContent.replace(/[^0-9.-]/g, '')) || 0;
    
    // Resetear campos
    document.getElementById('montoRecibido').value = total.toFixed(2);
    document.getElementById('cambioCalculado').textContent = 'Bs 0.00';
    document.getElementById('pagoInicialModal').value = '';
    document.getElementById('referenciaPagoGlobal').value = '';
    document.getElementById('fechaVencimiento').value = '';
    
    // Actualizar información del cliente
    const ventaRapida = document.getElementById('ventaRapida').checked;
    if (ventaRapida) {
        const nombreCliente = document.getElementById('nombreClienteRapido').value || 'Cliente Contado';
        document.getElementById('modalClienteNombre').textContent = nombreCliente;
        document.getElementById('modalCreditoInfo').style.display = 'none';
    } else {
        const selectCliente = document.getElementById('selectCliente');
        if (selectCliente.value) {
            const selected = selectCliente.options[selectCliente.selectedIndex];
            document.getElementById('modalClienteNombre').textContent = selected.text.split(' - ')[0];
            document.getElementById('modalCreditoInfo').style.display = 'block';
        }
    }
    
    // Establecer método por defecto
    cambiarMetodoPago('contado');
}

function cambiarMetodoPago(metodo) {
    // Ocultar todos los detalles
    document.getElementById('detalleContado').style.display = 'none';
    document.getElementById('detalleCredito').style.display = 'none';
    document.getElementById('detalleMixto').style.display = 'none';
    document.getElementById('pagoInicialContainer').style.display = 'none';
    document.getElementById('saldoPendienteContainer').style.display = 'none';
    
    // Mostrar según método
    if (metodo === 'contado') {
        document.getElementById('detalleContado').style.display = 'block';
        document.getElementById('pagoInicialContainer').style.display = 'none';
        document.getElementById('saldoPendienteContainer').style.display = 'none';
    } else if (metodo === 'credito') {
        document.getElementById('detalleCredito').style.display = 'block';
        document.getElementById('pagoInicialContainer').style.display = 'none';
        document.getElementById('saldoPendienteContainer').style.display = 'block';
        document.getElementById('saldoPendienteTexto').textContent = document.getElementById('modalTotal').textContent;
    } else if (metodo === 'mixto') {
        document.getElementById('detalleMixto').style.display = 'block';
        document.getElementById('pagoInicialContainer').style.display = 'block';
        document.getElementById('saldoPendienteContainer').style.display = 'block';
        
        const total = parseFloat(document.getElementById('modalTotal').textContent.replace(/[^0-9.-]/g, '')) || 0;
        document.getElementById('saldoPendienteTexto').textContent = formatearMoneda(total);
    }
    
    calcularTotales();
}

function calcularCambio() {
    const totalText = document.getElementById('modalTotal').textContent;
    const total = parseFloat(totalText.replace(/[^0-9.-]/g, '')) || 0;
    const monto = parseFloat(document.getElementById('montoRecibido').value) || 0;
    const cambio = monto - total;
    
    if (cambio >= 0) {
        document.getElementById('cambioCalculado').textContent = formatearMoneda(cambio);
        document.getElementById('cambioCalculado').className = 'fs-4 fw-bold text-success';
    } else {
        document.getElementById('cambioCalculado').textContent = 'Bs 0.00';
    }
}

// ============================================
// 7. FINALIZAR VENTA (AJAX)
// ============================================
function finalizarVenta() {
    if (carrito.length === 0) {
        mostrarAlerta('No hay productos en el carrito', 'warning');
        return;
    }
    
    // Obtener método de pago activo
    const metodoActivo = document.querySelector('.payment-method-card.active');
    if (!metodoActivo) {
        mostrarAlerta('Seleccione un método de pago', 'warning');
        return;
    }
    
    const tipoPago = metodoActivo.dataset.metodo;
    
    // Validar según método de pago
    if (tipoPago === 'credito' || tipoPago === 'mixto') {
        const ventaRapida = document.getElementById('ventaRapida').checked;
        const selectCliente = document.getElementById('selectCliente');
        
        if (!ventaRapida && !selectCliente.value) {
            mostrarAlerta('Debe seleccionar un cliente para venta al crédito', 'warning');
            return;
        }
    }
    
    // Obtener datos de pago
    let pagoInicial = 0;
    let metodoPago = 'efectivo';
    let referencia = document.getElementById('referenciaPagoGlobal').value || '';
    
    if (tipoPago === 'contado') {
        pagoInicial = parseFloat(document.getElementById('modalTotal').textContent.replace(/[^0-9.-]/g, '')) || 0;
        metodoPago = document.getElementById('metodoPagoContado').value;
    } else if (tipoPago === 'credito') {
        pagoInicial = 0;
        metodoPago = 'efectivo';
    } else if (tipoPago === 'mixto') {
        pagoInicial = parseFloat(document.getElementById('pagoInicialModal').value) || 0;
        metodoPago = document.getElementById('metodoPagoMixto').value;
        
        const total = parseFloat(document.getElementById('modalTotal').textContent.replace(/[^0-9.-]/g, '')) || 0;
        if (pagoInicial <= 0) {
            mostrarAlerta('El pago inicial debe ser mayor a 0', 'warning');
            return;
        }
        if (pagoInicial >= total) {
            mostrarAlerta('Para pago completo seleccione "Contado"', 'warning');
            return;
        }
    }
    
    // Obtener datos del cliente
    const ventaRapida = document.getElementById('ventaRapida').checked;
    let clienteId = null;
    let clienteContado = 'Cliente Contado';
    
    if (ventaRapida) {
        clienteContado = document.getElementById('nombreClienteRapido').value || 'Cliente Contado';
    } else {
        const selectCliente = document.getElementById('selectCliente');
        clienteId = selectCliente.value || null;
        if (clienteId) {
            const selectedText = selectCliente.options[selectCliente.selectedIndex].text;
            clienteContado = selectedText.split(' - ')[0];
        }
    }
    
    // Calcular totales
    let subtotal = 0;
    carrito.forEach(item => {
        subtotal += item.precio_unitario * item.cantidad;
    });
    
    const descuentoPorcentaje = parseFloat(document.getElementById('descuentoVenta').value) || 0;
    const descuentoMonto = subtotal * (descuentoPorcentaje / 100);
    const total = subtotal - descuentoMonto;
    
    // Preparar productos
    const productosVenta = carrito.map(item => ({
        id: parseInt(item.id),
        codigo: item.codigo,
        nombre: item.nombre,
        cantidad: parseInt(item.cantidad),
        precio_unitario: parseFloat(item.precio_unitario),
        subtotal: parseFloat(item.precio_unitario * item.cantidad)
    }));
    
    // Mostrar loading
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    // Enviar datos
    const formData = new FormData();
    formData.append('accion', 'finalizar_venta');
    formData.append('cliente_id', clienteId || '');
    formData.append('cliente_contado', clienteContado);
    formData.append('tipo_pago', tipoPago);
    formData.append('metodo_pago', metodoPago);
    formData.append('referencia_pago', referencia);
    formData.append('pago_inicial', pagoInicial);
    formData.append('subtotal', subtotal);
    formData.append('descuento', descuentoMonto);
    formData.append('total', total);
    formData.append('productos_json', JSON.stringify(productosVenta));
    
    fetch('ventas.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        loadingModal.hide();
        
        if (data.success) {
            // Cerrar modal de pago
            bootstrap.Modal.getInstance(document.getElementById('modalPago')).hide();
            
            // Mostrar mensaje de éxito
            mostrarAlerta(`Venta ${data.codigo_venta} registrada correctamente`, 'success');
            
            // Limpiar carrito y formulario
            vaciarCarrito();
            document.getElementById('descuentoVenta').value = '0';
            document.getElementById('ventaRapida').checked = false;
            document.getElementById('selectCliente').value = '';
            document.getElementById('clienteSelector').style.display = 'block';
            document.getElementById('clienteRapido').style.display = 'none';
            
            // Redirigir al recibo después de 1 segundo
            setTimeout(() => {
                window.location.href = `imprimir_recibo.php?venta_id=${data.venta_id}`;
            }, 1000);
        } else {
            mostrarAlerta(data.message, 'danger');
        }
    })
    .catch(error => {
        loadingModal.hide();
        console.error('Error:', error);
        mostrarAlerta('Error al procesar la venta', 'danger');
    });
}

// Asignar evento al botón confirmar
document.getElementById('btnConfirmarPago')?.addEventListener('click', finalizarVenta);

// ============================================
// 8. FUNCIONES UTILITARIAS
// ============================================
function formatearMoneda(valor) {
    return 'Bs ' + parseFloat(valor).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function mostrarAlerta(mensaje, tipo = 'success') {
    const alerta = document.createElement('div');
    alerta.className = `alert alert-${tipo} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-4`;
    alerta.style.zIndex = '9999';
    alerta.style.minWidth = '400px';
    alerta.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
    alerta.style.borderRadius = '12px';
    alerta.style.border = 'none';
    alerta.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas ${tipo === 'success' ? 'fa-check-circle' : tipo === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'} me-3 fs-4"></i>
            <div class="flex-grow-1">
                <strong>${mensaje}</strong>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.appendChild(alerta);
    setTimeout(() => alerta.remove(), 4000);
}

function habilitarBotonPagar() {
    document.getElementById('btnPagar').disabled = false;
}

// Eventos adicionales
function inicializarCarritoEventos() {
    // Descuento
    document.getElementById('descuentoVenta')?.addEventListener('input', calcularTotales);
    
    // Pago inicial
    document.getElementById('pagoInicialInput')?.addEventListener('input', function() {
        const total = parseFloat(document.getElementById('totalCarrito').textContent.replace(/[^0-9.-]/g, '')) || 0;
        let pago = parseFloat(this.value) || 0;
        
        if (pago > total) {
            pago = total;
            this.value = pago.toFixed(2);
        }
        
        const saldo = total - pago;
        document.getElementById('saldoPendienteTexto').textContent = formatearMoneda(saldo);
    });
}
</script>

<?php include 'footer.php'; ?>