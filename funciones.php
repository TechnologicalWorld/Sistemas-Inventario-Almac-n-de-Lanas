<?php

require_once 'config.php';

function generarCodigoVenta() {
        global $conn;
        
        $prefijo = 'VTA';
        $fecha = date('Ymd');
        $base = $prefijo . '-' . $fecha . '-';
        
        $query = "SELECT codigo_venta FROM ventas 
                  WHERE codigo_venta LIKE ? 
                  ORDER BY id DESC LIMIT 1";
        
        $like = $base . '%';
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $like);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $ultimo = $row['codigo_venta'];
                $numero = (int)substr($ultimo, -4) + 1;
            } else {
                $numero = 1;
            }
        } else {
            $numero = mt_rand(1, 9999);
        }
        
        return $base . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }

// Función para limpiar texto
function limpiarTexto($texto) {
    return htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
}

// Función para validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Función para redirigir
function redirigir($url) {
    header("Location: $url");
    exit;
}


function obtenerNombreUsuario($usuario_id) {
    global $conn;
    $query = "SELECT nombre FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['nombre'];
    }
    return 'Desconocido';
}
/**
 * Calcular precio según cantidad
 */
function calcularPrecioProducto($producto_id, $cantidad) {
    global $conn;
    
    $query = "SELECT precio_menor, precio_mayor 
              FROM productos 
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $producto = $result->fetch_assoc();
        return ($cantidad > 5) ? $producto['precio_mayor'] : $producto['precio_menor'];
    }
    
    return 0;
}

/**
 * Verificar stock disponible
 */
function verificarStock($producto_id, $cantidad) {
        global $conn;
        
        $query = "SELECT COALESCE(total_subpaquetes, 0) as stock 
                  FROM inventario 
                  WHERE producto_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $producto_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stock = (int)$row['stock'];
            $cantidad = (int)$cantidad;
            return $stock >= $cantidad;
        }
        
        return false;
}

/**
 * Registrar movimiento de caja
 */
function registrarMovimientoCaja($tipo, $categoria, $monto, $descripcion, $usuario_id, $referencia = null) {
        global $conn;
        
        $query = "INSERT INTO movimientos_caja 
                  (tipo, categoria, monto, descripcion, referencia_venta, fecha, hora, usuario_id) 
                  VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssdssi", $tipo, $categoria, $monto, $descripcion, $referencia, $usuario_id);
        return $stmt->execute();
    }

