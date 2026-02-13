<?php
session_start();
require_once 'config.php';
require_once 'funciones.php';

verificarSesion();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $response['message'] = 'Método no permitido';
    echo json_encode($response);
    exit;
}

$id = isset($_POST['id']) && !empty($_POST['id']) ? intval($_POST['id']) : null;
$descripcion = limpiar($_POST['descripcion'] ?? '');
$monto = floatval($_POST['monto'] ?? 0);
$categoria = limpiar($_POST['categoria'] ?? '');
$referencia = limpiar($_POST['referencia_venta'] ?? '');
$observaciones = limpiar($_POST['observaciones'] ?? '');

// Validaciones
if (empty($descripcion)) {
    $response['message'] = 'La descripción es requerida';
    echo json_encode($response);
    exit;
}

if ($monto <= 0) {
    $response['message'] = 'El monto debe ser mayor a 0';
    echo json_encode($response);
    exit;
}

if (empty($categoria)) {
    $response['message'] = 'La categoría es requerida';
    echo json_encode($response);
    exit;
}

$conn->begin_transaction();

try {
    if ($id) {
        // Actualizar gasto existente
        $query = "UPDATE movimientos_caja 
                 SET descripcion = ?, monto = ?, categoria = ?, 
                     referencia_venta = ?, observaciones = ?
                 WHERE id = ? AND tipo = 'gasto'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sdsssi", $descripcion, $monto, $categoria, $referencia, $observaciones, $id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Gasto actualizado exitosamente';
        } else {
            throw new Exception('No se pudo actualizar el gasto');
        }
    } else {
        // Crear nuevo gasto
        $query = "INSERT INTO movimientos_caja 
                 (tipo, categoria, monto, descripcion, referencia_venta, observaciones, fecha, hora, usuario_id) 
                 VALUES ('gasto', ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sdsssi", $categoria, $monto, $descripcion, $referencia, $observaciones, $_SESSION['usuario_id']);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Gasto registrado exitosamente';
        } else {
            throw new Exception('No se pudo registrar el gasto');
        }
    }
    
    $conn->commit();
    
} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
}

echo json_encode($response);