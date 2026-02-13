<?php
session_start();

$titulo_pagina = "Gestión de Clientes";
$icono_titulo = "fas fa-user-friends";
$breadcrumb = [
    ['text' => 'Clientes', 'link' => '#', 'active' => true]
];

require_once 'header.php';
require_once 'funciones.php';

// Verificar sesión (acceso para admin y vendedor)
verificarSesion();

$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'crear':
            $codigo = limpiar($_POST['codigo']);
            $nombre = limpiar($_POST['nombre']);
            $ciudad = limpiar($_POST['ciudad']);
            $telefono = limpiar($_POST['telefono']);
            $tipo_documento = limpiar($_POST['tipo_documento']);
            $numero_documento = limpiar($_POST['numero_documento']);
            $limite_credito = floatval($_POST['limite_credito']);
            $observaciones = limpiar($_POST['observaciones']);
            
            // Validar datos
            if (empty($codigo) || empty($nombre)) {
                $mensaje = "El código y nombre son obligatorios";
                $tipo_mensaje = "danger";
            } else {
                // Verificar si el código ya existe
                $query_check = "SELECT id FROM clientes WHERE codigo = ?";
                $stmt_check = $conn->prepare($query_check);
                $stmt_check->bind_param("s", $codigo);
                $stmt_check->execute();
                
                if ($stmt_check->get_result()->num_rows > 0) {
                    $mensaje = " El código de cliente ya existe";
                    $tipo_mensaje = "danger";
                } else {
                    $query = "INSERT INTO clientes 
                             (codigo, nombre, ciudad, telefono, tipo_documento, 
                              numero_documento, limite_credito, observaciones) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssssssds", $codigo, $nombre, $ciudad, $telefono, 
                                     $tipo_documento, $numero_documento, $limite_credito, $observaciones);
                    
                    if ($stmt->execute()) {
                        $mensaje = " Cliente creado exitosamente";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = " Error al crear cliente: " . $stmt->error;
                        $tipo_mensaje = "danger";
                    }
                }
            }
            break;
            
        case 'editar':
            $id = intval($_POST['id']);
            $codigo = limpiar($_POST['codigo']);
            $nombre = limpiar($_POST['nombre']);
            $ciudad = limpiar($_POST['ciudad']);
            $telefono = limpiar($_POST['telefono']);
            $tipo_documento = limpiar($_POST['tipo_documento']);
            $numero_documento = limpiar($_POST['numero_documento']);
            $limite_credito = floatval($_POST['limite_credito']);
            $activo = isset($_POST['activo']) ? 1 : 0;
            $observaciones = limpiar($_POST['observaciones']);
            
            if (empty($codigo) || empty($nombre)) {
                $mensaje = "El código y nombre son obligatorios";
                $tipo_mensaje = "danger";
            } else {
                // Verificar si el código ya existe para otro cliente
                $query_check = "SELECT id FROM clientes WHERE codigo = ? AND id != ?";
                $stmt_check = $conn->prepare($query_check);
                $stmt_check->bind_param("si", $codigo, $id);
                $stmt_check->execute();
                
                if ($stmt_check->get_result()->num_rows > 0) {
                    $mensaje = " El código de cliente ya está en uso por otro cliente";
                    $tipo_mensaje = "danger";
                } else {
                    $query = "UPDATE clientes SET 
                             codigo = ?, nombre = ?, ciudad = ?, telefono = ?, 
                             tipo_documento = ?, numero_documento = ?, 
                             limite_credito = ?, activo = ?, observaciones = ?
                             WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssssssdisi", $codigo, $nombre, $ciudad, $telefono, 
                                     $tipo_documento, $numero_documento, $limite_credito, 
                                     $activo, $observaciones, $id);
                    
                    if ($stmt->execute()) {
                        $mensaje = " Cliente actualizado exitosamente";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = " Error al actualizar cliente: " . $stmt->error;
                        $tipo_mensaje = "danger";
                    }
                }
            }
            break;
            
        case 'cambiar_estado':
            $id = intval($_POST['id']);
            $activo = intval($_POST['activo']);
            
            $query = "UPDATE clientes SET activo = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $activo, $id);
            
            if ($stmt->execute()) {
                $mensaje = " Estado del cliente actualizado";
                $tipo_mensaje = "success";
            } else {
                $mensaje = " Error al cambiar estado: " . $stmt->error;
                $tipo_mensaje = "danger";
            }
            break;
    }
}

