<?php
session_start();

$titulo_pagina = "Ajustar Inventario";
$icono_titulo = "fas fa-adjust";
$breadcrumb = [
    ['text' => 'Inventario', 'link' => 'inventario.php', 'active' => false],
    ['text' => 'Ajustar Inventario', 'link' => '#', 'active' => true]
];

require_once 'header.php';
require_once 'funciones.php';

// Verificar que sea administrador
verificarRol(['administrador']);

$producto_id = $_GET['producto_id'] ?? null;
$mensaje = '';
$tipo_mensaje = '';

$query_productos = "SELECT p.id, p.codigo, p.nombre_color, p.proveedor_id, 
                   pr.nombre as proveedor, pr.id as proveedor_id,
                   i.paquetes_completos, i.subpaquetes_sueltos, i.total_subpaquetes,
                   p.tiene_stock, p.precio_menor
                   FROM productos p
                   JOIN proveedores pr ON p.proveedor_id = pr.id
                   LEFT JOIN inventario i ON p.id = i.producto_id
                   WHERE p.activo = 1
                   ORDER BY pr.nombre, p.codigo";
$result_productos = $conn->query($query_productos);
$productos_array = [];
while ($producto = $result_productos->fetch_assoc()) {
    $productos_array[] = $producto;
}

// Guardar en variable para usar más tarde
$productos_para_modal = $productos_array;

// Procesar ajuste de inventario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'ajustar') {
        try {
            $producto_id = intval($_POST['producto_id']);
            $paquetes_fisicos = intval($_POST['paquetes_fisicos']);
            $subpaquetes_fisicos = intval($_POST['subpaquetes_fisicos']);
            $observaciones = limpiar($_POST['observaciones']);
            $tipo_ajuste = $_POST['tipo_ajuste'] ?? 'fisico';
            
            // Validar datos
            if ($paquetes_fisicos < 0 || $subpaquetes_fisicos < 0) {
                throw new Exception("Los valores no pueden ser negativos");
            }
            
            // Validar que se proporcionen observaciones
            if (empty($observaciones)) {
                throw new Exception("Las observaciones son obligatorias");
            }
            
            // Ajustar sueltos si son 10 o más
            if ($subpaquetes_fisicos >= 10) {
                $paquetes_fisicos += floor($subpaquetes_fisicos / 10);
                $subpaquetes_fisicos = $subpaquetes_fisicos % 10;
            }
            
            $conn->begin_transaction();
            
            // Obtener stock actual del sistema
            $query_actual = "SELECT paquetes_completos, subpaquetes_sueltos, total_subpaquetes 
                            FROM inventario WHERE producto_id = ?";
            $stmt_actual = $conn->prepare($query_actual);
            $stmt_actual->bind_param("i", $producto_id);
            $stmt_actual->execute();
            $result_actual = $stmt_actual->get_result();
            
            if ($result_actual->num_rows == 0) {
                throw new Exception("Producto no encontrado en inventario");
            }
            
            $stock_actual = $result_actual->fetch_assoc();
            
            // Calcular diferencia
            $total_actual = $stock_actual['total_subpaquetes'];
            $total_fisico = ($paquetes_fisicos * 10) + $subpaquetes_fisicos;
            $diferencia = $total_fisico - $total_actual;
            
            // Actualizar inventario en sistema
            $query_update = "UPDATE inventario SET 
                            paquetes_completos = ?,
                            subpaquetes_sueltos = ?,
                            total_subpaquetes = ?,
                            usuario_registro = ?,
                            fecha_ultimo_ingreso = CASE WHEN ? > 0 THEN CURDATE() ELSE fecha_ultimo_ingreso END,
                            fecha_ultima_salida = CASE WHEN ? < 0 THEN CURDATE() ELSE fecha_ultima_salida END
                            WHERE producto_id = ?";
            $stmt_update = $conn->prepare($query_update);
            $stmt_update->bind_param("iiiiiii", $paquetes_fisicos, $subpaquetes_fisicos, $total_fisico,
                                    $_SESSION['usuario_id'], $diferencia, $diferencia, $producto_id);
            
            if (!$stmt_update->execute()) {
                throw new Exception("Error al ajustar inventario: " . $stmt_update->error);
            }
            
            // Registrar en historial
            $query_historial = "INSERT INTO historial_inventario 
                               (producto_id, tipo_movimiento, 
                                paquetes_anteriores, subpaquetes_anteriores,
                                paquetes_nuevos, subpaquetes_nuevos,
                                diferencia, referencia, fecha_hora, usuario_id, observaciones)
                               VALUES (?, 'ajuste', ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
            $stmt_historial = $conn->prepare($query_historial);
            $referencia = $tipo_ajuste == 'devolucion' ? 'DEVOLUCION_CLIENTE' : 
                         ($tipo_ajuste == 'merma' ? 'MERMA_PERDIDA' : 'AJUSTE_FISICO');
            $observaciones_completas = "Tipo: " . ucfirst($tipo_ajuste) . " - " . $observaciones;
            
            $stmt_historial->bind_param("iiiiiiisi", $producto_id, 
                                       $stock_actual['paquetes_completos'], 
                                       $stock_actual['subpaquetes_sueltos'],
                                       $paquetes_fisicos, $subpaquetes_fisicos,
                                       $diferencia, $referencia, $_SESSION['usuario_id'], 
                                       $observaciones_completas);
            
            if (!$stmt_historial->execute()) {
                throw new Exception("Error al registrar en historial: " . $stmt_historial->error);
            }
            
            // Actualizar estado de stock del producto si es necesario
            $nuevo_total = $total_fisico;
            $tiene_stock = $nuevo_total > 0 ? 1 : 0;
            
            $query_update_producto = "UPDATE productos SET tiene_stock = ? WHERE id = ?";
            $stmt_update_producto = $conn->prepare($query_update_producto);
            $stmt_update_producto->bind_param("ii", $tiene_stock, $producto_id);
            $stmt_update_producto->execute();
            
            $conn->commit();
            
            $mensaje = "Inventario ajustado exitosamente. ";
            $mensaje .= "Diferencia: " . ($diferencia >= 0 ? '+' : '') . $diferencia . " subpaquetes";
            $tipo_mensaje = "success";
            
            // Recargar la página para mostrar datos actualizados
            header("Location: ajustar_inventario.php?producto_id=" . $producto_id . "&mensaje=" . urlencode($mensaje));
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
}

// Mostrar mensaje de URL si existe
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipo_mensaje = "success";
}

