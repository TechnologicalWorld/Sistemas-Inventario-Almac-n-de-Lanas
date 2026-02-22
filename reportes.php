<?php

session_start();

$titulo_pagina = "Centro de Reportes y Análisis";
$icono_titulo = "fas fa-chart-pie";
$breadcrumb = [
    ['text' => 'Reportes', 'link' => '#', 'active' => true]
];

require_once 'config.php';
require_once 'funciones.php';
require_once 'header.php';

// Verificar que sea administrador
verificarRol(['administrador']);

// Parámetros de filtro con valores por defecto inteligentes
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$tipo_reporte = $_GET['tipo_reporte'] ?? 'dashboard';
$vendedor_id = isset($_GET['vendedor_id']) && $_GET['vendedor_id'] !== '' ? intval($_GET['vendedor_id']) : null;
$proveedor_id = isset($_GET['proveedor_id']) && $_GET['proveedor_id'] !== '' ? intval($_GET['proveedor_id']) : null;
$categoria_id = isset($_GET['categoria_id']) && $_GET['categoria_id'] !== '' ? intval($_GET['categoria_id']) : null;
$cliente_id = isset($_GET['cliente_id']) && $_GET['cliente_id'] !== '' ? intval($_GET['cliente_id']) : null;
$formato = $_GET['formato'] ?? 'html';
?>

<!-- Estilos personalizados para reportes -->
<style>
:root {
    --report-primary: #4e73df;
    --report-success: #1cc88a;
    --report-warning: #f6c23e;
    --report-danger: #e74a3b;
    --report-info: #36b9cc;
}

.report-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: none;
    border-radius: 15px;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.report-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
}

.report-icon-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.border-left-primary { border-left: 0.25rem solid var(--report-primary) !important; }
.border-left-success { border-left: 0.25rem solid var(--report-success) !important; }
.border-left-warning { border-left: 0.25rem solid var(--report-warning) !important; }
.border-left-danger { border-left: 0.25rem solid var(--report-danger) !important; }
.border-left-info { border-left: 0.25rem solid var(--report-info) !important; }

