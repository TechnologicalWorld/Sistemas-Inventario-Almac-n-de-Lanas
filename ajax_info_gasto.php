<?php
session_start();
require_once 'config.php';
require_once 'funciones.php';

verificarSesion();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => true, 'message' => 'ID no válido']);
    exit;
}

$id = intval($_GET['id']);

$query = "SELECT * FROM movimientos_caja WHERE id = ? AND tipo = 'gasto'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['error' => true, 'message' => 'Gasto no encontrado']);
    exit;
}

$gasto = $result->fetch_assoc();
echo json_encode($gasto);