// Obtener información del producto si se seleccionó uno
$producto_info = null;
$inventario_info = null;
if ($producto_id) {
    $query_producto = "SELECT p.*, pr.nombre as proveedor_nombre, pr.id as proveedor_id,
                      c.nombre as categoria_nombre, c.id as categoria_id
                      FROM productos p
                      JOIN proveedores pr ON p.proveedor_id = pr.id
                      JOIN categorias c ON p.categoria_id = c.id
                      WHERE p.id = ? AND p.activo = 1";
    $stmt = $conn->prepare($query_producto);
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $producto_info = $result->fetch_assoc();
        
        // Obtener inventario actual
        $query_inventario = "SELECT * FROM inventario WHERE producto_id = ?";
        $stmt_inv = $conn->prepare($query_inventario);
        $stmt_inv->bind_param("i", $producto_id);
        $stmt_inv->execute();
        $result_inv = $stmt_inv->get_result();
        
        if ($result_inv->num_rows > 0) {
            $inventario_info = $result_inv->fetch_assoc();
        } else {
            // Si no existe registro de inventario, crear uno con cero
            $inventario_info = [
                'paquetes_completos' => 0,
                'subpaquetes_sueltos' => 0,
                'total_subpaquetes' => 0,
                'ubicacion' => null,
                'costo_paquete' => null
            ];
        }
    } else {
        $mensaje = "Producto no encontrado o está inactivo";
        $tipo_mensaje = "warning";
        $producto_id = null;
    }
}

// Obtener últimos ajustes para estadísticas
$query_stats_ajustes = "SELECT 
                        COUNT(*) as total_ajustes,
                        SUM(CASE WHEN diferencia > 0 THEN 1 ELSE 0 END) as ajustes_positivos,
                        SUM(CASE WHEN diferencia < 0 THEN 1 ELSE 0 END) as ajustes_negativos,
                        AVG(diferencia) as promedio_diferencia,
                        MAX(fecha_hora) as ultimo_ajuste
                        FROM historial_inventario 
                        WHERE tipo_movimiento = 'ajuste' 
                        AND fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$result_stats_ajustes = $conn->query($query_stats_ajustes);
$stats_ajustes = $result_stats_ajustes->fetch_assoc();

// Inicializar valores por defecto si no hay datos
if (!$stats_ajustes['total_ajustes']) {
    $stats_ajustes = [
        'total_ajustes' => 0,
        'ajustes_positivos' => 0,
        'ajustes_negativos' => 0,
        'promedio_diferencia' => 0,
        'ultimo_ajuste' => null
    ];
}
?>