// Obtener lista de clientes
$query_clientes = "SELECT id, codigo, nombre, ciudad, telefono, tipo_documento,
                  numero_documento, limite_credito, saldo_actual, total_comprado,
                  compras_realizadas, activo, observaciones,
                  DATE_FORMAT(fecha_registro, '%d/%m/%Y') as fecha_registro
                  FROM clientes 
                  ORDER BY nombre";
$result_clientes = $conn->query($query_clientes);

// Estadísticas para dashboard
$query_total = "SELECT COUNT(*) as total FROM clientes";
$result_total = $conn->query($query_total);
$total_clientes = $result_total->fetch_assoc()['total'];

$query_activos = "SELECT COUNT(*) as total FROM clientes WHERE activo = 1";
$result_activos = $conn->query($query_activos);
$total_activos = $result_activos->fetch_assoc()['total'];

$query_deuda = "SELECT SUM(saldo_actual) as total_deuda FROM clientes WHERE saldo_actual > 0";
$result_deuda = $conn->query($query_deuda);
$deuda_total = $result_deuda->fetch_assoc()['total_deuda'] ?? 0;

$query_compras = "SELECT SUM(total_comprado) as total_compras FROM clientes";
$result_compras = $conn->query($query_compras);
$compras_total = $result_compras->fetch_assoc()['total_compras'] ?? 0;

$query_con_deuda = "SELECT COUNT(*) as total FROM clientes WHERE saldo_actual > 0";
$result_con_deuda = $conn->query($query_con_deuda);
$clientes_con_deuda = $result_con_deuda->fetch_assoc()['total'];
?>

