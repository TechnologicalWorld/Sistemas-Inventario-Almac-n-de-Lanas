<?php

require_once 'config.php';
require_once 'funciones.php';

if (isset($_GET['proveedor_id'])) {
    $proveedor_id = intval($_GET['proveedor_id']);
    
    $query = "SELECT id, nombre FROM categorias 
              WHERE proveedor_id = ? AND activo = 1 
              ORDER BY nombre";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $proveedor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categorias = [];
    while ($row = $result->fetch_assoc()) {
        $categorias[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($categorias);
}
?>