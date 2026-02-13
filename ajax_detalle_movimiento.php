<?php
session_start();
require_once 'config.php';
require_once 'funciones.php';

verificarSesion();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID no válido');
}

$id = intval($_GET['id']);

$query = "SELECT mc.*, u.nombre as usuario_nombre, u.codigo as usuario_codigo
          FROM movimientos_caja mc
          JOIN usuarios u ON mc.usuario_id = u.id
          WHERE mc.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die('Movimiento no encontrado');
}

$movimiento = $result->fetch_assoc();
$categorias = [
    'venta_contado' => 'Venta Contado',
    'pago_inicial' => 'Pago Inicial',
    'abono_cliente' => 'Abono Cliente',
    'gasto_almuerzo' => 'Almuerzo',
    'gasto_varios' => 'Gastos Varios',
    'pago_proveedor' => 'Pago Proveedor',
    'otros' => 'Otros'
];
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-<?php echo $movimiento['tipo'] == 'ingreso' ? 'success' : 'danger'; ?> text-white">
                    <strong>Información General</strong>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">ID Movimiento:</th>
                            <td><?php echo $movimiento['id']; ?></td>
                        </tr>
                        <tr>
                            <th>Fecha:</th>
                            <td><?php echo formatearFecha($movimiento['fecha']); ?></td>
                        </tr>
                        <tr>
                            <th>Hora:</th>
                            <td><?php echo formatearHora($movimiento['hora']); ?></td>
                        </tr>
                        <tr>
                            <th>Tipo:</th>
                            <td>
                                <span class="badge bg-<?php echo $movimiento['tipo'] == 'ingreso' ? 'success' : 'danger'; ?>">
                                    <?php echo strtoupper($movimiento['tipo']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Categoría:</th>
                            <td><?php echo $categorias[$movimiento['categoria']] ?? $movimiento['categoria']; ?></td>
                        </tr>
                        <tr>
                            <th>Monto:</th>
                            <td class="fw-bold text-<?php echo $movimiento['tipo'] == 'ingreso' ? 'success' : 'danger'; ?>">
                                <?php echo formatearMoneda($movimiento['monto']); ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <strong>Usuario y Referencia</strong>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Usuario:</th>
                            <td><?php echo htmlspecialchars($movimiento['usuario_nombre']); ?></td>
                        </tr>
                        <tr>
                            <th>Código Usuario:</th>
                            <td><?php echo htmlspecialchars($movimiento['usuario_codigo']); ?></td>
                        </tr>
                        <tr>
                            <th>Referencia Venta:</th>
                            <td>
                                <?php if (!empty($movimiento['referencia_venta'])): ?>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($movimiento['referencia_venta']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header bg-secondary text-white">
                    <strong>Descripción y Observaciones</strong>
                </div>
                <div class="card-body">
                    <p><strong>Descripción:</strong></p>
                    <p><?php echo nl2br(htmlspecialchars($movimiento['descripcion'])); ?></p>
                    
                    <?php if (!empty($movimiento['observaciones'])): ?>
                    <hr>
                    <p><strong>Observaciones:</strong></p>
                    <p><?php echo nl2br(htmlspecialchars($movimiento['observaciones'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>