<?php if ($mensaje): ?>
<div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
    <?php echo $mensaje; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>Lista de Clientes</h5>
                    <small>Total: <?php echo $total_clientes; ?> clientes registrados</small>
                </div>
                <div class="btn-group">
                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalNuevoCliente">
                        <i class="fas fa-plus me-2"></i>Nuevo Cliente
                    </button>
                    <a href="cuentas_cobrar.php" class="btn btn-outline-light ms-2">
                        <i class="fas fa-hand-holding-usd me-2"></i>Cuentas por Cobrar
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Dashboard de estadísticas -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-start border-primary border-4">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">Total Clientes</h6>
                                        <h2 class="mb-0"><?php echo $total_clientes; ?></h2>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-primary"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <span class="text-success"><?php echo $total_activos; ?> activos</span> | 
                                        <span class="text-danger"><?php echo $total_clientes - $total_activos; ?> inactivos</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-start border-success border-4">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">Clientes Activos</h6>
                                        <h2 class="mb-0"><?php echo $total_activos; ?></h2>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-check fa-2x text-success"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <?php echo number_format(($total_activos / max($total_clientes, 1)) * 100, 1); ?>% del total
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-start border-warning border-4">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">Deuda Total</h6>
                                        <h2 class="mb-0"><?php echo formatearMoneda($deuda_total); ?></h2>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-money-bill-wave fa-2x text-warning"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <span class="text-warning"><?php echo $clientes_con_deuda; ?> clientes con deuda</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-start border-info border-4">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">Total Comprado</h6>
                                        <h2 class="mb-0"><?php echo formatearMoneda($compras_total); ?></h2>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x text-info"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <?php echo number_format($compras_total > 0 ? ($deuda_total / $compras_total) * 100 : 0, 1); ?>% en deuda
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros y búsqueda mejorados -->
                <div class="card border-light mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="buscarCliente" 
                                           placeholder="Buscar por código, nombre, teléfono, ciudad...">
                                    <button class="btn btn-outline-secondary" type="button" onclick="limpiarBusqueda()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <select class="form-select" id="filtroEstado">
                                    <option value="">Todos los estados</option>
                                    <option value="activo">Clientes Activos</option>
                                    <option value="inactivo">Clientes Inactivos</option>
                                    <option value="con_deuda">Con Deuda</option>
                                    <option value="sin_deuda">Sin Deuda</option>
                                    <option value="limite_excedido">Límite Excedido</option>
                                </select>
                            </div>
                            <div class="col-lg-2">
                                <select class="form-select" id="filtroOrden">
                                    <option value="nombre">Orden: Nombre A-Z</option>
                                    <option value="nombre_desc">Orden: Nombre Z-A</option>
                                    <option value="codigo">Orden: Código</option>
                                    <option value="deuda_desc">Orden: Deuda (Mayor)</option>
                                    <option value="deuda_asc">Orden: Deuda (Menor)</option>
                                    <option value="compras_desc">Orden: Compras (Mayor)</option>
                                </select>
                            </div>
                            <div class="col-lg-2">
                                <button class="btn btn-outline-primary w-100" onclick="filtrarClientes()">
                                    <i class="fas fa-filter me-2"></i>Filtrar
                                </button>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <div class="form-text">
                                    <span id="contadorResultados"><?php echo $total_clientes; ?></span> clientes encontrados
                                    <span id="contadorFiltrados" class="text-primary" style="display: none;"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de clientes mejorada -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="tablaClientes">
                        <thead class="table-primary">
                            <tr>
                                <th width="80">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="checkAll">
                                    </div>
                                </th>
                                <th width="120">Código</th>
                                <th>Cliente</th>
                                <th width="150">Contacto</th>
                                <th width="120" class="text-end">Límite Crédito</th>
                                <th width="140" class="text-end">Saldo Actual</th>
                                <th width="120" class="text-end">Total Comprado</th>
                                <th width="100">Estado</th>
                                <th width="100" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $contador = 0;
                            if ($result_clientes->num_rows > 0):
                                while ($cliente = $result_clientes->fetch_assoc()): 
                                    $contador++;
                                    $deuda_porcentaje = $cliente['limite_credito'] > 0 ? 
                                        ($cliente['saldo_actual'] / $cliente['limite_credito']) * 100 : 0;
                                    $limite_excedido = $deuda_porcentaje > 100;
                            ?>
                            <tr data-id="<?php echo $cliente['id']; ?>"
                                data-codigo="<?php echo htmlspecialchars($cliente['codigo']); ?>"
                                data-nombre="<?php echo htmlspecialchars(strtolower($cliente['nombre'])); ?>"
                                data-ciudad="<?php echo htmlspecialchars(strtolower($cliente['ciudad'])); ?>"
                                data-telefono="<?php echo htmlspecialchars($cliente['telefono']); ?>"
                                data-deuda="<?php echo $cliente['saldo_actual']; ?>"
                                data-activo="<?php echo $cliente['activo']; ?>"
                                data-limite-excedido="<?php echo $limite_excedido ? 'true' : 'false'; ?>">
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input checkCliente" type="checkbox" 
                                               value="<?php echo $cliente['id']; ?>">
                                    </div>
                                </td>
                                <td>
                                    <strong class="text-primary"><?php echo $cliente['codigo']; ?></strong>
                                    <div class="small text-muted">
                                        <?php echo $cliente['tipo_documento']; ?>: 
                                        <?php echo $cliente['numero_documento']; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo $cliente['nombre']; ?></div>
                                    <?php if ($cliente['ciudad']): ?>
                                    <div class="small text-muted">
                                        <i class="fas fa-map-marker-alt me-1"></i><?php echo $cliente['ciudad']; ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($cliente['observaciones']): ?>
                                    <div class="small text-truncate" style="max-width: 200px;" 
                                         title="<?php echo htmlspecialchars($cliente['observaciones']); ?>">
                                        <i class="fas fa-comment me-1"></i>
                                        <?php echo substr($cliente['observaciones'], 0, 50); ?>
                                        <?php if (strlen($cliente['observaciones']) > 50): ?>...<?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($cliente['telefono']): ?>
                                    <div>
                                        <i class="fas fa-phone me-1 text-success"></i>
                                        <?php echo $cliente['telefono']; ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="small text-muted mt-1">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo $cliente['fecha_registro']; ?>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div><?php echo formatearMoneda($cliente['limite_credito']); ?></div>
                                    <?php if ($cliente['limite_credito'] > 0): ?>
                                    <div class="small text-muted">
                                        <?php echo $cliente['compras_realizadas']; ?> compras
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($cliente['saldo_actual'] > 0): ?>
                                        <div class="fw-bold text-danger">
                                            <?php echo formatearMoneda($cliente['saldo_actual']); ?>
                                        </div>
                                        <?php if ($cliente['limite_credito'] > 0): ?>
                                        <div class="progress mt-1" style="height: 6px;">
                                            <div class="progress-bar <?php echo $limite_excedido ? 'bg-danger' : 'bg-warning'; ?>" 
                                                 style="width: <?php echo min($deuda_porcentaje, 100); ?>%"
                                                 title="<?php echo number_format($deuda_porcentaje, 1); ?>% del límite">
                                            </div>
                                        </div>
                                        <div class="small <?php echo $limite_excedido ? 'text-danger fw-bold' : 'text-warning'; ?>">
                                            <?php echo number_format($deuda_porcentaje, 1); ?>% del límite
                                        </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="fw-bold text-success">
                                            <?php echo formatearMoneda($cliente['saldo_actual']); ?>
                                        </div>
                                        <div class="small text-success">
                                            <i class="fas fa-check-circle me-1"></i>Al día
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div><?php echo formatearMoneda($cliente['total_comprado']); ?></div>
                                    <div class="small text-muted">
                                        Promedio: <?php echo $cliente['compras_realizadas'] > 0 ? 
                                            formatearMoneda($cliente['total_comprado'] / $cliente['compras_realizadas']) : 
                                            'Bs 0,00'; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($cliente['activo'] == 1): ?>
                                        <span class="badge bg-success rounded-pill px-3 py-1">
                                            <i class="fas fa-check-circle me-1"></i>Activo
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger rounded-pill px-3 py-1">
                                            <i class="fas fa-ban me-1"></i>Inactivo
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($cliente['saldo_actual'] > 0): ?>
                                        <span class="badge bg-warning rounded-pill px-2 py-1 mt-1 d-block">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Con Deuda
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($limite_excedido): ?>
                                        <span class="badge bg-danger rounded-pill px-2 py-1 mt-1 d-block">
                                            <i class="fas fa-exclamation-circle me-1"></i>Límite Excedido
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditarCliente"
                                                onclick="cargarDatosCliente(<?php echo $cliente['id']; ?>)"
                                                data-bs-toggle="tooltip" title="Editar cliente">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="historial_cliente.php?id=<?php echo $cliente['id']; ?>" 
                                           class="btn btn-sm btn-outline-info"
                                           data-bs-toggle="tooltip" title="Ver historial">
                                            <i class="fas fa-history"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-<?php echo $cliente['activo'] ? 'danger' : 'success'; ?>"
                                                onclick="cambiarEstadoCliente(<?php echo $cliente['id']; ?>, <?php echo $cliente['activo']; ?>, '<?php echo addslashes($cliente['nombre']); ?>')"
                                                data-bs-toggle="tooltip" title="<?php echo $cliente['activo'] ? 'Desactivar' : 'Activar'; ?> cliente">
                                            <i class="fas fa-<?php echo $cliente['activo'] ? 'ban' : 'check'; ?>"></i>
                                        </button>
                                        <?php if ($cliente['saldo_actual'] > 0): ?>
                                        <a href="registrar_pago.php?cliente_id=<?php echo $cliente['id']; ?>" 
                                           class="btn btn-sm btn-outline-warning"
                                           data-bs-toggle="tooltip" title="Registrar pago">
                                            <i class="fas fa-money-bill"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-1">
                                        <a href="nueva_venta.php?cliente_id=<?php echo $cliente['id']; ?>" 
                                           class="btn btn-sm btn-success btn-sm py-0">
                                            <small><i class="fas fa-cart-plus me-1"></i>Nueva Venta</small>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else: 
                            ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-users fa-3x mb-3"></i>
                                        <h5>No hay clientes registrados</h5>
                                        <p class="mb-0">Comience agregando su primer cliente</p>
                                        <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#modalNuevoCliente">
                                            <i class="fas fa-plus me-2"></i>Agregar Primer Cliente
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Acciones en lote -->
                <div class="card border-light mt-3" id="accionesLote" style="display: none;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span id="contadorSeleccionados">0</span> clientes seleccionados
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-outline-success btn-sm" onclick="activarSeleccionados()">
                                    <i class="fas fa-check-circle me-1"></i>Activar
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="desactivarSeleccionados()">
                                    <i class="fas fa-ban me-1"></i>Desactivar
                                </button>
                                <button class="btn btn-outline-warning btn-sm" onclick="exportarSeleccionados()">
                                    <i class="fas fa-file-export me-1"></i>Exportar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Paginación -->
                <?php if ($contador > 0): ?>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <small class="text-muted">
                            Mostrando <?php echo $contador; ?> de <?php echo $total_clientes; ?> clientes
                        </small>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-primary" onclick="exportarClientes()">
                            <i class="fas fa-file-excel me-1"></i>Exportar a Excel
                        </button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="imprimirLista()">
                            <i class="fas fa-print me-1"></i>Imprimir Lista
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuevo Cliente -->
<div class="modal fade" id="modalNuevoCliente" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" id="formNuevoCliente" novalidate>
                <div class="modal-header bg-gradient-success text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Nuevo Cliente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="crear">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Código *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white">CLT</span>
                                <input type="text" class="form-control" name="codigo" id="inputCodigo" required 
                                       pattern="[A-Z0-9]+" title="Solo letras mayúsculas y números"
                                       placeholder="Ej: 001, ALTO01" oninput="this.value = this.value.toUpperCase()">
                            </div>
                            <div class="form-text">
                                <i class="fas fa-lightbulb text-success"></i> Usar código único (ej: CLT001)
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Nombre Completo *</label>
                            <input type="text" class="form-control" name="nombre" required
                                   placeholder="Nombre y apellido del cliente">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ciudad</label>
                            <input type="text" class="form-control" name="ciudad" 
                                   placeholder="Ciudad de residencia">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" class="form-control" name="telefono" 
                                   placeholder="Número de contacto">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tipo Documento</label>
                            <select class="form-select" name="tipo_documento">
                                <option value="DNI">DNI</option>
                                <option value="RUC">RUC</option>
                                <option value="Cedula">Cédula</option>
                                <option value="Pasaporte">Pasaporte</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Número Documento</label>
                            <input type="text" class="form-control" name="numero_documento"
                                   placeholder="Número del documento">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Límite Crédito (Bs)</label>
                            <input type="number" class="form-control" name="limite_credito" 
                                   step="0.01" min="0" value="0" id="inputLimiteCredito">
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> 0 = Sin límite de crédito
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="3" 
                                      placeholder="Notas adicionales sobre el cliente..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Crear Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Cliente -->