<?php if ($mensaje): ?>
<div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($mensaje); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-xl-8">
        <div class="card shadow">
            <div class="card-header bg-gradient-warning text-dark">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="fas fa-balance-scale me-2"></i>Ajuste de Inventario Físico
                        </h5>
                        <small class="text-dark">Conciliación: Sistema vs Conteo Físico</small>
                    </div>
                    <div class="btn-group">
                        <a href="inventario.php" class="btn btn-sm btn-outline-dark">
                            <i class="fas fa-arrow-left me-1"></i>Volver
                        </a>
                        <?php if ($producto_info): ?>
                        <button class="btn btn-sm btn-dark" onclick="imprimirAjuste()">
                            <i class="fas fa-print me-1"></i>Imprimir
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Selector de producto con buscador avanzado -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-warning">
                            <div class="card-header bg-warning bg-opacity-10 py-2">
                                <h6 class="mb-0 text-warning">
                                    <i class="fas fa-search me-1"></i>Seleccionar Producto para Ajustar
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-warning text-dark">
                                        <i class="fas fa-box"></i>
                                    </span>
                                    <select class="form-select" id="selectProducto" 
                                            onchange="cargarProducto(this.value)">
                                        <option value="">Buscar producto para ajustar...</option>
                                        <?php foreach ($productos_array as $producto): 
                                            $stock_clase = 'bg-secondary';
                                            if ($producto['total_subpaquetes'] !== null) {
                                                if ($producto['total_subpaquetes'] < 20) {
                                                    $stock_clase = 'bg-danger';
                                                } elseif ($producto['total_subpaquetes'] < 50) {
                                                    $stock_clase = 'bg-warning';
                                                } else {
                                                    $stock_clase = 'bg-success';
                                                }
                                            }
                                        ?>
                                        <option value="<?php echo $producto['id']; ?>"
                                            <?php echo ($producto_id == $producto['id']) ? 'selected' : ''; ?>
                                            data-stock="<?php echo $producto['total_subpaquetes'] ?: 0; ?>"
                                            data-tiene-stock="<?php echo $producto['tiene_stock']; ?>">
                                            <?php echo htmlspecialchars($producto['codigo'] . ' - ' . $producto['nombre_color'] . 
                                                   ' (' . $producto['proveedor'] . ')'); ?>
                                            - Stock: <?php echo $producto['total_subpaquetes'] ?: '0'; ?> subp.
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-outline-warning" type="button" 
                                            onclick="buscarProductosModal()" data-bs-toggle="tooltip"
                                            title="Búsqueda avanzada">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" 
                                            onclick="window.location.reload()" data-bs-toggle="tooltip"
                                            title="Actualizar lista">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                                <div class="form-text text-muted mt-2">
                                    Seleccione un producto con diferencia entre el sistema y el conteo físico
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($producto_info): ?>
                <!-- Panel de comparación -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-primary">
                            <div class="card-header bg-primary bg-opacity-10 py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0 text-primary">
                                        <i class="fas fa-exchange-alt me-1"></i>Comparación: Sistema vs Conteo Físico
                                    </h6>
                                    <div>
                                        <span class="badge bg-info">ID: <?php echo $producto_id; ?></span>
                                        <span class="badge <?php echo $producto_info['tiene_stock'] ? 'bg-success' : 'bg-danger'; ?> ms-1">
                                            <?php echo $producto_info['tiene_stock'] ? 'DISPONIBLE' : 'AGOTADO'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Información del producto -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="border rounded p-3 bg-light">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <strong>Código:</strong><br>
                                                    <span class="text-success fw-bold fs-5"><?php echo htmlspecialchars($producto_info['codigo']); ?></span>
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>Producto:</strong><br>
                                                    <?php echo htmlspecialchars($producto_info['nombre_color']); ?>
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>Proveedor:</strong><br>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($producto_info['proveedor_nombre']); ?></span>
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>Categoría:</strong><br>
                                                    <?php echo htmlspecialchars($producto_info['categoria_nombre']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Sistema vs Físico -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card h-100 border-primary">
                                            <div class="card-header bg-primary text-white">
                                                <h6 class="mb-0 text-center">
                                                    <i class="fas fa-laptop me-2"></i>SISTEMA
                                                    <small class="float-end">Registro actual</small>
                                                </h6>
                                            </div>
                                            <div class="card-body text-center py-4">
                                                <div class="display-4 fw-bold text-primary mb-3">
                                                    <?php echo $inventario_info['total_subpaquetes']; ?>
                                                </div>
                                                <p class="text-muted mb-4">subpaquetes totales</p>
                                                
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="border rounded p-3">
                                                            <div class="fs-2 fw-bold"><?php echo $inventario_info['paquetes_completos']; ?></div>
                                                            <small class="text-muted">paquetes completos</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="border rounded p-3">
                                                            <div class="fs-2 fw-bold"><?php echo $inventario_info['subpaquetes_sueltos']; ?></div>
                                                            <small class="text-muted">subpaquetes sueltos</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mt-4">
                                                    <small class="text-muted">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        <?php echo $inventario_info['paquetes_completos']; ?> × 10 + 
                                                        <?php echo $inventario_info['subpaquetes_sueltos']; ?> = 
                                                        <?php echo $inventario_info['total_subpaquetes']; ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-light text-center">
                                                <small class="text-muted">
                                                    Última actualización: <?php echo date('d/m/Y H:i'); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card h-100 border-success">
                                            <div class="card-header bg-success text-white">
                                                <h6 class="mb-0 text-center">
                                                    <i class="fas fa-clipboard-check me-2"></i>CONTEO FÍSICO
                                                    <small class="float-end">Valor a ajustar</small>
                                                </h6>
                                            </div>
                                            <div class="card-body text-center py-4">
                                                <div class="display-4 fw-bold text-success mb-3" id="totalFisico">
                                                    <?php echo $inventario_info['total_subpaquetes']; ?>
                                                </div>
                                                <p class="text-muted mb-4">subpaquetes totales</p>
                                                
                                                <div class="row g-3">
                                                    <div class="col-6">
                                                        <label class="form-label fw-bold">Paquetes Completos</label>
                                                        <div class="input-group input-group-lg">
                                                            <input type="number" class="form-control text-center fw-bold" 
                                                                   name="paquetes_fisicos" id="paquetesFisicos"
                                                                   value="<?php echo $inventario_info['paquetes_completos']; ?>"
                                                                   min="0" onchange="calcularTotalFisico()" oninput="calcularTotalFisico()">
                                                            <span class="input-group-text">× 10</span>
                                                        </div>
                                                        <div class="form-text text-muted">
                                                            Paquetes cerrados de 10 unidades
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label fw-bold">Subpaquetes Sueltos</label>
                                                        <div class="input-group input-group-lg">
                                                            <input type="number" class="form-control text-center fw-bold" 
                                                                   name="subpaquetes_fisicos" id="subpaquetesFisicos"
                                                                   value="<?php echo $inventario_info['subpaquetes_sueltos']; ?>"
                                                                   min="0" onchange="calcularTotalFisico()" oninput="calcularTotalFisico()">
                                                            <span class="input-group-text">unid.</span>
                                                        </div>
                                                        <div class="form-text text-muted">
                                                            Se ajusta automáticamente
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mt-4">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calculator me-1"></i>
                                                        <span id="calculoFisico">
                                                            <?php echo $inventario_info['paquetes_completos']; ?> × 10 + 
                                                            <?php echo $inventario_info['subpaquetes_sueltos']; ?> = 
                                                            <?php echo $inventario_info['total_subpaquetes']; ?>
                                                        </span>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-light text-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>
                                                    Responsable: <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Panel de diferencia -->
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <div class="card border-0 shadow" id="cardDiferencia">
                                            <div class="card-body text-center py-4">
                                                <h5 class="mb-3">
                                                    <i class="fas fa-calculator me-2"></i>Resultado del Ajuste
                                                </h5>
                                                <div class="row align-items-center">
                                                    <div class="col-md-4">
                                                        <div class="fs-1 fw-bold text-primary">
                                                            <?php echo $inventario_info['total_subpaquetes']; ?>
                                                        </div>
                                                        <small class="text-muted">Sistema</small>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <div class="fs-1">
                                                            <i class="fas fa-arrow-right text-muted"></i>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="fs-1 fw-bold text-success" id="totalFisicoGrande">
                                                            <?php echo $inventario_info['total_subpaquetes']; ?>
                                                        </div>
                                                        <small class="text-muted">Físico</small>
                                                    </div>
                                                </div>
                                                
                                                <div class="mt-4">
                                                    <div class="alert alert-secondary" id="alertDiferencia">
                                                        <h2 class="mb-2" id="diferenciaText">0</h2>
                                                        <p class="mb-0" id="diferenciaDesc">Sin diferencia</p>
                                                    </div>
                                                    
                                                    <div class="row mt-3">
                                                        <div class="col-md-6">
                                                            <div class="border rounded p-2">
                                                                <small class="text-muted">Impacto en valor:</small>
                                                                <div class="fw-bold" id="impactoValor">Bs 0.00</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="border rounded p-2">
                                                                <small class="text-muted">Nuevo estado:</small>
                                                                <div class="fw-bold" id="nuevoEstado">SIN CAMBIOS</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Formulario completo de ajuste -->
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <div class="card border-warning">
                                            <div class="card-header bg-warning bg-opacity-10 py-2">
                                                <h6 class="mb-0 text-warning">
                                                    <i class="fas fa-edit me-1"></i>Detalles del Ajuste
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <form method="POST" action="" id="formAjusteCompleto">
                                                    <input type="hidden" name="action" value="ajustar">
                                                    <input type="hidden" name="producto_id" value="<?php echo $producto_id; ?>">
                                                    <input type="hidden" name="paquetes_fisicos" id="inputPaquetesFisicos" 
                                                           value="<?php echo $inventario_info['paquetes_completos']; ?>">
                                                    <input type="hidden" name="subpaquetes_fisicos" id="inputSubpaquetesFisicos" 
                                                           value="<?php echo $inventario_info['subpaquetes_sueltos']; ?>">
                                                    
                                                    <div class="row g-3">
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">Tipo de Ajuste *</label>
                                                            <select class="form-select" name="tipo_ajuste" required>
                                                                <option value="fisico">Conteo físico</option>
                                                                <option value="devolucion">Devolución de cliente</option>
                                                                <option value="merma">Merma o pérdida</option>
                                                                <option value="error">Error de registro</option>
                                                                <option value="traslado">Traslado entre almacenes</option>
                                                                <option value="donacion">Donación o muestra</option>
                                                                <option value="otro">Otro</option>
                                                            </select>
                                                            <div class="form-text">
                                                                Seleccione la razón principal del ajuste
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">Fecha del Conteo</label>
                                                            <input type="date" class="form-control" name="fecha_conteo" 
                                                                   value="<?php echo date('Y-m-d'); ?>">
                                                            <div class="form-text">
                                                                Fecha en que se realizó el conteo físico
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">Responsable del Conteo</label>
                                                            <input type="text" class="form-control" name="responsable_conteo" 
                                                                   value="<?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>">
                                                            <div class="form-text">
                                                                Persona que realizó el conteo físico
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mt-3">
                                                        <div class="col-md-12">
                                                            <label class="form-label fw-bold">Observaciones Detalladas *</label>
                                                            <textarea class="form-control" name="observaciones" rows="4" required
                                                                      placeholder="Describa detalladamente la razón del ajuste, condiciones del producto, ubicación, personas involucradas, etc."></textarea>
                                                            <div class="form-text">
                                                                <i class="fas fa-lightbulb text-warning"></i> 
                                                                Sea específico: "Conteo físico realizado por [nombre] en [ubicación] encontró diferencia por [razón]"
                                                            </div>
                                                            <div class="invalid-feedback">
                                                                Las observaciones son obligatorias
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mt-4">
                                                        <div class="col-md-12">
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                                <strong>Confirmación requerida:</strong> Este ajuste modificará permanentemente 
                                                                el inventario del sistema. Verifique los valores antes de proceder.
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mt-3">
                                                        <div class="col-md-12">
                                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                                <button type="button" class="btn btn-outline-secondary btn-lg me-md-2" onclick="resetearFormulario()">
                                                                    <i class="fas fa-undo me-2"></i>Restablecer
                                                                </button>
                                                                <button type="submit" class="btn btn-warning btn-lg px-5" id="btnAjustar">
                                                                    <i class="fas fa-adjust me-2"></i>Aplicar Ajuste
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Mensaje cuando no hay producto seleccionado -->
                <div class="text-center text-muted p-5">
                    <div class="mb-4">
                        <i class="fas fa-balance-scale fa-4x text-muted"></i>
                    </div>
                    <h4 class="mb-3">Seleccione un producto para ajustar inventario</h4>
                    <p class="lead mb-4">Use el selector de arriba o busque un producto con diferencia entre sistema y físico</p>
                    <button class="btn btn-warning btn-lg" onclick="buscarProductosModal()">
                        <i class="fas fa-search me-2"></i>Buscar Producto
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4">
        <!-- Estadísticas de ajustes -->
        <div class="card shadow mb-4">
            <div class="card-header bg-gradient-info text-white">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-1"></i>Estadísticas de Ajustes
                    <span class="float-end badge bg-white text-info">
                        30 días
                    </span>
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="border rounded p-3">
                            <div class="fs-3 fw-bold text-primary"><?php echo $stats_ajustes['total_ajustes']; ?></div>
                            <small class="text-muted">Total ajustes</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="border rounded p-3">
                            <div class="fs-3 fw-bold text-success"><?php echo $stats_ajustes['ajustes_positivos']; ?></div>
                            <small class="text-muted">Aumentos</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3">
                            <div class="fs-3 fw-bold text-danger"><?php echo $stats_ajustes['ajustes_negativos']; ?></div>
                            <small class="text-muted">Disminuciones</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3">
                            <div class="fs-3 fw-bold text-warning">
                                <?php echo number_format($stats_ajustes['promedio_diferencia'], 1); ?>
                            </div>
                            <small class="text-muted">Promedio dif.</small>
                        </div>
                    </div>
                </div>
                
                <?php if ($stats_ajustes['total_ajustes'] > 0): ?>
                <div class="mt-3">
                    <div class="progress" style="height: 20px;">
                        <?php 
                        $total = $stats_ajustes['total_ajustes'];
                        $positivos = $stats_ajustes['ajustes_positivos'];
                        $negativos = $stats_ajustes['ajustes_negativos'];
                        $porc_positivos = $total > 0 ? ($positivos / $total) * 100 : 0;
                        $porc_negativos = $total > 0 ? ($negativos / $total) * 100 : 0;
                        ?>
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo $porc_positivos; ?>%"
                             aria-valuenow="<?php echo $porc_positivos; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo round($porc_positivos); ?>%
                        </div>
                        <div class="progress-bar bg-danger" role="progressbar" 
                             style="width: <?php echo $porc_negativos; ?>%"
                             aria-valuenow="<?php echo $porc_negativos; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo round($porc_negativos); ?>%
                        </div>
                    </div>
                    <div class="text-center small text-muted mt-2">
                        Distribución: <span class="text-success">Aumentos</span> / <span class="text-danger">Disminuciones</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Historial de ajustes recientes -->
        <div class="card shadow mb-4">
            <div class="card-header bg-gradient-primary text-white">
                <h6 class="mb-0">
                    <i class="fas fa-history me-1"></i>Últimos Ajustes
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" style="max-height: 350px; overflow-y: auto;">
                    <?php
                    $query_ajustes = "SELECT p.codigo, p.nombre_color, hi.diferencia, 
                                     hi.fecha_hora, u.nombre as usuario, hi.observaciones,
                                     hi.referencia
                                     FROM historial_inventario hi
                                     JOIN productos p ON hi.producto_id = p.id
                                     JOIN usuarios u ON hi.usuario_id = u.id
                                     WHERE hi.tipo_movimiento = 'ajuste'
                                     ORDER BY hi.fecha_hora DESC
                                     LIMIT 8";
                    $result_ajustes = $conn->query($query_ajustes);
                    
                    if ($result_ajustes && $result_ajustes->num_rows > 0):
                        while ($ajuste = $result_ajustes->fetch_assoc()):
                            $diferencia = $ajuste['diferencia'];
                            $color = $diferencia > 0 ? 'success' : ($diferencia < 0 ? 'danger' : 'secondary');
                            $icon = $diferencia > 0 ? 'fa-arrow-up' : ($diferencia < 0 ? 'fa-arrow-down' : 'fa-equals');
                            
                            // Determinar tipo según referencia
                            $tipo_badge = 'bg-secondary';
                            if (strpos($ajuste['referencia'], 'DEVOLUCION') !== false) $tipo_badge = 'bg-info';
                            elseif (strpos($ajuste['referencia'], 'MERMA') !== false) $tipo_badge = 'bg-danger';
                            elseif (strpos($ajuste['referencia'], 'FISICO') !== false) $tipo_badge = 'bg-warning';
                    ?>
                    <div class="list-group-item border-bottom py-3">
                        <div class="d-flex w-100 justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1">
                                    <i class="fas <?php echo $icon; ?> text-<?php echo $color; ?> me-2"></i>
                                    <?php echo htmlspecialchars($ajuste['codigo']); ?>
                                </h6>
                                <small class="text-muted d-block">
                                    <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($ajuste['nombre_color']); ?>
                                </small>
                                <small class="text-muted">
                                    <span class="badge <?php echo $tipo_badge; ?>">
                                        <?php echo htmlspecialchars($ajuste['referencia']); ?>
                                    </span>
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-<?php echo $color; ?> fs-6">
                                    <?php echo ($diferencia > 0 ? '+' : '') . $diferencia; ?>
                                </span>
                                <div class="mt-1">
                                    <small class="text-muted"><?php echo date('d/m/Y', strtotime($ajuste['fecha_hora'])); ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small>
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($ajuste['usuario']); ?>
                            </small>
                            <?php if ($ajuste['observaciones']): ?>
                            <button class="btn btn-sm btn-outline-info py-0" 
                                    onclick="mostrarObservacion('<?php echo htmlspecialchars(addslashes($ajuste['observaciones']), ENT_QUOTES); ?>')"
                                    data-bs-toggle="tooltip" title="Ver observaciones">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                        endwhile;
                    else:
                    ?>
                    <div class="text-center text-muted p-5">
                        <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                        <p class="mb-0">No hay ajustes recientes</p>
                    </div>
                    <?php endif; ?>
                </div>
                <!--<div class="card-footer bg-light text-center py-2">
                    <a href="historial_inventario.php?tipo=ajuste" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-list me-1"></i>Ver Historial Completo
                    </a>
                </div>-->
            </div>
        </div>
        
        <!-- Guía rápida y ayuda -->
        <div class="card shadow">
            <div class="card-header bg-gradient-secondary text-white">
                <h6 class="mb-0">
                    <i class="fas fa-question-circle me-1"></i>Guía de Procedimiento
                </h6>
            </div>
            <div class="card-body">
                <div class="accordion" id="accordionGuia">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#collapseUno">
                                <i class="fas fa-check-circle text-success me-2"></i>Pasos para Ajustar
                            </button>
                        </h2>
                        <div id="collapseUno" class="accordion-collapse collapse" 
                             data-bs-parent="#accordionGuia">
                            <div class="accordion-body">
                                <ol class="mb-0">
                                    <li>Realizar conteo físico completo</li>
                                    <li>Verificar ubicación correcta</li>
                                    <li>Comparar con sistema</li>
                                    <li>Registrar diferencia y motivo</li>
                                    <li>Aplicar ajuste con supervisión</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#collapseDos">
                                <i class="fas fa-exclamation-triangle text-warning me-2"></i>Causas Comunes
                            </button>
                        </h2>
                        <div id="collapseDos" class="accordion-collapse collapse" 
                             data-bs-parent="#accordionGuia">
                            <div class="accordion-body">
                                <ul class="mb-0">
                                    <li>Ventas no registradas</li>
                                    <li>Errores en ingreso de stock</li>
                                    <li>Mermas o deterioro</li>
                                    <li>Conteo físico incorrecto</li>
                                    <li>Traslados no registrados</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#collapseTres">
                                <i class="fas fa-user-shield text-danger me-2"></i>Responsabilidades
                            </button>
                        </h2>
                        <div id="collapseTres" class="accordion-collapse collapse" 
                             data-bs-parent="#accordionGuia">
                            <div class="accordion-body">
                                <ul class="mb-0">
                                    <li>Solo administradores pueden ajustar</li>
                                    <li>Documentar siempre la razón</li>
                                    <li>Notificar al supervisor</li>
                                    <li>Revisar historial previo</li>
                                    <li>Mantener auditoría completa</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Recomendación:</strong> Realice ajustes periódicos 
                        (semanal/mensual) para mantener inventario preciso.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de búsqueda de productos mejorado -->
<div class="modal fade" id="modalBuscarProductos" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-gradient-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-search me-2"></i>Buscar Producto para Ajuste
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Filtros -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control" id="filtroCodigo" 
                                       placeholder="Ej: ROJO01, AZUL02">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="filtroNombre" 
                                       placeholder="Buscar por nombre...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Proveedor</label>
                                <select class="form-select" id="filtroProveedor">
                                    <option value="">Todos</option>
                                    <?php
                                    $query_proveedores = "SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre";
                                    $result_proveedores = $conn->query($query_proveedores);
                                    while ($proveedor = $result_proveedores->fetch_assoc()):
                                    ?>
                                    <option value="<?php echo $proveedor['id']; ?>">
                                        <?php echo htmlspecialchars($proveedor['nombre']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Stock en Sistema</label>
                                <select class="form-select" id="filtroStock">
                                    <option value="">Todos</option>
                                    <option value="sin_stock">Sin stock</option>
                                    <option value="critico">Crítico (< 20)</option>
                                    <option value="bajo">Bajo (20-49)</option>
                                    <option value="normal">Normal (≥ 50)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ordenar por</label>
                                <select class="form-select" id="filtroOrden">
                                    <option value="codigo">Código</option>
                                    <option value="nombre">Nombre</option>
                                    <option value="proveedor">Proveedor</option>
                                    <option value="stock_asc">Stock (menor a mayor)</option>
                                    <option value="stock_desc">Stock (mayor a menor)</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between">
                                    <button class="btn btn-outline-secondary" onclick="limpiarFiltrosModal()">
                                        <i class="fas fa-eraser me-1"></i>Limpiar filtros
                                    </button>
                                    <button class="btn btn-warning" onclick="filtrarProductosModal()">
                                        <i class="fas fa-search me-1"></i>Buscar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de resultados -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="tablaProductosModal">
                        <thead class="table-warning">
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Proveedor</th>
                                <th class="text-center">Stock Sistema</th>
                                <th class="text-center">Disponible</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyProductosModal">
                            <?php foreach ($productos_para_modal as $producto): 
                                $stock_clase = 'bg-secondary';
                                $stock_texto = '0';
                                if ($producto['total_subpaquetes'] !== null) {
                                    if ($producto['total_subpaquetes'] < 20) {
                                        $stock_clase = 'bg-danger';
                                    } elseif ($producto['total_subpaquetes'] < 50) {
                                        $stock_clase = 'bg-warning';
                                    } else {
                                        $stock_clase = 'bg-success';
                                    }
                                    $stock_texto = $producto['total_subpaquetes'];
                                }
                            ?>
                            <tr data-codigo="<?php echo htmlspecialchars($producto['codigo']); ?>"
                                data-nombre="<?php echo htmlspecialchars(strtolower($producto['nombre_color'])); ?>"
                                data-proveedor-id="<?php echo $producto['proveedor_id']; ?>"
                                data-stock="<?php echo $producto['total_subpaquetes'] ?: 0; ?>"
                                data-tiene-stock="<?php echo $producto['tiene_stock']; ?>">
                                <td>
                                    <strong class="text-primary"><?php echo htmlspecialchars($producto['codigo']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($producto['nombre_color']); ?></td>
                                <td><?php echo htmlspecialchars($producto['proveedor']); ?></td>
                                <td class="text-center">
                                    <span class="badge <?php echo $stock_clase; ?> fs-6">
                                        <?php echo $stock_texto; ?>
                                    </span>
                                    <?php if ($producto['total_subpaquetes'] > 0): ?>
                                    <div class="small text-muted mt-1">
                                        <?php echo floor($producto['total_subpaquetes'] / 10); ?> pqt + 
                                        <?php echo $producto['total_subpaquetes'] % 10; ?> sueltos
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($producto['tiene_stock']): ?>
                                    <span class="badge bg-success">SÍ</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">NO</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-warning btn-seleccionar-producto" 
                                            data-producto-id="<?php echo $producto['id']; ?>"
                                            data-bs-toggle="tooltip" title="Ajustar este producto">
                                        <i class="fas fa-adjust me-1"></i>Ajustar
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Mostrando <span id="contadorResultados"><?php echo count($productos_para_modal); ?></span> productos
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver observaciones -->
<div class="modal fade" id="modalObservaciones" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-comment-alt me-2"></i>Observaciones del Ajuste
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="textoObservaciones" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales
let productosData = [];
let precioMenor = <?php echo $producto_info['precio_menor'] ?? 0; ?>;

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Cargar datos para modal
    cargarDatosProductos();
    
    // Inicializar cálculos si hay producto
    <?php if ($producto_id): ?>
    calcularTotalFisico();
    <?php endif; ?>
    
    // Configurar validación
    const form = document.getElementById('formAjusteCompleto');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
            
            // Confirmación adicional
            if (form.checkValidity()) {
                const diferencia = calcularDiferencia();
                if (diferencia !== 0 && !confirm(`¿Está seguro de aplicar el ajuste con diferencia de ${diferencia > 0 ? '+' : ''}${diferencia} subpaquetes?`)) {
                    e.preventDefault();
                }
            }
        });
    }
    
    // Agregar eventos a los botones de selección en el modal
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-seleccionar-producto')) {
            const button = e.target.closest('.btn-seleccionar-producto');
            const productoId = button.getAttribute('data-producto-id');
            seleccionarProductoModal(productoId);
        }
    });
});

