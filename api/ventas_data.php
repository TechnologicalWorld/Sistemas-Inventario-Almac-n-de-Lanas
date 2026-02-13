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
            $query = "SELECT 
                        DATE_FORMAT(fecha, '%a') as dia,
                        COALESCE(SUM(total), 0) as total
                      FROM ventas 
                      WHERE fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
                      AND anulado = 0";
            
            if ($rol != 'administrador') {
                $query .= " AND vendedor_id = $usuario_id";
            }
            
            $query .= " GROUP BY fecha ORDER BY fecha";
            
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $data['labels'][] = $row['dia'];
                $data['valores'][] = (float)$row['total'];
            }
            break;
            
        case 'mes':
            $query = "SELECT 
                        DATE_FORMAT(fecha, '%d/%m') as dia,
                        COALESCE(SUM(total), 0) as total
                      FROM ventas 
                      WHERE fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE()
                      AND anulado = 0";
            
            if ($rol != 'administrador') {
                $query .= " AND vendedor_id = $usuario_id";
            }
            
            $query .= " GROUP BY fecha ORDER BY fecha LIMIT 30";
            
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $data['labels'][] = $row['dia'];
                $data['valores'][] = (float)$row['total'];
            }
            break;
            
        case 'año':
            $query = "SELECT 
                        DATE_FORMAT(fecha, '%M') as mes,
                        COALESCE(SUM(total), 0) as total
                      FROM ventas 
                      WHERE fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 11 MONTH) AND CURDATE()
                      AND anulado = 0";
            
            if ($rol != 'administrador') {
                $query .= " AND vendedor_id = $usuario_id";
            }
            
            $query .= " GROUP BY YEAR(fecha), MONTH(fecha) ORDER BY fecha LIMIT 12";
            
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $data['labels'][] = $row['mes'];
                $data['valores'][] = (float)$row['total'];
            }
            break;
    }
    
    echo json_encode($data);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>