<div class="modal fade" id="modalEditarCliente" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" id="formEditarCliente" novalidate>
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Editar Cliente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="editar">
                    <input type="hidden" name="id" id="editClienteId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Código *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white">CLT</span>
                                <input type="text" class="form-control" name="codigo" id="editCodigo" required
                                       oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Nombre Completo *</label>
                            <input type="text" class="form-control" name="nombre" id="editNombre" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ciudad</label>
                            <input type="text" class="form-control" name="ciudad" id="editCiudad">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" class="form-control" name="telefono" id="editTelefono">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tipo Documento</label>
                            <select class="form-select" name="tipo_documento" id="editTipoDocumento">
                                <option value="DNI">DNI</option>
                                <option value="RUC">RUC</option>
                                <option value="Cedula">Cédula</option>
                                <option value="Pasaporte">Pasaporte</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Número Documento</label>
                            <input type="text" class="form-control" name="numero_documento" id="editNumeroDocumento">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Límite Crédito (Bs)</label>
                            <input type="number" class="form-control" name="limite_credito" 
                                   id="editLimiteCredito" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-light">
                                <div class="row">
                                    <div class="col-6">
                                        <label class="form-label text-muted">Saldo Actual</label>
                                        <div class="h5 fw-bold" id="editSaldoActualDisplay">Bs 0,00</div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label text-muted">Total Comprado</label>
                                        <div class="h5 fw-bold" id="editTotalCompradoDisplay">Bs 0,00</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-light">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" 
                                           name="activo" id="editActivo" value="1">
                                    <label class="form-check-label fw-bold" for="editActivo">
                                        Cliente Activo
                                    </label>
                                </div>
                                <div class="form-text mt-2">
                                    <i class="fas fa-calendar me-1"></i>
                                    Registrado: <span id="editFechaRegistro"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea class="form-control" name="observaciones" id="editObservaciones" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Variables globales
