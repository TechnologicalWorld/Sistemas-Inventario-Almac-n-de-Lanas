<?php
session_start();

$titulo_pagina = "Dashboard";
$icono_titulo = "fas fa-chart-pie";
$breadcrumb = [
    ['text' => 'Inicio', 'link' => 'dashboard.php', 'active' => false],
    ['text' => 'Dashboard', 'link' => '#', 'active' => true]
];

require_once 'header.php';
require_once 'funciones.php';

// Obtener datos del dashboard con todas las estadísticas
$dashboard_data = obtenerDashboardDataProfesional($_SESSION['usuario_id'], $_SESSION['usuario_rol']);

// Obtener fecha y hora
$fecha_actual = date('d/m/Y');
$hora_actual = date('H:i:s');

// Meses en español
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
$mes_actual = $meses[(int)date('m')];
?>

<!-- ===================================================
     ESTILOS PERSONALIZADOS PARA DASHBOARD
     =================================================== -->
<style>
/* ========== VARIABLES ========== */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea, #764ba2);
    --success-gradient: linear-gradient(135deg, #28a745, #20c997);
    --warning-gradient: linear-gradient(135deg, #ffc107, #fd7e14);
    --danger-gradient: linear-gradient(135deg, #dc3545, #c82333);
    --info-gradient: linear-gradient(135deg, #17a2b8, #0dcaf0);
    --dark-gradient: linear-gradient(135deg, #2c3e50, #1e2a36);
}

/* ========== DASHBOARD LAYOUT ========== */
.dashboard-wrapper {
    display: grid;
    gap: 1.5rem;
    grid-template-columns: repeat(12, 1fr);
}

/* Tarjetas de estadísticas */
.stat-card {
    background: var(--primary-gradient);
    border-radius: 20px;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    grid-column: span 3;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200px;
    height: 200px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.stat-content {
    position: relative;
    z-index: 2;
}

.stat-label {
    color: rgba(255,255,255,0.9);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 0.5rem;
}

.stat-value {
    color: white;
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.stat-trend {
    color: rgba(255,255,255,0.9);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stat-icon {
    position: absolute;
    bottom: 1rem;
    right: 1.5rem;
    font-size: 3rem;
    color: rgba(255,255,255,0.2);
    z-index: 1;
}

/* Tarjetas de gráficos */
.chart-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.chart-card:hover {
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.chart-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.chart-title i {
    color: #28a745;
    margin-right: 0.5rem;
}

.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

/* Grid layout */
.grid-span-3 { grid-column: span 3; }
.grid-span-4 { grid-column: span 4; }
.grid-span-5 { grid-column: span 5; }
.grid-span-6 { grid-column: span 6; }
.grid-span-7 { grid-column: span 7; }
.grid-span-8 { grid-column: span 8; }
.grid-span-9 { grid-column: span 9; }
.grid-span-12 { grid-column: span 12; }

/* Tablas personalizadas */
.table-dashboard {
    width: 100%;
    border-collapse: collapse;
}

.table-dashboard th {
    background: #f8f9fa;
    padding: 0.75rem 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table-dashboard td {
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    border-bottom: 1px solid #e9ecef;
}

.table-dashboard tr:hover td {
    background: #f8f9fa;
}

/* Alertas y badges */
.alert-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
}

.alert-critical {
    background: #fee2e2;
    color: #dc3545;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
}

.alert-success {
    background: #d4edda;
    color: #155724;
}

/* Timeline */
.timeline-dashboard {
    position: relative;
    padding-left: 2rem;
}

.timeline-dashboard::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #28a745, #20c997);
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -2.1rem;
    top: 0.25rem;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: white;
    border: 2px solid #28a745;
}

/* Botones de reportes */
.report-btn-group {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.report-btn {
    padding: 0.5rem 1rem;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    background: white;
    color: #495057;
    font-size: 0.85rem;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    text-decoration: none;
}

.report-btn:hover {
    background: #f8f9fa;
    border-color: #28a745;
    color: #28a745;
}

.report-btn.active {
    background: #28a745;
    border-color: #28a745;
    color: white;
}

/* Responsive */
@media (max-width: 1200px) {
    .grid-span-3, .grid-span-4, .grid-span-5, .grid-span-6, 
    .grid-span-7, .grid-span-8, .grid-span-9 {
        grid-column: span 12;
    }
    
    .stat-card {
        grid-column: span 6;
    }
}

@media (max-width: 768px) {
    .stat-card {
        grid-column: span 12;
    }
    
    .dashboard-wrapper {
        gap: 1rem;
    }
    
    .stat-value {
        font-size: 1.8rem;
    }
}
</style>

<!-- ===================================================
     ENCABEZADO DEL DASHBOARD
     =================================================== -->
<div class="dashboard-wrapper">
    <div class="grid-span-12">
        <div class="chart-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1" style="color: var(--color-dark);">
                        <i class="fas fa-hand-wave me-2" style="color: var(--color-primary);"></i>
                        ¡Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>!
                    </h4>
                    <p class="text-muted mb-0">
                        <i class="fas fa-calendar-alt me-1"></i> <?php echo $fecha_actual; ?> 
                        <span class="mx-2">|</span>
                        <i class="fas fa-clock me-1"></i> <?php echo $hora_actual; ?>
                        <span class="mx-2">|</span>
                        <span class="badge bg-success"><?php echo ucfirst($_SESSION['usuario_rol']); ?></span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================================================
         FILA 1: ESTADÍSTICAS PRINCIPALES
         =================================================== -->
    <!-- Tarjeta 1: Ventas del Día -->
    <div class="stat-card" style="background: linear-gradient(135deg, #667eea, #764ba2);">
        <div class="stat-content">
            <div class="stat-label">
                <i class="fas fa-shopping-cart me-1"></i> Ventas Hoy
            </div>
            <div class="stat-value" id="ventasHoyValue">
                <?php echo number_format($dashboard_data['ventas_hoy']['total'] ?? 0); ?>
            </div>
            <div class="stat-trend">
                <i class="fas fa-arrow-up"></i>
                <?php echo $dashboard_data['comparacion_ayer'] ?? '+0%'; ?> vs ayer
            </div>
            <small class="text-white opacity-75">
                Total: <?php echo formatearMoneda($dashboard_data['ventas_hoy']['monto'] ?? 0); ?>
            </small>
        </div>
        <div class="stat-icon">
            <i class="fas fa-chart-line"></i>
        </div>
    </div>

    <!-- Tarjeta 2: Cobros Pendientes -->
    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
        <div class="stat-content">
            <div class="stat-label">
                <i class="fas fa-clock me-1"></i> Cobros Pendientes
            </div>
            <div class="stat-value">
                <?php echo number_format($dashboard_data['total_clientes_deuda'] ?? 0); ?>
            </div>
            <div class="stat-trend">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $dashboard_data['clientes_vencidos'] ?? 0; ?> vencidos
            </div>
            <small class="text-white opacity-75">
                Total: <?php echo formatearMoneda($dashboard_data['total_deuda'] ?? 0); ?>
            </small>
        </div>
        <div class="stat-icon">
            <i class="fas fa-hand-holding-usd"></i>
        </div>
    </div>

    <!-- Tarjeta 3: Stock Crítico -->
    <div class="stat-card" style="background: linear-gradient(135deg, #fa709a, #fee140);">
        <div class="stat-content">
            <div class="stat-label">
                <i class="fas fa-exclamation-triangle me-1"></i> Stock Crítico
            </div>
            <div class="stat-value">
                <?php echo number_format($dashboard_data['total_productos_criticos'] ?? 0); ?>
            </div>
            <div class="stat-trend">
                <i class="fas fa-boxes"></i>
                Bajo: <?php echo $dashboard_data['total_productos_bajos'] ?? 0; ?>
            </div>
            <small class="text-white opacity-75">
                Requiere atención inmediata
            </small>
        </div>
        <div class="stat-icon">
            <i class="fas fa-box-open"></i>
        </div>
    </div>

    <!-- Tarjeta 4: Balance de Caja -->
    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
        <div class="stat-content">
            <div class="stat-label">
                <i class="fas fa-cash-register me-1"></i> Balance Caja
            </div>
            <div class="stat-value">
                <?php echo formatearMoneda($dashboard_data['balance_caja_hoy'] ?? 0); ?>
            </div>
            <div class="stat-trend">
                <i class="fas fa-arrow-up text-success"></i> Ing: <?php echo formatearMoneda($dashboard_data['ingresos_hoy'] ?? 0); ?>
                <span class="mx-1">|</span>
                <i class="fas fa-arrow-down text-danger"></i> Gas: <?php echo formatearMoneda($dashboard_data['gastos_hoy'] ?? 0); ?>
            </div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-coins"></i>
        </div>
    </div>

    <!-- Tarjeta 5: Ventas del Mes -->
    <div class="stat-card" style="background: linear-gradient(135deg, #43e97b, #38f9d7);">
        <div class="stat-content">
            <div class="stat-label">
                <i class="fas fa-calendar-alt me-1"></i> Ventas <?php echo $mes_actual; ?>
            </div>
            <div class="stat-value">
                <?php echo number_format($dashboard_data['ventas_mes']['total'] ?? 0); ?>
            </div>
            <div class="stat-trend">
                <i class="fas fa-chart-bar"></i>
                <?php echo formatearMoneda($dashboard_data['ventas_mes']['monto'] ?? 0); ?>
            </div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-chart-bar"></i>
        </div>
    </div>

    <!-- Tarjeta 6: Clientes Activos -->
    <div class="stat-card" style="background: linear-gradient(135deg, #fdc830, #f37335);">
        <div class="stat-content">
            <div class="stat-label">
                <i class="fas fa-users me-1"></i> Clientes Activos
            </div>
            <div class="stat-value">
                <?php echo number_format($dashboard_data['total_clientes_activos'] ?? 0); ?>
            </div>
            <div class="stat-trend">
                <i class="fas fa-user-plus"></i>
                +<?php echo $dashboard_data['clientes_nuevos_mes'] ?? 0; ?> este mes
            </div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
    </div>

    <!-- Tarjeta 7: Deuda Proveedores -->
    <div class="stat-card" style="background: linear-gradient(135deg, #5f2c82, #49a09d);">
        <div class="stat-content">
            <div class="stat-label">
                <i class="fas fa-truck me-1"></i> Deuda Proveedores
            </div>
            <div class="stat-value">
                <?php echo formatearMoneda($dashboard_data['total_deuda_proveedores'] ?? 0); ?>
            </div>
            <div class="stat-trend">
                <i class="fas fa-building"></i>
                <?php echo $dashboard_data['proveedores_con_deuda'] ?? 0; ?> proveedores
            </div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-truck"></i>
        </div>
    </div>

    <!-- ===================================================
         FILA 2: GRÁFICOS PRINCIPALES
         =================================================== -->
    <!-- GRÁFICO 1: Ventas - Líneas -->
    <div class="grid-span-7 chart-card">
        <div class="chart-header">
            <h5 class="chart-title">
                <i class="fas fa-chart-line"></i>
                Tendencia de Ventas
            </h5>
            <div class="btn-group" style="gap: 5px;" id="periodoVentasButtons">
                <button class="report-btn active" onclick="cambiarPeriodoVentas('semana', this)">
                    <i class="fas fa-calendar-week"></i> Semana
                </button>
                <!--<button class="report-btn" onclick="cambiarPeriodoVentas('mes', this)">
                    <i class="fas fa-calendar-alt"></i> Mes
                </button>
                <button class="report-btn" onclick="cambiarPeriodoVentas('anio', this)">
                    <i class="fas fa-calendar"></i> Año
                </button>-->
            </div>
        </div>
        <div class="chart-container">
            <canvas id="graficoVentas"></canvas>
        </div>
    </div>

    <!-- GRÁFICO 2: Ventas por Tipo - Pastel -->
    <div class="grid-span-5 chart-card">
        <div class="chart-header">
            <h5 class="chart-title">
                <i class="fas fa-chart-pie"></i>
                Ventas por Tipo de Pago
            </h5>
            <span class="badge bg-success">Hoy</span>
        </div>
        <div class="chart-container">
            <canvas id="graficoVentasTipo"></canvas>
        </div>
        <div class="row mt-3 text-center">
            <div class="col-4">
                <span class="badge bg-success mb-1">●</span>
                <small class="d-block">Contado</small>
                <strong><?php echo formatearMoneda($dashboard_data['ventas_contado_hoy'] ?? 0); ?></strong>
            </div>
            <div class="col-4">
                <span class="badge bg-warning mb-1">●</span>
                <small class="d-block">Crédito</small>
                <strong><?php echo formatearMoneda($dashboard_data['ventas_credito_hoy'] ?? 0); ?></strong>
            </div>
            <div class="col-4">
                <span class="badge bg-info mb-1">●</span>
                <small class="d-block">Mixto</small>
                <strong><?php echo formatearMoneda($dashboard_data['ventas_mixto_hoy'] ?? 0); ?></strong>
            </div>
        </div>
    </div>

    <!-- ===================================================
         FILA 3: GRÁFICOS SECUNDARIOS
         =================================================== -->
    <!-- GRÁFICO 3: Productos Más Vendidos -->
    <div class="grid-span-4 chart-card">
        <div class="chart-header">
            <h5 class="chart-title">
                <i class="fas fa-crown"></i>
                Top 5 Productos
            </h5>
            <div class="dropdown">
                <button class="report-btn" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="javascript:void(0);" onclick="exportarGrafico('ProductosTop', 'png')">Exportar PNG</a></li>
                    <li><a class="dropdown-item" href="javascript:void(0);" onclick="exportarGrafico('ProductosTop', 'pdf')">Exportar PDF</a></li>
                </ul>
            </div>
        </div>
        <div class="chart-container" style="height: 250px;">
            <canvas id="graficoProductosTop"></canvas>
        </div>
    </div>

    <!-- GRÁFICO 4: Estado de Inventario -->
    <div class="grid-span-4 chart-card">
        <div class="chart-header">
            <h5 class="chart-title">
                <i class="fas fa-boxes"></i>
                Estado del Inventario
            </h5>
            <span class="badge bg-info">Stock Total: <?php echo number_format($dashboard_data['stock_total'] ?? 0); ?></span>
        </div>
        <div class="chart-container" style="height: 250px;">
            <canvas id="graficoInventario"></canvas>
        </div>
        <div class="d-flex justify-content-between mt-2">
            <div><span class="badge bg-success">●</span> Normal: <?php echo $dashboard_data['productos_normales'] ?? 0; ?></div>
            <div><span class="badge bg-warning">●</span> Bajo: <?php echo $dashboard_data['total_productos_bajos'] ?? 0; ?></div>
            <div><span class="badge bg-danger">●</span> Crítico: <?php echo $dashboard_data['total_productos_criticos'] ?? 0; ?></div>
        </div>
    </div>

    <!-- GRÁFICO 5: Gastos por Categoría -->
    <div class="grid-span-4 chart-card">
        <div class="chart-header">
            <h5 class="chart-title">
                <i class="fas fa-chart-pie"></i>
                Gastos del Mes
            </h5>
            <span class="badge bg-danger">Total: <?php echo formatearMoneda($dashboard_data['total_gastos_mes'] ?? 0); ?></span>
        </div>
        <div class="chart-container" style="height: 250px;">
            <canvas id="graficoGastos"></canvas>
        </div>
    </div>

    <!-- GRÁFICO 6: Evolución de Cobros -->
    <div class="grid-span-6 chart-card">
        <div class="chart-header">
            <h5 class="chart-title">
                <i class="fas fa-hand-holding-usd"></i>
                Evolución de Cobros
            </h5>
            <div class="btn-group" style="gap: 5px;" id="periodoCobrosButtons">
                <button class="report-btn active" onclick="cambiarPeriodoCobros('semana', this)">
                    <i class="fas fa-calendar-week"></i> Semana
                </button>
                <button class="report-btn" onclick="cambiarPeriodoCobros('mes', this)">
                    <i class="fas fa-calendar-alt"></i> Mes
                </button>
            </div>
        </div>
        <div class="chart-container" style="height: 250px;">
            <canvas id="graficoCobros"></canvas>
        </div>
    </div>

    <!-- GRÁFICO 7: Rentabilidad -->
    <div class="grid-span-6 chart-card">
        <div class="chart-header">
            <h5 class="chart-title">
                <i class="fas fa-chart-bar"></i>
                Rentabilidad por Producto
            </h5>
            <span class="badge bg-success">Margen Promedio: <?php echo $dashboard_data['margen_promedio'] ?? '0%'; ?></span>
        </div>
        <div class="chart-container" style="height: 250px;">
            <canvas id="graficoRentabilidad"></canvas>
        </div>
    </div>

    <!-- ===================================================
         FILA 4: TABLAS Y TIMELINE
         =================================================== -->
    <!-- Tabla: Últimas Ventas -->
    <div class="grid-span-6 chart-card">
        <div class="chart-header">
            <h5 class="chart-title">
                <i class="fas fa-history"></i>
                Últimas Ventas
            </h5>
            <button class="report-btn" onclick="window.location.href='ventas.php'">
                Ver todas <i class="fas fa-arrow-right ms-1"></i>
            </button>
        </div>
        <div style="max-height: 350px; overflow-y: auto;">
            <table class="table-dashboard">
                <thead>
                    <tr>
                        <th>Venta</th>
                        <th>Cliente</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Hora</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dashboard_data['ultimas_ventas'])): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                            No hay ventas recientes
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach (array_slice($dashboard_data['ultimas_ventas'], 0, 8) as $venta): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($venta['codigo_venta']); ?></code></td>
                            <td><?php echo htmlspecialchars($venta['cliente'] ?? 'Consumidor Final'); ?></td>
                            <td class="fw-bold"><?php echo formatearMoneda($venta['total']); ?></td>
                            <td>
                                <?php if ($venta['estado'] == 'pagada'): ?>
                                    <span class="alert-badge alert-success">Pagada</span>
                                <?php elseif ($venta['debe'] > 0): ?>
                                    <span class="alert-badge alert-warning">Pendiente</span>
                                <?php else: ?>
                                    <span class="alert-badge alert-success">Contado</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo date('H:i', strtotime($venta['hora_inicio'])); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Timeline: Movimientos de Caja -->
    <div class="grid-span-6 chart-card">
        <div class="chart-header">
            <h5 class="chart-title">
                <i class="fas fa-exchange-alt"></i>
                Movimientos de Caja - Hoy
            </h5>
            <span class="badge bg-info"><?php echo count($dashboard_data['movimientos'] ?? []); ?> movimientos</span>
        </div>
        <div style="max-height: 350px; overflow-y: auto;">
            <div class="timeline-dashboard">
                <?php if (empty($dashboard_data['movimientos'])): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                        <h6 class="text-muted">Sin movimientos hoy</h6>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($dashboard_data['movimientos'], 0, 8) as $movimiento): ?>
                    <div class="timeline-item">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <small class="text-muted"><?php echo date('H:i', strtotime($movimiento['hora'])); ?></small>
                                <h6 style="margin: 5px 0; font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($movimiento['descripcion']); ?>
                                </h6>
                                <small class="text-muted">
                                    <?php echo ucfirst(str_replace('_', ' ', $movimiento['categoria'])); ?>
                                </small>
                            </div>
                            <div class="<?php echo $movimiento['tipo'] == 'ingreso' ? 'text-success' : 'text-danger'; ?> fw-bold">
                                <?php echo $movimiento['tipo'] == 'ingreso' ? '+' : '-'; ?>
                                <?php echo formatearMoneda($movimiento['monto']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabla: Clientes con Mayor Deuda -->
    <div class="grid-span-6 chart-card">
        <div class="chart-header">
            <h5 class="chart-title">
                <i class="fas fa-exclamation-circle"></i>
                Clientes con Mayor Deuda
            </h5>
            <button class="report-btn" onclick="window.location.href='cobros.php'">
                Gestionar <i class="fas fa-arrow-right ms-1"></i>
            </button>
        </div>
        <div style="max-height: 300px; overflow-y: auto;">
            <table class="table-dashboard">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Ciudad</th>
                        <th>Teléfono</th>
                        <th class="text-end">Deuda</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dashboard_data['clientes_deuda'])): ?>
                    <tr>
                        <td colspan="4" class="text-center py-4 text-muted">
                            <i class="fas fa-check-circle fa-2x text-success d-block mb-2"></i>
                            No hay deudas pendientes
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($dashboard_data['clientes_deuda'] as $cliente): ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($cliente['ciudad'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($cliente['telefono'] ?? 'N/A'); ?></td>
                            <td class="text-end">
                                <span class="alert-badge alert-critical">
                                    <?php echo formatearMoneda($cliente['saldo_actual']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabla: Productos con Stock Bajo -->
    <div class="grid-span-6 chart-card">
        <div class="chart-header">
            <h5 class="chart-title">
                <i class="fas fa-exclamation-triangle"></i>
                Productos con Stock Bajo
            </h5>
            <button class="report-btn" onclick="window.location.href='inventario.php'">
                Ver inventario <i class="fas fa-arrow-right ms-1"></i>
            </button>
        </div>
        <div style="max-height: 300px; overflow-y: auto;">
            <table class="table-dashboard">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Proveedor</th>
                        <th class="text-center">Stock</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dashboard_data['inventario_bajo'])): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <i class="fas fa-check-circle fa-2x text-success d-block mb-2"></i>
                            No hay productos con stock bajo
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach (array_slice($dashboard_data['inventario_bajo'], 0, 8) as $producto): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($producto['codigo']); ?></code></td>
                            <td><?php echo htmlspecialchars($producto['nombre_color']); ?></td>
                            <td><?php echo htmlspecialchars($producto['proveedor']); ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo $producto['total_subpaquetes'] < 20 ? 'bg-danger' : 'bg-warning'; ?>">
                                    <?php echo $producto['total_subpaquetes']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($producto['total_subpaquetes'] < 20): ?>
                                    <span class="alert-badge alert-critical">CRÍTICO</span>
                                <?php else: ?>
                                    <span class="alert-badge alert-warning">BAJO</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===================================================
     MODAL PARA REPORTES PERSONALIZADOS
     =================================================== -->
<div class="modal fade" id="reportePersonalizadoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-alt me-2" style="color: var(--color-primary);"></i>
                    Generar Reporte Personalizado
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reporteForm" method="POST" action="exportar_reporte.php" target="_blank">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tipo de Reporte</label>
                            <select class="form-select" name="tipo_reporte" id="tipo_reporte" required>
                                <option value="">Seleccionar...</option>
                                <optgroup label="Ventas">
                                    <option value="ventas_diarias">Ventas Diarias</option>
                                    <option value="ventas_semanales">Ventas Semanales</option>
                                    <option value="ventas_mensuales">Ventas Mensuales</option>
                                    <option value="ventas_por_vendedor">Ventas por Vendedor</option>
                                </optgroup>
                                <optgroup label="Inventario">
                                    <option value="inventario_completo">Inventario Completo</option>
                                    <option value="stock_bajo">Stock Bajo</option>
                                    <option value="movimientos_inventario">Movimientos de Inventario</option>
                                </optgroup>
                                <optgroup label="Clientes">
                                    <option value="clientes_morosos">Clientes Morosos</option>
                                    <option value="estado_cuentas">Estado de Cuentas</option>
                                    <option value="historial_compras">Historial de Compras</option>
                                </optgroup>
                                <optgroup label="Proveedores">
                                    <option value="deudas_proveedores">Deudas con Proveedores</option>
                                    <option value="compras_proveedor">Compras por Proveedor</option>
                                </optgroup>
                                <optgroup label="Financiero">
                                    <option value="caja_diaria">Caja Diaria</option>
                                    <option value="gastos_mensuales">Gastos Mensuales</option>
                                    <option value="balance_general">Balance General</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Formato de Exportación</label>
                            <select class="form-select" name="formato" id="formato" required>
                                <option value="">Seleccionar...</option>
                                <option value="pdf">PDF - Documento Portátil</option>
                                <option value="excel">Excel - Hoja de Cálculo</option>
                                <option value="csv">CSV - Datos separados</option>
                                <option value="html">HTML - Página Web</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Fecha Inicio</label>
                            <input type="date" class="form-control" name="fecha_inicio" id="fecha_inicio" 
                                   value="<?php echo date('Y-m-01'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Fecha Fin</label>
                            <input type="date" class="form-control" name="fecha_fin" id="fecha_fin" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Incluir en el Reporte</label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="incluir_graficos" id="incluir_graficos" checked>
                                        <label class="form-check-label">Incluir Gráficos</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="incluir_tablas" id="incluir_tablas" checked>
                                        <label class="form-check-label">Incluir Tablas</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="incluir_resumen" id="incluir_resumen" checked>
                                        <label class="form-check-label">Incluir Resumen</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Orientación</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="orientacion" id="vertical" value="vertical" checked>
                                <label class="btn btn-outline-primary" for="vertical">
                                    <i class="fas fa-arrows-alt-vertical me-2"></i>Vertical
                                </label>
                                <input type="radio" class="btn-check" name="orientacion" id="horizontal" value="horizontal">
                                <label class="btn btn-outline-primary" for="horizontal">
                                    <i class="fas fa-arrows-alt-horizontal me-2"></i>Horizontal
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="generarReportePersonalizado()">
                    <i class="fas fa-file-export me-2"></i>Generar Reporte
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts necesarios -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
// ===================================================
// CONFIGURACIÓN GLOBAL DE CHART.JS
// ===================================================
Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
Chart.defaults.font.size = 12;
Chart.register(ChartDataLabels);

// Datos para los gráficos (desde PHP)
const datosDashboard = <?php echo json_encode($dashboard_data); ?>;

// Variables globales para los gráficos
let graficoVentas, graficoVentasTipo, graficoProductosTop, graficoInventario, graficoGastos, graficoCobros, graficoRentabilidad;

// ===================================================
// GRÁFICO 1: VENTAS (LÍNEAS)
// ===================================================
function inicializarGraficoVentas() {
    const ctx = document.getElementById('graficoVentas').getContext('2d');
    
    graficoVentas = new Chart(ctx, {
        type: 'line',
        data: {
            labels: datosDashboard.ventas_semanales?.labels || ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
            datasets: [{
                label: 'Ventas',
                data: datosDashboard.ventas_semanales?.valores || [0, 0, 0, 0, 0, 0, 0],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                borderWidth: 3,
                pointBorderColor: '#28a745',
                pointBackgroundColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    callbacks: {
                        label: function(context) {
                            return 'Bs ' + context.raw.toLocaleString('es-BO', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                },
                datalabels: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0, 0, 0, 0.05)' },
                    ticks: {
                        callback: function(value) {
                            return 'Bs ' + value.toLocaleString();
                        }
                    }
                },
                x: { grid: { display: false } }
            }
        }
    });
}

// Función para cambiar período de ventas - CORREGIDA
function cambiarPeriodoVentas(periodo, btn) {
    // Actualizar botones
    const buttons = document.querySelectorAll('#periodoVentasButtons .report-btn');
    buttons.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    // Mostrar loading
    Swal.fire({
        title: 'Cargando datos...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    // Petición AJAX
    fetch(`api/ventas_data.php?periodo=${periodo}`)
        .then(response => {
            if (!response.ok) throw new Error('Error en la respuesta del servidor');
            return response.json();
        })
        .then(data => {
            if (data.error) throw new Error(data.error);
            
            // Actualizar gráfico
            if (graficoVentas) {
                graficoVentas.data.labels = data.labels || [];
                graficoVentas.data.datasets[0].data = data.valores || [];
                graficoVentas.update();
            }
            
            Swal.close();
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'No se pudieron cargar los datos: ' + error.message, 'error');
        });
}

// ===================================================
// GRÁFICO 2: VENTAS POR TIPO
// ===================================================
function inicializarGraficoVentasTipo() {
    const ctx = document.getElementById('graficoVentasTipo').getContext('2d');
    
    graficoVentasTipo = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Contado', 'Crédito', 'Mixto'],
            datasets: [{
                data: [
                    datosDashboard.ventas_contado_hoy || 0,
                    datosDashboard.ventas_credito_hoy || 0,
                    datosDashboard.ventas_mixto_hoy || 0
                ],
                backgroundColor: ['#28a745', '#ffc107', '#17a2b8'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: Bs ${value.toLocaleString()} (${percentage}%)`;
                        }
                    }
                },
                datalabels: {
                    display: true,
                    color: 'white',
                    formatter: (value, context) => {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return percentage > 5 ? percentage + '%' : '';
                    },
                    font: { weight: 'bold', size: 12 }
                }
            },
            cutout: '70%'
        }
    });
}

// ===================================================
// GRÁFICO 3: TOP 5 PRODUCTOS
// ===================================================
function inicializarGraficoProductosTop() {
    const ctx = document.getElementById('graficoProductosTop').getContext('2d');
    
    const productos = datosDashboard.top_productos || [];
    const labels = productos.map(p => p.codigo || p.nombre_color);
    const valores = productos.map(p => p.total_vendido || 0);
    
    graficoProductosTop = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Cantidad Vendida',
                data: valores,
                backgroundColor: [
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(153, 102, 255, 0.8)'
                ],
                borderColor: [
                    '#28a745', '#36a2eb', '#ffce56', '#ff6384', '#9966ff'
                ],
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                datalabels: {
                    display: true,
                    anchor: 'end',
                    align: 'top',
                    formatter: (value) => value + ' und',
                    font: { weight: 'bold' }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                }
            }
        }
    });
}

// ===================================================
// GRÁFICO 4: ESTADO DEL INVENTARIO
// ===================================================
function inicializarGraficoInventario() {
    const ctx = document.getElementById('graficoInventario').getContext('2d');
    
    const stockNormal = datosDashboard.productos_normales || 0;
    const stockBajo = datosDashboard.total_productos_bajos || 0;
    const stockCritico = datosDashboard.total_productos_criticos || 0;
    
    graficoInventario = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Normal', 'Bajo', 'Crítico'],
            datasets: [{
                data: [stockNormal, stockBajo, stockCritico],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '80%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: ${value} productos (${percentage}%)`;
                        }
                    }
                },
                datalabels: { display: false }
            }
        }
    });
}

// ===================================================
// GRÁFICO 5: GASTOS POR CATEGORÍA
// ===================================================
function inicializarGraficoGastos() {
    const ctx = document.getElementById('graficoGastos').getContext('2d');
    
    const gastos = datosDashboard.gastos_mes || {};
    const categorias = {
        'gasto_almuerzo': 'Almuerzos',
        'gasto_varios': 'Varios',
        'pago_proveedor': 'Proveedores',
        'otros': 'Otros'
    };
    
    const labels = [];
    const valores = [];
    const colores = ['#ffc107', '#17a2b8', '#6f42c1', '#6c757d'];
    
    Object.keys(categorias).forEach((key, index) => {
        if (gastos[key] > 0) {
            labels.push(categorias[key]);
            valores.push(gastos[key]);
        }
    });
    
    graficoGastos = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: valores,
                backgroundColor: colores.slice(0, valores.length),
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: Bs ${value.toLocaleString()} (${percentage}%)`;
                        }
                    }
                },
                datalabels: {
                    display: true,
                    color: 'white',
                    formatter: (value, context) => {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return percentage > 5 ? percentage + '%' : '';
                    },
                    font: { weight: 'bold', size: 11 }
                }
            }
        }
    });
}

// ===================================================
// GRÁFICO 6: EVOLUCIÓN DE COBROS
// ===================================================
function inicializarGraficoCobros() {
    const ctx = document.getElementById('graficoCobros').getContext('2d');
    
    const cobros = datosDashboard.cobros_semanales || {
        labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
        valores: [0, 0, 0, 0, 0, 0, 0]
    };
    
    graficoCobros = new Chart(ctx, {
        type: 'line',
        data: {
            labels: cobros.labels,
            datasets: [{
                label: 'Cobros Realizados',
                data: cobros.valores,
                borderColor: '#fd7e14',
                backgroundColor: 'rgba(253, 126, 20, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                datalabels: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0, 0, 0, 0.05)' },
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

// Función para cambiar período de cobros - CORREGIDA
function cambiarPeriodoCobros(periodo, btn) {
    // Actualizar botones
    const buttons = document.querySelectorAll('#periodoCobrosButtons .report-btn');
    buttons.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    // Mostrar loading
    Swal.fire({
        title: 'Cargando datos...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    // Petición AJAX
    fetch(`api/cobros_data.php?periodo=${periodo}`)
        .then(response => {
            if (!response.ok) throw new Error('Error en la respuesta del servidor');
            return response.json();
        })
        .then(data => {
            if (data.error) throw new Error(data.error);
            
            // Actualizar gráfico
            if (graficoCobros) {
                graficoCobros.data.labels = data.labels || [];
                graficoCobros.data.datasets[0].data = data.valores || [];
                graficoCobros.update();
            }
            
            Swal.close();
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'No se pudieron cargar los datos', 'error');
        });
}

// ===================================================
// GRÁFICO 7: RENTABILIDAD
// ===================================================
function inicializarGraficoRentabilidad() {
    const ctx = document.getElementById('graficoRentabilidad').getContext('2d');
    
    const productos = datosDashboard.top_productos || [];
    const labels = productos.map(p => p.codigo || p.nombre_color).slice(0, 5);
    const ventas = productos.map(p => parseFloat(p.total_ingresos || 0)).slice(0, 5);
    const costos = ventas.map(v => v * 0.6);
    
    graficoRentabilidad = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Costo',
                    data: costos,
                    backgroundColor: 'rgba(108, 117, 125, 0.8)',
                    borderColor: '#6c757d',
                    borderWidth: 1
                },
                {
                    label: 'Ganancia',
                    data: ventas.map((v, i) => v - costos[i]),
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                    borderColor: '#28a745',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.raw || 0;
                            return `${label}: Bs ${value.toLocaleString()}`;
                        }
                    }
                },
                datalabels: { display: false }
            },
            scales: {
                x: {
                    stacked: true,
                    grid: { display: false }
                },
                y: {
                    stacked: true,
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

// ===================================================
// FUNCIONES PARA REPORTES
// ===================================================

/**
 * Generar reporte rápido
 */
function generarReporte(tipo, formato) {
    Swal.fire({
        title: 'Generando reporte...',
        text: 'Por favor espere',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'exportar_reporte.php';
    form.target = '_blank';
    
    const fechaInicio = document.getElementById('fecha_inicio')?.value || '<?php echo date('Y-m-01'); ?>';
    const fechaFin = document.getElementById('fecha_fin')?.value || '<?php echo date('Y-m-d'); ?>';
    
    const inputs = {
        tipo_reporte: tipo,
        formato: formato,
        fecha_inicio: fechaInicio,
        fecha_fin: fechaFin,
        incluir_graficos: '1',
        incluir_tablas: '1',
        incluir_resumen: '1',
        orientacion: 'horizontal'
    };
    
    for (const [key, value] of Object.entries(inputs)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    Swal.close();
}

/**
 * Generar reporte personalizado
 */
function generarReportePersonalizado() {
    const form = document.getElementById('reporteForm');
    
    const fechaInicio = new Date(document.getElementById('fecha_inicio').value);
    const fechaFin = new Date(document.getElementById('fecha_fin').value);
    
    if (fechaInicio > fechaFin) {
        Swal.fire('Error', 'La fecha de inicio no puede ser mayor a la fecha fin', 'error');
        return;
    }
    
    if (!document.getElementById('tipo_reporte').value) {
        Swal.fire('Error', 'Seleccione un tipo de reporte', 'error');
        return;
    }
    
    if (!document.getElementById('formato').value) {
        Swal.fire('Error', 'Seleccione un formato de exportación', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Generando reporte...',
        text: 'Esto puede tomar unos segundos',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    form.submit();
    
    setTimeout(() => {
        Swal.close();
        if (typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getInstance(document.getElementById('reportePersonalizadoModal'));
            if (modal) modal.hide();
        }
    }, 2000);
}

/**
 * Exportar gráfico
 */
function exportarGrafico(graficoId, formato) {
    const canvas = document.getElementById(`grafico${graficoId}`);
    
    if (!canvas) {
        Swal.fire('Error', 'No se encontró el gráfico', 'error');
        return;
    }
    
    if (formato === 'png') {
        const link = document.createElement('a');
        link.download = `grafico_${graficoId}_${Date.now()}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
        Swal.fire('Éxito', 'Gráfico exportado como PNG', 'success');
    } else if (formato === 'pdf') {
        try {
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF('landscape');
            const imgData = canvas.toDataURL('image/png');
            pdf.addImage(imgData, 'PNG', 15, 15, 180, 100);
            pdf.save(`grafico_${graficoId}_${Date.now()}.pdf`);
            Swal.fire('Éxito', 'Gráfico exportado como PDF', 'success');
        } catch (error) {
            console.error('Error al exportar PDF:', error);
            Swal.fire('Error', 'No se pudo exportar el gráfico como PDF', 'error');
        }
    }
}

// ===================================================
// FUNCIONES PARA ACTUALIZAR TABLAS
// ===================================================
function actualizarTablaVentas(ventas) {
    const tbody = document.querySelector('.table-dashboard tbody');
    if (!tbody) return;
    
    let html = '';
    ventas.slice(0, 8).forEach(venta => {
        let estadoClass = 'alert-success';
        let estadoText = 'Pagada';
        
        if (venta.debe > 0) {
            estadoClass = 'alert-warning';
            estadoText = 'Pendiente';
        }
        
        html += `<tr>
            <td><code>${venta.codigo_venta || ''}</code></td>
            <td>${venta.cliente || 'Consumidor Final'}</td>
            <td class="fw-bold">Bs ${parseFloat(venta.total || 0).toFixed(2)}</td>
            <td><span class="alert-badge ${estadoClass}">${estadoText}</span></td>
            <td><small>${venta.hora_inicio || ''}</small></td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function actualizarTimelineMovimientos(movimientos) {
    const timeline = document.querySelector('.timeline-dashboard');
    if (!timeline) return;
    
    let html = '';
    movimientos.slice(0, 8).forEach(mov => {
        const claseColor = mov.tipo == 'ingreso' ? 'text-success' : 'text-danger';
        const signo = mov.tipo == 'ingreso' ? '+' : '-';
        
        html += `<div class="timeline-item">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <small class="text-muted">${mov.hora || ''}</small>
                    <h6 style="margin: 5px 0; font-size: 0.95rem;">${mov.descripcion || ''}</h6>
                    <small class="text-muted">${(mov.categoria || '').replace(/_/g, ' ')}</small>
                </div>
                <div class="${claseColor} fw-bold">
                    ${signo} Bs ${parseFloat(mov.monto || 0).toFixed(2)}
                </div>
            </div>
        </div>`;
    });
    timeline.innerHTML = html;
}

// ===================================================
// INICIALIZAR TODOS LOS GRÁFICOS
// ===================================================
document.addEventListener('DOMContentLoaded', function() {
    try {
        inicializarGraficoVentas();
        inicializarGraficoVentasTipo();
        inicializarGraficoProductosTop();
        inicializarGraficoInventario();
        inicializarGraficoGastos();
        inicializarGraficoCobros();
        inicializarGraficoRentabilidad();
        
        // Configurar fechas por defecto en modal
        const hoy = new Date();
        const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        
        const fechaInicioInput = document.getElementById('fecha_inicio');
        const fechaFinInput = document.getElementById('fecha_fin');
        
        if (fechaInicioInput) {
            fechaInicioInput.value = primerDia.toISOString().split('T')[0];
        }
        
        if (fechaFinInput) {
            fechaFinInput.value = hoy.toISOString().split('T')[0];
        }
        
        console.log('Dashboard inicializado correctamente');
    } catch (error) {
        console.error('Error al inicializar gráficos:', error);
    }
});

// ===================================================
// ACTUALIZACIÓN EN TIEMPO REAL (CADA 60 SEGUNDOS)
// ===================================================
setInterval(function() {
    fetch('api/dashboard_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Error:', data.error);
                return;
            }
            
            // Actualizar Ventas Hoy
            if (data.ventas_hoy && document.getElementById('ventasHoyValue')) {
                document.getElementById('ventasHoyValue').textContent = data.ventas_hoy.total || 0;
            }
            
            // Actualizar tabla de últimas ventas
            if (data.ultimas_ventas) {
                actualizarTablaVentas(data.ultimas_ventas);
            }
            
            // Actualizar timeline de movimientos
            if (data.movimientos) {
                actualizarTimelineMovimientos(data.movimientos);
            }
            
            console.log('Dashboard actualizado:', new Date().toLocaleTimeString());
        })
        .catch(error => console.error('Error en actualización:', error));
}, 60000);
</script>

<?php
// ===================================================
// FUNCIÓN PRINCIPAL - DASHBOARD PROFESIONAL
// ===================================================
function obtenerDashboardDataProfesional($usuario_id, $rol) {
    global $conn;
    $data = [];
    
    try {
        // ========================================
        // 1. VENTAS DEL DÍA
        // ========================================
        $query_ventas_hoy = "SELECT 
                                COUNT(*) as total, 
                                COALESCE(SUM(total), 0) as monto,
                                COALESCE(SUM(CASE WHEN tipo_pago = 'contado' THEN total ELSE 0 END), 0) as ventas_contado,
                                COALESCE(SUM(CASE WHEN tipo_pago = 'credito' THEN total ELSE 0 END), 0) as ventas_credito,
                                COALESCE(SUM(CASE WHEN tipo_pago = 'mixto' THEN total ELSE 0 END), 0) as ventas_mixto
                            FROM ventas 
                            WHERE fecha = CURDATE() AND anulado = 0";
        
        if ($rol != 'administrador') {
            $query_ventas_hoy .= " AND vendedor_id = $usuario_id";
        }
        
        $result = $conn->query($query_ventas_hoy);
        $data['ventas_hoy'] = $result->fetch_assoc();
        $data['ventas_contado_hoy'] = $data['ventas_hoy']['ventas_contado'] ?? 0;
        $data['ventas_credito_hoy'] = $data['ventas_hoy']['ventas_credito'] ?? 0;
        $data['ventas_mixto_hoy'] = $data['ventas_hoy']['ventas_mixto'] ?? 0;
        
        // ========================================
        // 2. VENTAS DEL MES
        // ========================================
        $query_ventas_mes = "SELECT 
                                COUNT(*) as total, 
                                COALESCE(SUM(total), 0) as monto
                            FROM ventas 
                            WHERE MONTH(fecha) = MONTH(CURDATE()) 
                            AND YEAR(fecha) = YEAR(CURDATE())
                            AND anulado = 0";
        
        if ($rol != 'administrador') {
            $query_ventas_mes .= " AND vendedor_id = $usuario_id";
        }
        
        $result = $conn->query($query_ventas_mes);
        $data['ventas_mes'] = $result->fetch_assoc();
        
        // ========================================
        // 3. COMPARACIÓN CON AYER
        // ========================================
        $query_comparacion = "SELECT 
                                COALESCE(SUM(CASE WHEN fecha = CURDATE() THEN total ELSE 0 END), 0) as hoy,
                                COALESCE(SUM(CASE WHEN fecha = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN total ELSE 0 END), 0) as ayer
                            FROM ventas 
                            WHERE fecha IN (CURDATE(), DATE_SUB(CURDATE(), INTERVAL 1 DAY)) 
                            AND anulado = 0";
        $result = $conn->query($query_comparacion);
        $comparacion = $result->fetch_assoc();
        
        if ($comparacion['ayer'] > 0) {
            $porcentaje = (($comparacion['hoy'] - $comparacion['ayer']) / $comparacion['ayer']) * 100;
            $data['comparacion_ayer'] = ($porcentaje > 0 ? '+' : '') . number_format($porcentaje, 1) . '%';
        } else {
            $data['comparacion_ayer'] = 'Nuevo';
        }
        
        // ========================================
        // 4. VENTAS SEMANALES PARA GRÁFICO
        // ========================================
        $query_ventas_semanales = "SELECT 
                                    DATE_FORMAT(fecha, '%a') as dia,
                                    COALESCE(SUM(total), 0) as total
                                FROM ventas 
                                WHERE fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
                                AND anulado = 0
                                GROUP BY fecha
                                ORDER BY fecha";
        $result = $conn->query($query_ventas_semanales);
        
        $data['ventas_semanales'] = [
            'labels' => [],
            'valores' => []
        ];
        
        while ($row = $result->fetch_assoc()) {
            $data['ventas_semanales']['labels'][] = $row['dia'];
            $data['ventas_semanales']['valores'][] = (float)$row['total'];
        }
        
        // ========================================
        // 5. COBROS SEMANALES PARA GRÁFICO
        // ========================================
        $query_cobros_semanales = "SELECT 
                                    DATE_FORMAT(fecha, '%a') as dia,
                                    COALESCE(SUM(monto), 0) as total
                                FROM pagos_clientes 
                                WHERE fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
                                GROUP BY fecha
                                ORDER BY fecha";
        $result = $conn->query($query_cobros_semanales);
        
        $data['cobros_semanales'] = [
            'labels' => [],
            'valores' => []
        ];
        
        while ($row = $result->fetch_assoc()) {
            $data['cobros_semanales']['labels'][] = $row['dia'];
            $data['cobros_semanales']['valores'][] = (float)$row['total'];
        }
        
        // ========================================
        // 6. TOP PRODUCTOS MÁS VENDIDOS
        // ========================================
        $query_top_productos = "SELECT 
                                    p.codigo, 
                                    p.nombre_color, 
                                    COALESCE(SUM(vd.cantidad_subpaquetes), 0) as total_vendido,
                                    COALESCE(SUM(vd.subtotal), 0) as total_ingresos
                                FROM venta_detalles vd 
                                JOIN productos p ON vd.producto_id = p.id 
                                JOIN ventas v ON vd.venta_id = v.id 
                                WHERE v.fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
                                AND v.anulado = 0
                                GROUP BY p.id 
                                ORDER BY total_vendido DESC 
                                LIMIT 5";
        $result = $conn->query($query_top_productos);
        $data['top_productos'] = [];
        while ($row = $result->fetch_assoc()) {
            $data['top_productos'][] = $row;
        }
        
        // ========================================
        // 7. CLIENTES CON DEUDA
        // ========================================
        $query_clientes_deuda = "SELECT 
                                    nombre, 
                                    saldo_actual,
                                    telefono,
                                    ciudad
                                FROM clientes 
                                WHERE saldo_actual > 0 
                                ORDER BY saldo_actual DESC 
                                LIMIT 10";
        $result = $conn->query($query_clientes_deuda);
        $data['clientes_deuda'] = [];
        $data['total_deuda'] = 0;
        $data['total_clientes_deuda'] = 0;
        
        while ($row = $result->fetch_assoc()) {
            $data['clientes_deuda'][] = $row;
            $data['total_deuda'] += $row['saldo_actual'];
            $data['total_clientes_deuda']++;
        }
        
        // ========================================
        // 8. CLIENTES VENCIDOS
        // ========================================
        $query_vencidos = "SELECT COUNT(DISTINCT cliente_id) as total
                          FROM clientes_cuentas_cobrar 
                          WHERE estado = 'vencida' OR fecha_vencimiento < CURDATE()";
        $result = $conn->query($query_vencidos);
        $vencidos = $result->fetch_assoc();
        $data['clientes_vencidos'] = $vencidos['total'] ?? 0;
        
        // ========================================
        // 9. INVENTARIO - ESTADÍSTICAS
        // ========================================
        $query_inventario = "SELECT 
                                COUNT(*) as total_productos,
                                SUM(CASE WHEN i.total_subpaquetes >= 50 THEN 1 ELSE 0 END) as normales,
                                SUM(CASE WHEN i.total_subpaquetes >= 20 AND i.total_subpaquetes < 50 THEN 1 ELSE 0 END) as bajos,
                                SUM(CASE WHEN i.total_subpaquetes < 20 THEN 1 ELSE 0 END) as criticos,
                                COALESCE(SUM(i.total_subpaquetes), 0) as stock_total
                            FROM productos p 
                            JOIN inventario i ON p.id = i.producto_id 
                            WHERE p.activo = 1";
        $result = $conn->query($query_inventario);
        $inventario_stats = $result->fetch_assoc();
        
        $data['total_productos'] = $inventario_stats['total_productos'] ?? 0;
        $data['productos_normales'] = $inventario_stats['normales'] ?? 0;
        $data['total_productos_bajos'] = $inventario_stats['bajos'] ?? 0;
        $data['total_productos_criticos'] = $inventario_stats['criticos'] ?? 0;
        $data['stock_total'] = $inventario_stats['stock_total'] ?? 0;
        
        // ========================================
        // 10. INVENTARIO BAJO - LISTADO
        // ========================================
        $query_inventario_bajo = "SELECT 
                                    p.codigo, 
                                    p.nombre_color, 
                                    i.total_subpaquetes,
                                    pr.nombre as proveedor,
                                    i.ubicacion
                                FROM productos p 
                                JOIN inventario i ON p.id = i.producto_id 
                                JOIN proveedores pr ON p.proveedor_id = pr.id
                                WHERE i.total_subpaquetes < 50 
                                AND p.activo = 1 
                                ORDER BY i.total_subpaquetes ASC 
                                LIMIT 20";
        $result = $conn->query($query_inventario_bajo);
        $data['inventario_bajo'] = [];
        while ($row = $result->fetch_assoc()) {
            $data['inventario_bajo'][] = $row;
        }
        
        // ========================================
        // 11. MOVIMIENTOS DE CAJA
        // ========================================
        $query_movimientos = "SELECT 
                                tipo, 
                                categoria, 
                                descripcion, 
                                monto, 
                                hora 
                            FROM movimientos_caja 
                            WHERE fecha = CURDATE() 
                            ORDER BY hora DESC 
                            LIMIT 15";
        $result = $conn->query($query_movimientos);
        $data['movimientos'] = [];
        while ($row = $result->fetch_assoc()) {
            $data['movimientos'][] = $row;
        }
        
        // ========================================
        // 12. CAJA - INGRESOS Y GASTOS
        // ========================================
        $query_caja = "SELECT 
                        COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as ingresos,
                        COALESCE(SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END), 0) as gastos
                    FROM movimientos_caja 
                    WHERE fecha = CURDATE()";
        $result = $conn->query($query_caja);
        $caja = $result->fetch_assoc();
        $data['ingresos_hoy'] = $caja['ingresos'] ?? 0;
        $data['gastos_hoy'] = $caja['gastos'] ?? 0;
        $data['balance_caja_hoy'] = $data['ingresos_hoy'] - $data['gastos_hoy'];
        
        // ========================================
        // 13. GASTOS DEL MES
        // ========================================
        $query_gastos_mes = "SELECT 
                                categoria,
                                COALESCE(SUM(monto), 0) as total
                            FROM movimientos_caja 
                            WHERE tipo = 'gasto' 
                            AND MONTH(fecha) = MONTH(CURDATE())
                            AND YEAR(fecha) = YEAR(CURDATE())
                            GROUP BY categoria";
        $result = $conn->query($query_gastos_mes);
        $data['gastos_mes'] = [];
        $data['total_gastos_mes'] = 0;
        
        while ($row = $result->fetch_assoc()) {
            $data['gastos_mes'][$row['categoria']] = $row['total'];
            $data['total_gastos_mes'] += $row['total'];
        }
        
        // ========================================
        // 14. ÚLTIMAS VENTAS
        // ========================================
        $query_ultimas_ventas = "SELECT 
                                    v.codigo_venta,
                                    v.total,
                                    v.debe,
                                    v.estado,
                                    v.hora_inicio,
                                    COALESCE(c.nombre, v.cliente_contado, 'Consumidor Final') as cliente
                                FROM ventas v
                                LEFT JOIN clientes c ON v.cliente_id = c.id
                                WHERE v.anulado = 0
                                ORDER BY v.fecha DESC, v.hora_inicio DESC
                                LIMIT 15";
        $result = $conn->query($query_ultimas_ventas);
        $data['ultimas_ventas'] = [];
        while ($row = $result->fetch_assoc()) {
            $data['ultimas_ventas'][] = $row;
        }
        
        // ========================================
        // 15. PROVEEDORES - ESTADÍSTICAS
        // ========================================
        $query_proveedores = "SELECT 
                                COALESCE(SUM(saldo_actual), 0) as total_deuda,
                                COUNT(CASE WHEN saldo_actual > 0 THEN 1 END) as con_deuda,
                                COUNT(*) as total
                            FROM proveedores 
                            WHERE activo = 1";
        $result = $conn->query($query_proveedores);
        $proveedores_stats = $result->fetch_assoc();
        $data['total_deuda_proveedores'] = $proveedores_stats['total_deuda'] ?? 0;
        $data['proveedores_con_deuda'] = $proveedores_stats['con_deuda'] ?? 0;
        
        // ========================================
        // 16. CLIENTES - ESTADÍSTICAS
        // ========================================
        $query_clientes = "SELECT 
                            COUNT(*) as activos,
                            COUNT(CASE WHEN MONTH(fecha_registro) = MONTH(CURDATE()) 
                                      AND YEAR(fecha_registro) = YEAR(CURDATE()) THEN 1 END) as nuevos_mes
                          FROM clientes 
                          WHERE activo = 1";
        $result = $conn->query($query_clientes);
        $clientes_stats = $result->fetch_assoc();
        $data['total_clientes_activos'] = $clientes_stats['activos'] ?? 0;
        $data['clientes_nuevos_mes'] = $clientes_stats['nuevos_mes'] ?? 0;
        
        // ========================================
        // 17. MARGEN PROMEDIO (RENTABILIDAD)
        // ========================================
        $query_margen = "SELECT 
                            AVG((vd.subtotal - (vd.cantidad_subpaquetes * p.precio_menor * 0.6)) / vd.subtotal * 100) as margen
                        FROM venta_detalles vd
                        JOIN productos p ON vd.producto_id = p.id
                        JOIN ventas v ON vd.venta_id = v.id
                        WHERE v.fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()";
        $result = $conn->query($query_margen);
        $margen = $result->fetch_assoc();
        $data['margen_promedio'] = number_format($margen['margen'] ?? 0, 1) . '%';
        
    } catch (Exception $e) {
        error_log("Error en dashboard: " . $e->getMessage());
    }
    
    return $data;
}

require_once 'footer.php';
?>