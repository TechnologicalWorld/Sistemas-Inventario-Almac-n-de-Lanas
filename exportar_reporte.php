<?php

require_once 'config.php';
require_once 'funciones.php';

// Limpiar cualquier salida previa
ob_clean();

// Verificar sesión
verificarSesion();

// Verificar que se enviaron los datos
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: dashboard.php');
    exit();
}

// Obtener parámetros
$tipo_reporte = $_POST['tipo_reporte'] ?? '';
$formato = $_POST['formato'] ?? '';
$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_POST['fecha_fin'] ?? date('Y-m-d');
$incluir_graficos = isset($_POST['incluir_graficos']) ? true : false;
$incluir_tablas = isset($_POST['incluir_tablas']) ? true : false;
$incluir_resumen = isset($_POST['incluir_resumen']) ? true : false;
$orientacion = $_POST['orientacion'] ?? 'vertical';

// Validar datos
if (empty($tipo_reporte) || empty($formato)) {
    die('Parámetros incompletos');
}

// Determinar título del reporte
$titulos_reporte = [
    'ventas_diarias' => 'Reporte de Ventas Diarias',
    'ventas_semanales' => 'Reporte de Ventas Semanales',
    'ventas_mensuales' => 'Reporte de Ventas Mensuales',
    'ventas_por_vendedor' => 'Ventas por Vendedor',
    'inventario_completo' => 'Inventario Completo',
    'stock_bajo' => 'Productos con Stock Bajo',
    'movimientos_inventario' => 'Movimientos de Inventario',
    'clientes_morosos' => 'Clientes Morosos',
    'estado_cuentas' => 'Estado de Cuentas por Cobrar',
    'historial_compras' => 'Historial de Compras por Cliente',
    'deudas_proveedores' => 'Deudas con Proveedores',
    'compras_proveedor' => 'Compras por Proveedor',
    'caja_diaria' => 'Caja Diaria',
    'gastos_mensuales' => 'Gastos Mensuales',
    'balance_general' => 'Balance General'
];

$titulo = $titulos_reporte[$tipo_reporte] ?? 'Reporte del Sistema';

// Obtener datos según el tipo de reporte
$datos_reporte = obtenerDatosReporte($tipo_reporte, $fecha_inicio, $fecha_fin, $_SESSION['usuario_id'], $_SESSION['usuario_rol']);

// Generar reporte según formato
switch ($formato) {
    case 'pdf':
        generarPDF($titulo, $datos_reporte, $fecha_inicio, $fecha_fin, $orientacion, $incluir_graficos, $incluir_tablas, $incluir_resumen);
        break;
    case 'excel':
        generarExcel($titulo, $datos_reporte, $tipo_reporte, $fecha_inicio, $fecha_fin);
        break;
    case 'csv':
        generarCSV($titulo, $datos_reporte, $tipo_reporte, $fecha_inicio, $fecha_fin);
        break;
    case 'html':
        generarHTML($titulo, $datos_reporte, $fecha_inicio, $fecha_fin, $incluir_graficos, $incluir_tablas, $incluir_resumen);
        break;
    default:
        die('Formato no soportado');
}

// ===================================================
// FUNCIONES PARA OBTENER DATOS DE REPORTES
// ===================================================

function obtenerDatosReporte($tipo_reporte, $fecha_inicio, $fecha_fin, $usuario_id, $rol) {
    global $conn;
    $datos = [];
    
    try {
        switch ($tipo_reporte) {
            case 'ventas_diarias':
                $datos = reporteVentasDiarias($conn, $fecha_inicio, $fecha_fin, $usuario_id, $rol);
                break;
            case 'ventas_semanales':
                $datos = reporteVentasSemanales($conn, $fecha_inicio, $fecha_fin, $usuario_id, $rol);
                break;
            case 'ventas_mensuales':
                $datos = reporteVentasMensuales($conn, $fecha_inicio, $fecha_fin, $usuario_id, $rol);
                break;
            case 'ventas_por_vendedor':
                $datos = reporteVentasPorVendedor($conn, $fecha_inicio, $fecha_fin);
                break;
            case 'inventario_completo':
                $datos = reporteInventarioCompleto($conn);
                break;
            case 'stock_bajo':
                $datos = reporteStockBajo($conn);
                break;
            case 'movimientos_inventario':
                $datos = reporteMovimientosInventario($conn, $fecha_inicio, $fecha_fin);
                break;
            case 'clientes_morosos':
                $datos = reporteClientesMorosos($conn);
                break;
            case 'estado_cuentas':
                $datos = reporteEstadoCuentas($conn);
                break;
            case 'historial_compras':
                $datos = reporteHistorialCompras($conn, $fecha_inicio, $fecha_fin);
                break;
            case 'deudas_proveedores':
                $datos = reporteDeudasProveedores($conn);
                break;
            case 'compras_proveedor':
                $datos = reporteComprasProveedor($conn, $fecha_inicio, $fecha_fin);
                break;
            case 'caja_diaria':
                $datos = reporteCajaDiaria($conn, $fecha_inicio, $fecha_fin);
                break;
            case 'gastos_mensuales':
                $datos = reporteGastosMensuales($conn, $fecha_inicio, $fecha_fin);
                break;
            case 'balance_general':
                $datos = reporteBalanceGeneral($conn, $fecha_inicio, $fecha_fin);
                break;
        }
    } catch (Exception $e) {
        error_log("Error obteniendo datos del reporte: " . $e->getMessage());
        $datos = ['error' => $e->getMessage()];
    }
    
    return $datos;
}

// ===================================================
// REPORTES DE VENTAS
// ===================================================