// Mejorar la función obtenerDashboardData en funciones.php
function obtenerDashboardData($usuario_id, $rol) {
    global $conn;
    $data = [];
    
    // Ventas del día
    if ($rol == 'administrador') {
        $query_ventas = "SELECT 
                        COUNT(*) as total, 
                        COALESCE(SUM(total), 0) as monto,
                        COALESCE(SUM(debe), 0) as total_deuda,
                        COUNT(DISTINCT cliente_id) as clientes_atendidos
                        FROM ventas 
                        WHERE fecha = CURDATE() AND anulado = 0";
        $result = $conn->query($query_ventas);
        $data['ventas_hoy'] = $result->fetch_assoc();
    } else {
        $query_ventas = "SELECT 
                        COUNT(*) as total, 
                        COALESCE(SUM(total), 0) as monto,
                        COALESCE(SUM(debe), 0) as total_deuda,
                        COUNT(DISTINCT cliente_id) as clientes_atendidos
                        FROM ventas 
                        WHERE fecha = CURDATE() AND vendedor_id = ? AND anulado = 0";
        $stmt = $conn->prepare($query_ventas);
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data['ventas_hoy'] = $result->fetch_assoc();
    }
    
    // Clientes con mayor deuda
    $query_deudas = "SELECT id, nombre, saldo_actual, limite_credito,
                    (saldo_actual / NULLIF(limite_credito, 0)) * 100 as porcentaje_uso
                    FROM clientes 
                    WHERE saldo_actual > 0 AND activo = 1
                    ORDER BY saldo_actual DESC 
                    LIMIT 5";
    $result = $conn->query($query_deudas);
    $data['clientes_deuda'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['clientes_deuda'][] = $row;
    }
    
    // Inventario bajo (productos con menos de 50 unidades)
    $query_inventario = "SELECT 
                        p.codigo, 
                        p.nombre_color, 
                        pr.nombre as proveedor,
                        i.total_subpaquetes,
                        i.ubicacion,
                        p.precio_menor,
                        p.precio_mayor
                        FROM productos p 
                        JOIN inventario i ON p.id = i.producto_id 
                        JOIN proveedores pr ON p.proveedor_id = pr.id
                        WHERE i.total_subpaquetes < 50 
                        AND p.activo = 1 
                        ORDER BY i.total_subpaquetes ASC
                        LIMIT 10";
    $result = $conn->query($query_inventario);
    $data['inventario_bajo'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['inventario_bajo'][] = $row;
    }
    
    // Movimientos de caja recientes
    $query_movimientos = "SELECT mc.tipo, mc.categoria, mc.descripcion, mc.monto, mc.hora,
                         u.nombre as usuario_nombre
                         FROM movimientos_caja mc
                         JOIN usuarios u ON mc.usuario_id = u.id
                         WHERE mc.fecha = CURDATE() 
                         ORDER BY mc.hora DESC 
                         LIMIT 10";
    $result = $conn->query($query_movimientos);
    $data['movimientos'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['movimientos'][] = $row;
    }
    
    // Productos más vendidos (últimos 7 días)
    $query_top_productos = "SELECT 
                           p.codigo, 
                           p.nombre_color, 
                           p.precio_menor,
                           p.precio_mayor,
                           SUM(vd.cantidad_subpaquetes) as total_vendido,
                           SUM(vd.subtotal) as total_monto
                           FROM venta_detalles vd 
                           JOIN productos p ON vd.producto_id = p.id 
                           JOIN ventas v ON vd.venta_id = v.id 
                           WHERE v.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                           AND v.anulado = 0
                           GROUP BY p.id 
                           ORDER BY total_vendido DESC 
                           LIMIT 5";
    $result = $conn->query($query_top_productos);
    $data['top_productos'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['top_productos'][] = $row;
    }
    
    // Ventas por día de la semana (últimos 7 días)
    $query_ventas_semana = "SELECT 
                           DAYNAME(fecha) as dia,
                           DAYOFWEEK(fecha) as num_dia,
                           COUNT(*) as total_ventas,
                           COALESCE(SUM(total), 0) as monto_total
                           FROM ventas 
                           WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                           AND anulado = 0
                           GROUP BY fecha, DAYNAME(fecha), DAYOFWEEK(fecha)
                           ORDER BY fecha DESC
                           LIMIT 7";
    $result = $conn->query($query_ventas_semana);
    $data['ventas_semana'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['ventas_semana'][] = $row;
    }
    
    // Total cuentas por cobrar y pagar
    $query_cuentas = "SELECT 
                     (SELECT COALESCE(SUM(saldo_pendiente), 0) FROM clientes_cuentas_cobrar WHERE estado = 'pendiente') as total_cobrar,
                     (SELECT COALESCE(COUNT(*), 0) FROM clientes_cuentas_cobrar WHERE estado = 'pendiente') as cantidad_cuentas_cobrar,
                     (SELECT COALESCE(SUM(saldo_actual), 0) FROM proveedores WHERE saldo_actual > 0) as total_pagar,
                     (SELECT COALESCE(COUNT(*), 0) FROM proveedores WHERE saldo_actual > 0) as cantidad_proveedores_deuda";
    $result = $conn->query($query_cuentas);
    $data['cuentas'] = $result->fetch_assoc();
    
    return $data;
}


/**
 * Generar recibo en formato térmico
 */