let clientesData = [];

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Cargar datos de clientes
    cargarDatosClientes();
    
    // Configurar búsqueda en tiempo real
    const buscarInput = document.getElementById('buscarCliente');
    if (buscarInput) {
        buscarInput.addEventListener('input', buscarClientes);
    }
    
    // Configurar filtros
    document.getElementById('filtroEstado').addEventListener('change', function() {
        buscarClientes();
    });
    
    document.getElementById('filtroOrden').addEventListener('change', function() {
        ordenarClientes(this.value);
    });
    
    // Check all functionality
    document.getElementById('checkAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.checkCliente');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        actualizarContadorSeleccionados();
    });
    
    // Validación de formularios
    const formNuevo = document.getElementById('formNuevoCliente');
    if (formNuevo) {
        formNuevo.addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.classList.add('was-validated');
        });
    }
    
    const formEditar = document.getElementById('formEditarCliente');
    if (formEditar) {
        formEditar.addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.classList.add('was-validated');
        });
    }
    
    // Auto-generar código si está vacío
    document.getElementById('inputCodigo').addEventListener('blur', function() {
        if (!this.value.trim()) {
            generarCodigoAutomatico();
        }
    });
});

function cargarDatosClientes() {
    const filas = document.querySelectorAll('#tablaClientes tbody tr[data-id]');
    
    clientesData = Array.from(filas).map(fila => ({
        id: fila.getAttribute('data-id'),
        codigo: fila.getAttribute('data-codigo'),
        nombre: fila.getAttribute('data-nombre'),
        ciudad: fila.getAttribute('data-ciudad'),
        telefono: fila.getAttribute('data-telefono'),
        deuda: parseFloat(fila.getAttribute('data-deuda')),
        activo: parseInt(fila.getAttribute('data-activo')),
        limiteExcedido: fila.getAttribute('data-limite-excedido') === 'true',
        elemento: fila
    }));
}