function cargarProducto(productoId) {
    if (productoId) {
        window.location.href = 'ajustar_inventario.php?producto_id=' + productoId;
    }
}

function calcularTotalFisico() {
    let paquetes = parseInt(document.getElementById('paquetesFisicos').value) || 0;
    let sueltos = parseInt(document.getElementById('subpaquetesFisicos').value) || 0;
    
    // Ajustar sueltos si son 10 o más
    if (sueltos >= 10) {
        paquetes += Math.floor(sueltos / 10);
        sueltos = sueltos % 10;
        document.getElementById('paquetesFisicos').value = paquetes;
        document.getElementById('subpaquetesFisicos').value = sueltos;
    }
    
    let totalFisico = (paquetes * 10) + sueltos;
    
    // Actualizar visualizaciones
    document.getElementById('totalFisico').textContent = totalFisico.toLocaleString('es-ES');
    document.getElementById('totalFisicoGrande').textContent = totalFisico.toLocaleString('es-ES');
    document.getElementById('calculoFisico').textContent = `${paquetes} × 10 + ${sueltos} = ${totalFisico}`;
    
    // Actualizar inputs ocultos
    document.getElementById('inputPaquetesFisicos').value = paquetes;
    document.getElementById('inputSubpaquetesFisicos').value = sueltos;
    
    // Calcular y mostrar diferencia
    calcularDiferencia();
}