function generarReciboTermico($venta_id) {
    global $conn;
    
    // Obtener datos de la venta
    $query = "SELECT v.*, c.nombre as cliente_nombre, c.telefono, u.nombre as vendedor 
              FROM ventas v 
              LEFT JOIN clientes c ON v.cliente_id = c.id 
              JOIN usuarios u ON v.vendedor_id = u.id 
              WHERE v.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $venta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $venta = $result->fetch_assoc();
    
    // Obtener detalles
    $query_detalles = "SELECT vd.*, p.codigo, p.nombre_color 
                      FROM venta_detalles vd 
                      JOIN productos p ON vd.producto_id = p.id 
                      WHERE vd.venta_id = ?";
    $stmt = $conn->prepare($query_detalles);
    $stmt->bind_param("i", $venta_id);
    $stmt->execute();
    $detalles = $stmt->get_result();
    
    // Generar contenido HTML para térmica
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Recibo ' . $venta['codigo_venta'] . '</title>
        <style>
            @media print {
                body { width: 80mm; margin: 0; padding: 0; font-family: monospace; font-size: 12px; }
                .recibo { width: 100%; }
                .centrado { text-align: center; }
                .derecha { text-align: right; }
                .izquierda { text-align: left; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 2px; }
                hr { border: 0; border-top: 1px dashed #000; margin: 5px 0; }
                .total { font-weight: bold; font-size: 14px; }
                .footer { font-size: 10px; margin-top: 10px; }
            }
            body { width: 80mm; margin: 0 auto; font-family: monospace; font-size: 12px; }
            .recibo { width: 100%; }
            .centrado { text-align: center; }
            .derecha { text-align: right; }
            .izquierda { text-align: left; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 2px; }
            hr { border: 0; border-top: 1px dashed #000; margin: 5px 0; }
            .total { font-weight: bold; font-size: 14px; }
            .footer { font-size: 10px; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class="recibo">
            <div class="centrado">
                <h3>' . EMPRESA_NOMBRE . '</h3>
                <p>' . DIRECCION_EMPRESA . '<br>' . TELEFONO_EMPRESA . '</p>
            </div>
            <hr>
            <div class="izquierda">
                <p><strong>Recibo:</strong> ' . $venta['codigo_venta'] . '</p>
                <p><strong>Fecha:</strong> ' . formatearFecha($venta['fecha']) . ' ' . formatearHora($venta['hora_inicio']) . '</p>
                <p><strong>Cliente:</strong> ' . ($venta['cliente_nombre'] ?: $venta['cliente_contado']) . '</p>
                <p><strong>Vendedor:</strong> ' . $venta['vendedor'] . '</p>
            </div>
            <hr>
            <table>
                <thead>
                    <tr>
                        <th class="izquierda">Código</th>
                        <th class="izquierda">Producto</th>
                        <th class="derecha">Cant</th>
                        <th class="derecha">Precio</th>
                        <th class="derecha">Subtotal</th>
                    </tr>
                </thead>
                <tbody>';
    
    $total = 0;
    while ($detalle = $detalles->fetch_assoc()) {
        $subtotal = $detalle['cantidad_subpaquetes'] * $detalle['precio_unitario'];
        $total += $subtotal;
        $html .= '<tr>
                    <td>' . $detalle['codigo'] . '</td>
                    <td>' . $detalle['nombre_color'] . '</td>
                    <td class="derecha">' . $detalle['cantidad_subpaquetes'] . '</td>
                    <td class="derecha">' . formatearMoneda($detalle['precio_unitario']) . '</td>
                    <td class="derecha">' . formatearMoneda($subtotal) . '</td>
                </tr>';
    }
    
    $html .= '</tbody>
            </table>
            <hr>
            <table>
                <tr>
                    <td class="derecha"><strong>Subtotal:</strong></td>
                    <td class="derecha" width="30%">' . formatearMoneda($venta['subtotal']) . '</td>
                </tr>
                <tr>
                    <td class="derecha"><strong>Descuento:</strong></td>
                    <td class="derecha">' . formatearMoneda($venta['descuento']) . '</td>
                </tr>
                <tr class="total">
                    <td class="derecha"><strong>TOTAL:</strong></td>
                    <td class="derecha">' . formatearMoneda($venta['total']) . '</td>
                </tr>';
    
    if ($venta['pago_inicial'] > 0) {
        $html .= '<tr>
                    <td class="derecha"><strong>Pago Inicial:</strong></td>
                    <td class="derecha">' . formatearMoneda($venta['pago_inicial']) . '</td>
                </tr>
                <tr>
                    <td class="derecha"><strong>Saldo Pendiente:</strong></td>
                    <td class="derecha">' . formatearMoneda($venta['debe']) . '</td>
                </tr>';
    }
    
    $html .= '</table>
            <hr>
            <div class="centrado footer">
                <p>¡Gracias por su compra!</p>
                <p>Vuelva pronto</p>
                <p>---</p>
                <p>Recibo generado: ' . date('d/m/Y H:i:s') . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Exportar a Excel
 */
function exportarExcel($datos, $nombre_archivo, $columnas) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo '<table border="1">';
    echo '<tr>';
    foreach ($columnas as $columna) {
        echo '<th>' . $columna . '</th>';
    }
    echo '</tr>';
    
    foreach ($datos as $fila) {
        echo '<tr>';
        foreach ($fila as $celda) {
            echo '<td>' . $celda . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    exit();
}

/**
 * Exportar a PDF
 */
function exportarPDF($titulo, $contenido_html, $orientacion = 'P') {
    require_once('vendor/autoload.php'); // Requiere TCPDF instalado
    
    $pdf = new TCPDF($orientacion, 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(EMPRESA_NOMBRE);
    $pdf->SetAuthor(EMPRESA_NOMBRE);
    $pdf->SetTitle($titulo);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->writeHTML($contenido_html, true, false, true, false, '');
    
    $pdf->Output($titulo . '.pdf', 'D');
    exit();
}
/**
 * Calcula el tiempo transcurrido desde una fecha hasta ahora
 * @param string $fecha_hora Fecha y hora en formato MySQL
 * @return string Texto descriptivo del tiempo transcurrido
 */
function tiempoTranscurrido($fecha_hora) {
    if (!$fecha_hora) return 'Fecha no disponible';
    
    $fecha = new DateTime($fecha_hora);
    $ahora = new DateTime();
    $diferencia = $ahora->diff($fecha);
    
    if ($diferencia->y > 0) {
        return 'Hace ' . $diferencia->y . ' año' . ($diferencia->y > 1 ? 's' : '');
    } elseif ($diferencia->m > 0) {
        return 'Hace ' . $diferencia->m . ' mes' . ($diferencia->m > 1 ? 'es' : '');
    } elseif ($diferencia->d > 0) {
        if ($diferencia->d == 1) return 'Ayer';
        if ($diferencia->d < 7) return 'Hace ' . $diferencia->d . ' días';
        $semanas = floor($diferencia->d / 7);
        return 'Hace ' . $semanas . ' semana' . ($semanas > 1 ? 's' : '');
    } elseif ($diferencia->h > 0) {
        return 'Hace ' . $diferencia->h . ' hora' . ($diferencia->h > 1 ? 's' : '');
    } elseif ($diferencia->i > 0) {
        return 'Hace ' . $diferencia->i . ' minuto' . ($diferencia->i > 1 ? 's' : '');
    } else {
        return 'Hace unos segundos';
    }
}

function formatearFechaHora($fecha_hora, $formato = 'd/m/Y H:i') {
    if (!$fecha_hora) return 'No disponible';
    return date($formato, strtotime($fecha_hora));
}




/**
 * Obtener producto por código
 */
function obtenerProductoPorCodigo($codigo) {
        global $conn;
        
        $query = "SELECT 
                    p.id, 
                    p.codigo, 
                    p.nombre_color as nombre,
                    i.total_subpaquetes as stock,
                    p.precio_menor,
                    p.precio_mayor
                  FROM productos p
                  LEFT JOIN inventario i ON p.id = i.producto_id
                  WHERE p.codigo = ? AND p.activo = 1
                  LIMIT 1";
                  
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $codigo);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }

/**
 * Formatear número para WhatsApp
 */
function formatearNumeroWhatsApp($telefono) {
    $telefono = preg_replace('/[^0-9]/', '', $telefono);
    if (substr($telefono, 0, 1) == '0') {
        $telefono = '+591' . substr($telefono, 1);
    }
    return $telefono;
}

/**
 * Generar recibo térmico 80mm
 */
function generarReciboTermico80mm($venta_id) {
    global $conn;
    
    $query = "SELECT 
                v.*, 
                COALESCE(c.nombre, v.cliente_contado, 'Cliente') as cliente_nombre,
                c.telefono,
                u.nombre as vendedor_nombre
              FROM ventas v
              LEFT JOIN clientes c ON v.cliente_id = c.id
              JOIN usuarios u ON v.vendedor_id = u.id
              WHERE v.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $venta_id);
    $stmt->execute();
    $venta = $stmt->get_result()->fetch_assoc();
    
    $query_detalles = "SELECT 
                        vd.*,
                        p.codigo,
                        p.nombre_color
                      FROM venta_detalles vd
                      JOIN productos p ON vd.producto_id = p.id
                      WHERE vd.venta_id = ?";
    $stmt = $conn->prepare($query_detalles);
    $stmt->bind_param("i", $venta_id);
    $stmt->execute();
    $detalles = $stmt->get_result();
    
    // Estilos específicos para impresora térmica 80mm
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Recibo ' . $venta['codigo_venta'] . '</title>
        <style>
            @page { margin: 0; size: 80mm auto; }
            body { 
                margin: 0;
                padding: 5px;
                width: 80mm;
                font-family: "Courier New", monospace;
                font-size: 12px;
                line-height: 1.2;
            }
            .centrado { text-align: center; }
            .derecha { text-align: right; }
            .izquierda { text-align: left; }
            .recibo { width: 100%; }
            .empresa { 
                font-size: 18px; 
                font-weight: bold;
                margin-bottom: 5px;
            }
            .linea { 
                border-top: 1px dashed #000;
                margin: 8px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                padding: 2px 0;
            }
            .producto-nombre {
                max-width: 150px;
                overflow: hidden;
                white-space: nowrap;
                text-overflow: ellipsis;
            }
            .total {
                font-size: 14px;
                font-weight: bold;
            }
            .footer {
                margin-top: 15px;
                font-size: 11px;
            }
            @media print {
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="recibo">
            <div class="centrado">
                <div class="empresa">' . EMPRESA_NOMBRE . '</div>
                <div>' . (DIRECCION_EMPRESA ?? '') . '</div>
                <div>Tel: ' . (TELEFONO_EMPRESA ?? '') . '</div>
            </div>
            
            <div class="linea"></div>
            
            <table>
                <tr>
                    <td>Recibo:</td>
                    <td class="derecha"><strong>' . $venta['codigo_venta'] . '</strong></td>
                </tr>
                <tr>
                    <td>Fecha:</td>
                    <td class="derecha">' . date('d/m/Y', strtotime($venta['fecha'])) . '</td>
                </tr>
                <tr>
                    <td>Hora:</td>
                    <td class="derecha">' . date('H:i', strtotime($venta['hora_inicio'])) . '</td>
                </tr>
                <tr>
                    <td>Cliente:</td>
                    <td class="derecha">' . $venta['cliente_nombre'] . '</td>
                </tr>
                <tr>
                    <td>Vendedor:</td>
                    <td class="derecha">' . $venta['vendedor_nombre'] . '</td>
                </tr>
            </table>
            
            <div class="linea"></div>
            
            <table>
                <thead>
                    <tr>
                        <th class="izquierda">Producto</th>
                        <th class="derecha">Cant</th>
                        <th class="derecha">P/U</th>
                        <th class="derecha">Subtotal</th>
                    </tr>
                </thead>
                <tbody>';
    
    while ($detalle = $detalles->fetch_assoc()) {
        $html .= '<tr>
                    <td class="producto-nombre">' . $detalle['codigo'] . ' ' . $detalle['nombre_color'] . '</td>
                    <td class="derecha">' . $detalle['cantidad_subpaquetes'] . '</td>
                    <td class="derecha">' . number_format($detalle['precio_unitario'], 0) . '</td>
                    <td class="derecha">' . number_format($detalle['subtotal'], 0) . '</td>
                </tr>';
    }
    
    $html .= '</tbody>
            </table>
            
            <div class="linea"></div>
            
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td class="derecha">' . number_format($venta['subtotal'], 2) . '</td>
                </tr>';
    
    if ($venta['descuento'] > 0) {
        $html .= '<tr>
                    <td>Descuento:</td>
                    <td class="derecha">-' . number_format($venta['descuento'], 2) . '</td>
                </tr>';
    }
    
    $html .= '<tr class="total">
                    <td>TOTAL:</td>
                    <td class="derecha">' . number_format($venta['total'], 2) . '</td>
                </tr>';
    
    if ($venta['pago_inicial'] > 0) {
        $html .= '<tr>
                    <td>Pagado:</td>
                    <td class="derecha">' . number_format($venta['pago_inicial'], 2) . '</td>
                </tr>';
        if ($venta['debe'] > 0) {
            $html .= '<tr>
                        <td>Saldo:</td>
                        <td class="derecha">' . number_format($venta['debe'], 2) . '</td>
                    </tr>';
        }
    }
    
    $html .= '</table>
            
            <div class="linea"></div>
            
            <div class="centrado footer">
                <p>¡Gracias por su compra!</p>
                <p>Vuelva pronto</p>
                <p style="font-size: 10px; margin-top: 10px;">
                    Recibo generado: ' . date('d/m/Y H:i:s') . '
                </p>
                <p style="font-size: 10px;" class="no-print">
                    <button onclick="window.print()" style="padding: 5px 15px; margin-top: 10px;">
                        Imprimir Recibo
                    </button>
                </p>
            </div>
        </div>
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>';
    
    return $html;
}

?>