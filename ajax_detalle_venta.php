<?php
session_start();
require_once 'config.php';
require_once 'funciones.php';

verificarSesion();

$venta_id = isset($_GET['venta_id']) ? intval($_GET['venta_id']) : 0;

if ($venta_id <= 0) {
    echo '<div class="alert alert-danger">ID de venta inválido</div>';
    exit;
}

// Obtener datos de la venta
$query_venta = "SELECT v.*, 
                       COALESCE(c.nombre, v.cliente_contado) as cliente_nombre,
                       c.telefono as cliente_telefono,
                       c.codigo as cliente_codigo,
                       u.nombre as vendedor_nombre
                FROM ventas v
                LEFT JOIN clientes c ON v.cliente_id = c.id
                JOIN usuarios u ON v.vendedor_id = u.id
                WHERE v.id = ?";
$stmt = $conn->prepare($query_venta);
$stmt->bind_param("i", $venta_id);
$stmt->execute();
$venta = $stmt->get_result()->fetch_assoc();

if (!$venta) {
    echo '<div class="alert alert-danger">Venta no encontrada</div>';
    exit;
}

// Obtener detalles
$query_detalles = "SELECT vd.*, p.codigo, p.nombre_color
                   FROM venta_detalles vd
                   JOIN productos p ON vd.producto_id = p.id
                   WHERE vd.venta_id = ?";
$stmt = $conn->prepare($query_detalles);
$stmt->bind_param("i", $venta_id);
$stmt->execute();
$detalles = $stmt->get_result();
?>

<div class="row">
    <div class="col-md-6">
        <table class="table table-sm">
            <tr>
                <th width="40%">Código Venta:</th>
                <td><strong><?php echo $venta['codigo_venta']; ?></strong></td>
            </tr>
            <tr>
                <th>Fecha:</th>
                <td><?php echo formatearFecha($venta['fecha']); ?></td>
            </tr>
            <tr>
                <th>Hora:</th>
                <td><?php echo formatearHora($venta['hora_inicio']); ?></td>
            </tr>
            <tr>
                <th>Cliente:</th>
                <td><?php echo htmlspecialchars($venta['cliente_nombre']); ?></td>
            </tr>
            <?php if ($venta['cliente_telefono']): ?>
            <tr>
                <th>Teléfono:</th>
                <td><?php echo $venta['cliente_telefono']; ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    <div class="col-md-6">
        <table class="table table-sm">
            <tr>
                <th width="40%">Vendedor:</th>
                <td><?php echo $venta['vendedor_nombre']; ?></td>
            </tr>
            <tr>
                <th>Tipo Pago:</th>
                <td><span class="badge bg-info"><?php echo ucfirst($venta['tipo_pago']); ?></span></td>
            </tr>
            <tr>
                <th>Estado:</th>
                <td>
                    <?php 
                    $badge_estado = [
                        'pagada' => 'success',
                        'pendiente' => 'warning',
                        'cancelada' => 'danger'
                    ];
                    ?>
                    <span class="badge bg-<?php echo $badge_estado[$venta['estado']] ?? 'secondary'; ?>">
                        <?php echo ucfirst($venta['estado']); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Método Pago:</th>
                <td><?php echo ucfirst($venta['metodo_pago_inicial'] ?? 'N/A'); ?></td>
            </tr>
        </table>
    </div>
</div>

<hr>

<h6 class="mb-3">Productos:</h6>
<div class="table-responsive">
    <table class="table table-sm table-bordered">
        <thead class="table-light">
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th class="text-center">Cantidad</th>
                <th class="text-end">P. Unitario</th>
                <th class="text-end">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($detalle = $detalles->fetch_assoc()): ?>
            <tr>
                <td><?php echo $detalle['codigo']; ?></td>
                <td><?php echo htmlspecialchars($detalle['nombre_color']); ?></td>
                <td class="text-center"><?php echo $detalle['cantidad_subpaquetes']; ?></td>
                <td class="text-end"><?php echo formatearMoneda($detalle['precio_unitario']); ?></td>
                <td class="text-end"><?php echo formatearMoneda($detalle['subtotal']); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<hr>

<div class="row">
    <div class="col-md-6 offset-md-6">
        <table class="table table-sm">
            <tr>
                <th>Subtotal:</th>
                <td class="text-end"><?php echo formatearMoneda($venta['subtotal']); ?></td>
            </tr>
            <?php if ($venta['descuento'] > 0): ?>
            <tr>
                <th>Descuento:</th>
                <td class="text-end text-danger">-<?php echo formatearMoneda($venta['descuento']); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="table-primary">
                <th>TOTAL:</th>
                <td class="text-end"><strong><?php echo formatearMoneda($venta['total']); ?></strong></td>
            </tr>
            <?php if ($venta['pago_inicial'] > 0): ?>
            <tr>
                <th>Pago Inicial:</th>
                <td class="text-end text-success"><?php echo formatearMoneda($venta['pago_inicial']); ?></td>
            </tr>
            <?php if ($venta['debe'] > 0): ?>
            <tr class="table-warning">
                <th>Saldo Pendiente:</th>
                <td class="text-end"><strong><?php echo formatearMoneda($venta['debe']); ?></strong></td>
            </tr>
            <?php endif; ?>
            <?php endif; ?>
        </table>
    </div>
</div>