function calcularDiferencia() {
    let paquetes = parseInt(document.getElementById('paquetesFisicos').value) || 0;
    let sueltos = parseInt(document.getElementById('subpaquetesFisicos').value) || 0;
    let totalFisico = (paquetes * 10) + sueltos;
    let totalSistema = <?php echo $inventario_info['total_subpaquetes'] ?? 0; ?>;
    let diferencia = totalFisico - totalSistema;
    
    // Actualizar texto de diferencia
    document.getElementById('diferenciaText').textContent = (diferencia >= 0 ? '+' : '') + diferencia;
    
    // Actualizar alerta
    let alert = document.getElementById('alertDiferencia');
    let desc = document.getElementById('diferenciaDesc');
    
    if (diferencia > 0) {
        alert.className = 'alert alert-success';
        desc.textContent = 'Sobrante en conteo físico';
    } else if (diferencia < 0) {
        alert.className = 'alert alert-danger';
        desc.textContent = 'Faltante en conteo físico';
    } else {
        alert.className = 'alert alert-secondary';
        desc.textContent = 'Sin diferencia - No se requiere ajuste';
    }
    
    // Actualizar card de diferencia
    let card = document.getElementById('cardDiferencia');
    if (diferencia !== 0) {
        card.classList.add('border', 'border-3');
        if (diferencia > 0) {
            card.classList.add('border-success');
            card.classList.remove('border-danger');
        } else {
            card.classList.add('border-danger');
            card.classList.remove('border-success');
        }
        card.classList.remove('border-0');
    } else {
        card.classList.remove('border', 'border-3', 'border-success', 'border-danger');
        card.classList.add('border-0');
    }
    
    // Calcular impacto en valor
    let impactoValor = diferencia * precioMenor;
    document.getElementById('impactoValor').textContent = 
        'Bs ' + impactoValor.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Determinar nuevo estado
    let nuevoEstado = document.getElementById('nuevoEstado');
    let nuevoTotal = totalSistema + diferencia;
    
    if (nuevoTotal <= 0) {
        nuevoEstado.textContent = 'AGOTADO';
        nuevoEstado.className = 'fw-bold text-danger';
    } else if (nuevoTotal < 20) {
        nuevoEstado.textContent = 'CRÍTICO';
        nuevoEstado.className = 'fw-bold text-danger';
    } else if (nuevoTotal < 50) {
        nuevoEstado.textContent = 'BAJO';
        nuevoEstado.className = 'fw-bold text-warning';
    } else {
        nuevoEstado.textContent = 'NORMAL';
        nuevoEstado.className = 'fw-bold text-success';
    }
    
    // Habilitar/deshabilitar botón de ajuste
    let btnAjustar = document.getElementById('btnAjustar');
    if (btnAjustar) {
        btnAjustar.disabled = (diferencia === 0);
        btnAjustar.title = diferencia === 0 ? 'No hay diferencia para ajustar' : 'Aplicar ajuste';
    }
    
    return diferencia;
}

