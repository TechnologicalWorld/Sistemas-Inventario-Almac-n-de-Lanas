<?php
require_once 'config.php';
require_once 'funciones.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $query = "SELECT *, DATE_FORMAT(fecha_registro, '%d/%m/%Y') as fecha_registro 
              FROM clientes WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode($result->fetch_assoc());
    } else {
        echo json_encode(null);
    }
}
?>