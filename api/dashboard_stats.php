<?php

require_once '../config.php';
require_once '../funciones.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['usuario_rol'];

try {
    $data = [];
    
    // ========================================
    // 1. VENTAS DEL DÍA
    // ========================================
    $query_ventas_hoy = "SELECT 
                            COUNT(*) as total, 
                            COALESCE(SUM(total), 0) as monto
                        FROM ventas 
                        WHERE fecha = CURDATE() AND anulado = 0";
    
    if ($rol != 'administrador') {
        $query_ventas_hoy .= " AND vendedor_id = $usuario_id";
    }
    
    $result = $conn->query($query_ventas_hoy);
    $data['ventas_hoy'] = $result->fetch_assoc();
    
    // ========================================
    // 2. CAJA - INGRESOS Y GASTOS
    // ========================================
    $query_caja = "SELECT 
                    COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as ingresos,
                    COALESCE(SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END), 0) as gastos
                FROM movimientos_caja 
                WHERE fecha = CURDATE()";
    $result = $conn->query($query_caja);
    $caja = $result->fetch_assoc();
    $data['balance_caja'] = $caja['ingresos'] - $caja['gastos'];
    $data['ingresos_hoy'] = $caja['ingresos'];
    $data['gastos_hoy'] = $caja['gastos'];
    
    // ========================================
    // 3. COBROS PENDIENTES
    // ========================================
    $query_cobros = "SELECT 
                        COUNT(DISTINCT cliente_id) as total_clientes,
                        COALESCE(SUM(saldo_pendiente), 0) as total_deuda,
                        COUNT(CASE WHEN fecha_vencimiento < CURDATE() THEN 1 END) as vencidos
                    FROM clientes_cuentas_cobrar 
                    WHERE estado = 'pendiente'";
    $result = $conn->query($query_cobros);
    $cobros = $result->fetch_assoc();
    $data['total_clientes_deuda'] = $cobros['total_clientes'] ?? 0;
    $data['total_deuda'] = $cobros['total_deuda'] ?? 0;
    $data['clientes_vencidos'] = $cobros['vencidos'] ?? 0;
    
    // ========================================
    // 4. STOCK CRÍTICO
    // ========================================
    $query_stock = "SELECT 
                        SUM(CASE WHEN i.total_subpaquetes < 20 THEN 1 ELSE 0 END) as criticos,
                        SUM(CASE WHEN i.total_subpaquetes >= 20 AND i.total_subpaquetes < 50 THEN 1 ELSE 0 END) as bajos,
                        COALESCE(SUM(i.total_subpaquetes), 0) as stock_total
                    FROM productos p 
                    JOIN inventario i ON p.id = i.producto_id 
                    WHERE p.activo = 1";
    $result = $conn->query($query_stock);
    $stock = $result->fetch_assoc();
    $data['total_productos_criticos'] = $stock['criticos'] ?? 0;
    $data['total_productos_bajos'] = $stock['bajos'] ?? 0;
    $data['stock_total'] = $stock['stock_total'] ?? 0;
    
    // ========================================
    // 5. ÚLTIMAS VENTAS
    // ========================================
    $query_ultimas = "SELECT 
                        v.codigo_venta,
                        COALESCE(c.nombre, v.cliente_contado, 'Consumidor Final') as cliente,
                        v.total,
                        v.hora_inicio
                    FROM ventas v
                    LEFT JOIN clientes c ON v.cliente_id = c.id
                    WHERE v.anulado = 0
                    ORDER BY v.fecha DESC, v.hora_inicio DESC
                    LIMIT 8";
    $result = $conn->query($query_ultimas);
    $data['ultimas_ventas'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['ultimas_ventas'][] = $row;
    }
    
    // ========================================
    // 6. MOVIMIENTOS DE CAJA RECIENTES
    // ========================================
    $query_movimientos = "SELECT 
                            tipo, 
                            categoria, 
                            descripcion, 
                            monto, 
                            hora 
                        FROM movimientos_caja 
                        WHERE fecha = CURDATE() 
                        ORDER BY hora DESC 
                        LIMIT 8";
    $result = $conn->query($query_movimientos);
    $data['movimientos'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['movimientos'][] = $row;
    }
    
    echo json_encode($data);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>