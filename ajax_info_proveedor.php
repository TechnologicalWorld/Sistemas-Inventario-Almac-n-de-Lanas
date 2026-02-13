<?php
require_once 'config.php';
require_once 'funciones.php';

header('Content-Type: application/json');
verificarSesion();
verificarRol(['administrador']);

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de proveedor no especificado']);
    exit();
}

$proveedor_id = intval($_GET['id']);

// Obtener información del proveedor
$query_proveedor = "SELECT codigo, nombre, saldo_actual, credito_limite, ciudad 
                   FROM proveedores 
                   WHERE id = ? AND activo = 1";
$stmt_proveedor = $conn->prepare($query_proveedor);
$stmt_proveedor->bind_param("i", $proveedor_id);
$stmt_proveedor->execute();
$result_proveedor = $stmt_proveedor->get_result();

if ($result_proveedor->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
    exit();
}

$proveedor = $result_proveedor->fetch_assoc();

echo json_encode([
    'success' => true,
    'codigo' => $proveedor['codigo'],
    'nombre' => $proveedor['nombre'],
    'saldo_actual' => floatval($proveedor['saldo_actual']),
    'credito_limite' => floatval($proveedor['credito_limite']),
    'ciudad' => $proveedor['ciudad']
]);