.badge-gold { background: linear-gradient(45deg, #FFD700, #FFA500); color: #000; }
.badge-silver { background: linear-gradient(45deg, #C0C0C0, #A9A9A9); color: #000; }
.badge-bronze { background: linear-gradient(45deg, #CD7F32, #8B4513); color: #fff; }

.table-report thead th {
    background: linear-gradient(45deg, #f8f9fc, #e9ecef);
    border-bottom: 2px solid #4e73df;
    color: #2c3e50;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

.kpi-card {
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

.kpi-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.2;
}

.kpi-label {
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    opacity: 0.8;
}

.kpi-change {
    font-size: 0.8rem;
    margin-top: 5px;
}

.kpi-change.positive { color: #1cc88a; }
.kpi-change.negative { color: #e74a3b; }

.progress-report {
    height: 8px;
    border-radius: 4px;
    background-color: #eaecf4;
}

.progress-report-bar {
    background: linear-gradient(90deg, #4e73df, #224abe);
    border-radius: 4px;
    transition: width 0.6s ease;
}

@media print {
    .no-print { display: none !important; }
    body { background: white; }
    .card { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<!-- Barra de herramientas superior -->
<div class="row mb-4 no-print">
    <div class="col-md-12">
        <div class="card report-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="text-primary mb-0">
                            <i class="<?php echo $icono_titulo; ?> me-2"></i>
                            <?php echo $titulo_pagina; ?>
                        </h4>
                        <p class="text-muted mb-0">
                            <i class="fas fa-calendar-alt me-1"></i>
                            Período: <?php echo formatearFecha($fecha_inicio); ?> - <?php echo formatearFecha($fecha_fin); ?>
                        </p>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Imprimir
                        </button>

                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<?php
// ============================================
// GENERADOR DE REPORTES PROFESIONAL
// ============================================

switch ($tipo_reporte) {
    case 'dashboard':
        generarDashboardGeneral($conn, $fecha_inicio, $fecha_fin);
        break;
    case 'ventas_periodo':
        generarReporteVentasPeriodo($conn, $fecha_inicio, $fecha_fin);
        break;
    case 'ventas_vendedor':
        generarReporteVentasVendedor($conn, $fecha_inicio, $fecha_fin, $vendedor_id);
        break;
    case 'ventas_producto':
        generarReporteVentasProducto($conn, $fecha_inicio, $fecha_fin, $categoria_id);
        break;
    case 'ventas_categoria':
        generarReporteVentasCategoria($conn, $fecha_inicio, $fecha_fin);
        break;
    case 'ventas_horario':
        generarReporteVentasHorario($conn, $fecha_inicio, $fecha_fin);
        break;
    case 'inventario_general':
        generarReporteInventarioGeneral($conn);
        break;
    case 'stock_bajo':
        generarReporteStockBajo($conn);
        break;
    case 'valor_inventario':
        generarReporteValorInventario($conn);
        break;
    case 'movimientos_stock':
        generarReporteMovimientosStock($conn, $fecha_inicio, $fecha_fin);
        break;
    case 'caja':
        generarReporteCaja($conn, $fecha_inicio, $fecha_fin);
        break;
    case 'cuentas_cobrar':
        generarReporteCuentasCobrar($conn);
        break;
    case 'cuentas_pagar':
        generarReporteCuentasPagar($conn);
        break;
    case 'balance':
        generarReporteBalance($conn, $fecha_inicio, $fecha_fin);
        break;
    case 'clientes_frecuentes':
        generarReporteClientesFrecuentes($conn, $fecha_inicio, $fecha_fin);
        break;
    case 'clientes_morosos':
        generarReporteClientesMorosos($conn);
        break;
    case 'clientes_inactivos':
        generarReporteClientesInactivos($conn);
        break;
    case 'vendedores':
        generarReporteVendedores($conn, $fecha_inicio, $fecha_fin);
        break;
    default:
        generarDashboardGeneral($conn, $fecha_inicio, $fecha_fin);
}
?>

<script>
// Control de visibilidad de filtros según tipo de reporte
document.getElementById('tipoReporte').addEventListener('change', function() {
    const tipo = this.value;
    
    document.getElementById('filtroVendedor').style.display = 
        ['ventas_vendedor', 'vendedores'].includes(tipo) ? 'block' : 'none';
    
    document.getElementById('filtroProveedor').style.display = 
        ['inventario_general', 'stock_bajo', 'valor_inventario'].includes(tipo) ? 'block' : 'none';
    
    document.getElementById('filtroCategoria').style.display = 
        ['ventas_producto', 'ventas_categoria', 'inventario_general'].includes(tipo) ? 'block' : 'none';
});

// Inicializar al cargar
document.addEventListener('DOMContentLoaded', function() {
    const event = new Event('change');
    document.getElementById('tipoReporte').dispatchEvent(event);
});

function exportarReporte(formato) {
    const form = document.getElementById('filtrosReporte');
    const inputs = form.querySelectorAll('input, select');
    let url = 'exportar_reporte.php?formato=' + formato;
    
    inputs.forEach(input => {
        if (input.name && input.value) {
            url += '&' + encodeURIComponent(input.name) + '=' + encodeURIComponent(input.value);
        }
    });
    
    window.open(url, '_blank');
}
</script>

<?php
// ============================================
// FUNCIONES DE REPORTES - ADAPTADAS A TU BD
// ============================================

/**
 * DASHBOARD GENERAL - CORREGIDO
 */
function generarDashboardGeneral($conn, $fecha_inicio, $fecha_fin) {
    // KPI - Ventas Totales
    $ventas = $conn->prepare("
        SELECT 
            COUNT(*) as total_ventas,
            COALESCE(SUM(total), 0) as monto_total,
            COALESCE(SUM(pago_inicial), 0) as total_pagado,
            COALESCE(SUM(debe), 0) as total_deuda,
            COALESCE(AVG(total), 0) as ticket_promedio
        FROM ventas 
        WHERE fecha BETWEEN ? AND ? AND anulado = 0
    ");
    $ventas->bind_param("ss", $fecha_inicio, $fecha_fin);
    $ventas->execute();
    $resumen_ventas = $ventas->get_result()->fetch_assoc();
    
    // KPI - Productos Vendidos
    $productos = $conn->prepare("
        SELECT COALESCE(SUM(vd.cantidad_subpaquetes), 0) as total_unidades
        FROM venta_detalles vd
        JOIN ventas v ON vd.venta_id = v.id
        WHERE v.fecha BETWEEN ? AND ? AND v.anulado = 0
    ");
    $productos->bind_param("ss", $fecha_inicio, $fecha_fin);
    $productos->execute();
    $total_unidades = $productos->get_result()->fetch_assoc()['total_unidades'];
    
    // KPI - Clientes Atendidos
    $clientes = $conn->prepare("
        SELECT COUNT(DISTINCT cliente_id) as total_clientes
        FROM ventas 
        WHERE fecha BETWEEN ? AND ? AND anulado = 0 AND cliente_id IS NOT NULL
    ");
    $clientes->bind_param("ss", $fecha_inicio, $fecha_fin);
    $clientes->execute();
    $total_clientes = $clientes->get_result()->fetch_assoc()['total_clientes'];
    
    // KPI - Margen de Utilidad (usando costo_paquete de inventario)
    $margen = $conn->prepare("
        SELECT 
            COALESCE(SUM(vd.subtotal), 0) as ventas,
            COALESCE(SUM(vd.cantidad_subpaquetes * (i.costo_paquete / i.subpaquetes_por_paquete)), 0) as costo
        FROM venta_detalles vd
        JOIN productos p ON vd.producto_id = p.id
        JOIN inventario i ON p.id = i.producto_id
        JOIN ventas v ON vd.venta_id = v.id
        WHERE v.fecha BETWEEN ? AND ? AND v.anulado = 0
    ");
    $margen->bind_param("ss", $fecha_inicio, $fecha_fin);
    $margen->execute();
    $m = $margen->get_result()->fetch_assoc();
    $utilidad = $m['ventas'] - $m['costo'];
    $margen_porcentaje = $m['ventas'] > 0 ? ($utilidad / $m['ventas']) * 100 : 0;
    
    // Ventas por día para gráfico
    $ventas_diarias = $conn->prepare("
        SELECT 
            DATE(fecha) as fecha,
            COUNT(*) as cantidad,
            SUM(total) as monto
        FROM ventas 
        WHERE fecha BETWEEN ? AND ? AND anulado = 0
        GROUP BY DATE(fecha)
        ORDER BY fecha
    ");
    $ventas_diarias->bind_param("ss", $fecha_inicio, $fecha_fin);
    $ventas_diarias->execute();
    $datos_diarios = $ventas_diarias->get_result();
    
    // Top productos
    $top_productos = $conn->prepare("
        SELECT 
            p.codigo,
            p.nombre_color,
            SUM(vd.cantidad_subpaquetes) as cantidad,
            SUM(vd.subtotal) as monto
        FROM venta_detalles vd
        JOIN productos p ON vd.producto_id = p.id
        JOIN ventas v ON vd.venta_id = v.id
        WHERE v.fecha BETWEEN ? AND ? AND v.anulado = 0
        GROUP BY p.id
        ORDER BY cantidad DESC
        LIMIT 5
    ");
    $top_productos->bind_param("ss", $fecha_inicio, $fecha_fin);
    $top_productos->execute();
    $top_productos_result = $top_productos->get_result();
    
    // Top vendedores
    $top_vendedores = $conn->prepare("
        SELECT 
            u.nombre,
            COUNT(v.id) as ventas,
            SUM(v.total) as monto
        FROM ventas v
        JOIN usuarios u ON v.vendedor_id = u.id
        WHERE v.fecha BETWEEN ? AND ? AND v.anulado = 0
        GROUP BY v.vendedor_id
        ORDER BY monto DESC
        LIMIT 5
    ");
    $top_vendedores->bind_param("ss", $fecha_inicio, $fecha_fin);
    $top_vendedores->execute();
    $top_vendedores_result = $top_vendedores->get_result();
    ?>
    
    <!-- KPIs principales -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card report-card border-left-primary h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="kpi-label text-primary fw-bold">VENTAS TOTALES</div>
                            <div class="kpi-value"><?php echo formatearMoneda($resumen_ventas['monto_total']); ?></div>
                            <div class="kpi-change">
                                <i class="fas fa-shopping-cart me-1"></i>
                                <?php echo $resumen_ventas['total_ventas']; ?> transacciones
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="report-icon-circle bg-primary bg-gradient text-white">
                                <i class="fas fa-chart-line fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card report-card border-left-success h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="kpi-label text-success fw-bold">TICKET PROMEDIO</div>
                            <div class="kpi-value"><?php echo formatearMoneda($resumen_ventas['ticket_promedio']); ?></div>
                            <div class="kpi-change">
                                <i class="fas fa-box me-1"></i>
                                <?php echo number_format($total_unidades, 0); ?> unidades vendidas
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="report-icon-circle bg-success bg-gradient text-white">
                                <i class="fas fa-receipt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card report-card border-left-warning h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="kpi-label text-warning fw-bold">UTILIDAD BRUTA</div>
                            <div class="kpi-value"><?php echo formatearMoneda($utilidad); ?></div>
                            <div class="kpi-change <?php echo $margen_porcentaje >= 30 ? 'positive' : ($margen_porcentaje >= 20 ? 'text-warning' : 'negative'); ?>">
                                <i class="fas fa-percentage me-1"></i>
                                <?php echo number_format($margen_porcentaje, 1); ?>% margen
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="report-icon-circle bg-warning bg-gradient text-dark">
                                <i class="fas fa-coins fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card report-card border-left-info h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="kpi-label text-info fw-bold">CLIENTES ACTIVOS</div>
                            <div class="kpi-value"><?php echo number_format($total_clientes, 0); ?></div>
                            <div class="kpi-change">
                                <i class="fas fa-users me-1"></i>
                                <?php echo $total_clientes > 0 ? number_format($total_unidades / $total_clientes, 1) : 0; ?> unid/cliente
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="report-icon-circle bg-info bg-gradient text-white">
                                <i class="fas fa-user-friends fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráficos y tablas -->
    <div class="row">
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card report-card">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="fas fa-chart-area me-2"></i>
                        Evolución de Ventas Diarias
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="graficoEvolucion"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="card report-card">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-success">
                        <i class="fas fa-chart-pie me-2"></i>
                        Distribución de Ventas
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="graficoPastel"></canvas>
                    </div>
                    <hr>
                    <div class="text-center small">
                        <span class="badge bg-primary me-2">Contado</span>
                        <span class="badge bg-warning me-2">Crédito</span>
                        <span class="badge bg-info">Mixto</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="card report-card">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-danger">
                        <i class="fas fa-star me-2"></i>
                        Productos Más Vendidos
                    </h6>
                    <span class="badge bg-secondary">Período</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-report">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center">Cantidad</th>
                                    <th class="text-end">Monto</th>
                                    <th class="text-center">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_monto_top = 0;
                                $productos_lista = [];
                                while ($top = $top_productos_result->fetch_assoc()): 
                                    $total_monto_top += $top['monto'];
                                    $productos_lista[] = $top;
                                ?>
                                <tr>
                                    <td>
                                        <div><?php echo htmlspecialchars($top['nombre_color']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($top['codigo']); ?></small>
                                    </td>
                                    <td class="text-center fw-bold"><?php echo number_format($top['cantidad'], 0); ?></td>
                                    <td class="text-end"><?php echo formatearMoneda($top['monto']); ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $porc = $resumen_ventas['monto_total'] > 0 ? 
                                            ($top['monto'] / $resumen_ventas['monto_total']) * 100 : 0;
                                        ?>
                                        <span class="badge bg-info"><?php echo number_format($porc, 1); ?>%</span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if (count($productos_lista) == 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4">
                                        <p class="text-muted mb-0">No hay ventas en el período</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-6 mb-4">
            <div class="card report-card">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-info">
                        <i class="fas fa-trophy me-2"></i>
                        Mejores Vendedores
                    </h6>
                    <span class="badge bg-secondary">Período</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-report">
                            <thead>
                                <tr>
                                    <th>Vendedor</th>
                                    <th class="text-center">Ventas</th>
                                    <th class="text-end">Monto</th>
                                    <th class="text-center">Participación</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                while ($vendedor = $top_vendedores_result->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td>
                                        <div><?php echo htmlspecialchars($vendedor['nombre']); ?></div>
                                    </td>
                                    <td class="text-center fw-bold"><?php echo $vendedor['ventas']; ?></td>
                                    <td class="text-end"><?php echo formatearMoneda($vendedor['monto']); ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $participacion = $resumen_ventas['monto_total'] > 0 ? 
                                            ($vendedor['monto'] / $resumen_ventas['monto_total']) * 100 : 0;
                                        ?>
                                        <div class="progress-report">
                                            <div class="progress-report-bar" style="width: <?php echo $participacion; ?>%"></div>
                                        </div>
                                        <small><?php echo number_format($participacion, 1); ?>%</small>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if ($top_vendedores_result->num_rows == 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4">
                                        <p class="text-muted mb-0">No hay ventas en el período</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gráfico de evolución
        const fechas = [];
        const montos = [];
        <?php 
        $datos_diarios->data_seek(0);
        while ($dia = $datos_diarios->fetch_assoc()): 
        ?>
        fechas.push('<?php echo date('d/m', strtotime($dia['fecha'])); ?>');
        montos.push(<?php echo $dia['monto']; ?>);
        <?php endwhile; ?>
        
        if (fechas.length > 0) {
            new Chart(document.getElementById('graficoEvolucion'), {
                type: 'line',
                data: {
                    labels: fechas,
                    datasets: [{
                        label: 'Ventas (Bs)',
                        data: montos,
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.05)',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: '#4e73df',
                        pointBorderColor: '#fff',
                        pointHoverRadius: 5,
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    return 'Bs ' + ctx.parsed.y.toLocaleString('es-BO', {minimumFractionDigits: 2});
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Bs ' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Gráfico de pastel - Distribución de ventas
        <?php
        $distribucion = $conn->prepare("
            SELECT 
                tipo_pago,
                COUNT(*) as cantidad,
                SUM(total) as monto
            FROM ventas 
            WHERE fecha BETWEEN ? AND ? AND anulado = 0
            GROUP BY tipo_pago
        ");
        $distribucion->bind_param("ss", $fecha_inicio, $fecha_fin);
        $distribucion->execute();
        $dist = $distribucion->get_result();
        $contado = 0; $credito = 0; $mixto = 0;
        while ($d = $dist->fetch_assoc()) {
            if ($d['tipo_pago'] == 'contado') $contado = $d['monto'];
            if ($d['tipo_pago'] == 'credito') $credito = $d['monto'];
            if ($d['tipo_pago'] == 'mixto') $mixto = $d['monto'];
        }
        ?>
        
        if (<?php echo $contado + $credito + $mixto; ?> > 0) {
            new Chart(document.getElementById('graficoPastel'), {
                type: 'doughnut',
                data: {
                    labels: ['Contado', 'Crédito', 'Mixto'],
                    datasets: [{
                        data: [<?php echo $contado; ?>, <?php echo $credito; ?>, <?php echo $mixto; ?>],
                        backgroundColor: ['#1cc88a', '#f6c23e', '#36b9cc'],
                        hoverBackgroundColor: ['#17a673', '#e0a800', '#2c9faf'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    let total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = ((ctx.parsed / total) * 100).toFixed(1);
                                    return ctx.label + ': ' + formatearMoneda(ctx.parsed) + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    });
    
    function formatearMoneda(valor) {
        return 'Bs ' + parseFloat(valor).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    </script>
    <?php
}

/**
 * REPORTE DE VENTAS POR PERÍODO
 */
function generarReporteVentasPeriodo($conn, $fecha_inicio, $fecha_fin) {
    // Resumen del período
    $resumen = $conn->prepare("
        SELECT 
            COUNT(*) as total_ventas,
            COALESCE(SUM(total), 0) as monto_total,
            COALESCE(SUM(pago_inicial), 0) as total_pagado,
            COALESCE(SUM(debe), 0) as total_deuda,
            COALESCE(AVG(total), 0) as ticket_promedio,
            COUNT(DISTINCT cliente_id) as clientes_atendidos,
            COUNT(DISTINCT vendedor_id) as vendedores_activos
        FROM ventas 
        WHERE fecha BETWEEN ? AND ? AND anulado = 0
    ");
    $resumen->bind_param("ss", $fecha_inicio, $fecha_fin);
    $resumen->execute();
    $r = $resumen->get_result()->fetch_assoc();
    
    // Detalle por día
    $detalle = $conn->prepare("
        SELECT 
            DATE(fecha) as fecha,
            DAYOFWEEK(fecha) as dia_semana,
            COUNT(*) as ventas,
            COALESCE(SUM(total), 0) as monto,
            COALESCE(SUM(pago_inicial), 0) as pagado,
            COALESCE(SUM(debe), 0) as deuda,
            COUNT(CASE WHEN tipo_pago = 'contado' THEN 1 END) as contado,
            COUNT(CASE WHEN tipo_pago = 'credito' THEN 1 END) as credito,
            COUNT(CASE WHEN tipo_pago = 'mixto' THEN 1 END) as mixto
        FROM ventas 
        WHERE fecha BETWEEN ? AND ? AND anulado = 0
        GROUP BY DATE(fecha)
        ORDER BY fecha DESC
    ");
    $detalle->bind_param("ss", $fecha_inicio, $fecha_fin);
    $detalle->execute();
    $detalle_result = $detalle->get_result();
    
    $dias_semana = ['', 'Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    ?>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card report-card">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-primary">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Reporte de Ventas por Período
                        </h5>
                        <div>
                            <span class="badge bg-primary p-3">
                                <i class="fas fa-chart-bar me-2"></i>
                                <?php echo $r['total_ventas']; ?> ventas
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- KPIs del período -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="bg-light p-3 rounded">
                                <small class="text-muted">Monto Total</small>
                                <h3 class="mb-0 text-success"><?php echo formatearMoneda($r['monto_total']); ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-light p-3 rounded">
                                <small class="text-muted">Ticket Promedio</small>
                                <h3 class="mb-0 text-primary"><?php echo formatearMoneda($r['ticket_promedio']); ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-light p-3 rounded">
                                <small class="text-muted">Clientes Atendidos</small>
                                <h3 class="mb-0 text-info"><?php echo $r['clientes_atendidos']; ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-light p-3 rounded">
                                <small class="text-muted">Deuda Generada</small>
                                <h3 class="mb-0 text-danger"><?php echo formatearMoneda($r['total_deuda']); ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($detalle_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-report table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Día</th>
                                    <th class="text-center">Ventas</th>
                                    <th class="text-end">Monto</th>
                                    <th class="text-end">Pagado</th>
                                    <th class="text-end">Deuda</th>
                                    <th class="text-center">Contado</th>
                                    <th class="text-center">Crédito</th>
                                    <th class="text-center">Mixto</th>
                                    <th class="text-center">Eficiencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_monto = 0;
                                $total_deuda = 0;
                                while ($d = $detalle_result->fetch_assoc()): 
                                    $total_monto += $d['monto'];
                                    $total_deuda += $d['deuda'];
                                    $eficiencia = $d['monto'] > 0 ? ($d['pagado'] / $d['monto']) * 100 : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo formatearFecha($d['fecha']); ?></strong></td>
                                    <td><?php echo $dias_semana[$d['dia_semana']]; ?></td>
                                    <td class="text-center"><?php echo $d['ventas']; ?></td>
                                    <td class="text-end"><?php echo formatearMoneda($d['monto']); ?></td>
                                    <td class="text-end"><?php echo formatearMoneda($d['pagado']); ?></td>
                                    <td class="text-end text-danger"><?php echo formatearMoneda($d['deuda']); ?></td>
                                    <td class="text-center"><?php echo $d['contado']; ?></td>
                                    <td class="text-center"><?php echo $d['credito']; ?></td>
                                    <td class="text-center"><?php echo $d['mixto']; ?></td>
                                    <td class="text-center">
                                        <div class="progress-report" style="width: 100px; margin: 0 auto;">
                                            <div class="progress-report-bar" style="width: <?php echo $eficiencia; ?>%;"></div>
                                        </div>
                                        <small><?php echo number_format($eficiencia, 1); ?>%</small>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot class="table-success">
                                <tr>
                                    <th colspan="2">TOTALES</th>
                                    <th class="text-center"><?php echo $r['total_ventas']; ?></th>
                                    <th class="text-end"><?php echo formatearMoneda($total_monto); ?></th>
                                    <th class="text-end"><?php echo formatearMoneda($r['total_pagado']); ?></th>
                                    <th class="text-end text-danger"><?php echo formatearMoneda($total_deuda); ?></th>
                                    <th colspan="4"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No hay ventas en el período seleccionado</h5>
                        <p class="text-muted">Intente con otro rango de fechas</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * REPORTE DE STOCK BAJO - CORREGIDO (usa i.subpaquetes_por_paquete)
 */
function generarReporteStockBajo($conn) {
    $query = "SELECT 
                p.codigo,
                p.nombre_color,
                pr.nombre as proveedor,
                c.nombre as categoria,
                i.total_subpaquetes as stock_actual,
                i.paquetes_completos,
                i.subpaquetes_sueltos,
                i.subpaquetes_por_paquete,
                i.costo_paquete,
                ROUND(i.costo_paquete / i.subpaquetes_por_paquete, 2) as costo_subpaquete,
                p.precio_menor,
                p.precio_mayor
              FROM productos p
              JOIN inventario i ON p.id = i.producto_id
              JOIN proveedores pr ON p.proveedor_id = pr.id
              JOIN categorias c ON p.categoria_id = c.id
              WHERE i.total_subpaquetes < 50
                AND p.activo = 1
              ORDER BY i.total_subpaquetes ASC
              LIMIT 100";
    
    $result = $conn->query($query);
    
    $total_productos_bajo = $result->num_rows;
    $valor_total_reposicion = 0;
    ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card report-card border-left-warning">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Productos con Stock Bajo
                        </h5>
                        <span class="badge bg-warning text-dark p-3">
                            <i class="fas fa-boxes me-2"></i>
                            <?php echo $total_productos_bajo; ?> productos
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($result->num_rows > 0): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        Estos productos requieren reposición urgente. Stock mínimo recomendado: 50 unidades.
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-report table-hover">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Producto</th>
                                    <th>Proveedor</th>
                                    <th>Categoría</th>
                                    <th class="text-center">Stock Actual</th>
                                    <th class="text-center">Paquetes</th>
                                    <th class="text-center">Subpaquetes</th>
                                    <th class="text-end">Costo Paquete</th>
                                    <th class="text-end">Precio Menor</th>
                                    <th class="text-end">Precio Mayor</th>
                                    <th class="text-end">Valor Reposición</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): 
                                    $necesario = 50 - $row['stock_actual'];
                                    $valor_reposicion = $necesario > 0 ? $necesario * $row['costo_subpaquete'] : 0;
                                    $valor_total_reposicion += $valor_reposicion;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['codigo']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['nombre_color']); ?></td>
                                    <td><?php echo htmlspecialchars($row['proveedor']); ?></td>
                                    <td><?php echo htmlspecialchars($row['categoria']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-danger p-2"><?php echo $row['stock_actual']; ?></span>
                                    </td>
                                    <td class="text-center"><?php echo $row['paquetes_completos']; ?></td>
                                    <td class="text-center"><?php echo $row['subpaquetes_sueltos']; ?></td>
                                    <td class="text-end"><?php echo formatearMoneda($row['costo_paquete']); ?></td>
                                    <td class="text-end"><?php echo formatearMoneda($row['precio_menor']); ?></td>
                                    <td class="text-end"><?php echo formatearMoneda($row['precio_mayor']); ?></td>
                                    <td class="text-end text-danger fw-bold">
                                        <?php echo formatearMoneda($valor_reposicion); ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot class="table-warning">
                                <tr>
                                    <th colspan="10" class="text-end">TOTAL REPOSICIÓN ESTIMADA:</th>
                                    <th class="text-end text-danger"><?php echo formatearMoneda($valor_total_reposicion); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h5 class="text-success">¡Inventario en óptimas condiciones!</h5>
                        <p class="text-muted">No hay productos con stock bajo.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * REPORTE DE CLIENTES MOROSOS - CORREGIDO
 */
function generarReporteClientesMorosos($conn) {
    $query = "SELECT 
                c.codigo,
                c.nombre,
                c.telefono,
                c.saldo_actual,
                c.limite_credito,
                COUNT(cc.id) as facturas_pendientes,
                DATEDIFF(CURDATE(), MIN(cc.fecha_vencimiento)) as max_dias_mora,
                DATEDIFF(CURDATE(), MAX(cc.fecha_registro)) as dias_ultima_compra
              FROM clientes c
              JOIN clientes_cuentas_cobrar cc ON c.id = cc.cliente_id
              WHERE c.saldo_actual > 0 
                AND cc.estado = 'pendiente'
              GROUP BY c.id
              ORDER BY max_dias_mora DESC, c.saldo_actual DESC";
    
    $result = $conn->query($query);
    
    $total_deuda = 0;
    $clientes_riesgo = 0;
    ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card report-card border-left-danger">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Clientes con Deuda Pendiente
                        </h5>
                        <div>
                            <span class="badge bg-danger p-3 me-2">
                                <i class="fas fa-users me-2"></i>
                                <?php echo $result->num_rows; ?> clientes
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-report table-hover">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Teléfono</th>
                                    <th class="text-end">Deuda Total</th>
                                    <th class="text-end">Límite Crédito</th>
                                    <th class="text-end">% Utilizado</th>
                                    <th class="text-center">Facturas</th>
                                    <th class="text-center">Días Mora</th>
                                    <th class="text-center">Última Compra</th>
                                    <th class="text-center">Riesgo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                while ($row = $result->fetch_assoc()): 
                                    $total_deuda += $row['saldo_actual'];
                                    $porc_credito = $row['limite_credito'] > 0 ? 
                                        ($row['saldo_actual'] / $row['limite_credito']) * 100 : 100;
                                    
                                    if ($row['max_dias_mora'] > 90 || $porc_credito > 90) {
                                        $riesgo = 'CRÍTICO';
                                        $clase_riesgo = 'bg-danger';
                                        $clientes_riesgo++;
                                    } elseif ($row['max_dias_mora'] > 30 || $porc_credito > 70) {
                                        $riesgo = 'ALTO';
                                        $clase_riesgo = 'bg-warning text-dark';
                                        $clientes_riesgo++;
                                    } elseif ($row['max_dias_mora'] > 15 || $porc_credito > 50) {
                                        $riesgo = 'MEDIO';
                                        $clase_riesgo = 'bg-info';
                                    } else {
                                        $riesgo = 'BAJO';
                                        $clase_riesgo = 'bg-success';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['nombre']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($row['codigo']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['telefono'] ?: 'N/A'); ?></td>
                                    <td class="text-end text-danger fw-bold"><?php echo formatearMoneda($row['saldo_actual']); ?></td>
                                    <td class="text-end"><?php echo formatearMoneda($row['limite_credito']); ?></td>
                                    <td class="text-end">
                                        <div style="width: 80px; float: right;">
                                            <div class="progress-report">
                                                <div class="progress-report-bar bg-<?php echo $porc_credito > 80 ? 'danger' : ($porc_credito > 50 ? 'warning' : 'success'); ?>" 
                                                     style="width: <?php echo min($porc_credito, 100); ?>%;"></div>
                                            </div>
                                            <small><?php echo number_format($porc_credito, 1); ?>%</small>
                                        </div>
                                    </td>
                                    <td class="text-center"><?php echo $row['facturas_pendientes']; ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $row['max_dias_mora'] > 30 ? 'danger' : ($row['max_dias_mora'] > 15 ? 'warning' : 'info'); ?> p-2">
                                            <?php echo $row['max_dias_mora']; ?> días
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo $row['dias_ultima_compra']; ?> días</td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $clase_riesgo; ?> p-2"><?php echo $riesgo; ?></span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot class="table-danger">
                                <tr>
                                    <th colspan="2">TOTAL DEUDA</th>
                                    <th class="text-end"><?php echo formatearMoneda($total_deuda); ?></th>
                                    <th colspan="2"></th>
                                    <th class="text-center"><?php echo $result->num_rows; ?></th>
                                    <th class="text-center">-</th>
                                    <th class="text-center">-</th>
                                    <th class="text-center"><?php echo $clientes_riesgo; ?> críticos</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-smile fa-4x text-success mb-3"></i>
                        <h5 class="text-success">¡Excelente!</h5>
                        <p class="text-muted">No hay clientes con deuda pendiente.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * REPORTE DE MOVIMIENTOS DE CAJA
 */
function generarReporteCaja($conn, $fecha_inicio, $fecha_fin) {
    // Resumen de caja
    $resumen = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as total_ingresos,
            COALESCE(SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END), 0) as total_gastos,
            COUNT(*) as total_movimientos,
            COUNT(DISTINCT categoria) as categorias
        FROM movimientos_caja
        WHERE fecha BETWEEN ? AND ?
    ");
    $resumen->bind_param("ss", $fecha_inicio, $fecha_fin);
    $resumen->execute();
    $r = $resumen->get_result()->fetch_assoc();
    $balance = $r['total_ingresos'] - $r['total_gastos'];
    
    // Detalle de movimientos
    $detalle = $conn->prepare("
        SELECT 
            mc.*,
            u.nombre as usuario_nombre
        FROM movimientos_caja mc
        JOIN usuarios u ON mc.usuario_id = u.id
        WHERE mc.fecha BETWEEN ? AND ?
        ORDER BY mc.fecha DESC, mc.hora DESC
    ");
    $detalle->bind_param("ss", $fecha_inicio, $fecha_fin);
    $detalle->execute();
    $detalle_result = $detalle->get_result();
    
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
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card report-card">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-success">
                        <i class="fas fa-cash-register me-2"></i>
                        Movimientos de Caja
                    </h5>
                </div>
                <div class="card-body">
                    <!-- KPIs de caja -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="bg-success bg-gradient text-white p-3 rounded">
                                <small>Ingresos</small>
                                <h3 class="mb-0"><?php echo formatearMoneda($r['total_ingresos']); ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-danger bg-gradient text-white p-3 rounded">
                                <small>Gastos</small>
                                <h3 class="mb-0"><?php echo formatearMoneda($r['total_gastos']); ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-primary bg-gradient text-white p-3 rounded">
                                <small>Balance</small>
                                <h3 class="mb-0"><?php echo formatearMoneda($balance); ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-info bg-gradient text-white p-3 rounded">
                                <small>Movimientos</small>
                                <h3 class="mb-0"><?php echo $r['total_movimientos']; ?></h3>
                                <small><?php echo $r['categorias']; ?> categorías</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($detalle_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-report table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Hora</th>
                                    <th>Tipo</th>
                                    <th>Categoría</th>
                                    <th>Descripción</th>
                                    <th class="text-end">Monto</th>
                                    <th>Usuario</th>
                                    <th>Referencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($mov = $detalle_result->fetch_assoc()): ?>
                                <tr class="<?php echo $mov['tipo'] == 'ingreso' ? 'table-success' : 'table-danger'; ?>">
                                    <td><?php echo formatearFecha($mov['fecha']); ?></td>
                                    <td><?php echo substr($mov['hora'], 0, 5); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $mov['tipo'] == 'ingreso' ? 'success' : 'danger'; ?> p-2">
                                            <?php echo strtoupper($mov['tipo']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $categorias[$mov['categoria']] ?? $mov['categoria']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($mov['descripcion']); ?>
                                        <?php if (!empty($mov['observaciones'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($mov['observaciones'], 0, 50)); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold <?php echo $mov['tipo'] == 'ingreso' ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $mov['tipo'] == 'ingreso' ? '+' : '-'; ?>
                                        <?php echo formatearMoneda($mov['monto']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($mov['usuario_nombre']); ?></td>
                                    <td><?php echo !empty($mov['referencia_venta']) ? '<span class="badge bg-info">' . $mov['referencia_venta'] . '</span>' : '-'; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-coins fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No hay movimientos de caja en el período</h5>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * REPORTE DE BALANCE GENERAL - CORREGIDO
 */
function generarReporteBalance($conn, $fecha_inicio, $fecha_fin) {
    // Activos - Inventario (usando costo_paquete)
    $inventario = $conn->query("
        SELECT 
            COUNT(*) as total_productos,
            COALESCE(SUM(i.total_subpaquetes), 0) as total_unidades,
            COALESCE(SUM(i.total_subpaquetes * (i.costo_paquete / i.subpaquetes_por_paquete)), 0) as valor_inventario
        FROM inventario i
        JOIN productos p ON i.producto_id = p.id
        WHERE p.activo = 1
    ")->fetch_assoc();
    
    // Activos - Cuentas por Cobrar
    $cxc = $conn->query("
        SELECT 
            COUNT(*) as total_clientes,
            COALESCE(SUM(saldo_actual), 0) as total_cxc
        FROM clientes
        WHERE saldo_actual > 0
    ")->fetch_assoc();
    
    // Pasivos - Cuentas por Pagar
    $cxp = $conn->query("
        SELECT 
            COUNT(*) as total_proveedores,
            COALESCE(SUM(saldo_actual), 0) as total_cxp
        FROM proveedores
        WHERE saldo_actual > 0
    ")->fetch_assoc();
    
    // Efectivo estimado (ventas contado del período - gastos)
    $efectivo = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as ingresos,
            COALESCE(SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END), 0) as gastos
        FROM movimientos_caja
        WHERE fecha <= ?
    ");
    $efectivo->bind_param("s", $fecha_fin);
    $efectivo->execute();
    $ef = $efectivo->get_result()->fetch_assoc();
    $saldo_efectivo = $ef['ingresos'] - $ef['gastos'];
    
    // Capital
    $total_activos = $inventario['valor_inventario'] + $cxc['total_cxc'] + $saldo_efectivo;
    $total_pasivos = $cxp['total_cxp'];
    $capital = $total_activos - $total_pasivos;
    ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card report-card">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary">
                        <i class="fas fa-balance-scale me-2"></i>
                        Balance General
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- ACTIVOS -->
                        <div class="col-md-6">
                            <div class="card border-success mb-4">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">ACTIVOS</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Efectivo en Caja</strong></td>
                                            <td class="text-end"><?php echo formatearMoneda($saldo_efectivo); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Inventario de Productos</strong></td>
                                            <td class="text-end"><?php echo formatearMoneda($inventario['valor_inventario']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Cuentas por Cobrar</strong> (<?php echo $cxc['total_clientes']; ?> clientes)</td>
                                            <td class="text-end"><?php echo formatearMoneda($cxc['total_cxc']); ?></td>
                                        </tr>
                                        <tr class="table-success">
                                            <td><h6 class="mb-0">TOTAL ACTIVOS</h6></td>
                                            <td class="text-end"><h6 class="mb-0"><?php echo formatearMoneda($total_activos); ?></h6></td>
                                        </tr>
                                    </table>
                                    
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-cube me-1"></i>
                                            <?php echo $inventario['total_productos']; ?> productos, 
                                            <?php echo number_format($inventario['total_unidades'], 0); ?> unidades
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- PASIVOS Y CAPITAL -->
                        <div class="col-md-6">
                            <div class="card border-danger mb-4">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0">PASIVOS</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Cuentas por Pagar</strong> (<?php echo $cxp['total_proveedores']; ?> proveedores)</td>
                                            <td class="text-end"><?php echo formatearMoneda($cxp['total_cxp']); ?></td>
                                        </tr>
                                        <tr class="table-danger">
                                            <td><h6 class="mb-0">TOTAL PASIVOS</h6></td>
                                            <td class="text-end"><h6 class="mb-0"><?php echo formatearMoneda($total_pasivos); ?></h6></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">CAPITAL</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Capital Contable</strong></td>
                                            <td class="text-end"><?php echo formatearMoneda($capital); ?></td>
                                        </tr>
                                        <tr class="table-info">
                                            <td><h6 class="mb-0">TOTAL PASIVO + CAPITAL</h6></td>
                                            <td class="text-end"><h6 class="mb-0"><?php echo formatearMoneda($total_pasivos + $capital); ?></h6></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ratios Financieros -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card bg-light">
                                <div class="card-header">
                                    <h6 class="mb-0">Ratios Financieros</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-md-3">
                                            <div class="p-3">
                                                <h5 class="text-primary"><?php echo $total_activos > 0 ? number_format(($total_activos / ($total_activos + $total_pasivos)) * 100, 1) : 0; ?>%</h5>
                                                <small class="text-muted">Solvencia</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="p-3">
                                                <h5 class="text-success"><?php echo formatearMoneda($capital); ?></h5>
                                                <small class="text-muted">Capital de Trabajo</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="p-3">
                                                <h5 class="text-warning"><?php echo $total_activos > 0 ? number_format(($total_pasivos / $total_activos) * 100, 1) : 0; ?>%</h5>
                                                <small class="text-muted">Endeudamiento</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="p-3">
                                                <h5 class="text-info"><?php echo $total_pasivos > 0 ? number_format($cxc['total_cxc'] / $total_pasivos, 2) : $cxc['total_cxc']; ?></h5>
                                                <small class="text-muted">Liquidez</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Funciones restantes (deben implementarse según necesidad)
function generarReporteVentasVendedor($conn, $fecha_inicio, $fecha_fin, $vendedor_id = null) {
    echo "<div class='alert alert-info'>Reporte de ventas por vendedor en construcción</div>";
}

function generarReporteVentasProducto($conn, $fecha_inicio, $fecha_fin, $categoria_id = null) {
    echo "<div class='alert alert-info'>Reporte de ventas por producto en construcción</div>";
}

function generarReporteVentasCategoria($conn, $fecha_inicio, $fecha_fin) {
    echo "<div class='alert alert-info'>Reporte de ventas por categoría en construcción</div>";
}

function generarReporteVentasHorario($conn, $fecha_inicio, $fecha_fin) {
    echo "<div class='alert alert-info'>Reporte de ventas por horario en construcción</div>";
}

function generarReporteInventarioGeneral($conn) {
    echo "<div class='alert alert-info'>Reporte de inventario general en construcción</div>";
}

function generarReporteValorInventario($conn) {
    echo "<div class='alert alert-info'>Reporte de valor de inventario en construcción</div>";
}

function generarReporteMovimientosStock($conn, $fecha_inicio, $fecha_fin) {
    echo "<div class='alert alert-info'>Reporte de movimientos de stock en construcción</div>";
}

function generarReporteCuentasCobrar($conn) {
    echo "<div class='alert alert-info'>Reporte de cuentas por cobrar en construcción</div>";
}

function generarReporteCuentasPagar($conn) {
    echo "<div class='alert alert-info'>Reporte de cuentas por pagar en construcción</div>";
}

function generarReporteClientesFrecuentes($conn, $fecha_inicio, $fecha_fin) {
    echo "<div class='alert alert-info'>Reporte de clientes frecuentes en construcción</div>";
}

function generarReporteClientesInactivos($conn) {
    echo "<div class='alert alert-info'>Reporte de clientes inactivos en construcción</div>";
}

function generarReporteVendedores($conn, $fecha_inicio, $fecha_fin) {
    echo "<div class='alert alert-info'>Reporte de vendedores en construcción</div>";
}

require_once 'footer.php';
?>