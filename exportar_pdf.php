<?php


session_start();
require_once 'config.php';
require_once 'funciones.php';

// Verificar sesión
verificarSesion();

$type = $_GET['type'] ?? 'recibo';
$id = $_GET['id'] ?? 0;

if ($type == 'recibo') {
    generarReciboPDF($id);
} else {
    die('Tipo no soportado');
}

// ============================================
// GENERAR RECIBO EN FORMATO PDF (HTML)
// ============================================
function generarReciboPDF($venta_id) {
    global $conn;
    
    $venta_id = (int)$venta_id;
    if ($venta_id <= 0) {
        die('ID de venta no válido');
    }
    
    // Obtener datos de la venta
    $query = "SELECT 
                v.*, 
                c.nombre as cliente_nombre, 
                c.telefono,
                c.codigo as cliente_codigo,
                u.nombre as vendedor
              FROM ventas v 
              LEFT JOIN clientes c ON v.cliente_id = c.id 
              JOIN usuarios u ON v.vendedor_id = u.id 
              WHERE v.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $venta_id);
    $stmt->execute();
    $venta = $stmt->get_result()->fetch_assoc();
    
    if (!$venta) {
        die('Venta no encontrada');
    }
    
    // Obtener detalles
    $query_detalles = "SELECT 
                        vd.*, 
                        p.codigo, 
                        p.nombre_color
                      FROM venta_detalles vd 
                      JOIN productos p ON vd.producto_id = p.id 
                      WHERE vd.venta_id = ? 
                      ORDER BY vd.id";
    
    $stmt = $conn->prepare($query_detalles);
    $stmt->bind_param("i", $venta_id);
    $stmt->execute();
    $detalles = $stmt->get_result();
    
    $detalles_array = [];
    $items = 0;
    while ($d = $detalles->fetch_assoc()) {
        $d['subtotal_calc'] = $d['cantidad_subpaquetes'] * $d['precio_unitario'];
        $detalles_array[] = $d;
        $items += $d['cantidad_subpaquetes'];
    }
    
    $empresa_nombre = defined('EMPRESA_NOMBRE') ? EMPRESA_NOMBRE : 'TIENDA DE LANAS';
    $empresa_direccion = defined('DIRECCION_EMPRESA') ? DIRECCION_EMPRESA : '';
    $empresa_telefono = defined('TELEFONO_EMPRESA') ? TELEFONO_EMPRESA : '';
    
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Recibo <?php echo $venta['codigo_venta']; ?></title>
        <style>
            body {
                font-family: 'Courier New', Courier, monospace;
                margin: 20px;
                padding: 0;
                background: white;
            }
            .recibo {
                max-width: 80mm;
                margin: 0 auto;
                padding: 15px;
                border: 1px solid #ddd;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            .header {
                text-align: center;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #000;
            }
            .header h1 {
                font-size: 20px;
                font-weight: bold;
                margin: 0 0 5px 0;
                text-transform: uppercase;
            }
            .header p {
                margin: 2px 0;
                font-size: 11px;
            }
            .titulo {
                text-align: center;
                font-size: 16px;
                font-weight: bold;
                margin: 15px 0;
                padding: 8px;
                background: #f5f5f5;
            }
            .info {
                margin-bottom: 15px;
                padding: 10px;
                background: #f9f9f9;
                font-size: 12px;
            }
            .info-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 5px;
            }
            .info-label {
                font-weight: bold;
                width: 35%;
            }
            .info-value {
                width: 65%;
                text-align: right;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
                font-size: 11px;
            }
            th {
                background: #f2f2f2;
                padding: 8px 5px;
                border-top: 2px solid #000;
                border-bottom: 2px solid #000;
            }
            td {
                padding: 6px 5px;
                border-bottom: 1px dashed #ccc;
            }
            .total-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 5px;
                font-size: 12px;
            }
            .total-final {
                font-size: 16px;
                font-weight: bold;
                margin-top: 15px;
                padding-top: 10px;
                border-top: 2px solid #000;
            }
            .pagos {
                margin-top: 15px;
                padding: 10px;
                background: #f5f5f5;
            }
            .footer {
                text-align: center;
                margin-top: 20px;
                padding-top: 15px;
                border-top: 2px dashed #000;
                font-size: 11px;
            }
            .gracias {
                font-size: 14px;
                font-weight: bold;
                color: #28a745;
                margin-bottom: 5px;
            }
            @media print {
                body { background: white; }
                .recibo { box-shadow: none; border: none; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="recibo">
            <!-- ENCABEZADO -->
            <div class="header">
                <h1><?php echo htmlspecialchars($empresa_nombre); ?></h1>
                <?php if (!empty($empresa_direccion)): ?>
                <p><?php echo htmlspecialchars($empresa_direccion); ?></p>
                <?php endif; ?>
                <?php if (!empty($empresa_telefono)): ?>
                <p>Tel: <?php echo htmlspecialchars($empresa_telefono); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- TÍTULO -->
            <div class="titulo">
                RECIBO DE VENTA
            </div>
            <div style="text-align: center; font-size: 14px; font-weight: bold; margin-bottom: 15px;">
                <?php echo $venta['codigo_venta']; ?>
            </div>
            
            <!-- INFORMACIÓN -->
            <div class="info">
                <div class="info-row">
                    <span class="info-label">FECHA:</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($venta['fecha'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">HORA:</span>
                    <span class="info-value"><?php echo date('H:i:s', strtotime($venta['hora_inicio'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">CLIENTE:</span>
                    <span class="info-value"><?php echo htmlspecialchars($venta['cliente_nombre'] ?: $venta['cliente_contado']); ?></span>
                </div>
                <?php if (!empty($venta['vendedor'])): ?>
                <div class="info-row">
                    <span class="info-label">VENDEDOR:</span>
                    <span class="info-value"><?php echo htmlspecialchars($venta['vendedor']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- PRODUCTOS -->
            <table>
                <thead>
                    <tr>
                        <th>COD</th>
                        <th>PRODUCTO</th>
                        <th>CANT</th>
                        <th>P/U</th>
                        <th>SUBT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles_array as $detalle): ?>
                    <tr>
                        <td><strong><?php echo $detalle['codigo']; ?></strong></td>
                        <td><?php echo htmlspecialchars($detalle['nombre_color']); ?></td>
                        <td align="center"><?php echo $detalle['cantidad_subpaquetes']; ?></td>
                        <td align="right"><?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                        <td align="right"><?php echo number_format($detalle['subtotal_calc'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- TOTALES -->
            <div style="margin-top: 15px;">
                <div class="total-row">
                    <span>SUBTOTAL:</span>
                    <span><strong>Bs <?php echo number_format($venta['subtotal'], 2, ',', '.'); ?></strong></span>
                </div>
                
                <?php if ($venta['descuento'] > 0): ?>
                <div class="total-row">
                    <span>DESCUENTO:</span>
                    <span style="color: #dc3545;">- Bs <?php echo number_format($venta['descuento'], 2, ',', '.'); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="total-row total-final">
                    <span>TOTAL:</span>
                    <span>Bs <?php echo number_format($venta['total'], 2, ',', '.'); ?></span>
                </div>
            </div>
            
            <!-- PAGOS -->
            <?php if ($venta['pago_inicial'] > 0): ?>
            <div class="pagos">
                <div class="total-row">
                    <span>PAGO INICIAL:</span>
                    <span>Bs <?php echo number_format($venta['pago_inicial'], 2, ',', '.'); ?></span>
                </div>
                <div class="total-row">
                    <span>MÉTODO:</span>
                    <span><?php echo strtoupper($venta['metodo_pago_inicial']); ?></span>
                </div>
                <?php if ($venta['debe'] > 0): ?>
                <div class="total-row" style="font-weight: bold; color: #dc3545;">
                    <span>SALDO PENDIENTE:</span>
                    <span>Bs <?php echo number_format($venta['debe'], 2, ',', '.'); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- PIE DE PÁGINA -->
            <div class="footer">
                <div class="gracias">¡GRACIAS POR SU COMPRA!</div>
                <div>VUELVA PRONTO</div>
                <div style="margin-top: 10px;"><?php echo $items; ?> artículo(s)</div>
                <div style="margin-top: 10px; font-size: 10px; color: #666;">
                    Recibo generado: <?php echo date('d/m/Y H:i:s'); ?>
                </div>
            </div>
        </div>
        
        <div class="no-print" style="text-align: center; margin-top: 30px;">
            <button onclick="window.print();" style="padding: 12px 30px; background: #28a745; color: white; border: none; border-radius: 5px; font-size: 14px; font-weight: bold; cursor: pointer;">
                🖨️ IMPRIMIR RECIBO
            </button>
            <button onclick="window.close();" style="padding: 12px 30px; background: #6c757d; color: white; border: none; border-radius: 5px; font-size: 14px; font-weight: bold; cursor: pointer; margin-left: 10px;">
                ✖️ CERRAR
            </button>
        </div>
        
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>