function resetearFormulario() {
    <?php if ($producto_id): ?>
    document.getElementById('paquetesFisicos').value = <?php echo $inventario_info['paquetes_completos']; ?>;
    document.getElementById('subpaquetesFisicos').value = <?php echo $inventario_info['subpaquetes_sueltos']; ?>;
    calcularTotalFisico();
    <?php endif; ?>
}

function buscarProductosModal() {
    const modal = new bootstrap.Modal(document.getElementById('modalBuscarProductos'));
    modal.show();
}

function cargarDatosProductos() {
    const filas = document.querySelectorAll('#tbodyProductosModal tr');
    
    productosData = Array.from(filas).map(fila => {
        const btn = fila.querySelector('.btn-seleccionar-producto');
        if (!btn) return null;
        
        return {
            id: btn.getAttribute('data-producto-id'),
            codigo: fila.getAttribute('data-codigo'),
            nombre: fila.getAttribute('data-nombre'),
            proveedorId: fila.getAttribute('data-proveedor-id'),
            stock: parseInt(fila.getAttribute('data-stock')),
            tieneStock: parseInt(fila.getAttribute('data-tiene-stock')),
            elemento: fila
        };
    }).filter(p => p !== null);
}

function filtrarProductosModal() {
    const filtroCodigo = document.getElementById('filtroCodigo').value.toLowerCase();
    const filtroNombre = document.getElementById('filtroNombre').value.toLowerCase();
    const filtroProveedor = document.getElementById('filtroProveedor').value;
    const filtroStock = document.getElementById('filtroStock').value;
    const filtroOrden = document.getElementById('filtroOrden').value;
    
    let resultados = productosData.filter(producto => {
        // Filtro por código
        if (filtroCodigo && !producto.codigo.toLowerCase().includes(filtroCodigo)) {
            return false;
        }
        
        // Filtro por nombre
        if (filtroNombre && !producto.nombre.includes(filtroNombre)) {
            return false;
        }
        
        // Filtro por proveedor
        if (filtroProveedor && producto.proveedorId !== filtroProveedor) {
            return false;
        }
        
        // Filtro por stock
        switch(filtroStock) {
            case 'sin_stock':
                if (producto.stock > 0) return false;
                break;
            case 'critico':
                if (producto.stock >= 20 || producto.stock === 0) return false;
                break;
            case 'bajo':
                if (producto.stock < 20 || producto.stock >= 50) return false;
                break;
            case 'normal':
                if (producto.stock < 50) return false;
                break;
        }
        
        return true;
    });
    
    // Ordenar resultados
    resultados.sort((a, b) => {
        switch(filtroOrden) {
            case 'codigo':
                return a.codigo.localeCompare(b.codigo);
            case 'nombre':
                return a.nombre.localeCompare(b.nombre);
            case 'proveedor':
                return a.proveedorId - b.proveedorId;
            case 'stock_asc':
                return a.stock - b.stock;
            case 'stock_desc':
                return b.stock - a.stock;
            default:
                return 0;
        }
    });
    
    // Mostrar/ocultar filas
    productosData.forEach(producto => {
        producto.elemento.style.display = 'none';
    });
    
    resultados.forEach(producto => {
        producto.elemento.style.display = '';
    });
    
    // Actualizar contador
    document.getElementById('contadorResultados').textContent = resultados.length;
}