function buscarClientes() {
    const textoBusqueda = document.getElementById('buscarCliente').value.toLowerCase();
    const filtroEstado = document.getElementById('filtroEstado').value;
    
    let resultados = clientesData.filter(cliente => {
        // Filtro por texto de búsqueda
        if (textoBusqueda) {
            const enCodigo = cliente.codigo.includes(textoBusqueda);
            const enNombre = cliente.nombre.includes(textoBusqueda);
            const enCiudad = cliente.ciudad.includes(textoBusqueda);
            const enTelefono = cliente.telefono.includes(textoBusqueda);
            
            if (!enCodigo && !enNombre && !enCiudad && !enTelefono) {
                return false;
            }
        }
        
        // Filtro por estado
        if (filtroEstado) {
            switch(filtroEstado) {
                case 'activo':
                    if (cliente.activo !== 1) return false;
                    break;
                case 'inactivo':
                    if (cliente.activo !== 0) return false;
                    break;
                case 'con_deuda':
                    if (cliente.deuda <= 0) return false;
                    break;
                case 'sin_deuda':
                    if (cliente.deuda > 0) return false;
                    break;
                case 'limite_excedido':
                    if (!cliente.limiteExcedido) return false;
                    break;
            }
        }
        
        return true;
    });
    
    // Mostrar/ocultar filas
    clientesData.forEach(cliente => {
        cliente.elemento.style.display = 'none';
    });
    
    resultados.forEach(cliente => {
        cliente.elemento.style.display = '';
    });
    
    // Actualizar contadores
    document.getElementById('contadorResultados').textContent = resultados.length;
    
    const contadorFiltrados = document.getElementById('contadorFiltrados');
    if (resultados.length !== clientesData.length) {
        contadorFiltrados.textContent = ` (${resultados.length} filtrados)`;
        contadorFiltrados.style.display = 'inline';
    } else {
        contadorFiltrados.style.display = 'none';
    }
    
    return resultados.length;
}

function ordenarClientes(criterio) {
    const filas = Array.from(document.querySelectorAll('#tablaClientes tbody tr[data-id]'));
    const tbody = document.querySelector('#tablaClientes tbody');
    
    filas.sort((a, b) => {
        const aId = a.getAttribute('data-id');
        const bId = b.getAttribute('data-id');
        
        const clienteA = clientesData.find(c => c.id === aId);
        const clienteB = clientesData.find(c => c.id === bId);
        
        if (!clienteA || !clienteB) return 0;
        
        switch(criterio) {
            case 'nombre':
                return clienteA.nombre.localeCompare(clienteB.nombre);
            case 'nombre_desc':
                return clienteB.nombre.localeCompare(clienteA.nombre);
            case 'codigo':
                return clienteA.codigo.localeCompare(clienteB.codigo);
            case 'deuda_desc':
                return clienteB.deuda - clienteA.deuda;
            case 'deuda_asc':
                return clienteA.deuda - clienteB.deuda;
            case 'compras_desc':
                // Aquí necesitarías agregar el campo de compras a los datos
                return 0;
            default:
                return clienteA.nombre.localeCompare(clienteB.nombre);
        }
    });
    
    // Reordenar filas en la tabla
    filas.forEach(fila => tbody.appendChild(fila));
}

function filtrarClientes() {
    buscarClientes();
}

function limpiarBusqueda() {
    document.getElementById('buscarCliente').value = '';
    document.getElementById('filtroEstado').value = '';
    document.getElementById('filtroOrden').value = 'nombre';
    buscarClientes();
}

