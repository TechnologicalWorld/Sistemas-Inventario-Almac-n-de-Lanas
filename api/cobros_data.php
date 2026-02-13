<?php

require_once '../config.php';
require_once '../funciones.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$periodo = $_GET['periodo'] ?? 'semana';
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['usuario_rol'];

try {
    $data = ['labels' => [], 'valores' => []];
    
    switch ($periodo) {
        case 'semana':
            // Cobros de los últimos 7 días
            $query = "SELECT 
                        DATE_FORMAT(fecha, '%a') as dia,
                        COALESCE(SUM(monto), 0) as total
                      FROM pagos_clientes 
                      WHERE fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
                      GROUP BY fecha
                      ORDER BY fecha";
            
            $result = $conn->query($query);
            
            // Crear un array con todos los días de la semana
            $dias_semana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            $fecha_actual = strtotime('-6 days');
            
            for ($i = 0; $i < 7; $i++) {
                $fecha = date('Y-m-d', strtotime("+$i days", $fecha_actual));
                $dia_nombre = $dias_semana[date('w', strtotime($fecha))];
                $data['labels'][] = $dia_nombre;
                $data['valores'][] = 0;
            }
            
            // Llenar con los datos reales
            $result->data_seek(0);
            while ($row = $result->fetch_assoc()) {
                $dia_index = date('w', strtotime($row['dia']));
                $posicion = array_search($dias_semana[$dia_index], $data['labels']);
                if ($posicion !== false) {
                    $data['valores'][$posicion] = (float)$row['total'];
                }
            }
            break;
            
        case 'mes':
            // Cobros de los últimos 30 días agrupados por semana
            $query = "SELECT 
                        WEEK(fecha) as semana,
                        MIN(DATE_FORMAT(fecha, '%d/%m')) as fecha_inicio,
                        MAX(DATE_FORMAT(fecha, '%d/%m')) as fecha_fin,
                        COALESCE(SUM(monto), 0) as total
                      FROM pagos_clientes 
                      WHERE fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE()
                      GROUP BY WEEK(fecha)
                      ORDER BY semana DESC
                      LIMIT 5";
            
            $result = $conn->query($query);
            
            while ($row = $result->fetch_assoc()) {
                $data['labels'][] = $row['fecha_inicio'] . ' - ' . $row['fecha_fin'];
                $data['valores'][] = (float)$row['total'];
            }
            
            // Si no hay datos, invertir el orden para que sea cronológico
            if (!empty($data['labels'])) {
                $data['labels'] = array_reverse($data['labels']);
                $data['valores'] = array_reverse($data['valores']);
            }
            break;
            
        case 'pendientes':
            // Cobros pendientes por vencer (próximos 7 días)
            $query = "SELECT 
                        DATE_FORMAT(fecha_vencimiento, '%a %d/%m') as fecha,
                        COUNT(*) as cantidad,
                        COALESCE(SUM(saldo_pendiente), 0) as total
                      FROM clientes_cuentas_cobrar 
                      WHERE estado = 'pendiente'
                      AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                      GROUP BY fecha_vencimiento
                      ORDER BY fecha_vencimiento
                      LIMIT 7";
            
            $result = $conn->query($query);
            
            while ($row = $result->fetch_assoc()) {
                $data['labels'][] = $row['fecha'];
                $data['valores'][] = (float)$row['total'];
            }
            break;
            
        case 'vencidos':
            // Cobros vencidos
            $query = "SELECT 
                        DATEDIFF(CURDATE(), fecha_vencimiento) as dias_vencidos,
                        COUNT(*) as cantidad,
                        COALESCE(SUM(saldo_pendiente), 0) as total
                      FROM clientes_cuentas_cobrar 
                      WHERE estado = 'pendiente'
                      AND fecha_vencimiento < CURDATE()
                      GROUP BY dias_vencidos
                      ORDER BY dias_vencidos DESC
                      LIMIT 10";
            
            $result = $conn->query($query);
            
            while ($row = $result->fetch_assoc()) {
                $data['labels'][] = $row['dias_vencidos'] . ' días';
                $data['valores'][] = (float)$row['total'];
            }
            break;
            
        default:
            // Por defecto, últimos 7 días
            $query = "SELECT 
                        DATE_FORMAT(fecha, '%a') as dia,
                        COALESCE(SUM(monto), 0) as total
                      FROM pagos_clientes 
                      WHERE fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
                      GROUP BY fecha
                      ORDER BY fecha";
            
            $result = $conn->query($query);
            
            $dias_semana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            $fecha_actual = strtotime('-6 days');
            
            for ($i = 0; $i < 7; $i++) {
                $fecha = date('Y-m-d', strtotime("+$i days", $fecha_actual));
                $dia_nombre = $dias_semana[date('w', strtotime($fecha))];
                $data['labels'][] = $dia_nombre;
                $data['valores'][] = 0;
            }
            
            $result->data_seek(0);
            while ($row = $result->fetch_assoc()) {
                $dia_index = date('w', strtotime($row['dia']));
                $posicion = array_search($dias_semana[$dia_index], $data['labels']);
                if ($posicion !== false) {
                    $data['valores'][$posicion] = (float)$row['total'];
                }
            }
            break;
    }
    
    echo json_encode($data);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>