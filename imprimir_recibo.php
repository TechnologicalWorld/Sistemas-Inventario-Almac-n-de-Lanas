<?php
session_start();
require_once 'config.php';
require_once 'funciones.php';

$venta_id = isset($_GET['venta_id']) ? intval($_GET['venta_id']) : 0;

if ($venta_id <= 0) {
    die('<div style="text-align:center; padding:50px;">
            <h2>ID de venta no válido</h2>
            <a href="ventas.php" class="btn btn-primary">Volver a Ventas</a>
        </div>');
}

// Verificar que la venta existe
$query_venta = "SELECT v.*, 
                       COALESCE(c.nombre, v.cliente_contado) as cliente_nombre,
                       c.telefono,
                       u.nombre as vendedor_nombre
                FROM ventas v
                LEFT JOIN clientes c ON v.cliente_id = c.id
                JOIN usuarios u ON v.vendedor_id = u.id
                WHERE v.id = ?";
$stmt = $conn->prepare($query_venta);
$stmt->bind_param("i", $venta_id);
$stmt->execute();
$venta = $stmt->get_result()->fetch_assoc();

if (!$venta) {
    die('<div style="text-align:center; padding:50px;">
            <h2>Venta no encontrada</h2>
            <a href="ventas.php" class="btn btn-primary">Volver a Ventas</a>
        </div>');
}

// Obtener detalles de la venta
$query_detalles = "SELECT vd.*, p.codigo, p.nombre_color
                   FROM venta_detalles vd
                   JOIN productos p ON vd.producto_id = p.id
                   WHERE vd.venta_id = ?
                   ORDER BY vd.id ASC";
$stmt = $conn->prepare($query_detalles);
$stmt->bind_param("i", $venta_id);
$stmt->execute();
$detalles = $stmt->get_result();

// Marcar venta como impresa
$query_update = "UPDATE ventas SET impreso = 1 WHERE id = ?";
$stmt = $conn->prepare($query_update);
$stmt->bind_param("i", $venta_id);
$stmt->execute();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo - <?php echo $venta['codigo_venta']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @page { 
            margin: 0; 
            size: 80mm auto;
        }
        
        body { 
            margin: 0;
            padding: 20px 0;
            font-family: "Courier New", monospace;
            font-size: 12px;
            line-height: 1.4;
            background: #f5f5f5;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .recibo-container {
            background: white;
            width: 72mm;
            margin: 0 auto;
            padding: 5mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            box-sizing: border-box;
            word-wrap: break-word;
        }
        
        .centrado { 
            text-align: center; 
        }
        
        .derecha { 
            text-align: right; 
        }
        
        .izquierda { 
            text-align: left; 
        }
        
        .empresa { 
            font-size: 16px; 
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .linea { 
            border-top: 1px dashed #000;
            margin: 8px 0;
            width: 100%;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        th, td {
            padding: 3px 0;
            word-wrap: break-word;
        }
        
        th {
            border-bottom: 1px solid #000;
            font-weight: bold;
        }
        
        .producto-row td {
            padding: 5px 0;
            border-bottom: 1px dotted #ccc;
        }
        
        .producto-nombre {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .total-row {
            font-size: 14px;
            font-weight: bold;
            border-top: 2px solid #000;
        }
        
        .footer {
            margin-top: 15px;
            font-size: 11px;
        }
        
        .botones-accion {
            position: fixed;
            top: 20px;
            right: 30px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        .btn-accion {
            padding: 12px 24px;
            font-size: 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .btn-imprimir {
            background: #28a745;
            color: white;
        }
        
        .btn-imprimir:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-pdf {
            background: #dc3545;
            color: white;
        }
        
        .btn-pdf:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-volver {
            background: #6c757d;
            color: white;
        }
        
        .btn-volver:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        @media print {
            .no-print { 
                display: none !important; 
            }
            
            body {
                background: white;
                padding: 0;
                display: block;
            }
            
            .recibo-container {
                box-shadow: none;
                margin: 0 auto;
                width: 72mm;
            }
        }
        
        /* Ajustes para pantallas pequeñas */
        @media (max-width: 768px) {
            .botones-accion {
                position: static;
                justify-content: center;
                margin: 20px auto;
                width: 100%;
            }
            
            body {
                padding: 10px;
            }
        }
        
        /* Estilos específicos para el recibo */
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }
        
        .info-label {
            width: 40%;
        }
        
        .info-value {
            width: 60%;
            text-align: right;
            font-weight: 500;
        }
        
        .producto-detalle {
            font-size: 10px;
            color: #666;
        }
        
        .monto-negrita {
            font-weight: bold;
        }
        
        .gracias {
            margin-top: 10px;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="botones-accion no-print">
        <button onclick="imprimirRecibo()" class="btn-accion btn-imprimir">
            <i class="fas fa-print"></i> Imprimir
        </button>
        <button onclick="descargarPDF()" class="btn-accion btn-pdf">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
        <button onclick="nuevaVenta()" class="btn-accion btn-volver">
            <i class="fas fa-plus"></i> Nueva Venta
        </button>
    </div>

    <div class="recibo-container" id="recibo-contenido">
        <div class="centrado">
            <div class="empresa"><?php echo EMPRESA_NOMBRE; ?></div>
            <div style="font-size: 11px;"><?php echo DIRECCION_EMPRESA; ?></div>
            <div style="font-size: 11px;">Tel: <?php echo TELEFONO_EMPRESA; ?></div>
        </div>
        
        <div class="linea"></div>
        
        <!-- Información del recibo en formato más compacto -->
        <div class="info-row">
            <span class="info-label">Recibo:</span>
            <span class="info-value"><strong><?php echo $venta['codigo_venta']; ?></strong></span>
        </div>
        <div class="info-row">
            <span class="info-label">Fecha:</span>
            <span class="info-value"><?php echo formatearFecha($venta['fecha']); ?> <?php echo formatearHora($venta['hora_inicio']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Cliente:</span>
            <span class="info-value"><?php echo $venta['cliente_nombre']; ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Vendedor:</span>
            <span class="info-value"><?php echo $venta['vendedor_nombre']; ?></span>
        </div>
        
        <div class="linea"></div>
        
        <table>
            <thead>
                <tr>
                    <th class="izquierda" style="width: 40%;">Producto</th>
                    <th class="derecha" style="width: 15%;">Cant</th>
                    <th class="derecha" style="width: 20%;">P/U</th>
                    <th class="derecha" style="width: 25%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($detalle = $detalles->fetch_assoc()): ?>
                <tr class="producto-row">
                    <td class="izquierda">
                        <div style="font-weight: bold;"><?php echo $detalle['codigo']; ?></div>
                        <div class="producto-detalle"><?php echo substr($detalle['nombre_color'], 0, 20); ?></div>
                    </td>
                    <td class="derecha"><?php echo $detalle['cantidad_subpaquetes']; ?></td>
                    <td class="derecha"><?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                    <td class="derecha"><?php echo number_format($detalle['subtotal'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <div class="linea"></div>
        
        <table>
            <tr>
                <td style="width: 50%;">Subtotal:</td>
                <td class="derecha">Bs <?php echo number_format($venta['subtotal'], 2); ?></td>
            </tr>
            <?php if ($venta['descuento'] > 0): ?>
            <tr>
                <td>Descuento:</td>
                <td class="derecha">-Bs <?php echo number_format($venta['descuento'], 2); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="total-row">
                <td><strong>TOTAL:</strong></td>
                <td class="derecha"><strong>Bs <?php echo number_format($venta['total'], 2); ?></strong></td>
            </tr>
            <?php if ($venta['pago_inicial'] > 0): ?>
            <tr>
                <td>Pagado:</td>
                <td class="derecha">Bs <?php echo number_format($venta['pago_inicial'], 2); ?></td>
            </tr>
            <?php if ($venta['debe'] > 0): ?>
            <tr style="border-top: 1px dashed #000;">
                <td><strong>Saldo Pendiente:</strong></td>
                <td class="derecha"><strong>Bs <?php echo number_format($venta['debe'], 2); ?></strong></td>
            </tr>
            <?php endif; ?>
            <?php endif; ?>
        </table>
        
        <div class="linea"></div>
        
        <div class="centrado footer">
            <div class="gracias">¡GRACIAS POR SU COMPRA!</div>
            <div style="margin-top: 5px; font-style: italic;">Vuelva pronto</div>
            <div style="margin-top: 8px; font-size: 9px; color: #666;">
                Impreso: <?php echo date('d/m/Y H:i:s'); ?>
            </div>
        </div>
    </div>

    <script>
        function imprimirRecibo() {
            window.print();
        }

        function descargarPDF() {
            const element = document.getElementById('recibo-contenido');
            const opt = {
                margin: [5, 5, 5, 5],
                filename: 'Recibo_<?php echo $venta['codigo_venta']; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2, 
                    useCORS: true,
                    letterRendering: true,
                    width: 260 // 72mm en puntos
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: [80, 297], 
                    orientation: 'portrait' 
                }
            };
            
            html2pdf().set(opt).from(element).save();
        }

        function nuevaVenta() {
            window.location.href = 'ventas.php';
        }
    </script>
</body>
</html> 