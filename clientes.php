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

// Parámetros de búsqueda
$search = limpiar($_GET['search'] ?? '');
$filtroEstado = limpiar($_GET['filter'] ?? '');

// Construir cláusulas WHERE dinámicas
$where = "1=1";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (codigo LIKE ? OR nombre LIKE ? OR ciudad LIKE ? OR telefono LIKE ? OR numero_documento LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
    $types .= str_repeat('s', 5);
}

switch ($filtroEstado) {
    case 'activo':
        $where .= " AND activo = 1";
        break;
    case 'inactivo':
        $where .= " AND activo = 0";
        break;
    case 'con_deuda':
        $where .= " AND saldo_actual > 0";
        break;
    case 'sin_deuda':
        $where .= " AND saldo_actual <= 0";
        break;
    case 'limite_excedido':
        $where .= " AND saldo_actual > limite_credito AND limite_credito > 0";
        break;
}

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
                    $mensaje = "El código de cliente ya existe";
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
                        $mensaje = "Cliente creado exitosamente";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = "Error al crear cliente: " . $stmt->error;
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
                    $mensaje = "El código de cliente ya está en uso por otro cliente";
                    $tipo_mensaje = "danger";
                } else {
                    $query = "UPDATE clientes SET 
                             codigo = ?, nombre = ?, ciudad = ?, telefono = ?, 
                             tipo_documento = ?, numero_documento = ?, 
                             limite_credito = ?, activo = ?, observaciones = ?
                             WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssssssdiss", $codigo, $nombre, $ciudad, $telefono, 
                                     $tipo_documento, $numero_documento, $limite_credito, 
                                     $activo, $observaciones, $id);
                    
                    if ($stmt->execute()) {
                        $mensaje = "Cliente actualizado exitosamente";
                        $tipo_mensaje = "success";
                    } else {
                        $mensaje = "Error al actualizar cliente: " . $stmt->error;
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
                $mensaje = "Estado del cliente actualizado";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al cambiar estado: " . $stmt->error;
                $tipo_mensaje = "danger";
            }
            break;
            
        case 'activar_lote':
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $query = "UPDATE clientes SET activo = 1 WHERE id IN ($placeholders)";
                $stmt = $conn->prepare($query);
                $types = str_repeat('i', count($ids));
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $mensaje = "Clientes activados exitosamente";
                $tipo_mensaje = "success";
            }
            break;
            
        case 'desactivar_lote':
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $query = "UPDATE clientes SET activo = 0 WHERE id IN ($placeholders)";
                $stmt = $conn->prepare($query);
                $types = str_repeat('i', count($ids));
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $mensaje = "Clientes desactivados exitosamente";
                $tipo_mensaje = "success";
            }
            break;
    }
}

// Obtener lista de clientes (SIN PAGINACIÓN - todos los registros)
$query_clientes = "SELECT id, codigo, nombre, ciudad, telefono, tipo_documento,
                  numero_documento, limite_credito, saldo_actual, total_comprado,
                  compras_realizadas, activo, observaciones,
                  DATE_FORMAT(fecha_registro, '%d/%m/%Y') as fecha_registro
                  FROM clientes
                  WHERE $where
                  ORDER BY nombre";

if (!empty($params)) {
    $stmt = $conn->prepare($query_clientes);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result_clientes = $stmt->get_result();
} else {
    $result_clientes = $conn->query($query_clientes);
}