function reporteVentasDiarias($conn, $fecha_inicio, $fecha_fin, $usuario_id, $rol) {
    $datos = [];
    
    $datos['titulo'] = 'Ventas Diarias';
    $datos['subtitulo'] = "Desde: " . date('d/m/Y', strtotime($fecha_inicio)) . " Hasta: " . date('d/m/Y', strtotime($fecha_fin));
    $datos['fecha_generacion'] = date('d/m/Y H:i:s');
    $datos['usuario'] = $_SESSION['usuario_nombre'];
    
    // Resumen
    $query_resumen = "SELECT 
                        COUNT(*) as total_ventas,
                        COALESCE(SUM(total), 0) as total_monto,
                        COALESCE(AVG(total), 0) as promedio_venta,
                        COALESCE(SUM(CASE WHEN tipo_pago = 'contado' THEN total ELSE 0 END), 0) as ventas_contado,
                        COALESCE(SUM(CASE WHEN tipo_pago = 'credito' THEN total ELSE 0 END), 0) as ventas_credito,
                        COALESCE(SUM(CASE WHEN tipo_pago = 'mixto' THEN total ELSE 0 END), 0) as ventas_mixto
                    FROM ventas 
                    WHERE fecha BETWEEN ? AND ? AND anulado = 0";
    
    if ($rol != 'administrador') {
        $query_resumen .= " AND vendedor_id = ?";
        $stmt = $conn->prepare($query_resumen);
        $stmt->bind_param("ssi", $fecha_inicio, $fecha_fin, $usuario_id);
    } else {
        $stmt = $conn->prepare($query_resumen);
        $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['resumen'] = $result->fetch_assoc();
    
    // Ventas por día
    $query_detalle = "SELECT 
                        fecha,
                        COUNT(*) as cantidad,
                        COALESCE(SUM(total), 0) as total,
                        COUNT(CASE WHEN tipo_pago = 'contado' THEN 1 END) as contado_cant,
                        COUNT(CASE WHEN tipo_pago = 'credito' THEN 1 END) as credito_cant,
                        COUNT(CASE WHEN tipo_pago = 'mixto' THEN 1 END) as mixto_cant
                    FROM ventas 
                    WHERE fecha BETWEEN ? AND ? AND anulado = 0";
    
    if ($rol != 'administrador') {
        $query_detalle .= " AND vendedor_id = ?";
        $stmt = $conn->prepare($query_detalle);
        $stmt->bind_param("ssi", $fecha_inicio, $fecha_fin, $usuario_id);
    } else {
        $stmt = $conn->prepare($query_detalle);
        $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['detalle'] = [];
    while ($row = $result->fetch_assoc()) {
        $datos['detalle'][] = $row;
    }
    
    // Top productos vendidos
    $query_top = "SELECT 
                    p.codigo,
                    p.nombre_color,
                    pr.nombre as proveedor,
                    COALESCE(SUM(vd.cantidad_subpaquetes), 0) as cantidad,
                    COALESCE(SUM(vd.subtotal), 0) as total
                FROM venta_detalles vd
                JOIN productos p ON vd.producto_id = p.id
                JOIN proveedores pr ON p.proveedor_id = pr.id
                JOIN ventas v ON vd.venta_id = v.id
                WHERE v.fecha BETWEEN ? AND ? AND v.anulado = 0
                GROUP BY p.id
                ORDER BY cantidad DESC
                LIMIT 10";
    
    $stmt = $conn->prepare($query_top);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['top_productos'] = [];
    while ($row = $result->fetch_assoc()) {
        $datos['top_productos'][] = $row;
    }
    
    return $datos;
}

function reporteVentasSemanales($conn, $fecha_inicio, $fecha_fin, $usuario_id, $rol) {
    $datos = [];
    
    $datos['titulo'] = 'Ventas Semanales';
    $datos['subtitulo'] = "Desde: " . date('d/m/Y', strtotime($fecha_inicio)) . " Hasta: " . date('d/m/Y', strtotime($fecha_fin));
    $datos['fecha_generacion'] = date('d/m/Y H:i:s');
    $datos['usuario'] = $_SESSION['usuario_nombre'];
    
    // Resumen
    $query_resumen = "SELECT 
                        COUNT(*) as total_ventas,
                        COALESCE(SUM(total), 0) as total_monto,
                        COALESCE(AVG(total), 0) as promedio_venta
                    FROM ventas 
                    WHERE fecha BETWEEN ? AND ? AND anulado = 0";
    
    if ($rol != 'administrador') {
        $query_resumen .= " AND vendedor_id = ?";
        $stmt = $conn->prepare($query_resumen);
        $stmt->bind_param("ssi", $fecha_inicio, $fecha_fin, $usuario_id);
    } else {
        $stmt = $conn->prepare($query_resumen);
        $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['resumen'] = $result->fetch_assoc();
    
    // Ventas por semana
    $query_semanal = "SELECT 
                        YEARWEEK(fecha) as semana,
                        MIN(fecha) as fecha_inicio,
                        MAX(fecha) as fecha_fin,
                        COUNT(*) as cantidad,
                        COALESCE(SUM(total), 0) as total
                    FROM ventas 
                    WHERE fecha BETWEEN ? AND ? AND anulado = 0
                    GROUP BY YEARWEEK(fecha)
                    ORDER BY semana";
    
    $stmt = $conn->prepare($query_semanal);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['detalle'] = [];
    while ($row = $result->fetch_assoc()) {
        $datos['detalle'][] = $row;
    }
    
    return $datos;
}

function reporteVentasMensuales($conn, $fecha_inicio, $fecha_fin, $usuario_id, $rol) {
    $datos = [];
    
    $datos['titulo'] = 'Ventas Mensuales';
    $datos['subtitulo'] = "Desde: " . date('d/m/Y', strtotime($fecha_inicio)) . " Hasta: " . date('d/m/Y', strtotime($fecha_fin));
    $datos['fecha_generacion'] = date('d/m/Y H:i:s');
    $datos['usuario'] = $_SESSION['usuario_nombre'];
    
    // Ventas por mes
    $query_mensual = "SELECT 
                        DATE_FORMAT(fecha, '%Y-%m') as mes,
                        DATE_FORMAT(fecha, '%M %Y') as nombre_mes,
                        COUNT(*) as cantidad,
                        COALESCE(SUM(total), 0) as total
                    FROM ventas 
                    WHERE fecha BETWEEN ? AND ? AND anulado = 0
                    GROUP BY DATE_FORMAT(fecha, '%Y-%m')
                    ORDER BY mes";
    
    $stmt = $conn->prepare($query_mensual);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['detalle'] = [];
    $datos['total_general'] = 0;
    
    while ($row = $result->fetch_assoc()) {
        $datos['detalle'][] = $row;
        $datos['total_general'] += $row['total'];
    }
    
    return $datos;
}

function reporteVentasPorVendedor($conn, $fecha_inicio, $fecha_fin) {
    $datos = [];
    
    $datos['titulo'] = 'Ventas por Vendedor';
    $datos['subtitulo'] = "Desde: " . date('d/m/Y', strtotime($fecha_inicio)) . " Hasta: " . date('d/m/Y', strtotime($fecha_fin));
    $datos['fecha_generacion'] = date('d/m/Y H:i:s');
    $datos['usuario'] = $_SESSION['usuario_nombre'];
    
    // Ventas por vendedor
    $query_vendedores = "SELECT 
                            u.nombre,
                            u.codigo,
                            COUNT(v.id) as total_ventas,
                            COALESCE(SUM(v.total), 0) as monto_total,
                            COALESCE(AVG(v.total), 0) as promedio_venta,
                            COUNT(DISTINCT v.cliente_id) as clientes_atendidos
                        FROM usuarios u
                        LEFT JOIN ventas v ON u.id = v.vendedor_id 
                            AND v.fecha BETWEEN ? AND ? 
                            AND v.anulado = 0
                        WHERE u.rol = 'vendedor' AND u.activo = 1
                        GROUP BY u.id
                        ORDER BY monto_total DESC";
    
    $stmt = $conn->prepare($query_vendedores);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['detalle'] = [];
    $datos['total_general'] = 0;
    
    while ($row = $result->fetch_assoc()) {
        $datos['detalle'][] = $row;
        $datos['total_general'] += $row['monto_total'];
    }
    
    return $datos;
}

// ===================================================
// REPORTES DE INVENTARIO
// ===================================================

function reporteInventarioCompleto($conn) {
    $datos = [];
    
    $datos['titulo'] = 'Inventario Completo';
    $datos['subtitulo'] = 'Estado actual del inventario';
    $datos['fecha_generacion'] = date('d/m/Y H:i:s');
    $datos['usuario'] = $_SESSION['usuario_nombre'];
    
    // Resumen
    $query_resumen = "SELECT 
                        COUNT(DISTINCT p.id) as total_productos,
                        COALESCE(SUM(i.total_subpaquetes), 0) as stock_total,
                        COALESCE(SUM(i.paquetes_completos), 0) as paquetes_totales,
                        COALESCE(SUM(i.subpaquetes_sueltos), 0) as subpaquetes_totales,
                        COALESCE(SUM(i.total_subpaquetes * p.precio_menor), 0) as valor_inventario
                    FROM productos p
                    LEFT JOIN inventario i ON p.id = i.producto_id
                    WHERE p.activo = 1";
    
    $result = $conn->query($query_resumen);
    $datos['resumen'] = $result->fetch_assoc();
    
    // Detalle de inventario
    $query_detalle = "SELECT 
                        p.codigo,
                        p.nombre_color,
                        pr.nombre as proveedor,
                        c.nombre as categoria,
                        i.paquetes_completos,
                        i.subpaquetes_sueltos,
                        i.total_subpaquetes,
                        i.subpaquetes_por_paquete,
                        p.precio_menor,
                        p.precio_mayor,
                        i.ubicacion,
                        i.fecha_ultimo_ingreso,
                        i.fecha_ultima_salida
                    FROM productos p
                    JOIN proveedores pr ON p.proveedor_id = pr.id
                    JOIN categorias c ON p.categoria_id = c.id
                    LEFT JOIN inventario i ON p.id = i.producto_id
                    WHERE p.activo = 1
                    ORDER BY pr.nombre, p.codigo";
    
    $result = $conn->query($query_detalle);
    $datos['detalle'] = [];
    while ($row = $result->fetch_assoc()) {
        $datos['detalle'][] = $row;
    }
    
    return $datos;
}

function reporteStockBajo($conn) {
    $datos = [];
    
    $datos['titulo'] = 'Productos con Stock Bajo';
    $datos['subtitulo'] = 'Productos que requieren reposición';
    $datos['fecha_generacion'] = date('d/m/Y H:i:s');
    $datos['usuario'] = $_SESSION['usuario_nombre'];
    
    // Resumen
    $query_resumen = "SELECT 
                        COUNT(*) as total_criticos,
                        COALESCE(SUM(CASE WHEN i.total_subpaquetes < 20 THEN 1 ELSE 0 END), 0) as criticos,
                        COALESCE(SUM(CASE WHEN i.total_subpaquetes BETWEEN 20 AND 49 THEN 1 ELSE 0 END), 0) as bajos
                    FROM productos p
                    JOIN inventario i ON p.id = i.producto_id
                    WHERE i.total_subpaquetes < 50 AND p.activo = 1";
    
    $result = $conn->query($query_resumen);
    $datos['resumen'] = $result->fetch_assoc();
    
    // Detalle
    $query_detalle = "SELECT 
                        p.codigo,
                        p.nombre_color,
                        pr.nombre as proveedor,
                        i.total_subpaquetes as stock_actual,
                        i.ubicacion,
                        CASE 
                            WHEN i.total_subpaquetes < 20 THEN 'CRÍTICO'
                            ELSE 'BAJO'
                        END as nivel_alerta
                    FROM productos p
                    JOIN inventario i ON p.id = i.producto_id
                    JOIN proveedores pr ON p.proveedor_id = pr.id
                    WHERE i.total_subpaquetes < 50 AND p.activo = 1
                    ORDER BY i.total_subpaquetes ASC";
    
    $result = $conn->query($query_detalle);
    $datos['detalle'] = [];
    while ($row = $result->fetch_assoc()) {
        $datos['detalle'][] = $row;
    }
    
    return $datos;
}

function reporteMovimientosInventario($conn, $fecha_inicio, $fecha_fin) {
    $datos = [];
    
    $datos['titulo'] = 'Movimientos de Inventario';
    $datos['subtitulo'] = "Desde: " . date('d/m/Y', strtotime($fecha_inicio)) . " Hasta: " . date('d/m/Y', strtotime($fecha_fin));
    $datos['fecha_generacion'] = date('d/m/Y H:i:s');
    $datos['usuario'] = $_SESSION['usuario_nombre'];
    
    // Resumen por tipo
    $query_resumen = "SELECT 
                        tipo_movimiento,
                        COUNT(*) as cantidad,
                        COALESCE(SUM(ABS(diferencia)), 0) as unidades
                    FROM historial_inventario
                    WHERE DATE(fecha_hora) BETWEEN ? AND ?
                    GROUP BY tipo_movimiento";
    
    $stmt = $conn->prepare($query_resumen);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['resumen'] = [];
    while ($row = $result->fetch_assoc()) {
        $datos['resumen'][$row['tipo_movimiento']] = $row;
    }
    
    // Detalle de movimientos
    $query_detalle = "SELECT 
                        hi.*,
                        p.codigo as producto_codigo,
                        p.nombre_color as producto_nombre,
                        u.nombre as usuario_nombre
                    FROM historial_inventario hi
                    JOIN productos p ON hi.producto_id = p.id
                    JOIN usuarios u ON hi.usuario_id = u.id
                    WHERE DATE(hi.fecha_hora) BETWEEN ? AND ?
                    ORDER BY hi.fecha_hora DESC
                    LIMIT 500";
    
    $stmt = $conn->prepare($query_detalle);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['detalle'] = [];
    while ($row = $result->fetch_assoc()) {
        $datos['detalle'][] = $row;
    }
    
    return $datos;
}

// ===================================================
// REPORTES DE CLIENTES Y COBROS
// ===================================================

function reporteClientesMorosos($conn) {
    $datos = [];
    
    $datos['titulo'] = 'Clientes Morosos';
    $datos['subtitulo'] = 'Clientes con deudas vencidas';
    $datos['fecha_generacion'] = date('d/m/Y H:i:s');
    $datos['usuario'] = $_SESSION['usuario_nombre'];
    
    // Resumen
    $query_resumen = "SELECT 
                        COUNT(DISTINCT c.id) as total_clientes,
                        COALESCE(SUM(c.saldo_actual), 0) as deuda_total,
                        COALESCE(AVG(c.saldo_actual), 0) as promedio_deuda
                    FROM clientes c
                    WHERE c.saldo_actual > 0";
    
    $result = $conn->query($query_resumen);
    $datos['resumen'] = $result->fetch_assoc();
    
    // Detalle de clientes morosos
    $query_detalle = "SELECT 
                        c.codigo,
                        c.nombre,
                        c.ciudad,
                        c.telefono,
                        c.saldo_actual,
                        c.limite_credito,
                        COUNT(cc.id) as facturas_pendientes,
                        MIN(cc.fecha_vencimiento) as vencimiento_mas_antiguo,
                        DATEDIFF(CURDATE(), MIN(cc.fecha_vencimiento)) as dias_mora
                    FROM clientes c
                    JOIN clientes_cuentas_cobrar cc ON c.id = cc.cliente_id
                    WHERE c.saldo_actual > 0 AND cc.estado = 'pendiente'
                    GROUP BY c.id
                    ORDER BY dias_mora DESC";
    
    $result = $conn->query($query_detalle);
    $datos['detalle'] = [];
    while ($row = $result->fetch_assoc()) {
        $datos['detalle'][] = $row;
    }
    
    return $datos;
}

function reporteEstadoCuentas($conn) {
    $datos = [];
    
    $datos['titulo'] = 'Estado de Cuentas por Cobrar';
    $datos['subtitulo'] = 'Situación actual de créditos';
    $datos['fecha_generacion'] = date('d/m/Y H:i:s');
    $datos['usuario'] = $_SESSION['usuario_nombre'];
    
    // Resumen por estado
    $query_resumen = "SELECT 
                        estado,
                        COUNT(*) as cantidad,
                        COALESCE(SUM(saldo_pendiente), 0) as monto
                    FROM clientes_cuentas_cobrar
                    GROUP BY estado";
    
    $result = $conn->query($query_resumen);
    $datos['resumen'] = [];
    while ($row = $result->fetch_assoc()) {
        $datos['resumen'][$row['estado']] = $row;
    }
    
    // Detalle de cuentas por cobrar
    $query_detalle = "SELECT 
                        c.codigo as cliente_codigo,
                        c.nombre as cliente_nombre,
                        v.codigo_venta,
                        cc.monto_total,
                        cc.saldo_pendiente,
                        cc.fecha_vencimiento,
                        cc.estado,
                        DATEDIFF(CURDATE(), cc.fecha_vencimiento) as dias_vencimiento
                    FROM clientes_cuentas_cobrar cc
                    JOIN clientes c ON cc.cliente_id = c.id
                    JOIN ventas v ON cc.venta_id = v.id
                    WHERE cc.estado = 'pendiente'
                    ORDER BY cc.fecha_vencimiento ASC";
    
    $result = $conn->query($query_detalle);
    $datos['detalle'] = [];
    while ($row = $result->fetch_assoc()) {
        $datos['detalle'][] = $row;
    }
    
    return $datos;
}

function reporteHistorialCompras($conn, $fecha_inicio, $fecha_fin) {
    $datos = [];
    
    $datos['titulo'] = 'Historial de Compras por Cliente';
    $datos['subtitulo'] = "Desde: " . date('d/m/Y', strtotime($fecha_inicio)) . " Hasta: " . date('d/m/Y', strtotime($fecha_fin));
    $datos['fecha_generacion'] = date('d/m/Y H:i:s');
    $datos['usuario'] = $_SESSION['usuario_nombre'];
    
    // Top clientes
    $query_top = "SELECT 
                    c.nombre,
                    c.codigo,
                    COUNT(v.id) as compras,
                    COALESCE(SUM(v.total), 0) as total_gastado,
                    COALESCE(AVG(v.total), 0) as ticket_promedio
                FROM clientes c
                JOIN ventas v ON c.id = v.cliente_id
                WHERE v.fecha BETWEEN ? AND ? AND v.anulado = 0
                GROUP BY c.id
                ORDER BY total_gastado DESC
                LIMIT 20";
    
    $stmt = $conn->prepare($query_top);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['top_clientes'] = [];
    while ($row = $result->fetch_assoc()) {
        $datos['top_clientes'][] = $row;
    }
    
    return $datos;
}

// ===================================================
// REPORTES DE PROVEEDORES
// ===================================================

function reporteDeudasProveedores($conn) {
    $datos = [];
    
    $datos['titulo'] = 'Deudas con Proveedores';
    $datos['subtitulo'] = 'Estado actual de cuentas por pagar';
    $datos['fecha_generacion'] = date('d/m/Y H:i:s');
    $datos['usuario'] = $_SESSION['usuario_nombre'];
    
    // Resumen
    $query_resumen = "SELECT 
                        COUNT(*) as total_proveedores,
                        COALESCE(SUM(saldo_actual), 0) as deuda_total,
                        COALESCE(AVG(saldo_actual), 0) as promedio_deuda
                    FROM proveedores
                    WHERE saldo_actual > 0";
    
    $result = $conn->query($query_resumen);
    $datos['resumen'] = $result->fetch_assoc();
    
    // Detalle
    $query_detalle = "SELECT 
                        codigo,
                        nombre,
                        ciudad,
                        telefono,
                        saldo_actual,
                        credito_limite,
                        CASE 
                            WHEN saldo_actual > credito_limite THEN 'EXCEDIDO'
                            ELSE 'NORMAL'
                        END as estado
                    FROM proveedores
                    WHERE saldo_actual > 0
                    ORDER BY saldo_actual DESC";
    
    $result = $conn->query($query_detalle);
    $datos['detalle'] = [];
    while ($row = $result->fetch_assoc()) {
        $datos['detalle'][] = $row;
    }
    
    return $datos;
}

function reporteComprasProveedor($conn, $fecha_inicio, $fecha_fin) {
    $datos = [];
    
    $datos['titulo'] = 'Compras por Proveedor';
    $datos['subtitulo'] = "Desde: " . date('d/m/Y', strtotime($fecha_inicio)) . " Hasta: " . date('d/m/Y', strtotime($fecha_fin));
    $datos['fecha_generacion'] = date('d/m/Y H:i:s');
    $datos['usuario'] = $_SESSION['usuario_nombre'];
    
    // Compras por proveedor
    $query_compras = "SELECT 
                        p.codigo,
                        p.nombre,
                        p.ciudad,
                        COALESCE(SUM(pec.compra), 0) as total_compras,
                        COALESCE(SUM(pec.adelanto), 0) as total_pagado,
                        COALESCE(SUM(pec.saldo), 0) as saldo_actual
                    FROM proveedores p
                    LEFT JOIN proveedores_estado_cuentas pec ON p.id = pec.proveedor_id
                        AND pec.fecha BETWEEN ? AND ?
                    WHERE p.activo = 1
                    GROUP BY p.id
                    ORDER BY total_compras DESC";
    
    $stmt = $conn->prepare($query_compras);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['detalle'] = [];
    while ($row = $result->fetch_assoc()) {
        $datos['detalle'][] = $row;
    }
    
    return $datos;
}

// ===================================================
// REPORTES FINANCIEROS
// ===================================================

function reporteCajaDiaria($conn, $fecha_inicio, $fecha_fin) {
    $datos = [];
    
    $datos['titulo'] = 'Caja Diaria';
    $datos['subtitulo'] = "Desde: " . date('d/m/Y', strtotime($fecha_inicio)) . " Hasta: " . date('d/m/Y', strtotime($fecha_fin));
    $datos['fecha_generacion'] = date('d/m/Y H:i:s');
    $datos['usuario'] = $_SESSION['usuario_nombre'];
    
    // Resumen por día
    $query_diario = "SELECT 
                        fecha,
                        COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as ingresos,
                        COALESCE(SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END), 0) as gastos,
                        COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END), 0) as balance,
                        COUNT(*) as movimientos
                    FROM movimientos_caja
                    WHERE fecha BETWEEN ? AND ?
                    GROUP BY fecha
                    ORDER BY fecha DESC";
    
    $stmt = $conn->prepare($query_diario);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['detalle'] = [];
    $datos['total_ingresos'] = 0;
    $datos['total_gastos'] = 0;
    $datos['balance_final'] = 0;
    
    while ($row = $result->fetch_assoc()) {
        $datos['detalle'][] = $row;
        $datos['total_ingresos'] += $row['ingresos'];
        $datos['total_gastos'] += $row['gastos'];
        $datos['balance_final'] += $row['balance'];
    }
    
    return $datos;
}