function cargarDatosCliente(clienteId) {
    // Hacer una petición AJAX para obtener los datos completos del cliente
    fetch(`obtener_datos_cliente.php?id=${clienteId}`)
        .then(response => response.json())
        .then(cliente => {
            if (cliente) {
                document.getElementById('editClienteId').value = cliente.id;
                document.getElementById('editCodigo').value = cliente.codigo;
                document.getElementById('editNombre').value = cliente.nombre;
                document.getElementById('editCiudad').value = cliente.ciudad || '';
                document.getElementById('editTelefono').value = cliente.telefono || '';
                document.getElementById('editTipoDocumento').value = cliente.tipo_documento;
                document.getElementById('editNumeroDocumento').value = cliente.numero_documento || '';
                document.getElementById('editLimiteCredito').value = cliente.limite_credito;
                document.getElementById('editSaldoActualDisplay').textContent = formatearMoneda(cliente.saldo_actual);
                document.getElementById('editTotalCompradoDisplay').textContent = formatearMoneda(cliente.total_comprado);
                document.getElementById('editActivo').checked = cliente.activo == 1;
                document.getElementById('editObservaciones').value = cliente.observaciones || '';
                document.getElementById('editFechaRegistro').textContent = cliente.fecha_registro || '';
            }
        })
        .catch(error => {
            console.error('Error al cargar datos del cliente:', error);
            mostrarNotificacion('Error al cargar datos del cliente', 'danger');
        });
}

function cambiarEstadoCliente(id, estadoActual, nombre) {
    const nuevoEstado = estadoActual == 1 ? 0 : 1;
    const accion = nuevoEstado == 1 ? 'activar' : 'desactivar';
    
    if (confirm(`¿${accion.toUpperCase()} al cliente "${nombre}"?`)) {
        const formData = new FormData();
        formData.append('action', 'cambiar_estado');
        formData.append('id', id);
        formData.append('activo', nuevoEstado);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(() => {
            location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al cambiar estado', 'danger');
        });
    }
}

