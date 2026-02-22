<?php
session_start();

$titulo_pagina = "Gestión de Proveedores";
$icono_titulo = "fas fa-truck";
$breadcrumb = [
    ['text' => 'Proveedores', 'link' => '#', 'active' => true]
];

require_once 'header.php';
require_once 'funciones.php';

// Verificar que sea administrador
verificarRol(['administrador']);

$mensaje = '';
$tipo_mensaje = '';

// Procesar acción de cambiar estado (si viene por GET)
if (isset($_GET['cambiar_estado'])) {
    $id = intval($_GET['cambiar_estado']);
    $nuevo_estado = isset($_GET['nuevo_estado']) ? intval($_GET['nuevo_estado']) : 0;
    
    $query = "UPDATE proveedores SET activo = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $nuevo_estado, $id);
    
    if ($stmt->execute()) {
        $accion = $nuevo_estado == 1 ? 'activado' : 'desactivado';
        $mensaje = "Proveedor $accion exitosamente";
        $tipo_mensaje = "success";
    } else {
        $mensaje = "Error al cambiar estado del proveedor";
        $tipo_mensaje = "danger";
    }
}

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'crear':
            $codigo = limpiar($_POST['codigo']);
            $nombre = limpiar($_POST['nombre']);
            $ciudad = limpiar($_POST['ciudad']);
            $telefono = limpiar($_POST['telefono']);
            $email = limpiar($_POST['email']);
            $credito_limite = floatval($_POST['credito_limite']);
            $observaciones = limpiar($_POST['observaciones']);
            
            // Verificar si el código ya existe
            $check_query = "SELECT id FROM proveedores WHERE codigo = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("s", $codigo);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $mensaje = "Error: El código ya está en uso por otro proveedor";
                $tipo_mensaje = "danger";
            } else {
                $query = "INSERT INTO proveedores 
                         (codigo, nombre, ciudad, telefono, email, credito_limite, observaciones) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssssds", $codigo, $nombre, $ciudad, $telefono, $email, $credito_limite, $observaciones);
                
                if ($stmt->execute()) {
                    $mensaje = "Proveedor creado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al crear proveedor: " . $stmt->error;
                    $tipo_mensaje = "danger";
                }
            }
            break;
            
        case 'editar':
            $id = $_POST['id'];
            $codigo = limpiar($_POST['codigo']);
            $nombre = limpiar($_POST['nombre']);
            $ciudad = limpiar($_POST['ciudad']);
            $telefono = limpiar($_POST['telefono']);
            $email = limpiar($_POST['email']);
            $credito_limite = floatval($_POST['credito_limite']);
            $activo = isset($_POST['activo']) ? 1 : 0;
            $observaciones = limpiar($_POST['observaciones']);
            
            // Verificar si el código ya existe (excluyendo el actual)
            $check_query = "SELECT id FROM proveedores WHERE codigo = ? AND id != ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("si", $codigo, $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $mensaje = "Error: El código ya está en uso por otro proveedor";
                $tipo_mensaje = "danger";
            } else {
                $query = "UPDATE proveedores SET 
                         codigo = ?, nombre = ?, ciudad = ?, telefono = ?, email = ?, 
                         credito_limite = ?, activo = ?, observaciones = ?
                         WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssssdisi", $codigo, $nombre, $ciudad, $telefono, $email, 
                                 $credito_limite, $activo, $observaciones, $id);
                
                if ($stmt->execute()) {
                    $mensaje = "Proveedor actualizado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al actualizar proveedor: " . $stmt->error;
                    $tipo_mensaje = "danger";
                }
            }
            break;
    }
}