function reporteGastosMensuales($conn, $fecha_inicio, $fecha_fin) {
    $datos = [];
    
    $datos['titulo'] = 'Gastos Mensuales';
    $datos['subtitulo'] = "Desde: " . date('d/m/Y', strtotime($fecha_inicio)) . " Hasta: " . date('d/m/Y', strtotime($fecha_fin));
    $datos['fecha_generacion'] = date('d/m/Y H:i:s');
    $datos['usuario'] = $_SESSION['usuario_nombre'];
    
    // Gastos por categoría
    $query_categorias = "SELECT 
                            categoria,
                            COUNT(*) as cantidad,
                            COALESCE(SUM(monto), 0) as total,
                            COALESCE(AVG(monto), 0) as promedio
                        FROM movimientos_caja
                        WHERE tipo = 'gasto' 
                            AND fecha BETWEEN ? AND ?
                        GROUP BY categoria
                        ORDER BY total DESC";
    
    $stmt = $conn->prepare($query_categorias);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['categorias'] = [];
    $datos['total_gastos'] = 0;
    
    while ($row = $result->fetch_assoc()) {
        $datos['categorias'][] = $row;
        $datos['total_gastos'] += $row['total'];
    }
    
    // Detalle de gastos
    $query_detalle = "SELECT 
                        fecha,
                        hora,
                        descripcion,
                        categoria,
                        monto,
                        u.nombre as usuario
                    FROM movimientos_caja mc
                    JOIN usuarios u ON mc.usuario_id = u.id
                    WHERE mc.tipo = 'gasto' 
                        AND mc.fecha BETWEEN ? AND ?
                    ORDER BY mc.fecha DESC, mc.hora DESC
                    LIMIT 500";
    
    $stmt = $conn->prepare($query_detalle);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos['detalle'] = [];
    while ($row = $result->fetch_assoc()) {
        $datos['detalle'][] = $row;
    }
    
    return $datos;
}