function generarCodigoAutomatico() {
    // Generar código automático basado en el último código
    fetch('generar_codigo_cliente.php')
        .then(response => response.text())
        .then(codigo => {
            document.getElementById('inputCodigo').value = codigo;
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

function actualizarContadorSeleccionados() {
    const checkboxes = document.querySelectorAll('.checkCliente:checked');
    const contador = document.getElementById('contadorSeleccionados');
    const accionesLote = document.getElementById('accionesLote');
    
    contador.textContent = checkboxes.length;
    
    if (checkboxes.length > 0) {
        accionesLote.style.display = 'block';
    } else {
        accionesLote.style.display = 'none';
    }
}

function activarSeleccionados() {
    const checkboxes = document.querySelectorAll('.checkCliente:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) return;
    
    if (confirm(`¿Activar ${ids.length} cliente(s) seleccionado(s)?`)) {
        const formData = new FormData();
        formData.append('action', 'activar_lote');
        formData.append('ids', JSON.stringify(ids));
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(() => {
            location.reload();
        });
    }
}

function desactivarSeleccionados() {
    const checkboxes = document.querySelectorAll('.checkCliente:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) return;
    
    if (confirm(`¿Desactivar ${ids.length} cliente(s) seleccionado(s)?`)) {
        const formData = new FormData();
        formData.append('action', 'desactivar_lote');
        formData.append('ids', JSON.stringify(ids));
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(() => {
            location.reload();
        });
    }
}

function exportarSeleccionados() {
    const checkboxes = document.querySelectorAll('.checkCliente:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) return;
    
    window.open(`exportar_clientes.php?ids=${ids.join(',')}`, '_blank');
}

function exportarClientes() {
    window.open('exportar_clientes.php', '_blank');
}

function imprimirLista() {
    // Crear contenido HTML básico
    let contenido = '<html><head><title>Lista de Clientes</title>';
    contenido += '<style>';
    contenido += 'body { font-family: Arial; margin: 20px; }';
    contenido += 'h2 { text-align: center; color: #2c3e50; }';
    contenido += 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
    contenido += 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
    contenido += 'th { background-color: #3498db; color: white; }';
    contenido += '.header { text-align: center; margin-bottom: 30px; }';
    contenido += '.footer { margin-top: 30px; text-align: center; font-size: 12px; color: #7f8c8d; }';
    contenido += '</style>';
    contenido += '</head><body>';
    
    // Encabezado
    contenido += '<div class="header">';
    contenido += '<h2>LISTA DE CLIENTES</h2>';
    contenido += '<p><strong>Empresa:</strong> <?php echo htmlspecialchars(EMPRESA_NOMBRE); ?></p>';
    contenido += '<p><strong>Fecha:</strong> ' + new Date().toLocaleDateString('es-ES') + '</p>';
    contenido += '<p><strong>Total:</strong> <?php echo $total_clientes; ?> clientes</p>';
    contenido += '</div>';
    
    // Tabla
    contenido += '<table>';
    contenido += '<thead><tr>';
    contenido += '<th>Código</th><th>Nombre</th><th>Ciudad</th><th>Teléfono</th>';
    contenido += '<th>Saldo</th><th>Estado</th>';
    contenido += '</tr></thead>';
    contenido += '<tbody>';
    
    // Agregar filas
    const filas = document.querySelectorAll('#tablaClientes tbody tr[data-id]');
    filas.forEach(function(fila) {
        if (fila.style.display !== 'none') {
            const codigo = fila.getAttribute('data-codigo') || '';
            const nombre = fila.querySelector('td:nth-child(3)').textContent.trim() || '';
            const ciudad = fila.getAttribute('data-ciudad') || '';
            const telefono = fila.getAttribute('data-telefono') || '';
            const deuda = parseFloat(fila.getAttribute('data-deuda')) || 0;
            const activo = fila.getAttribute('data-activo') == '1' ? 'Activo' : 'Inactivo';
            
            contenido += '<tr>';
            contenido += '<td>' + codigo + '</td>';
            contenido += '<td>' + nombre + '</td>';
            contenido += '<td>' + ciudad + '</td>';
            contenido += '<td>' + telefono + '</td>';
            contenido += '<td>Bs ' + deuda.toFixed(2).replace('.', ',') + '</td>';
            contenido += '<td>' + activo + '</td>';
            contenido += '</tr>';
        }
    });
    
    contenido += '</tbody></table>';
    
    // Pie de página
    contenido += '<div class="footer">';
    contenido += '<p>Documento generado el ' + new Date().toLocaleString('es-ES') + '</p>';
    contenido += '<p>Sistema de Gestión de Clientes</p>';
    contenido += '</div>';
    
    contenido += '</body></html>';
    
    // Abrir ventana e imprimir
    const ventana = window.open('', '_blank', 'width=900,height=700');
    if (ventana) {
        ventana.document.write(contenido);
        ventana.document.close();
        
        // Esperar a que cargue e imprimir
        ventana.onload = function() {
            setTimeout(function() {
                ventana.print();
                setTimeout(function() {
                    ventana.close();
                }, 1000);
            }, 500);
        };
    } else {
        alert('Por favor permita ventanas emergentes para imprimir');
    }
}

function mostrarNotificacion(mensaje, tipo = 'info') {
    // Eliminar notificaciones anteriores
    const notificacionesAnteriores = document.querySelectorAll('.notificacion-flotante');
    notificacionesAnteriores.forEach(function(n) {
        n.remove();
    });
    
    const notificacion = document.createElement('div');
    notificacion.className = 'alert alert-' + tipo + ' alert-dismissible fade show notificacion-flotante';
    notificacion.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 350px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    
    notificacion.innerHTML = 
        mensaje +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    
    document.body.appendChild(notificacion);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(function() {
        if (notificacion.parentNode) {
            notificacion.remove();
        }
    }, 5000);
}


</script>

<style>
/* Estilos para la tabla */
.table-hover tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.05);
    transform: translateY(-1px);
    transition: all 0.2s;
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(0, 0, 0, 0.02);
}

.table-primary th {
    background: linear-gradient(135deg, #0d6efd, #0b5ed7);
    color: white;
    border: none;
}

/* Badges personalizados */
.badge.bg-success {
    background: linear-gradient(135deg, #28a745, #1e7e34) !important;
}

.badge.bg-danger {
    background: linear-gradient(135deg, #dc3545, #c82333) !important;
}

.badge.bg-warning {
    background: linear-gradient(135deg, #ffc107, #e0a800) !important;
    color: #212529;
}

/* Progress bar personalizado */
.progress {
    border-radius: 3px;
}

.progress-bar {
    border-radius: 3px;
}

/* Botones de acción */
.btn-group .btn {
    border-radius: 5px !important;
}

/* Notificaciones flotantes */
.notificacion-flotante {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Efectos de hover para las tarjetas */
.card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 14px;
    }
    
    .btn-group {
        flex-wrap: wrap;
    }
    
    .btn-group .btn {
        margin-bottom: 5px;
    }
}
</style>

<?php require_once 'footer.php'; ?>