// Obtener lista de proveedores
// Si el campo saldo_actual está en cero utilizamos la suma de movimientos
// para evitar inconsistencias cuando el trigger no haya ejecutado correctamente.
$query = "SELECT id, codigo, nombre, ciudad, telefono, email, 
          credito_limite,
          COALESCE(saldo_actual,
                   (SELECT COALESCE(SUM(compra - a_cuenta - adelanto),0) 
                    FROM proveedores_estado_cuentas pec 
                    WHERE pec.proveedor_id = proveedores.id)
          ) as saldo_actual,
          activo,
          DATE_FORMAT(fecha_registro, '%d/%m/%Y') as fecha_registro,
          observaciones
          FROM proveedores ORDER BY nombre";
$result = $conn->query($query);
?>

<?php if ($mensaje): ?>
<div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
    <div class="d-flex align-items-center">
        <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $mensaje; ?>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Resumen de proveedores -->
<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 opacity-75">Total Proveedores</h6>
                        <h2 class="card-title mb-0">
                            <?php
                            $query_total = "SELECT COUNT(*) as total FROM proveedores";
                            $result_total = $conn->query($query_total);
                            $total = $result_total->fetch_assoc()['total'];
                            echo number_format($total);
                            ?>
                        </h2>
                    </div>
                    <i class="fas fa-truck fa-3x opacity-25"></i>
                </div>
                <div class="mt-3 small">
                    <i class="fas fa-chart-line me-1"></i>
                    <span class="opacity-75">Registrados en el sistema</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 opacity-75">Proveedores Activos</h6>
                        <h2 class="card-title mb-0">
                            <?php
                            $query_activos = "SELECT COUNT(*) as total FROM proveedores WHERE activo = 1";
                            $result_activos = $conn->query($query_activos);
                            $activos = $result_activos->fetch_assoc()['total'];
                            echo number_format($activos);
                            ?>
                        </h2>
                    </div>
                    <i class="fas fa-check-circle fa-3x opacity-25"></i>
                </div>
                <div class="mt-3 small">
                    <i class="fas fa-percentage me-1"></i>
                    <span class="opacity-75">
                        <?php echo $total > 0 ? number_format(($activos / $total) * 100, 1) : 0; ?>% del total
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card bg-danger text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 opacity-75">Total Deuda</h6>
                        <h2 class="card-title mb-0">
                            <?php
                            $query_deuda = "SELECT COALESCE(SUM(saldo_actual), 0) as total_deuda FROM proveedores WHERE saldo_actual > 0";
                            $result_deuda = $conn->query($query_deuda);
                            $deuda = $result_deuda->fetch_assoc()['total_deuda'];
                            echo formatearMoneda($deuda);
                            ?>
                        </h2>
                    </div>
                    <i class="fas fa-money-bill-wave fa-3x opacity-25"></i>
                </div>
                <div class="mt-3 small">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <?php
                    $query_proveedores_deuda = "SELECT COUNT(*) as total FROM proveedores WHERE saldo_actual > 0";
                    $result_proveedores_deuda = $conn->query($query_proveedores_deuda);
                    $proveedores_deuda = $result_proveedores_deuda->fetch_assoc()['total'];
                    ?>
                    <span class="opacity-75"><?php echo $proveedores_deuda; ?> proveedor(es) con deuda</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 opacity-75">Límite Disponible</h6>
                        <h2 class="card-title mb-0">
                            <?php
                            $query_limite = "SELECT COALESCE(SUM(credito_limite - saldo_actual), 0) as disponible FROM proveedores";
                            $result_limite = $conn->query($query_limite);
                            $disponible = $result_limite->fetch_assoc()['disponible'];
                            echo formatearMoneda($disponible);
                            ?>
                        </h2>
                    </div>
                    <i class="fas fa-credit-card fa-3x opacity-25"></i>
                </div>
                <div class="mt-3 small">
                    <i class="fas fa-shield-alt me-1"></i>
                    <span class="opacity-75">Capacidad de crédito disponible</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Lista de Proveedores</h5>
                <div class="btn-group">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNuevoProveedor">
                        <i class="fas fa-plus me-2"></i>Nuevo Proveedor
                    </button>
                    <a href="categorias.php" class="btn btn-outline-primary ms-2">
                        <i class="fas fa-tags me-2"></i>Ver Categorías
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Filtros y buscador -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control" id="buscarProveedor" 
                                   placeholder="Buscar proveedor..." onkeyup="buscarProveedores()">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-city"></i>
                            </span>
                            <select class="form-select" id="filtroCiudad" onchange="filtrarProveedores()">
                                <option value="">Todas las ciudades</option>
                                <?php
                                $query_ciudades = "SELECT DISTINCT ciudad FROM proveedores WHERE ciudad != '' ORDER BY ciudad";
                                $result_ciudades = $conn->query($query_ciudades);
                                while ($ciudad = $result_ciudades->fetch_assoc()):
                                ?>
                                <option value="<?php echo htmlspecialchars($ciudad['ciudad']); ?>">
                                    <?php echo htmlspecialchars($ciudad['ciudad']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-filter"></i>
                            </span>
                            <select class="form-select" id="filtroEstado" onchange="filtrarProveedores()">
                                <option value="">Todos los estados</option>
                                <option value="1">Activos</option>
                                <option value="0">Inactivos</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 text-end">
                        <button class="btn btn-outline-secondary" onclick="resetearFiltros()" title="Limpiar filtros">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="tablaProveedores">
                        <thead class="table-light">
                            <tr>
                                <th width="100">Código</th>
                                <th>Proveedor</th>
                                <th width="120">Ciudad</th>
                                <th width="120">Teléfono</th>
                                <th width="140">Límite Crédito</th>
                                <th width="160">Debe</th>
                                <th width="100">Estado</th>
                                <th width="120">Registro</th>
                                <th width="180" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($proveedor = $result->fetch_assoc()): 
                                    $credito_limite = floatval($proveedor['credito_limite']);
                                    $saldo_actual = floatval($proveedor['saldo_actual']);
                                    $saldo_porcentaje = $credito_limite > 0 ? ($saldo_actual / $credito_limite) * 100 : 0;
                                    $nivel_riesgo = '';
                                    $color_riesgo = '';
                                    
                                    if ($saldo_porcentaje > 80) {
                                        $nivel_riesgo = 'Alto';
                                        $color_riesgo = 'danger';
                                    } elseif ($saldo_porcentaje > 50) {
                                        $nivel_riesgo = 'Medio';
                                        $color_riesgo = 'warning';
                                    } elseif ($saldo_porcentaje > 0) {
                                        $nivel_riesgo = 'Bajo';
                                        $color_riesgo = 'info';
                                    }
                                ?>
                                <tr data-ciudad="<?php echo htmlspecialchars($proveedor['ciudad']); ?>"
                                    data-estado="<?php echo $proveedor['activo']; ?>"
                                    data-busqueda="<?php echo strtolower(htmlspecialchars($proveedor['codigo'] . ' ' . $proveedor['nombre'] . ' ' . $proveedor['telefono'] . ' ' . $proveedor['email'] . ' ' . $proveedor['ciudad'])); ?>">
                                    <td>
                                        <span class="badge bg-dark"><?php echo htmlspecialchars($proveedor['codigo']); ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                <i class="fas fa-truck"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($proveedor['nombre']); ?></strong>
                                                <?php if ($proveedor['email']): ?>
                                                <div class="small text-muted">
                                                    <i class="fas fa-envelope me-1"></i>
                                                    <?php echo htmlspecialchars($proveedor['email']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <i class="fas fa-city me-1"></i>
                                            <?php echo htmlspecialchars($proveedor['ciudad']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($proveedor['telefono']): ?>
                                            <a href="tel:<?php echo htmlspecialchars($proveedor['telefono']); ?>" 
                                               class="text-decoration-none">
                                                <i class="fas fa-phone me-1"></i>
                                                <?php echo htmlspecialchars($proveedor['telefono']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold"><?php echo formatearMoneda($proveedor['credito_limite']); ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="fw-bold <?php echo $saldo_actual > 0 ? 'text-danger' : 'text-success'; ?>">
                                                    <?php echo formatearMoneda($proveedor['saldo_actual']); ?>
                                                </span>
                                                <?php if ($nivel_riesgo): ?>
                                                    <span class="badge bg-<?php echo $color_riesgo; ?> badge-sm">
                                                        <?php echo $nivel_riesgo; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($credito_limite > 0): ?>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-<?php echo $color_riesgo ?: 'success'; ?>" 
                                                     style="width: <?php echo min($saldo_porcentaje, 100); ?>%"
                                                     title="<?php echo number_format($saldo_porcentaje, 1); ?>% del límite utilizado">
                                                </div>
                                            </div>
                                            <small class="text-muted mt-1">
                                                <?php echo number_format($saldo_porcentaje, 1); ?>% utilizado
                                            </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($proveedor['activo'] == 1): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i>Activo
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times-circle me-1"></i>Inactivo
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo $proveedor['fecha_registro']; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEditarProveedor"
                                                    onclick="cargarDatosProveedor(<?php echo htmlspecialchars(json_encode($proveedor)); ?>)"
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <!-- <a href="estado_cuenta_proveedor.php?id=<?php echo $proveedor['id']; ?>" 
                                               class="btn btn-outline-info" title="Estado de Cuenta">
                                                <i class="fas fa-file-invoice-dollar"></i>
                                            </a> -->
                                            <a href="categorias.php?proveedor_id=<?php echo $proveedor['id']; ?>" 
                                               class="btn btn-outline-success" title="Ver Categorías">
                                                <i class="fas fa-tags"></i>
                                            </a>
                                            <button class="btn btn-outline-<?php echo $proveedor['activo'] ? 'danger' : 'success'; ?>"
                                                    onclick="cambiarEstadoProveedor(<?php echo $proveedor['id']; ?>, <?php echo $proveedor['activo']; ?>, '<?php echo addslashes($proveedor['nombre']); ?>')"
                                                    title="<?php echo $proveedor['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                                <i class="fas fa-<?php echo $proveedor['activo'] ? 'ban' : 'check'; ?>"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                                            <h5>No hay proveedores registrados</h5>
                                            <p class="text-muted">Comienza creando tu primer proveedor</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="9" class="text-muted small">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-info-circle me-1"></i>
                                            Mostrando <?php echo $result->num_rows; ?> proveedores
                                        </div>
                                        <div class="text-end">
                                            <button class="btn btn-sm btn-outline-secondary ms-1" onclick="imprimirTabla()">
                                                <i class="fas fa-print me-1"></i>Imprimir
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>



<!-- Modal Nuevo Proveedor -->
<div class="modal fade" id="modalNuevoProveedor" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" id="formNuevoProveedor">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-truck-loading me-2"></i>Nuevo Proveedor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="crear">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Código <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-hashtag"></i>
                                </span>
                                <input type="text" class="form-control" name="codigo" required 
                                       pattern="[A-Z0-9]{3,20}" 
                                       title="3-20 caracteres (solo mayúsculas y números)">
                            </div>
                            <div class="form-text">Ejemplo: PROV001</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre del Proveedor <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user-tie"></i>
                                </span>
                                <input type="text" class="form-control" name="nombre" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ciudad <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-city"></i>
                                </span>
                                <input type="text" class="form-control" name="ciudad" required
                                       list="ciudadesList">
                                <datalist id="ciudadesList">
                                    <?php
                                    $query_ciudades_list = "SELECT DISTINCT ciudad FROM proveedores ORDER BY ciudad";
                                    $result_ciudades_list = $conn->query($query_ciudades_list);
                                    while ($ciudad = $result_ciudades_list->fetch_assoc()):
                                    ?>
                                    <option value="<?php echo htmlspecialchars($ciudad['ciudad']); ?>">
                                    <?php endwhile; ?>
                                </datalist>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-phone"></i>
                                </span>
                                <input type="tel" class="form-control" name="telefono"
                                       pattern="[0-9]{7,15}"
                                       title="Número telefónico válido (7-15 dígitos)">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" name="email">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Límite de Crédito (Bs) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-credit-card"></i>
                                </span>
                                <input type="number" class="form-control" name="credito_limite" 
                                       step="0.01" min="0" value="10000" required>
                            </div>
                            <div class="form-text">Establecer en 0 si no aplica crédito</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="3" 
                                      placeholder="Información adicional sobre el proveedor..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>Crear Proveedor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Proveedor -->
<div class="modal fade" id="modalEditarProveedor" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" id="formEditarProveedor">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Proveedor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="editar">
                    <input type="hidden" name="id" id="editProveedorId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Código <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-hashtag"></i>
                                </span>
                                <input type="text" class="form-control" name="codigo" id="editCodigo" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre del Proveedor <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user-tie"></i>
                                </span>
                                <input type="text" class="form-control" name="nombre" id="editNombre" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ciudad <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-city"></i>
                                </span>
                                <input type="text" class="form-control" name="ciudad" id="editCiudad" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-phone"></i>
                                </span>
                                <input type="tel" class="form-control" name="telefono" id="editTelefono">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" name="email" id="editEmail">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Límite de Crédito (Bs) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-credit-card"></i>
                                </span>
                                <input type="number" class="form-control" name="credito_limite" 
                                       id="editCreditoLimite" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="activo" 
                                       id="editActivo" value="1" role="switch">
                                <label class="form-check-label" for="editActivo">
                                    <span id="estadoProveedor">Proveedor Activo</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Debe</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-money-bill-wave"></i>
                                </span>
                                <input type="text" class="form-control" id="editSaldoActual" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea class="form-control" name="observaciones" 
                                      id="editObservaciones" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function cargarDatosProveedor(proveedor) {
    document.getElementById('editProveedorId').value = proveedor.id;
    document.getElementById('editCodigo').value = proveedor.codigo;
    document.getElementById('editNombre').value = proveedor.nombre;
    document.getElementById('editCiudad').value = proveedor.ciudad;
    document.getElementById('editTelefono').value = proveedor.telefono || '';
    document.getElementById('editEmail').value = proveedor.email || '';
    document.getElementById('editCreditoLimite').value = parseFloat(proveedor.credito_limite).toFixed(2);
    document.getElementById('editSaldoActual').value = formatearMonedaJS(proveedor.saldo_actual);
    document.getElementById('editObservaciones').value = proveedor.observaciones || '';
    
    const activoCheckbox = document.getElementById('editActivo');
    const estadoLabel = document.getElementById('estadoProveedor');
    
    activoCheckbox.checked = proveedor.activo == 1;
    estadoLabel.textContent = proveedor.activo == 1 ? 'Proveedor Activo' : 'Proveedor Inactivo';
}

function cambiarEstadoProveedor(id, estadoActual, nombre) {
    const nuevoEstado = estadoActual == 1 ? 0 : 1;
    const accion = nuevoEstado == 1 ? 'activar' : 'desactivar';
    const mensaje = estadoActual == 1 ? 
        `¿Desactivar al proveedor ${nombre}?\n\nEl proveedor no podrá ser utilizado en nuevas compras hasta que sea reactivado.` :
        `¿Activar al proveedor ${nombre}?\n\nEl proveedor podrá ser utilizado nuevamente en compras.`;
    
    if (confirm(mensaje)) {
        window.location.href = `proveedores.php?cambiar_estado=${id}&nuevo_estado=${nuevoEstado}`;
    }
}

function buscarProveedores() {
    const filter = document.getElementById('buscarProveedor').value.toLowerCase();
    const rows = document.querySelectorAll('#tablaProveedores tbody tr');
    
    rows.forEach(row => {
        const searchText = row.getAttribute('data-busqueda') || '';
        if (searchText.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filtrarProveedores() {
    const ciudad = document.getElementById('filtroCiudad').value;
    const estado = document.getElementById('filtroEstado').value;
    const rows = document.querySelectorAll('#tablaProveedores tbody tr');
    
    rows.forEach(row => {
        const rowCiudad = row.getAttribute('data-ciudad');
        const rowEstado = row.getAttribute('data-estado');
        let mostrar = true;
        
        if (ciudad && rowCiudad !== ciudad) {
            mostrar = false;
        }
        
        if (estado !== '' && rowEstado !== estado) {
            mostrar = false;
        }
        
        row.style.display = mostrar ? '' : 'none';
    });
}

function resetearFiltros() {
    document.getElementById('buscarProveedor').value = '';
    document.getElementById('filtroCiudad').value = '';
    document.getElementById('filtroEstado').value = '';
    
    const rows = document.querySelectorAll('#tablaProveedores tbody tr');
    rows.forEach(row => {
        row.style.display = '';
    });
}

function formatearMonedaJS(monto) {
    return new Intl.NumberFormat('es-BO', {
        style: 'currency',
        currency: 'BOB',
        minimumFractionDigits: 2
    }).format(monto);
}

function exportarTabla() {
    // En una implementación real, esto enviaría una solicitud al servidor
    // para generar un archivo Excel o CSV
    alert('Funcionalidad de exportación en desarrollo.\nSe generará un archivo Excel con los datos de proveedores.');
}

function imprimirTabla() {
    const printContent = document.getElementById('tablaProveedores').outerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <html>
            <head>
                <title>Lista de Proveedores - <?php echo EMPRESA_NOMBRE; ?></title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    @media print {
                        .no-print { display: none !important; }
                        body { padding: 20px; }
                        table { font-size: 12px; }
                        .badge { border: 1px solid #000 !important; }
                    }
                    h1 { margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                    .summary { margin-bottom: 20px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1 class="text-center">Lista de Proveedores</h1>
                    <div class="summary">
                        <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i'); ?></p>
                        <p><strong>Sistema:</strong> <?php echo SISTEMA_NOMBRE; ?></p>
                    </div>
                    ${printContent}
                    <div class="mt-4 text-center text-muted">
                        <small>Documento generado automáticamente</small>
                    </div>
                </div>
            </body>
        </html>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

// Inicializar tooltips de Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-generar código si está vacío
    const codigoInput = document.querySelector('input[name="codigo"]');
    if (codigoInput) {
        codigoInput.addEventListener('blur', function() {
            if (!this.value.trim()) {
                // Consultar último código
                fetch('ajax/proveedores.php?action=ultimo_codigo')
                    .then(response => response.json())
                    .then(data => {
                        if (data.codigo) {
                            this.value = data.codigo;
                        }
                    });
            }
        });
    }
});

// Validación de formularios
document.getElementById('formNuevoProveedor').addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});

document.getElementById('formEditarProveedor').addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});
</script>

<style>
.avatar-sm {
    width: 36px;
    height: 36px;
    font-size: 14px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.table th {
    font-weight: 600;
    background-color: #f8f9fa;
    position: sticky;
    top: 0;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
    transform: translateY(-1px);
    transition: transform 0.2s;
}

.btn-group .btn {
    border-radius: 4px !important;
    margin-right: 2px;
    padding: 0.25rem 0.5rem;
}

.progress {
    border-radius: 3px;
}

.badge-sm {
    font-size: 0.7em;
    padding: 0.2em 0.5em;
}

.card {
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-2px);
}

.input-group-text {
    background-color: #f8f9fa;
    border-right: none;
}

.form-control:focus + .input-group-text {
    border-color: #86b7fe;
    background-color: #e7f1ff;
}

.form-switch .form-check-input {
    height: 1.5em;
    width: 3em;
}
</style>

<?php require_once 'footer.php'; ?>