$total_clientes_filtrados = $result_clientes->num_rows;

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
                
                <!-- Filtros y búsqueda -->
                <form method="GET" class="card border-light mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control" id="buscarCliente"
                                           value="<?php echo htmlspecialchars($search); ?>"
                                           placeholder="Buscar por código, nombre, teléfono, ciudad...">
                                    <button class="btn btn-outline-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <select class="form-select" name="filter" id="filtroEstado">
                                    <option value=""<?php echo $filtroEstado == '' ? ' selected' : ''; ?>>Todos los estados</option>
                                    <option value="activo"<?php echo $filtroEstado == 'activo' ? ' selected' : ''; ?>>Clientes Activos</option>
                                    <option value="inactivo"<?php echo $filtroEstado == 'inactivo' ? ' selected' : ''; ?>>Clientes Inactivos</option>
                                    <option value="con_deuda"<?php echo $filtroEstado == 'con_deuda' ? ' selected' : ''; ?>>Con Deuda</option>
                                    <option value="sin_deuda"<?php echo $filtroEstado == 'sin_deuda' ? ' selected' : ''; ?>>Sin Deuda</option>
                                    <option value="limite_excedido"<?php echo $filtroEstado == 'limite_excedido' ? ' selected' : ''; ?>>Límite Excedido</option>
                                </select>
                            </div>
                            <div class="col-lg-2">
                                <button class="btn btn-outline-primary w-100" type="submit">
                                    <i class="fas fa-filter me-2"></i>Filtrar
                                </button>
                            </div>
                            <div class="col-lg-2">
                                <?php if ($search || $filtroEstado): ?>
                                <a href="clientes.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-times me-2"></i>Limpiar
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <div class="form-text">
                                    <span id="contadorResultados"><?php echo $total_clientes_filtrados; ?></span> clientes encontrados
                                </div>
                            </div>
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
                </form>
                
                <!-- Tabla de clientes con scroll -->
                <div class="table-responsive" style="max-height: 600px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px;">
                    <table class="table table-hover table-striped mb-0" id="tablaClientes">
                        <thead class="table-primary" style="position: sticky; top: 0; z-index: 10;">
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
                                <th width="100" class="text-end">Compras</th>
                                <th width="100">Estado</th>
                                <th width="150" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result_clientes->num_rows > 0):
                                while ($cliente = $result_clientes->fetch_assoc()): 
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
                                        <?php echo $cliente['numero_documento'] ?: 'N/A'; ?>
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
                                    <?php echo formatearMoneda($cliente['limite_credito']); ?>
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
                                    <?php echo formatearMoneda($cliente['total_comprado']); ?>
                                    <?php if ($cliente['compras_realizadas'] > 0): ?>
                                    <div class="small text-muted">
                                        Promedio: <?php echo formatearMoneda($cliente['total_comprado'] / $cliente['compras_realizadas']); ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php echo $cliente['compras_realizadas']; ?>
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
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditarCliente"
                                                onclick="cargarDatosCliente(<?php echo $cliente['id']; ?>)"
                                                title="Editar cliente">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <button class="btn btn-outline-<?php echo $cliente['activo'] ? 'danger' : 'success'; ?>"
                                                onclick="cambiarEstadoCliente(<?php echo $cliente['id']; ?>, <?php echo $cliente['activo']; ?>, '<?php echo addslashes($cliente['nombre']); ?>')"
                                                title="<?php echo $cliente['activo'] ? 'Desactivar' : 'Activar'; ?> cliente">
                                            <i class="fas fa-<?php echo $cliente['activo'] ? 'ban' : 'check'; ?>"></i>
                                        </button>
                                        <?php if ($cliente['saldo_actual'] > 0): ?>
                                        
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else: 
                            ?>
                            <tr>
                                <td colspan="10" class="text-center py-5">
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
                                <span class="fw-bold" id="contadorSeleccionados">0</span> clientes seleccionados
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
                
                <!-- Botones de exportación -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <small class="text-muted">
                            Mostrando <?php echo $result_clientes->num_rows; ?> de <?php echo $total_clientes; ?> clientes
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuevo Cliente -->
<div class="modal fade" id="modalNuevoCliente" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" id="formNuevoCliente">
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
            <form method="POST" action="" id="formEditarCliente">
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
                                           name="activo" id="editActivo" value="1" checked>
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
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Check all functionality
    const checkAll = document.getElementById('checkAll');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.checkCliente');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            actualizarContadorSeleccionados();
        });
    }
    
    // Event listeners para checkboxes individuales
    document.querySelectorAll('.checkCliente').forEach(checkbox => {
        checkbox.addEventListener('change', actualizarContadorSeleccionados);
    });
    
    // Auto-generar código si está vacío
    const inputCodigo = document.getElementById('inputCodigo');
    if (inputCodigo) {
        inputCodigo.addEventListener('blur', function() {
            if (!this.value.trim()) {
                generarCodigoAutomatico();
            }
        });
    }
});

