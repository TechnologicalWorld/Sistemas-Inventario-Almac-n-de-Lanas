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
    // Obtener datos actualizados
    $data = [];
    
    // Ventas hoy
    $query_ventas = "SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto 
                    FROM ventas WHERE fecha = CURDATE() AND anulado = 0";
    
    if ($rol != 'administrador') {
        $query_ventas .= " AND vendedor_id = $usuario_id";
    }
    
    $result = $conn->query($query_ventas);
    $data['ventas_hoy'] = $result->fetch_assoc();
    
    // Balance caja
    $query_caja = "SELECT 
                    COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as ingresos,
                    COALESCE(SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END), 0) as gastos
                  FROM movimientos_caja 
                  WHERE fecha = CURDATE()";
    $result = $conn->query($query_caja);
    $caja = $result->fetch_assoc();
    $data['balance_caja'] = $caja['ingresos'] - $caja['gastos'];
    
    // Últimas ventas
    $query_ultimas = "SELECT 
                        v.codigo_venta,
                        COALESCE(c.nombre, v.cliente_contado, 'Consumidor Final') as cliente,
                        v.total,
                        v.hora_inicio
                    FROM ventas v
                    LEFT JOIN clientes c ON v.cliente_id = c.id
                    WHERE v.anulado = 0
                    ORDER BY v.fecha DESC, v.hora_inicio DESC
                    LIMIT 5";
    $result = $conn->query($query_ultimas);
    $data['ultimas_ventas'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['ultimas_ventas'][] = $row;
    }
    
    echo json_encode($data);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>