function reporteBalanceGeneral($conn, $fecha_inicio, $fecha_fin) {
    $datos = [];
    
    $datos['titulo'] = 'Balance General';
    $datos['subtitulo'] = "Período: " . date('d/m/Y', strtotime($fecha_inicio)) . " - " . date('d/m/Y', strtotime($fecha_fin));
    $datos['fecha_generacion'] = date('d/m/Y H:i:s');
    $datos['usuario'] = $_SESSION['usuario_nombre'];
    
    // ===== ACTIVOS =====
    
    // 1. Efectivo en caja
    $query_efectivo = "SELECT 
                        COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END), 0) as saldo_actual
                      FROM movimientos_caja
                      WHERE fecha <= ?";
    
    $stmt = $conn->prepare($query_efectivo);
    $stmt->bind_param("s", $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $efectivo = $result->fetch_assoc();
    $datos['activos']['efectivo'] = $efectivo['saldo_actual'] ?? 0;
    
    // 2. Cuentas por cobrar
    $query_cxc = "SELECT COALESCE(SUM(saldo_pendiente), 0) as total
                  FROM clientes_cuentas_cobrar
                  WHERE estado = 'pendiente'";
    $result = $conn->query($query_cxc);
    $cxc = $result->fetch_assoc();
    $datos['activos']['cuentas_por_cobrar'] = $cxc['total'] ?? 0;
    
    // 3. Valor del inventario
    $query_inventario = "SELECT 
                            COALESCE(SUM(i.total_subpaquetes * p.precio_menor), 0) as valor_inventario
                        FROM productos p
                        JOIN inventario i ON p.id = i.producto_id
                        WHERE p.activo = 1";
    $result = $conn->query($query_inventario);
    $inventario = $result->fetch_assoc();
    $datos['activos']['inventario'] = $inventario['valor_inventario'] ?? 0;
    
    // ===== PASIVOS =====
    
    // 4. Cuentas por pagar a proveedores
    $query_cxp = "SELECT COALESCE(SUM(saldo_actual), 0) as total
                  FROM proveedores
                  WHERE saldo_actual > 0";
    $result = $conn->query($query_cxp);
    $cxp = $result->fetch_assoc();
    $datos['pasivos']['cuentas_por_pagar'] = $cxp['total'] ?? 0;
    
    // ===== PATRIMONIO =====
    $datos['patrimonio'] = $datos['activos']['efectivo'] + 
                           $datos['activos']['cuentas_por_cobrar'] + 
                           $datos['activos']['inventario'] - 
                           $datos['pasivos']['cuentas_por_pagar'];
    
    // ===== ESTADO DE RESULTADOS =====
    
    // Ventas del período
    $query_ventas_periodo = "SELECT 
                                COALESCE(SUM(total), 0) as total_ventas
                            FROM ventas
                            WHERE fecha BETWEEN ? AND ? AND anulado = 0";
    $stmt = $conn->prepare($query_ventas_periodo);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $ventas = $result->fetch_assoc();
    $datos['resultados']['ventas'] = $ventas['total_ventas'] ?? 0;
    
    // Gastos del período
    $query_gastos_periodo = "SELECT COALESCE(SUM(monto), 0) as total_gastos
                            FROM movimientos_caja
                            WHERE tipo = 'gasto' 
                                AND fecha BETWEEN ? AND ?";
    $stmt = $conn->prepare($query_gastos_periodo);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $gastos = $result->fetch_assoc();
    $datos['resultados']['gastos'] = $gastos['total_gastos'] ?? 0;
    
    // Utilidad
    $datos['resultados']['utilidad'] = $datos['resultados']['ventas'] - $datos['resultados']['gastos'];
    
    return $datos;
}

