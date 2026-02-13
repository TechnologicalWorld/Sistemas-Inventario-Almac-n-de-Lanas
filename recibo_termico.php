<?php

session_start();
require_once 'config.php';
require_once 'funciones.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$venta_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$venta_id) {
    die('ID de venta no especificado');
}

// Obtener datos de la venta - CORREGIDO: u.apellido NO EXISTE
$query = "SELECT 
            v.*, 
            COALESCE(c.nombre, v.cliente_contado, 'VENTA RÁPIDA') as cliente_nombre,
            u.nombre as vendedor
          FROM ventas v 
          LEFT JOIN clientes c ON v.cliente_id = c.id 
          JOIN usuarios u ON v.vendedor_id = u.id 
          WHERE v.id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die('Error en la consulta: ' . $conn->error);
}

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
                    p.nombre_color,
                    p.descripcion
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

// Configuración de empresa desde constantes
$empresa_nombre = defined('EMPRESA_NOMBRE') ? EMPRESA_NOMBRE : 'TIENDA DE LANAS';
$empresa_direccion = defined('DIRECCION_EMPRESA') ? DIRECCION_EMPRESA : '';
$empresa_telefono = defined('TELEFONO_EMPRESA') ? TELEFONO_EMPRESA : '';
$empresa_nit = defined('NIT_EMPRESA') ? NIT_EMPRESA : '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo <?php echo htmlspecialchars($venta['codigo_venta']); ?></title>
    <style>
        /* ======================================== */
        /* ESTILOS PARA IMPRESORA TÉRMICA - 80mm   */
        /* BLANCO Y NEGRO - SIN MÁRGENES           */
        /* ======================================== */
        
        @page {
            size: 80mm auto;
            margin: 0;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 3mm 2mm;
            }
            
            .no-print, .botones-impresion {
                display: none !important;
            }
        }
        
        body {
            font-family: 'Courier New', Courier, monospace;
            width: 72mm;
            margin: 0 auto;
            padding: 5px 3px;
            font-size: 12px;
            line-height: 1.3;
            color: #000000;
            background: #ffffff;
        }
        
        .header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px dashed #000000;
        }
        
        .header h1 {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 3px 0;
            padding: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .header p {
            margin: 2px 0;
            font-size: 11px;
        }
        
        .titulo {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            margin: 10px 0;
            padding: 5px 0;
            border-bottom: 1px solid #000000;
            border-top: 1px solid #000000;
            text-transform: uppercase;
        }
        
        .codigo-recibo {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin: 10px 0;
            padding: 8px 5px;
            background: #f0f0f0;
            border-radius: 0;
            letter-spacing: 2px;
        }
        
        .info {
            margin: 10px 0;
            padding: 5px 0;
            border-bottom: 1px dashed #000000;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
        }
        
        .info-label {
            font-weight: bold;
            width: 35%;
        }
        
        .info-value {
            width: 65%;
            text-align: right;
        }
        
        .productos {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 11px;
        }
        
        .productos th {
            font-weight: bold;
            text-align: center;
            border-bottom: 1px solid #000000;
            border-top: 1px solid #000000;
            padding: 5px 0;
            font-size: 11px;
        }
        
        .productos td {
            padding: 4px 0;
            border-bottom: 1px dotted #ccc;
            vertical-align: top;
        }
        
        .productos .codigo {
            font-weight: bold;
            white-space: nowrap;
        }
        
        .productos .descripcion {
            max-width: 120px;
            word-wrap: break-word;
        }
        
        .productos .cantidad {
            text-align: center;
            white-space: nowrap;
        }
        
        .productos .precio,
        .productos .subtotal {
            text-align: right;
            white-space: nowrap;
        }
        
        .totales {
            width: 100%;
            margin: 15px 0;
            border-top: 2px solid #000000;
            border-bottom: 2px solid #000000;
            padding: 8px 0;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }
        
        .total-final {
            font-size: 15px;
            font-weight: bold;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #000000;
        }
        
        .pagos {
            margin: 10px 0;
            padding: 8px 0;
            border-top: 1px dashed #000000;
            border-bottom: 1px dashed #000000;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px dashed #000000;
            font-size: 11px;
        }
        
        .gracias {
            font-size: 14px;
            font-weight: bold;
            margin: 8px 0;
            text-transform: uppercase;
        }
        
        .botones-impresion {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .corte {
            page-break-after: always;
        }
        
        .firma {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #000000;
            display: flex;
            justify-content: space-between;
        }
        
        .firma div {
            text-align: center;
            width: 45%;
        }
        
        .firma-linea {
            margin-top: 25px;
            border-bottom: 1px solid #000000;
            width: 100%;
        }
    </style>
</head>
<body>
    <!-- BOTONES DE ACCIÓN (SOLO VISIBLES EN PANTALLA) -->
    <div class="botones-impresion no-print">
        <button onclick="window.print()" class="btn btn-primary">
            🖨️ Imprimir Recibo
        </button>
        <button onclick="window.close()" class="btn btn-secondary">
            ✖️ Cerrar
        </button>
        <a href="ventas.php" class="btn btn-success">
            ➕ Nueva Venta
        </a>
    </div>

    <!-- ENCABEZADO EMPRESA -->
    <div class="header">
        <h1><?php echo htmlspecialchars($empresa_nombre); ?></h1>
        <?php if (!empty($empresa_nit)): ?>
        <p>NIT: <?php echo htmlspecialchars($empresa_nit); ?></p>
        <?php endif; ?>
        <?php if (!empty($empresa_direccion)): ?>
        <p><?php echo htmlspecialchars($empresa_direccion); ?></p>
        <?php endif; ?>
        <?php if (!empty($empresa_telefono)): ?>
        <p>TEL: <?php echo htmlspecialchars($empresa_telefono); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- TÍTULO DEL DOCUMENTO -->
    <div class="titulo">
        RECIBO DE VENTA
    </div>
    
    <!-- CÓDIGO DE RECIBO DESTACADO -->
    <div class="codigo-recibo">
        <?php echo htmlspecialchars($venta['codigo_venta']); ?>
    </div>
    
    <!-- INFORMACIÓN DE LA VENTA -->
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
            <span class="info-value"><?php echo htmlspecialchars($venta['cliente_nombre']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">VENDEDOR:</span>
            <span class="info-value"><?php echo htmlspecialchars($venta['vendedor']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">TIPO VENTA:</span>
            <span class="info-value"><?php echo strtoupper($venta['tipo_venta'] ?? 'MENOR'); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">TIPO PAGO:</span>
            <span class="info-value"><?php echo strtoupper($venta['tipo_pago']); ?></span>
        </div>
    </div>
    
    <!-- DETALLE DE PRODUCTOS -->
    <table class="productos">
        <thead>
            <tr>
                <th width="20%">CÓDIGO</th>
                <th width="35%">PRODUCTO</th>
                <th width="15%">CANT</th>
                <th width="15%">P/U</th>
                <th width="15%">SUBT</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($detalles_array as $detalle): ?>
            <tr>
                <td class="codigo"><?php echo htmlspecialchars($detalle['codigo']); ?></td>
                <td class="descripcion"><?php echo htmlspecialchars(substr($detalle['nombre_color'], 0, 18)); ?></td>
                <td class="cantidad"><?php echo $detalle['cantidad_subpaquetes']; ?></td>
                <td class="precio"><?php echo number_format($detalle['precio_unitario'], 0, '', '.'); ?></td>
                <td class="subtotal"><?php echo number_format($detalle['subtotal_calc'], 0, '', '.'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- RESUMEN DE ARTÍCULOS -->
    <div style="text-align: right; font-size: 11px; margin: 8px 0;">
        <strong>Total artículos:</strong> <?php echo $items; ?>
    </div>
    
    <!-- TOTALES -->
    <div class="totales">
        <div class="total-row">
            <span>SUBTOTAL:</span>
            <span>Bs <?php echo number_format($venta['subtotal'], 2, ',', '.'); ?></span>
        </div>
        
        <?php if ($venta['descuento'] > 0): ?>
        <div class="total-row">
            <span>DESCUENTO:</span>
            <span class="text-danger">- Bs <?php echo number_format($venta['descuento'], 2, ',', '.'); ?></span>
        </div>
        <?php endif; ?>
        
        <div class="total-row total-final">
            <span>TOTAL A PAGAR:</span>
            <span>Bs <?php echo number_format($venta['total'], 2, ',', '.'); ?></span>
        </div>
    </div>
    
    <!-- INFORMACIÓN DE PAGOS -->
    <?php if ($venta['pago_inicial'] > 0 || ($venta['debe'] ?? 0) > 0): ?>
    <div class="pagos">
        <div class="total-row">
            <span>PAGO INICIAL:</span>
            <span>Bs <?php echo number_format($venta['pago_inicial'], 2, ',', '.'); ?></span>
        </div>
        <?php if (($venta['debe'] ?? 0) > 0): ?>
        <div class="total-row" style="font-weight: bold;">
            <span>SALDO PENDIENTE:</span>
            <span>Bs <?php echo number_format($venta['debe'], 2, ',', '.'); ?></span>
        </div>
        <div style="margin-top: 8px; font-size: 11px; color: #666;">
            * Pago pendiente. Fecha límite: <?php echo date('d/m/Y', strtotime('+30 days')); ?>
        </div>
        <?php else: ?>
        <div style="margin-top: 5px; font-size: 11px; color: #28a745;">
            ✓ PAGADO COMPLETAMENTE
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- OBSERVACIONES -->
    <?php if (!empty($venta['observaciones'])): ?>
    <div style="margin-top: 10px; padding: 8px; border-top: 1px dashed #000000; border-bottom: 1px dashed #000000; font-size: 11px;">
        <strong>OBSERVACIONES:</strong>
        <div style="margin-top: 3px;"><?php echo nl2br(htmlspecialchars($venta['observaciones'])); ?></div>
    </div>
    <?php endif; ?>
    
    <!-- FIRMAS -->
    <div class="firma">
        <div>
            <div class="firma-linea"></div>
            <p style="margin-top: 5px;">Firma Cliente</p>
        </div>
        <div>
            <div class="firma-linea"></div>
            <p style="margin-top: 5px;">Firma Vendedor</p>
        </div>
    </div>
    
    <!-- PIE DE PÁGINA -->
    <div class="footer">
        <div class="gracias">¡GRACIAS POR SU COMPRA!</div>
        <div style="margin: 5px 0;">VUELVA PRONTO</div>
        <div style="margin-top: 8px; font-size: 10px;">
            <?php echo date('d/m/Y H:i:s'); ?>
        </div>
        <div style="margin-top: 5px; font-size: 9px;">
            <?php echo htmlspecialchars($venta['codigo_venta']); ?>
        </div>
    </div>
    
    <!-- CORTE DE PAPEL PARA IMPRESORA -->
    <div class="corte"></div>
    
    <script>
        // IMPRIMIR AUTOMÁTICAMENTE AL CARGAR
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>