function cargarDatosCliente(clienteId) {
    fetch('obtener_datos_cliente.php?id=' + clienteId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(cliente => {
            if (cliente && !cliente.error) {
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
            } else {
                mostrarNotificacion('Error al cargar datos del cliente', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al cargar datos del cliente', 'danger');
        });
}

function cambiarEstadoCliente(id, estadoActual, nombre) {
    const nuevoEstado = estadoActual == 1 ? 0 : 1;
    const accion = nuevoEstado == 1 ? 'activar' : 'desactivar';
    
    if (confirm('¿' + accion.toUpperCase() + ' al cliente "' + nombre + '"?')) {
        const formData = new FormData();
        formData.append('action', 'cambiar_estado');
        formData.append('id', id);
        formData.append('activo', nuevoEstado);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
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
    
    if (contador) contador.textContent = checkboxes.length;
    
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
    
    if (confirm('¿Activar ' + ids.length + ' cliente(s) seleccionado(s)?')) {
        const formData = new FormData();
        formData.append('action', 'activar_lote');
        formData.append('ids', JSON.stringify(ids));
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(() => {
            location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al activar clientes', 'danger');
        });
    }
}

function desactivarSeleccionados() {
    const checkboxes = document.querySelectorAll('.checkCliente:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) return;
    
    if (confirm('¿Desactivar ' + ids.length + ' cliente(s) seleccionado(s)?')) {
        const formData = new FormData();
        formData.append('action', 'desactivar_lote');
        formData.append('ids', JSON.stringify(ids));
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(() => {
            location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al desactivar clientes', 'danger');
        });
    }
}

function exportarSeleccionados() {
    const checkboxes = document.querySelectorAll('.checkCliente:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) return;
    
    window.open('exportar_clientes.php?ids=' + ids.join(','), '_blank');
}

function exportarClientes() {
    window.open('exportar_clientes.php', '_blank');
}

function imprimirLista() {
    let contenido = '<html><head><title>Lista de Clientes</title>';
    contenido += '<style>';
    contenido += 'body { font-family: Arial; margin: 20px; }';
    contenido += 'h2 { text-align: center; color: #2c3e50; }';
    contenido += 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
    contenido += 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
    contenido += 'th { background-color: #0d6efd; color: white; }';
    contenido += '.header { text-align: center; margin-bottom: 30px; }';
    contenido += '.footer { margin-top: 30px; text-align: center; font-size: 12px; color: #7f8c8d; }';
    contenido += '</style>';
    contenido += '</head><body>';
    
    contenido += '<div class="header">';
    contenido += '<h2>LISTA DE CLIENTES</h2>';
    contenido += '<p><strong>Empresa:</strong> <?php echo htmlspecialchars(EMPRESA_NOMBRE ?? 'TIENDA DE LANAS'); ?></p>';
    contenido += '<p><strong>Fecha:</strong> ' + new Date().toLocaleDateString('es-ES') + '</p>';
    contenido += '<p><strong>Total:</strong> <?php echo $total_clientes_filtrados; ?> clientes</p>';
    contenido += '</div>';
    
    contenido += '<table>';
    contenido += '<thead><tr>';
    contenido += '<th>Código</th><th>Nombre</th><th>Ciudad</th><th>Teléfono</th>';
    contenido += '<th>Saldo</th><th>Estado</th>';
    contenido += '</tr></thead>';
    contenido += '<tbody>';
    
    const filas = document.querySelectorAll('#tablaClientes tbody tr');
    filas.forEach(function(fila) {
        const codigo = fila.querySelector('td:nth-child(2) .text-primary')?.textContent.trim() || '';
        const nombre = fila.querySelector('td:nth-child(3) .fw-bold')?.textContent.trim() || '';
        const ciudad = fila.querySelector('td:nth-child(3) .fa-map-marker-alt')?.parentElement?.textContent.trim() || '';
        const telefono = fila.querySelector('td:nth-child(4) .fa-phone')?.parentElement?.textContent.trim() || '';
        const saldoElement = fila.querySelector('td:nth-child(6) .fw-bold');
        const deuda = saldoElement ? parseFloat(saldoElement.textContent.replace(/[^0-9,-]/g, '').replace(',', '.')) || 0 : 0;
        const activo = fila.querySelector('.badge.bg-success') ? 'Activo' : 'Inactivo';
        
        contenido += '<tr>';
        contenido += '<td>' + codigo + '</td>';
        contenido += '<td>' + nombre + '</td>';
        contenido += '<td>' + ciudad + '</td>';
        contenido += '<td>' + telefono + '</td>';
        contenido += '<td>Bs ' + deuda.toFixed(2).replace('.', ',') + '</td>';
        contenido += '<td>' + activo + '</td>';
        contenido += '</tr>';
    });
    
    contenido += '</tbody></table>';
    
    contenido += '<div class="footer">';
    contenido += '<p>Documento generado el ' + new Date().toLocaleString('es-ES') + '</p>';
    contenido += '<p>Sistema de Gestión de Clientes</p>';
    contenido += '</div>';
    
    contenido += '</body></html>';
    
    const ventana = window.open('', '_blank', 'width=900,height=700');
    if (ventana) {
        ventana.document.write(contenido);
        ventana.document.close();
        setTimeout(function() {
            ventana.print();
            setTimeout(function() {
                ventana.close();
            }, 1000);
        }, 500);
    } else {
        alert('Por favor permita ventanas emergentes para imprimir');
    }
}

function mostrarNotificacion(mensaje, tipo = 'info') {
    const notificacion = document.createElement('div');
    notificacion.className = 'alert alert-' + tipo + ' alert-dismissible fade show notificacion-flotante';
    notificacion.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 350px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    
    notificacion.innerHTML = mensaje +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    
    document.body.appendChild(notificacion);
    
    setTimeout(function() {
        if (notificacion.parentNode) {
            notificacion.remove();
        }
    }, 5000);
}

function formatearMoneda(valor) {
    return 'Bs ' + parseFloat(valor).toFixed(2).replace('.', ',');
}
</script>

<style>
/* Estilos para la tabla */
.table-hover tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.05);
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
    margin: 0 2px;
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

/* Scroll personalizado */
.table-responsive::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Responsive */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 14px;
    }
    
    .btn-group {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .btn-group .btn {
        margin-bottom: 5px;
    }
}
</style>

<?php require_once 'footer.php'; ?>