// ===================================================
// GENERADOR DE PDF - CORREGIDO SIN ERRORES DE TCPDF
// ===================================================

function generarPDF($titulo, $datos, $fecha_inicio, $fecha_fin, $orientacion, $incluir_graficos, $incluir_tablas, $incluir_resumen) {
    
    // Verificar si existe TCPDF
    if (!file_exists('vendor/tecnickcom/tcpdf/tcpdf.php')) {
        die('Error: TCPDF no está instalado. Por favor, ejecute: composer require tecnickcom/tcpdf');
    }
    
    // Limpiar cualquier salida previa
    if (ob_get_length()) ob_clean();
    
    // Incluir TCPDF de manera segura
    define('K_TCPDF_EXTERNAL_CONFIG', true);
    define('PDF_PAGE_FORMAT', 'A4');
    define('PDF_PAGE_ORIENTATION', $orientacion == 'horizontal' ? 'L' : 'P');
    define('PDF_UNIT', 'mm');
    define('PDF_MARGIN_TOP', 15);
    define('PDF_MARGIN_BOTTOM', 15);
    define('PDF_MARGIN_LEFT', 15);
    define('PDF_MARGIN_RIGHT', 15);
    
    require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
    
    // Crear nuevo PDF
    $pdf = new TCPDF(
        $orientacion == 'horizontal' ? 'L' : 'P', 
        PDF_UNIT, 
        PDF_PAGE_FORMAT, 
        true, 
        'UTF-8', 
        false
    );
    
    // Configurar documento
    $pdf->SetCreator(EMPRESA_NOMBRE);
    $pdf->SetAuthor($_SESSION['usuario_nombre'] ?? 'Sistema');
    $pdf->SetTitle($titulo);
    $pdf->SetSubject('Reporte del Sistema');
    $pdf->SetKeywords('PDF, reporte, ' . $titulo);
    
    // Eliminar cabecera y pie por defecto
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Configurar márgenes
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Agregar página
    $pdf->AddPage();
    
    // Establecer fuente
    $pdf->SetFont('helvetica', '', 10);
    
    // ===== ENCABEZADO =====
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(40, 167, 69);
    $pdf->Cell(0, 10, EMPRESA_NOMBRE, 0, 1, 'L');
    
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 8, $titulo, 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(0, 6, 'Período: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin)), 0, 1, 'L');
    $pdf->Cell(0, 6, 'Generado: ' . date('d/m/Y H:i:s'), 0, 1, 'L');
    $pdf->Cell(0, 6, 'Usuario: ' . ($_SESSION['usuario_nombre'] ?? 'Sistema'), 0, 1, 'L');
    
    $pdf->Ln(5);
    $pdf->SetDrawColor(40, 167, 69);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(15, $pdf->GetY(), $pdf->getPageWidth() - 15, $pdf->GetY());
    $pdf->Ln(10);
    
    // ===== RESUMEN =====
    if ($incluir_resumen && isset($datos['resumen']) && !empty($datos['resumen'])) {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(40, 167, 69);
        $pdf->Cell(0, 8, 'RESUMEN', 0, 1, 'L');
        $pdf->Ln(2);
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->SetFillColor(248, 249, 250);
        
        $contador = 0;
        foreach ($datos['resumen'] as $key => $value) {
            if ($contador % 3 == 0 && $contador > 0) {
                $pdf->Ln(8);
                $x = $pdf->GetX();
            }
            
            $label = ucwords(str_replace('_', ' ', $key));
            if (strpos($key, 'monto') !== false || strpos($key, 'total') !== false || strpos($key, 'deuda') !== false) {
                $valor = 'Bs ' . number_format($value, 2, ',', '.');
            } else {
                $valor = number_format($value, 0, ',', '.');
            }
            
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY($x + ($contador % 3) * 65, $y + floor($contador / 3) * 12);
            $pdf->Cell(25, 6, $label . ':', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(40, 6, $valor, 0, 0, 'L');
            
            $contador++;
        }
        $pdf->Ln(15);
    }
    
    // ===== TABLA DE DETALLE =====
    if ($incluir_tablas && isset($datos['detalle']) && !empty($datos['detalle'])) {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(40, 167, 69);
        $pdf->Cell(0, 8, 'DETALLE', 0, 1, 'L');
        $pdf->Ln(2);
        
        // Obtener las primeras 50 filas para no saturar el PDF
        $filas_mostrar = array_slice($datos['detalle'], 0, 50);
        
        if (!empty($filas_mostrar)) {
            // Cabeceras
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(40, 167, 69);
            $pdf->SetTextColor(255, 255, 255);
            
            $columnas = array_keys($filas_mostrar[0]);
            $ancho_columna = ($pdf->getPageWidth() - 30) / count($columnas);
            
            foreach ($columnas as $columna) {
                $label = ucwords(str_replace('_', ' ', $columna));
                $pdf->Cell($ancho_columna, 8, $label, 1, 0, 'C', 1);
            }
            $pdf->Ln();
            
            // Datos
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFillColor(248, 249, 250);
            
            $fill = false;
            foreach ($filas_mostrar as $fila) {
                foreach ($fila as $key => $valor) {
                    if (strpos($key, 'fecha') !== false && $valor) {
                        $valor = date('d/m/Y', strtotime($valor));
                    } elseif (strpos($key, 'monto') !== false || strpos($key, 'total') !== false || strpos($key, 'deuda') !== false || strpos($key, 'precio') !== false) {
                        $valor = 'Bs ' . number_format($valor, 2, ',', '.');
                    } elseif (strpos($key, 'cantidad') !== false || strpos($key, 'stock') !== false) {
                        $valor = number_format($valor, 0, ',', '.');
                    }
                    $pdf->Cell($ancho_columna, 7, substr($valor, 0, 30), 1, 0, 'L', $fill);
                }
                $pdf->Ln();
                $fill = !$fill;
            }
            
            if (count($datos['detalle']) > 50) {
                $pdf->Ln(5);
                $pdf->SetFont('helvetica', 'I', 9);
                $pdf->SetTextColor(108, 117, 125);
                $pdf->Cell(0, 6, '... y ' . (count($datos['detalle']) - 50) . ' registros más', 0, 1, 'L');
            }
        }
    }
    
    // ===== TOP PRODUCTOS =====
    if (isset($datos['top_productos']) && !empty($datos['top_productos'])) {
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(40, 167, 69);
        $pdf->Cell(0, 8, 'PRODUCTOS MÁS VENDIDOS', 0, 1, 'L');
        $pdf->Ln(2);
        
        // Cabeceras
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(40, 167, 69);
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(30, 8, 'Código', 1, 0, 'C', 1);
        $pdf->Cell(70, 8, 'Producto', 1, 0, 'C', 1);
        $pdf->Cell(50, 8, 'Proveedor', 1, 0, 'C', 1);
        $pdf->Cell(30, 8, 'Cantidad', 1, 0, 'C', 1);
        $pdf->Cell(40, 8, 'Total', 1, 0, 'C', 1);
        $pdf->Ln();
        
        // Datos
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(248, 249, 250);
        
        $fill = false;
        foreach ($datos['top_productos'] as $producto) {
            $pdf->Cell(30, 7, substr($producto['codigo'], 0, 15), 1, 0, 'L', $fill);
            $pdf->Cell(70, 7, substr($producto['nombre_color'], 0, 30), 1, 0, 'L', $fill);
            $pdf->Cell(50, 7, substr($producto['proveedor'] ?? '', 0, 25), 1, 0, 'L', $fill);
            $pdf->Cell(30, 7, number_format($producto['cantidad'] ?? 0, 0, ',', '.'), 1, 0, 'R', $fill);
            $pdf->Cell(40, 7, 'Bs ' . number_format($producto['total'] ?? 0, 2, ',', '.'), 1, 0, 'R', $fill);
            $pdf->Ln();
            $fill = !$fill;
        }
    }
    
    // ===== BALANCE GENERAL =====
    if (isset($datos['activos']) && isset($datos['pasivos'])) {
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(40, 167, 69);
        $pdf->Cell(0, 8, 'BALANCE GENERAL', 0, 1, 'L');
        $pdf->Ln(2);
        
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);
        
        // Activos
        $pdf->SetFillColor(40, 167, 69, 0.1);
        $pdf->Cell(($pdf->getPageWidth() - 30) / 2, 8, 'ACTIVOS', 0, 0, 'L', true);
        $pdf->Cell(($pdf->getPageWidth() - 30) / 2, 8, 'PASIVOS Y PATRIMONIO', 0, 1, 'L', true);
        
        $pdf->SetFont('helvetica', '', 10);
        $y = $pdf->GetY();
        
        // Columna Activos
        $pdf->SetXY(15, $y);
        $pdf->Cell(80, 7, 'Efectivo en Caja:', 0, 0, 'L');
        $pdf->Cell(40, 7, 'Bs ' . number_format($datos['activos']['efectivo'] ?? 0, 2, ',', '.'), 0, 1, 'R');
        
        $pdf->SetX(15);
        $pdf->Cell(80, 7, 'Cuentas por Cobrar:', 0, 0, 'L');
        $pdf->Cell(40, 7, 'Bs ' . number_format($datos['activos']['cuentas_por_cobrar'] ?? 0, 2, ',', '.'), 0, 1, 'R');
        
        $pdf->SetX(15);
        $pdf->Cell(80, 7, 'Inventario:', 0, 0, 'L');
        $pdf->Cell(40, 7, 'Bs ' . number_format($datos['activos']['inventario'] ?? 0, 2, ',', '.'), 0, 1, 'R');
        
        $pdf->SetX(15);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(80, 7, 'TOTAL ACTIVOS:', 0, 0, 'L');
        $pdf->Cell(40, 7, 'Bs ' . number_format(
            ($datos['activos']['efectivo'] ?? 0) + 
            ($datos['activos']['cuentas_por_cobrar'] ?? 0) + 
            ($datos['activos']['inventario'] ?? 0), 2, ',', '.'), 0, 1, 'R');
        
        // Columna Pasivos
        $pdf->SetXY(15 + ($pdf->getPageWidth() - 30) / 2, $y);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(80, 7, 'Cuentas por Pagar:', 0, 0, 'L');
        $pdf->Cell(40, 7, 'Bs ' . number_format($datos['pasivos']['cuentas_por_pagar'] ?? 0, 2, ',', '.'), 0, 1, 'R');
        
        $pdf->SetX(15 + ($pdf->getPageWidth() - 30) / 2);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(80, 7, 'TOTAL PASIVOS:', 0, 0, 'L');
        $pdf->Cell(40, 7, 'Bs ' . number_format($datos['pasivos']['cuentas_por_pagar'] ?? 0, 2, ',', '.'), 0, 1, 'R');
        
        $pdf->Ln(5);
        $pdf->SetX(15 + ($pdf->getPageWidth() - 30) / 2);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(80, 7, 'PATRIMONIO:', 0, 0, 'L');
        $pdf->Cell(40, 7, 'Bs ' . number_format($datos['patrimonio'] ?? 0, 2, ',', '.'), 0, 1, 'R');
    }
    
    // ===== PIE DE PÁGINA =====
    $pdf->Ln(20);
    $pdf->SetDrawColor(108, 117, 125);
    $pdf->SetLineWidth(0.2);
    $pdf->Line(15, $pdf->GetY(), $pdf->getPageWidth() - 15, $pdf->GetY());
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(0, 4, EMPRESA_NOMBRE . ' - Sistema de Gestión', 0, 1, 'C');
    $pdf->Cell(0, 4, 'Reporte generado el ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    $pdf->Cell(0, 4, 'Página 1 de 1', 0, 1, 'C');
    
    // Salida del PDF
    $pdf->Output($titulo . '_' . date('Ymd_His') . '.pdf', 'D');
    exit();
}

// ===================================================
// GENERADOR DE EXCEL
// ===================================================

function generarExcel($titulo, $datos, $tipo_reporte, $fecha_inicio, $fecha_fin) {
    // Limpiar cualquier salida previa
    if (ob_get_length()) ob_clean();
    
    // Cabeceras para descargar Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $titulo . '_' . date('Ymd_His') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Iniciar HTML para Excel
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>' . $titulo . '</title>';
    echo '<style>';
    echo 'td { border: 1px solid #dee2e6; padding: 6px; }';
    echo 'th { background-color: #28a745; color: white; padding: 8px; border: 1px solid #1e7e34; }';
    echo '.titulo { font-size: 20pt; font-weight: bold; color: #28a745; }';
    echo '.subtitulo { font-size: 12pt; color: #6c757d; }';
    echo '.resumen { background-color: #f8f9fa; font-weight: bold; }';
    echo '.moneda { text-align: right; }';
    echo '.numero { text-align: right; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Encabezado
    echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
    echo '<tr><td colspan="10" style="border: none;">';
    echo '<span class="titulo">' . EMPRESA_NOMBRE . '</span><br>';
    echo '<span class="subtitulo">' . $titulo . '</span><br>';
    echo 'Período: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin)) . '<br>';
    echo 'Fecha de generación: ' . date('d/m/Y H:i:s') . '<br>';
    echo 'Usuario: ' . $_SESSION['usuario_nombre'];
    echo '</td></tr>';
    echo '<tr><td colspan="10" style="border: none;"><hr></td></tr>';
    
    // Resumen
    if (isset($datos['resumen'])) {
        echo '<tr><td colspan="10" style="border: none;"><h2>RESUMEN</h2></td></tr>';
        echo '<tr>';
        $contador = 0;
        foreach ($datos['resumen'] as $key => $value) {
            if ($contador % 4 == 0 && $contador > 0) {
                echo '</tr><tr>';
            }
            $label = ucwords(str_replace('_', ' ', $key));
            if (strpos($key, 'monto') !== false || strpos($key, 'total') !== false || strpos($key, 'deuda') !== false) {
                $valor = number_format($value, 2, ',', '.') . ' Bs';
            } else {
                $valor = number_format($value, 0, ',', '.');
            }
            echo '<td style="border: none; width: 25%;"><strong>' . $label . ':</strong> ' . $valor . '</td>';
            $contador++;
        }
        echo '</tr>';
        echo '<tr><td colspan="10" style="border: none;">&nbsp;</td></tr>';
    }
    
    // Tabla de detalle
    if (isset($datos['detalle']) && !empty($datos['detalle'])) {
        echo '<tr><td colspan="10" style="border: none;"><h2>DETALLE</h2></td></tr>';
        
        // Cabeceras
        echo '<tr>';
        foreach (array_keys($datos['detalle'][0]) as $columna) {
            $label = ucwords(str_replace('_', ' ', $columna));
            echo '<th>' . $label . '</th>';
        }
        echo '</tr>';
        
        // Datos
        foreach ($datos['detalle'] as $fila) {
            echo '<tr>';
            foreach ($fila as $key => $valor) {
                if (strpos($key, 'fecha') !== false && $valor) {
                    echo '<td>' . date('d/m/Y', strtotime($valor)) . '</td>';
                } elseif (strpos($key, 'monto') !== false || strpos($key, 'total') !== false || strpos($key, 'deuda') !== false || strpos($key, 'precio') !== false) {
                    echo '<td class="moneda">' . number_format($valor, 2, ',', '.') . '</td>';
                } elseif (strpos($key, 'cantidad') !== false || strpos($key, 'stock') !== false) {
                    echo '<td class="numero">' . number_format($valor, 0, ',', '.') . '</td>';
                } else {
                    echo '<td>' . htmlspecialchars($valor) . '</td>';
                }
            }
            echo '</tr>';
        }
        echo '<tr><td colspan="10" style="border: none;">&nbsp;</td></tr>';
    }
    
    // Top productos
    if (isset($datos['top_productos']) && !empty($datos['top_productos'])) {
        echo '<tr><td colspan="10" style="border: none;"><h2>PRODUCTOS MÁS VENDIDOS</h2></td></tr>';
        echo '<tr>';
        echo '<th>Código</th>';
        echo '<th>Producto</th>';
        echo '<th>Proveedor</th>';
        echo '<th>Cantidad</th>';
        echo '<th>Total</th>';
        echo '</tr>';
        
        foreach ($datos['top_productos'] as $producto) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($producto['codigo']) . '</td>';
            echo '<td>' . htmlspecialchars($producto['nombre_color']) . '</td>';
            echo '<td>' . htmlspecialchars($producto['proveedor'] ?? '') . '</td>';
            echo '<td class="numero">' . number_format($producto['cantidad'], 0, ',', '.') . '</td>';
            echo '<td class="moneda">' . number_format($producto['total'], 2, ',', '.') . '</td>';
            echo '</tr>';
        }
    }
    
    // Totales
    if (isset($datos['total_general'])) {
        echo '<tr><td colspan="10" style="border: none;">&nbsp;</td></tr>';
        echo '<tr>';
        echo '<td colspan="4" style="text-align: right; font-weight: bold;">TOTAL GENERAL:</td>';
        echo '<td class="moneda" style="font-weight: bold; background-color: #f8f9fa;">' . number_format($datos['total_general'], 2, ',', '.') . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
    exit();
}

// ===================================================
// GENERADOR DE CSV
// ===================================================

function generarCSV($titulo, $datos, $tipo_reporte, $fecha_inicio, $fecha_fin) {
    // Limpiar cualquier salida previa
    if (ob_get_length()) ob_clean();
    
    // Cabeceras para descargar CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $titulo . '_' . date('Ymd_His') . '.csv"');
    
    // Crear archivo CSV
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezado del reporte
    fputcsv($output, [EMPRESA_NOMBRE]);
    fputcsv($output, [$titulo]);
    fputcsv($output, ['Período:', date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin))]);
    fputcsv($output, ['Generado:', date('d/m/Y H:i:s')]);
    fputcsv($output, ['Usuario:', $_SESSION['usuario_nombre']]);
    fputcsv($output, []);
    
    // Resumen
    if (isset($datos['resumen'])) {
        fputcsv($output, ['RESUMEN']);
        foreach ($datos['resumen'] as $key => $value) {
            $label = ucwords(str_replace('_', ' ', $key));
            if (strpos($key, 'monto') !== false || strpos($key, 'total') !== false || strpos($key, 'deuda') !== false) {
                $valor = number_format($value, 2, ',', '.') . ' Bs';
            } else {
                $valor = number_format($value, 0, ',', '.');
            }
            fputcsv($output, [$label, $valor]);
        }
        fputcsv($output, []);
    }
    
    // Detalle
    if (isset($datos['detalle']) && !empty($datos['detalle'])) {
        fputcsv($output, ['DETALLE']);
        
        // Cabeceras
        $cabeceras = array_keys($datos['detalle'][0]);
        fputcsv($output, $cabeceras);
        
        // Datos
        foreach ($datos['detalle'] as $fila) {
            $linea = [];
            foreach ($fila as $key => $valor) {
                if (strpos($key, 'fecha') !== false && $valor) {
                    $linea[] = date('d/m/Y', strtotime($valor));
                } elseif (strpos($key, 'monto') !== false || strpos($key, 'total') !== false || strpos($key, 'deuda') !== false || strpos($key, 'precio') !== false) {
                    $linea[] = number_format($valor, 2, ',', '.');
                } else {
                    $linea[] = $valor;
                }
            }
            fputcsv($output, $linea);
        }
    }
    
    fclose($output);
    exit();
}