function limpiarFiltrosModal() {
    document.getElementById('filtroCodigo').value = '';
    document.getElementById('filtroNombre').value = '';
    document.getElementById('filtroProveedor').value = '';
    document.getElementById('filtroStock').value = '';
    document.getElementById('filtroOrden').value = 'codigo';
    
    filtrarProductosModal();
}

function seleccionarProductoModal(productoId) {
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalBuscarProductos'));
    if (modal) {
        modal.hide();
    }
    // Pequeño delay para que se cierre el modal antes de redirigir
    setTimeout(() => {
        cargarProducto(productoId);
    }, 300);
}

function mostrarObservacion(texto) {
    document.getElementById('textoObservaciones').textContent = texto;
    const modal = new bootstrap.Modal(document.getElementById('modalObservaciones'));
    modal.show();
}

function imprimirAjuste() {
    <?php if (!$producto_id): ?>
    alert('Primero seleccione un producto');
    return;
    <?php endif; ?>
    
    window.print();
}
</script>

<style>
/* Estilos específicos para ajuste de inventario */
.card {
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-2px);
}

.display-4 {
    font-size: 3.5rem;
    font-weight: 700;
}

.border-warning {
    border-color: #ffc107 !important;
}

.border-success {
    border-color: #28a745 !important;
}

