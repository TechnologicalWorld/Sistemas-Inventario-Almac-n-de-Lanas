<?php
require_once 'config.php';
require_once 'funciones.php';

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="clientes_' . date('Ymd') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

$where = '';
$params = [];

if (isset($_GET['ids']) && !empty($_GET['ids'])) {
    $ids = explode(',', $_GET['ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $where = "WHERE id IN ($placeholders)";
    $params = $ids;
}

$query = "SELECT codigo, nombre, ciudad, telefono, tipo_documento, 
                 numero_documento, limite_credito, saldo_actual, total_comprado,
                 compras_realizadas,
                 CASE WHEN activo = 1 THEN 'Activo' ELSE 'Inactivo' END as estado,
                 DATE_FORMAT(fecha_registro, '%d/%m/%Y') as fecha_registro,
                 observaciones
          FROM clientes $where ORDER BY nombre";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $types = str_repeat('i', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

echo '<table border="1">';
echo '<tr>';
echo '<th>Código</th><th>Nombre</th><th>Ciudad</th><th>Teléfono</th>';
echo '<th>Tipo Doc.</th><th>Número Doc.</th><th>Límite Crédito</th>';
echo '<th>Saldo Actual</th><th>Total Comprado</th><th>Compras</th>';
echo '<th>Estado</th><th>Fecha Registro</th><th>Observaciones</th>';
echo '</tr>';

while ($row = $result->fetch_assoc()) {
    echo '<tr>';
    foreach ($row as $cell) {
        echo '<td>' . htmlspecialchars($cell) . '</td>';
    }
    echo '</tr>';
}

echo '</table>';
exit();
?>