// ===================================================
// GENERADOR DE HTML (Vista previa)
// ===================================================

function generarHTML($titulo, $datos, $fecha_inicio, $fecha_fin, $incluir_graficos, $incluir_tablas, $incluir_resumen) {
    // Limpiar cualquier salida previa
    if (ob_get_length()) ob_clean();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $titulo; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            @media print {
                .no-print { display: none !important; }
                body { background-color: white; }
                .card { border: 1px solid #dee2e6; box-shadow: none; }
            }
            body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); padding: 20px; }
            .reporte-container { max-width: 1200px; margin: 0 auto; }
            .header-reporte { 
                background: linear-gradient(135deg, #28a745, #1e7e34); 
                color: white; 
                padding: 20px; 
                border-radius: 10px 10px 0 0; 
            }
            .card { border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
            .table th { background-color: #28a745; color: white; }
            .moneda { text-align: right; }
            .numero { text-align: right; }
            .badge-venta { background-color: #28a745; color: white; padding: 5px 10px; border-radius: 5px; }
            .footer-reporte { 
                background: #f8f9fa; 
                padding: 15px; 
                border-radius: 0 0 10px 10px; 
                text-align: center; 
                color: #6c757d; 
                margin-top: 20px; 
            }
        </style>
    </head>
    <body>
        <div class="reporte-container">
            <!-- Botones de acción -->
            <div class="no-print mb-3">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <button onclick="window.close()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cerrar
                </button>
            </div>
            
            <!-- Encabezado -->
            <div class="header-reporte">
                <div class="row">
                    <div class="col-8">
                        <h1><i class="fas fa-chart-line"></i> <?php echo EMPRESA_NOMBRE; ?></h1>
                        <h3><?php echo $titulo; ?></h3>
                        <p>
                            <i class="fas fa-calendar"></i> Período: <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?><br>
                            <i class="fas fa-clock"></i> Generado: <?php echo date('d/m/Y H:i:s'); ?><br>
                            <i class="fas fa-user"></i> Usuario: <?php echo $_SESSION['usuario_nombre']; ?>
                        </p>
                    </div>
                    <div class="col-4 text-end">
                        <span class="badge-venta"><?php echo $_SESSION['usuario_rol']; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Resumen -->
            <?php if ($incluir_resumen && isset($datos['resumen'])): ?>
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Resumen</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($datos['resumen'] as $key => $value): ?>
                        <div class="col-md-4 mb-3">
                            <div class="p-3 border rounded">
                                <strong><?php echo ucwords(str_replace('_', ' ', $key)); ?>:</strong>
                                <h4 class="text-success">
                                    <?php 
                                    if (strpos($key, 'monto') !== false || strpos($key, 'total') !== false || strpos($key, 'deuda') !== false) {
                                        echo formatearMoneda($value);
                                    } else {
                                        echo number_format($value, 0, ',', '.');
                                    }
                                    ?>
                                </h4>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Tabla de detalle -->
            <?php if ($incluir_tablas && isset($datos['detalle']) && !empty($datos['detalle'])): ?>
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-table"></i> Detalle</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($datos['detalle'][0]) as $columna): ?>
                                        <th><?php echo ucwords(str_replace('_', ' ', $columna)); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($datos['detalle'], 0, 100) as $fila): ?>
                                <tr>
                                    <?php foreach ($fila as $key => $valor): ?>
                                        <?php if (strpos($key, 'fecha') !== false && $valor): ?>
                                            <td><?php echo date('d/m/Y', strtotime($valor)); ?></td>
                                        <?php elseif (strpos($key, 'monto') !== false || strpos($key, 'total') !== false || strpos($key, 'deuda') !== false || strpos($key, 'precio') !== false): ?>
                                            <td class="moneda"><?php echo formatearMoneda($valor); ?></td>
                                        <?php elseif (strpos($key, 'cantidad') !== false || strpos($key, 'stock') !== false): ?>
                                            <td class="numero"><?php echo number_format($valor, 0, ',', '.'); ?></td>
                                        <?php else: ?>
                                            <td><?php echo htmlspecialchars($valor); ?></td>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (count($datos['detalle']) > 100): ?>
                                <tr>
                                    <td colspan="<?php echo count($datos['detalle'][0]); ?>" class="text-muted text-center">
                                        ... y <?php echo count($datos['detalle']) - 100; ?> registros más
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Top productos -->
            <?php if (isset($datos['top_productos']) && !empty($datos['top_productos'])): ?>
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-crown"></i> Productos Más Vendidos</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Producto</th>
                                    <th>Proveedor</th>
                                    <th class="numero">Cantidad</th>
                                    <th class="moneda">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($datos['top_productos'] as $producto): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($producto['codigo']); ?></td>
                                    <td><?php echo htmlspecialchars($producto['nombre_color']); ?></td>
                                    <td><?php echo htmlspecialchars($producto['proveedor'] ?? ''); ?></td>
                                    <td class="numero"><?php echo number_format($producto['cantidad'], 0, ',', '.'); ?></td>
                                    <td class="moneda"><?php echo formatearMoneda($producto['total']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Pie de página -->
            <div class="footer-reporte">
                <p class="mb-0">
                    <?php echo EMPRESA_NOMBRE; ?> - Sistema de Gestión de Lanas<br>
                    <small>Este reporte fue generado automáticamente por el sistema el <?php echo date('d/m/Y H:i:s'); ?></small>
                </p>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit();
}
?>