<?php

require_once 'config.php';
require_once 'funciones.php';

// Verificar sesión
verificarSesion();

$tipo_reporte = $_GET['tipo'] ?? 'ventas';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$proveedor_id = $_GET['proveedor_id'] ?? '';
$cliente_id = $_GET['cliente_id'] ?? '';

// Configurar cabeceras para Excel
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="reporte_' . $tipo_reporte . '_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Función para generar reporte de ventas
function generarReporteVentas($conn, $fecha_inicio, $fecha_fin, $cliente_id) {
    $where_conditions = ["v.anulado = 0", "v.fecha BETWEEN ? AND ?"];
    $params = [$fecha_inicio, $fecha_fin];
    $types = "ss";
    
    if ($cliente_id) {
        $where_conditions[] = "v.cliente_id = ?";
        $params[] = $cliente_id;
        $types .= "i";
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    $query = "SELECT v.codigo_venta, v.fecha, v.hora_inicio,
             COALESCE(c.nombre, v.cliente_contado) as cliente,
             u.nombre as vendedor,
             v.tipo_pago,
             v.subtotal,
             v.descuento,
             v.total,
             v.pago_inicial,
             v.debe,
             v.estado,
             GROUP_CONCAT(CONCAT(p.codigo, ' (', vd.cantidad_subpaquetes, ')') SEPARATOR ', ') as productos
             FROM ventas v
             LEFT JOIN clientes c ON v.cliente_id = c.id
             JOIN usuarios u ON v.vendedor_id = u.id
             LEFT JOIN venta_detalles vd ON v.id = vd.venta_id
             LEFT JOIN productos p ON vd.producto_id = p.id
             $where_clause
             GROUP BY v.id
             ORDER BY v.fecha DESC, v.hora_inicio DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// Función para generar reporte de inventario
function generarReporteInventario($conn) {
    $query = "SELECT p.codigo, p.nombre_color,
             pr.nombre as proveedor,
             c.nombre as categoria,
             i.paquetes_completos,
             i.subpaquetes_sueltos,
             i.total_subpaquetes,
             i.ubicacion,
             p.precio_menor,
             p.precio_mayor,
             i.fecha_ultimo_ingreso,
             i.fecha_ultima_salida
             FROM productos p
             JOIN proveedores pr ON p.proveedor_id = pr.id
             JOIN categorias c ON p.categoria_id = c.id
             LEFT JOIN inventario i ON p.id = i.producto_id
             WHERE p.activo = 1
             ORDER BY pr.nombre, p.codigo";
    
    return $conn->query($query);
}

// Función para generar reporte de clientes
function generarReporteClientes($conn) {
    $query = "SELECT codigo, nombre, ciudad, telefono,
             tipo_documento, numero_documento,
             limite_credito, saldo_actual,
             total_comprado, compras_realizadas,
             fecha_registro,
             CASE WHEN activo = 1 THEN 'Activo' ELSE 'Inactivo' END as estado
             FROM clientes
             ORDER BY nombre";
    
    return $conn->query($query);
}

// Función para generar reporte de proveedores
function generarReporteProveedores($conn) {
    $query = "SELECT codigo, nombre, ciudad, telefono, email,
             credito_limite, saldo_actual,
             fecha_registro,
             CASE WHEN activo = 1 THEN 'Activo' ELSE 'Inactivo' END as estado
             FROM proveedores
             ORDER BY nombre";
    
    return $conn->query($query);
}

// Función para generar reporte de movimientos de caja
function generarReporteCaja($conn, $fecha_inicio, $fecha_fin) {
    $query = "SELECT fecha, hora, tipo, categoria, descripcion,
             monto, referencia_venta,
             (SELECT nombre FROM usuarios WHERE id = mc.usuario_id) as usuario,
             observaciones
             FROM movimientos_caja mc
             WHERE fecha BETWEEN ? AND ?
             ORDER BY fecha DESC, hora DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    return $stmt->get_result();
}

// Generar contenido Excel según tipo de reporte
echo '<html>';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<style>';
echo 'table { border-collapse: collapse; width: 100%; }';
echo 'th { background-color: #28a745; color: white; font-weight: bold; padding: 8px; border: 1px solid #ddd; }';
echo 'td { padding: 6px; border: 1px solid #ddd; }';
echo '.total { background-color: #f8f9fa; font-weight: bold; }';
echo '.text-right { text-align: right; }';
echo '.text-center { text-align: center; }';
echo '</style>';
echo '</head>';
echo '<body>';

switch ($tipo_reporte) {
    case 'ventas':
        echo '<h2>Reporte de Ventas</h2>';
        echo '<p><strong>Período:</strong> ' . formatearFecha($fecha_inicio) . ' al ' . formatearFecha($fecha_fin) . '</p>';
        echo '<p><strong>Generado:</strong> ' . date('d/m/Y H:i:s') . '</p>';
        
        $result = generarReporteVentas($conn, $fecha_inicio, $fecha_fin, $cliente_id);
        
        echo '<table border="1">';
        echo '<tr>';
        echo '<th>Código Venta</th>';
        echo '<th>Fecha</th>';
        echo '<th>Cliente</th>';
        echo '<th>Vendedor</th>';
        echo '<th>Tipo Pago</th>';
        echo '<th>Subtotal</th>';
        echo '<th>Descuento</th>';
        echo '<th>Total</th>';
        echo '<th>Pagado</th>';
        echo '<th>Debe</th>';
        echo '<th>Estado</th>';
        echo '<th>Productos</th>';
        echo '</tr>';
        
        $total_ventas = 0;
        $total_deuda = 0;
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['codigo_venta'] . '</td>';
            echo '<td>' . formatearFecha($row['fecha']) . ' ' . substr($row['hora_inicio'], 0, 5) . '</td>';
            echo '<td>' . $row['cliente'] . '</td>';
            echo '<td>' . $row['vendedor'] . '</td>';
            echo '<td>' . ucfirst($row['tipo_pago']) . '</td>';
            echo '<td class="text-right">' . formatearMoneda($row['subtotal']) . '</td>';
            echo '<td class="text-right">' . formatearMoneda($row['descuento']) . '</td>';
            echo '<td class="text-right">' . formatearMoneda($row['total']) . '</td>';
            echo '<td class="text-right">' . formatearMoneda($row['pago_inicial']) . '</td>';
            echo '<td class="text-right">' . formatearMoneda($row['debe']) . '</td>';
            echo '<td>' . ucfirst($row['estado']) . '</td>';
            echo '<td>' . $row['productos'] . '</td>';
            echo '</tr>';
            
            $total_ventas += $row['total'];
            $total_deuda += $row['debe'];
        }
        
        echo '<tr class="total">';
        echo '<td colspan="5"><strong>TOTALES:</strong></td>';
        echo '<td class="text-right"><strong>' . formatearMoneda($total_ventas) . '</strong></td>';
        echo '<td></td>';
        echo '<td class="text-right"><strong>' . formatearMoneda($total_ventas) . '</strong></td>';
        echo '<td></td>';
        echo '<td class="text-right"><strong>' . formatearMoneda($total_deuda) . '</strong></td>';
        echo '<td colspan="2"></td>';
        echo '</tr>';
        
        echo '</table>';
        break;
        
    case 'inventario':
        echo '<h2>Reporte de Inventario</h2>';
        echo '<p><strong>Generado:</strong> ' . date('d/m/Y H:i:s') . '</p>';
        
        $result = generarReporteInventario($conn);
        
        echo '<table border="1">';
        echo '<tr>';
        echo '<th>Código</th>';
        echo '<th>Producto/Color</th>';
        echo '<th>Proveedor</th>';
        echo '<th>Categoría</th>';
        echo '<th>Paquetes</th>';
        echo '<th>Sueltos</th>';
        echo '<th>Total Subp.</th>';
        echo '<th>Ubicación</th>';
        echo '<th>Precio Menor</th>';
        echo '<th>Precio Mayor</th>';
        echo '<th>Último Ingreso</th>';
        echo '<th>Última Salida</th>';
        echo '</tr>';
        
        $total_subpaquetes = 0;
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['codigo'] . '</td>';
            echo '<td>' . $row['nombre_color'] . '</td>';
            echo '<td>' . $row['proveedor'] . '</td>';
            echo '<td>' . $row['categoria'] . '</td>';
            echo '<td class="text-center">' . $row['paquetes_completos'] . '</td>';
            echo '<td class="text-center">' . $row['subpaquetes_sueltos'] . '</td>';
            echo '<td class="text-center">' . $row['total_subpaquetes'] . '</td>';
            echo '<td>' . $row['ubicacion'] . '</td>';
            echo '<td class="text-right">' . formatearMoneda($row['precio_menor']) . '</td>';
            echo '<td class="text-right">' . formatearMoneda($row['precio_mayor']) . '</td>';
            echo '<td>' . ($row['fecha_ultimo_ingreso'] ? formatearFecha($row['fecha_ultimo_ingreso']) : '') . '</td>';
            echo '<td>' . ($row['fecha_ultima_salida'] ? formatearFecha($row['fecha_ultima_salida']) : '') . '</td>';
            echo '</tr>';
            
            $total_subpaquetes += $row['total_subpaquetes'];
        }
        
        echo '<tr class="total">';
        echo '<td colspan="6"><strong>TOTAL SUBPAQUETES:</strong></td>';
        echo '<td class="text-center"><strong>' . number_format($total_subpaquetes) . '</strong></td>';
        echo '<td colspan="6"></td>';
        echo '</tr>';
        
        echo '</table>';
        break;
        
    case 'clientes':
        echo '<h2>Reporte de Clientes</h2>';
        echo '<p><strong>Generado:</strong> ' . date('d/m/Y H:i:s') . '</p>';
        
        $result = generarReporteClientes($conn);
        
        echo '<table border="1">';
        echo '<tr>';
        echo '<th>Código</th>';
        echo '<th>Nombre</th>';
        echo '<th>Ciudad</th>';
        echo '<th>Teléfono</th>';
        echo '<th>Documento</th>';
        echo '<th>Número</th>';
        echo '<th>Límite Crédito</th>';
        echo '<th>Saldo Actual</th>';
        echo '<th>Total Comprado</th>';
        echo '<th>Compras</th>';
        echo '<th>Fecha Registro</th>';
        echo '<th>Estado</th>';
        echo '</tr>';
        
        $total_deuda = 0;
        $total_comprado = 0;
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['codigo'] . '</td>';
            echo '<td>' . $row['nombre'] . '</td>';
            echo '<td>' . $row['ciudad'] . '</td>';
            echo '<td>' . $row['telefono'] . '</td>';
            echo '<td>' . $row['tipo_documento'] . '</td>';
            echo '<td>' . $row['numero_documento'] . '</td>';
            echo '<td class="text-right">' . formatearMoneda($row['limite_credito']) . '</td>';
            echo '<td class="text-right">' . formatearMoneda($row['saldo_actual']) . '</td>';
            echo '<td class="text-right">' . formatearMoneda($row['total_comprado']) . '</td>';
            echo '<td class="text-center">' . $row['compras_realizadas'] . '</td>';
            echo '<td>' . formatearFecha($row['fecha_registro']) . '</td>';
            echo '<td>' . $row['estado'] . '</td>';
            echo '</tr>';
            
            $total_deuda += $row['saldo_actual'];
            $total_comprado += $row['total_comprado'];
        }
        
        echo '<tr class="total">';
        echo '<td colspan="6"><strong>TOTALES:</strong></td>';
        echo '<td></td>';
        echo '<td class="text-right"><strong>' . formatearMoneda($total_deuda) . '</strong></td>';
        echo '<td class="text-right"><strong>' . formatearMoneda($total_comprado) . '</strong></td>';
        echo '<td></td>';
        echo '<td colspan="2"></td>';
        echo '</tr>';
        
        echo '</table>';
        break;
        
    case 'proveedores':
        echo '<h2>Reporte de Proveedores</h2>';
        echo '<p><strong>Generado:</strong> ' . date('d/m/Y H:i:s') . '</p>';
        
        $result = generarReporteProveedores($conn);
        
        echo '<table border="1">';
        echo '<tr>';
        echo '<th>Código</th>';
        echo '<th>Nombre</th>';
        echo '<th>Ciudad</th>';
        echo '<th>Teléfono</th>';
        echo '<th>Email</th>';
        echo '<th>Límite Crédito</th>';
        echo '<th>Saldo Actual</th>';
        echo '<th>Fecha Registro</th>';
        echo '<th>Estado</th>';
        echo '</tr>';
        
        $total_deuda = 0;
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['codigo'] . '</td>';
            echo '<td>' . $row['nombre'] . '</td>';
            echo '<td>' . $row['ciudad'] . '</td>';
            echo '<td>' . $row['telefono'] . '</td>';
            echo '<td>' . $row['email'] . '</td>';
            echo '<td class="text-right">' . formatearMoneda($row['credito_limite']) . '</td>';
            echo '<td class="text-right">' . formatearMoneda($row['saldo_actual']) . '</td>';
            echo '<td>' . formatearFecha($row['fecha_registro']) . '</td>';
            echo '<td>' . $row['estado'] . '</td>';
            echo '</tr>';
            
            $total_deuda += $row['saldo_actual'];
        }
        
        echo '<tr class="total">';
        echo '<td colspan="6"><strong>TOTAL DEUDA:</strong></td>';
        echo '<td class="text-right"><strong>' . formatearMoneda($total_deuda) . '</strong></td>';
        echo '<td colspan="2"></td>';
        echo '</tr>';
        
        echo '</table>';
        break;
        
    case 'caja':
        echo '<h2>Reporte de Movimientos de Caja</h2>';
        echo '<p><strong>Período:</strong> ' . formatearFecha($fecha_inicio) . ' al ' . formatearFecha($fecha_fin) . '</p>';
        echo '<p><strong>Generado:</strong> ' . date('d/m/Y H:i:s') . '</p>';
        
        $result = generarReporteCaja($conn, $fecha_inicio, $fecha_fin);
        
        echo '<table border="1">';
        echo '<tr>';
        echo '<th>Fecha</th>';
        echo '<th>Hora</th>';
        echo '<th>Tipo</th>';
        echo '<th>Categoría</th>';
        echo '<th>Descripción</th>';
        echo '<th>Monto</th>';
        echo '<th>Referencia</th>';
        echo '<th>Usuario</th>';
        echo '<th>Observaciones</th>';
        echo '</tr>';
        
        $total_ingresos = 0;
        $total_gastos = 0;
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . formatearFecha($row['fecha']) . '</td>';
            echo '<td>' . substr($row['hora'], 0, 5) . '</td>';
            echo '<td>' . ucfirst($row['tipo']) . '</td>';
            echo '<td>' . $row['categoria'] . '</td>';
            echo '<td>' . $row['descripcion'] . '</td>';
            echo '<td class="text-right">' . formatearMoneda($row['monto']) . '</td>';
            echo '<td>' . $row['referencia_venta'] . '</td>';
            echo '<td>' . $row['usuario'] . '</td>';
            echo '<td>' . $row['observaciones'] . '</td>';
            echo '</tr>';
            
            if ($row['tipo'] == 'ingreso') {
                $total_ingresos += $row['monto'];
            } else {
                $total_gastos += $row['monto'];
            }
        }
        
        $balance = $total_ingresos - $total_gastos;
        
        echo '<tr class="total">';
        echo '<td colspan="5"><strong>TOTALES:</strong></td>';
        echo '<td colspan="4"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td colspan="5">Total Ingresos</td>';
        echo '<td class="text-right"><strong>' . formatearMoneda($total_ingresos) . '</strong></td>';
        echo '<td colspan="3"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td colspan="5">Total Gastos</td>';
        echo '<td class="text-right"><strong>' . formatearMoneda($total_gastos) . '</strong></td>';
        echo '<td colspan="3"></td>';
        echo '</tr>';
        
        echo '<tr class="total">';
        echo '<td colspan="5"><strong>BALANCE FINAL:</strong></td>';
        echo '<td class="text-right"><strong>' . formatearMoneda($balance) . '</strong></td>';
        echo '<td colspan="3"></td>';
        echo '</tr>';
        
        echo '</table>';
        break;
        
    default:
        echo '<h2>Reporte no disponible</h2>';
        break;
}

echo '</body>';
echo '</html>';

$conn->close();
?>