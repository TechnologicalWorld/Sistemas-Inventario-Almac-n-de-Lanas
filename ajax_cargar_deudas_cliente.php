<?php
require_once 'config.php';
require_once 'funciones.php';

header('Content-Type: application/json');
verificarSesion();

if (!isset($_GET['cliente_id']) || empty($_GET['cliente_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de cliente no especificado']);
    exit();
}

$cliente_id = intval($_GET['cliente_id']);

// Obtener ventas pendientes del cliente
$query_deudas = "SELECT v.id as venta_id, v.codigo_venta, v.fecha, v.total,
                v.pago_inicial, v.debe, v.estado,
                COALESCE(SUM(pc.monto), 0) as abonos_realizados
                FROM ventas v
                LEFT JOIN pagos_clientes pc ON v.id = pc.venta_id AND pc.tipo = 'abono'
                WHERE v.cliente_id = ? AND v.estado = 'pendiente' AND v.anulado = 0
                GROUP BY v.id
                ORDER BY v.fecha DESC";
$stmt_deudas = $conn->prepare($query_deudas);
$stmt_deudas->bind_param("i", $cliente_id);
$stmt_deudas->execute();
$result_deudas = $stmt_deudas->get_result();

$deudas = [];
while ($deuda = $result_deudas->fetch_assoc()) {
    $saldo_pendiente = floatval($deuda['debe']);
    $deudas[] = [
        'venta_id' => $deuda['venta_id'],
        'codigo_venta' => $deuda['codigo_venta'],
        'fecha' => formatearFecha($deuda['fecha']),
        'total' => floatval($deuda['total']),
        'saldo_pendiente' => $saldo_pendiente,
        'saldo_pendiente_formateado' => formatearMoneda($saldo_pendiente)
    ];
}

echo json_encode([
    'success' => true,
    'deudas' => $deudas
]);