.border-primary {
    border-color: #007bff !important;
}

.bg-gradient-warning {
    background: linear-gradient(135deg, #ffc107, #e0a800) !important;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #007bff, #0056b3) !important;
}

.bg-gradient-info {
    background: linear-gradient(135deg, #17a2b8, #138496) !important;
}

.bg-gradient-secondary {
    background: linear-gradient(135deg, #6c757d, #545b62) !important;
}

.input-group-lg .form-control,
.input-group-lg .input-group-text {
    height: calc(3.5rem + 2px);
    font-size: 1.25rem;
}

.accordion-button:not(.collapsed) {
    background-color: rgba(255, 193, 7, 0.1);
    color: #856404;
}

.list-group-item:hover {
    background-color: rgba(255, 193, 7, 0.05);
}

@media (max-width: 768px) {
    .display-4 {
        font-size: 2.5rem;
    }
    
    .fs-2 {
        font-size: 1.5rem !important;
    }
    
    .input-group-lg .form-control,
    .input-group-lg .input-group-text {
        height: calc(2.5rem + 2px);
        font-size: 1rem;
    }
}

@media print {
    .btn, .modal, .card-footer, .list-group-item .btn,
    .accordion, .form-text, .input-group-text,
    #selectProducto, .col-xl-4 {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .card-header {
        background-color: #fff !important;
        color: #000 !important;
        border-bottom: 2px solid #000 !important;
    }
    
    .col-xl-8 {
        width: 100% !important;
    }
}
</style>

<?php require_once 'footer.php'; ?>