<?php
session_start();

$titulo_pagina = "Ingresar Stock";
$icono_titulo = "fas fa-boxes";
$breadcrumb = [
    ['text' => 'Inventario', 'link' => 'inventario.php', 'active' => false],
    ['text' => 'Ingresar Stock', 'link' => '#', 'active' => true]
];

require_once 'header.php';
require_once 'funciones.php';

// Verificar que sea administrador
verificarRol(['administrador']);

$producto_id = $_GET['producto_id'] ?? null;
$mensaje = '';
$tipo_mensaje = '';

// Procesar ingreso de stock
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'ingresar') {
    try {
        $producto_id = intval($_POST['producto_id']);
        $paquetes_completos = intval($_POST['paquetes_completos']);
        $subpaquetes_sueltos = intval($_POST['subpaquetes_sueltos']);
        $costo_paquete = floatval($_POST['costo_paquete']);
        $ubicacion = limpiar($_POST['ubicacion']);
        $observaciones = limpiar($_POST['observaciones']);
        $subpaquetes_por_paquete = intval($_POST['subpaquetes_por_paquete'] ?? 10);
        
        // Validar datos
        if ($paquetes_completos < 0 || $subpaquetes_sueltos < 0 || $costo_paquete < 0) {
            throw new Exception("Los valores no pueden ser negativos");
        }
        
        if ($subpaquetes_sueltos >= $subpaquetes_por_paquete) {
            // Convertir sueltos a paquetes completos si excede el límite
            $paquetes_completos += floor($subpaquetes_sueltos / $subpaquetes_por_paquete);
            $subpaquetes_sueltos = $subpaquetes_sueltos % $subpaquetes_por_paquete;
        }
        
        $conn->begin_transaction();
        
        // Verificar si ya existe inventario para este producto
        $query_check = "SELECT id, paquetes_completos, subpaquetes_sueltos, subpaquetes_por_paquete 
                       FROM inventario WHERE producto_id = ?";
        $stmt_check = $conn->prepare($query_check);
        $stmt_check->bind_param("i", $producto_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        $paquetes_anteriores = 0;
        $subpaquetes_anteriores = 0;
        $subpaquetes_por_paquete_actual = $subpaquetes_por_paquete;
        $total_subpaquetes = ($paquetes_completos * $subpaquetes_por_paquete) + $subpaquetes_sueltos;
        
        if ($result_check->num_rows > 0) {
            // Obtener valores actuales para el historial
            $inventario_actual = $result_check->fetch_assoc();
            $paquetes_anteriores = $inventario_actual['paquetes_completos'];
            $subpaquetes_anteriores = $inventario_actual['subpaquetes_sueltos'];
            $subpaquetes_por_paquete_actual = $inventario_actual['subpaquetes_por_paquete'];
            
            // Actualizar inventario existente
            $query_update = "UPDATE inventario SET 
                            paquetes_completos = paquetes_completos + ?,
                            subpaquetes_sueltos = subpaquetes_sueltos + ?,
                            subpaquetes_por_paquete = COALESCE(?, subpaquetes_por_paquete),
                            costo_paquete = COALESCE(?, costo_paquete),
                            ubicacion = COALESCE(?, ubicacion),
                            fecha_ultimo_ingreso = CURDATE(),
                            usuario_registro = ?,
                            observaciones = CONCAT(
                                COALESCE(observaciones, ''),
                                IF(COALESCE(observaciones, '') != '', ' | ', ''),
                                ?
                            )
                            WHERE producto_id = ?";
            $stmt_update = $conn->prepare($query_update);
            $stmt_update->bind_param("iiidsisi", 
                $paquetes_completos, 
                $subpaquetes_sueltos, 
                $subpaquetes_por_paquete, 
                $costo_paquete, 
                $ubicacion, 
                $_SESSION['usuario_id'], 
                $observaciones, 
                $producto_id
            );
            
            if (!$stmt_update->execute()) {
                throw new Exception("Error al actualizar stock: " . $stmt_update->error);
            }
            
            $mensaje = "Stock actualizado exitosamente";
        } else {
            // Crear nuevo registro de inventario
            $query_insert = "INSERT INTO inventario 
                            (producto_id, paquetes_completos, subpaquetes_sueltos, 
                             subpaquetes_por_paquete, costo_paquete, ubicacion, 
                             fecha_ultimo_ingreso, usuario_registro, observaciones)
                            VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)";
            $stmt_insert = $conn->prepare($query_insert);
            $stmt_insert->bind_param("iiidisi", 
                $producto_id, 
                $paquetes_completos, 
                $subpaquetes_sueltos, 
                $subpaquetes_por_paquete, 
                $costo_paquete, 
                $ubicacion, 
                $_SESSION['usuario_id'], 
                $observaciones
            );
            
            if (!$stmt_insert->execute()) {
                throw new Exception("Error al ingresar stock: " . $stmt_insert->error);
            }
            
            $mensaje = "Stock ingresado exitosamente";
        }
        
        // Registrar en historial de inventario
        $query_historial = "INSERT INTO historial_inventario 
                           (producto_id, tipo_movimiento, 
                            paquetes_anteriores, subpaquetes_anteriores,
                            paquetes_nuevos, subpaquetes_nuevos,
                            diferencia, referencia, 
                            fecha_hora, usuario_id, observaciones)
                           VALUES (?, 'ingreso', ?, ?, 
                                   ?, ?, ?, 'INGRESO_MANUAL', 
                                   NOW(), ?, ?)";
        $stmt_historial = $conn->prepare($query_historial);
        
        // Calcular nuevos valores
        $paquetes_nuevos = $paquetes_anteriores + $paquetes_completos;
        $subpaquetes_nuevos = $subpaquetes_anteriores + $subpaquetes_sueltos;
        
        $stmt_historial->bind_param("iiiiiisi", 
            $producto_id,
            $paquetes_anteriores,
            $subpaquetes_anteriores,
            $paquetes_nuevos,
            $subpaquetes_nuevos,
            $total_subpaquetes,
            $_SESSION['usuario_id'],
            $observaciones
        );
        
        if (!$stmt_historial->execute()) {
            throw new Exception("Error al registrar en historial: " . $stmt_historial->error);
        }
        
        // El saldo del proveedor por la compra de inventario lo gestiona el trigger
        // `after_inventario_insert` / `after_inventario_update`. No necesitamos
        // actualizarlo manualmente aquí.
        
        $conn->commit();
        $tipo_mensaje = "success";
        
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Obtener información del producto si se seleccionó uno
$producto_info = null;
$subpaquetes_por_paquete = 10; // Valor por defecto
if ($producto_id) {
    $query_producto = "SELECT p.*, pr.nombre as proveedor_nombre, pr.id as proveedor_id,
                      c.nombre as categoria_nombre,
                      i.paquetes_completos, i.subpaquetes_sueltos, i.total_subpaquetes,
                      i.costo_paquete, i.ubicacion, i.subpaquetes_por_paquete
                      FROM productos p
                      JOIN proveedores pr ON p.proveedor_id = pr.id
                      JOIN categorias c ON p.categoria_id = c.id
                      LEFT JOIN inventario i ON p.id = i.producto_id
                      WHERE p.id = ? AND p.activo = 1";
    $stmt = $conn->prepare($query_producto);
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $producto_info = $result->fetch_assoc();
        $subpaquetes_por_paquete = $producto_info['subpaquetes_por_paquete'] ?? 10;
    } else {
        $mensaje = "Producto no encontrado o está inactivo";
        $tipo_mensaje = "warning";
        $producto_id = null;
    }
}

// Obtener lista de productos para el selector
$query_productos = "SELECT p.id, p.codigo, p.nombre_color, pr.nombre as proveedor,
                   i.total_subpaquetes, i.paquetes_completos, i.subpaquetes_sueltos,
                   i.subpaquetes_por_paquete
                   FROM productos p
                   JOIN proveedores pr ON p.proveedor_id = pr.id
                   LEFT JOIN inventario i ON p.id = i.producto_id
                   WHERE p.activo = 1
                   ORDER BY pr.nombre, p.codigo";
$result_productos = $conn->query($query_productos);
?>

<?php if ($mensaje): ?>
<div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
    <?php echo $mensaje; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-gradient-success text-white">
                <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Ingresar Stock de Productos</h5>
            </div>
            <div class="card-body">
                <!-- Selector de producto -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-primary">
                            <div class="card-body">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-search me-1"></i>Seleccionar Producto
                                </h6>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-primary text-white">
                                        <i class="fas fa-box"></i>
                                    </span>
                                    <select class="form-select" id="selectProducto" 
                                            onchange="cargarProducto(this.value)">
                                        <option value="">Seleccionar producto...</option>
                                        <?php while ($producto = $result_productos->fetch_assoc()): 
                                            $stock_info = '';
                                            $spp = $producto['subpaquetes_por_paquete'] ?? 10;
                                            if ($producto['total_subpaquetes'] !== null) {
                                                $stock_info = ' - Stock: ' . $producto['total_subpaquetes'] . ' subp. (' . $spp . '/paq)';
                                            }
                                        ?>
                                        <option value="<?php echo $producto['id']; ?>"
                                            <?php echo ($producto_id == $producto['id']) ? 'selected' : ''; ?>
                                            data-stock="<?php echo $producto['total_subpaquetes'] ?: 0; ?>"
                                            data-spp="<?php echo $spp; ?>">
                                            <?php echo $producto['codigo'] . ' - ' . $producto['nombre_color'] . 
                                                   ' (' . $producto['proveedor'] . ')' . $stock_info; ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <button class="btn btn-outline-primary" type="button" 
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
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Información del producto seleccionado -->
                <?php if ($producto_info): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white py-2">
                                <h6 class="mb-0">
                                    <i class="fas fa-info-circle me-1"></i>Producto Seleccionado
                                    <span class="float-end badge bg-warning">ID: <?php echo $producto_id; ?></span>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="border rounded p-2 bg-light">
                                            <small class="text-muted d-block">Código</small>
                                            <strong class="text-success fs-5"><?php echo $producto_info['codigo']; ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-2 bg-light">
                                            <small class="text-muted d-block">Producto</small>
                                            <strong><?php echo $producto_info['nombre_color']; ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-2 bg-light">
                                            <small class="text-muted d-block">Proveedor</small>
                                            <strong class="text-info"><?php echo $producto_info['proveedor_nombre']; ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-2 bg-light">
                                            <small class="text-muted d-block">Categoría</small>
                                            <strong><?php echo $producto_info['categoria_nombre']; ?></strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr class="my-3">
                                
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="border rounded p-2">
                                            <small class="text-muted d-block">Stock Actual</small>
                                            <?php if ($producto_info['total_subpaquetes']): 
                                                $stock_class = 'bg-success';
                                                if ($producto_info['total_subpaquetes'] < $subpaquetes_por_paquete * 2) {
                                                    $stock_class = 'bg-danger';
                                                } elseif ($producto_info['total_subpaquetes'] < $subpaquetes_por_paquete * 5) {
                                                    $stock_class = 'bg-warning';
                                                }
                                            ?>
                                            <div class="d-flex align-items-center">
                                                <span class="badge <?php echo $stock_class; ?> fs-6 me-2">
                                                    <?php echo $producto_info['total_subpaquetes']; ?>
                                                </span>
                                                <span>subpaquetes</span>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo floor($producto_info['total_subpaquetes'] / $subpaquetes_por_paquete); ?> paq + 
                                                <?php echo $producto_info['total_subpaquetes'] % $subpaquetes_por_paquete; ?> sueltos
                                            </small>
                                            <?php else: ?>
                                            <span class="badge bg-danger fs-6">SIN STOCK</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-2">
                                            <small class="text-muted d-block">Ubicación</small>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                                <span><?php echo $producto_info['ubicacion'] ?: 'No asignada'; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-2">
                                            <small class="text-muted d-block">Precio Menor</small>
                                            <strong class="text-success fs-5">
                                                <?php echo formatearMoneda($producto_info['precio_menor']); ?>
                                            </strong>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-2">
                                            <small class="text-muted d-block">Precio Mayor</small>
                                            <strong class="text-primary fs-5">
                                                <?php echo formatearMoneda($producto_info['precio_mayor']); ?>
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Configuración del producto:</strong> 
                                            Cada paquete completo contiene 
                                            <span class="badge bg-primary" id="badgeSubpaquetesPorPaquete">
                                                <?php echo $subpaquetes_por_paquete; ?>
                                            </span> 
                                            subpaquetes. Este valor puede ser configurado por categoría o producto.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Formulario de ingreso de stock -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white py-2">
                                <h6 class="mb-0">
                                    <i class="fas fa-edit me-1"></i>Detalles del Ingreso
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" id="formIngresarStock" novalidate>
                                    <input type="hidden" name="action" value="ingresar">
                                    <input type="hidden" name="producto_id" value="<?php echo $producto_id; ?>">
                                    <input type="hidden" name="subpaquetes_por_paquete" id="subpaquetesPorPaquete" 
                                           value="<?php echo $subpaquetes_por_paquete; ?>">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Paquetes Completos *</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control form-control-lg" 
                                                       name="paquetes_completos" id="paquetesCompletos" 
                                                       value="0" min="0" required onchange="calcularTotalSubpaquetes()">
                                                <span class="input-group-text" id="textoSubpaquetesPorPaquete">
                                                    × <?php echo $subpaquetes_por_paquete; ?> subp.
                                                </span>
                                            </div>
                                            <div class="form-text text-muted">
                                                Cada paquete tiene <span id="formTextSubpaquetes"><?php echo $subpaquetes_por_paquete; ?></span> subpaquetes
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Subpaquetes Sueltos *</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control form-control-lg" 
                                                       name="subpaquetes_sueltos" id="subpaquetesSueltos" 
                                                       value="0" min="0" max="<?php echo $subpaquetes_por_paquete - 1; ?>" 
                                                       required onchange="calcularTotalSubpaquetes()"
                                                       data-bs-toggle="tooltip" 
                                                       title="Si ingresa <?php echo $subpaquetes_por_paquete; ?> o más, se convertirán en paquetes completos automáticamente">
                                                <span class="input-group-text">subp.</span>
                                            </div>
                                            <div class="form-text text-muted">
                                                Máximo <span id="maxSubpaquetes"><?php echo $subpaquetes_por_paquete - 1; ?></span> por ingreso directo
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Total Subpaquetes</label>
                                            <div class="border rounded p-3 bg-light text-center">
                                                <h3 id="totalSubpaquetes" class="mb-0 text-success">0</h3>
                                                <small class="text-muted">subpaquetes</small>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Estadísticas</label>
                                            <div class="border rounded p-2 bg-light">
                                                <div class="d-flex justify-content-between">
                                                    <small>Después del ingreso:</small>
                                                    <small id="estadisticasStock" class="text-success"></small>
                                                </div>
                                                <div class="progress mt-1" style="height: 5px;">
                                                    <div id="barraStock" class="progress-bar bg-success" 
                                                         role="progressbar" style="width: 0%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Costo por Paquete *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">Bs</span>
                                                <input type="number" class="form-control" name="costo_paquete" 
                                                       value="<?php echo $producto_info['costo_paquete'] ? number_format($producto_info['costo_paquete'], 2) : '75.00'; ?>" 
                                                       step="0.01" min="0" required onchange="calcularTotalSubpaquetes()">
                                            </div>
                                            <div class="form-text text-muted">
                                                Costo de cada paquete completo
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Costo Total</label>
                                            <div class="border rounded p-3 bg-light text-center">
                                                <h4 id="costoTotal" class="mb-0 text-primary">Bs 0.00</h4>
                                                <small class="text-muted">Total a pagar</small>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Ubicación</label>
                                            <select class="form-select" name="ubicacion" id="ubicacionSelect">
                                                <option value="">Seleccionar ubicación...</option>
                                                <option value="Estantería A1" <?php echo ($producto_info['ubicacion'] == 'Estantería A1') ? 'selected' : ''; ?>>Estantería A1</option>
                                                <option value="Estantería A2" <?php echo ($producto_info['ubicacion'] == 'Estantería A2') ? 'selected' : ''; ?>>Estantería A2</option>
                                                <option value="Estantería B1" <?php echo ($producto_info['ubicacion'] == 'Estantería B1') ? 'selected' : ''; ?>>Estantería B1</option>
                                                <option value="Estantería B2" <?php echo ($producto_info['ubicacion'] == 'Estantería B2') ? 'selected' : ''; ?>>Estantería B2</option>
                                                <option value="Almacén Principal" <?php echo ($producto_info['ubicacion'] == 'Almacén Principal') ? 'selected' : ''; ?>>Almacén Principal</option>
                                                <option value="Almacén Secundario" <?php echo ($producto_info['ubicacion'] == 'Almacén Secundario') ? 'selected' : ''; ?>>Almacén Secundario</option>
                                            </select>
                                            <input type="text" class="form-control mt-2 d-none" name="ubicacion_personalizada" 
                                                   id="ubicacionPersonalizada" placeholder="Ingresar ubicación personalizada...">
                                            <button type="button" class="btn btn-sm btn-outline-secondary mt-1" 
                                                    onclick="toggleUbicacionPersonalizada()">
                                                <i class="fas fa-pen"></i> Personalizar
                                            </button>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Responsable</label>
                                            <div class="border rounded p-2 bg-light">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-user text-primary me-2"></i>
                                                    <div>
                                                        <div class="fw-bold"><?php echo $_SESSION['usuario_nombre']; ?></div>
                                                        <small class="text-muted"><?php echo date('d/m/Y H:i'); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <div class="row">
                                        <div class="col-md-12">
                                            <label class="form-label fw-bold">Observaciones</label>
                                            <textarea class="form-control" name="observaciones" rows="3" 
                                                      placeholder="Ej: Compra a <?php echo $producto_info['proveedor_nombre']; ?>, Factura #001234, Fecha de compra: <?php echo date('d/m/Y'); ?>"
                                                      onfocus="this.placeholder=''" 
                                                      onblur="this.placeholder='Ej: Compra a <?php echo $producto_info['proveedor_nombre']; ?>, Factura #001234, Fecha de compra: <?php echo date('d/m/Y'); ?>'"></textarea>
                                            <div class="form-text">
                                                <i class="fas fa-lightbulb text-warning"></i> 
                                                Incluya información relevante como número de factura, proveedor, fecha de compra, etc.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                <button type="reset" class="btn btn-outline-secondary btn-lg me-md-2">
                                                    <i class="fas fa-undo me-2"></i>Limpiar
                                                </button>
                                                <button type="submit" class="btn btn-success btn-lg px-5">
                                                    <i class="fas fa-save me-2"></i>Ingresar Stock
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Mensaje cuando no hay producto seleccionado -->
                <div class="text-center text-muted p-5">
                    <div class="mb-4">
                        <i class="fas fa-box-open fa-4x text-muted"></i>
                    </div>
                    <h4 class="mb-3">Seleccione un producto para ingresar stock</h4>
                    <p class="lead mb-4">Use el selector de arriba o busque un producto</p>
                    <button class="btn btn-primary btn-lg" onclick="buscarProductosModal()">
                        <i class="fas fa-search me-2"></i>Buscar Producto
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <!-- Panel de últimos ingresos -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-gradient-info text-white">
                <h6 class="mb-0">
                    <i class="fas fa-history me-1"></i>Últimos Ingresos
                    <span class="float-end badge bg-white text-info">
                        <i class="fas fa-sync-alt fa-spin"></i> Actualizado
                    </span>
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                    <?php
                    $query_ultimos = "SELECT p.codigo, p.nombre_color, i.paquetes_completos, 
                                     i.subpaquetes_sueltos, i.fecha_ultimo_ingreso,
                                     u.nombre as usuario, pr.nombre as proveedor,
                                     i.subpaquetes_por_paquete,
                                     (i.paquetes_completos * i.subpaquetes_por_paquete + i.subpaquetes_sueltos) as total_ingresado
                                     FROM inventario i
                                     JOIN productos p ON i.producto_id = p.id
                                     JOIN proveedores pr ON p.proveedor_id = pr.id
                                     JOIN usuarios u ON i.usuario_registro = u.id
                                     WHERE i.fecha_ultimo_ingreso IS NOT NULL
                                     ORDER BY i.fecha_ultimo_ingreso DESC, i.id DESC
                                     LIMIT 8";
                    $result_ultimos = $conn->query($query_ultimos);
                    
                    if ($result_ultimos->num_rows > 0):
                        while ($ingreso = $result_ultimos->fetch_assoc()):
                            $hace = tiempoTranscurrido($ingreso['fecha_ultimo_ingreso'] . ' 00:00:00');
                            $spp = $ingreso['subpaquetes_por_paquete'] ?? 10;
                            $color_clase = '';
                            $icono = 'fa-box';
                            if (strpos($hace, 'hora') !== false) {
                                $color_clase = 'border-start border-success border-4';
                                $icono = 'fa-clock text-success';
                            } elseif (strpos($hace, 'ayer') !== false) {
                                $color_clase = 'border-start border-primary border-4';
                                $icono = 'fa-calendar-day text-primary';
                            }
                    ?>
                    <div class="list-group-item <?php echo $color_clase; ?> py-3">
                        <div class="d-flex w-100 justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1">
                                    <i class="fas <?php echo $icono; ?> me-2"></i>
                                    <?php echo $ingreso['codigo']; ?>
                                </h6>
                                <small class="text-muted d-block">
                                    <i class="fas fa-tag me-1"></i><?php echo $ingreso['nombre_color']; ?>
                                </small>
                                <small class="text-muted">
                                    <i class="fas fa-truck me-1"></i><?php echo $ingreso['proveedor']; ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-info rounded-pill fs-6">
                                    +<?php echo $ingreso['total_ingresado']; ?>
                                </span>
                                <div class="mt-1">
                                    <small class="text-muted"><?php echo $hace; ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-box me-1"></i><?php echo $ingreso['paquetes_completos']; ?> pqt
                                </span>
                                <span class="badge bg-light text-dark ms-1">
                                    <i class="fas fa-box-open me-1"></i><?php echo $ingreso['subpaquetes_sueltos']; ?> sueltos
                                </span>
                                <span class="badge bg-secondary text-white ms-1">
                                    <i class="fas fa-cubes me-1"></i><?php echo $spp; ?>/paq
                                </span>
                            </small>
                            <small class="text-muted">
                                <i class="fas fa-user me-1"></i><?php echo $ingreso['usuario']; ?>
                            </small>
                        </div>
                    </div>
                    <?php
                        endwhile;
                    else:
                    ?>
                    <div class="text-center text-muted p-5">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p class="mb-0">No hay ingresos recientes</p>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal de búsqueda de productos con mejoras -->
<div class="modal fade" id="modalBuscarProductos" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-search me-2"></i>Búsqueda Avanzada de Productos
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Filtros de búsqueda -->
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
                                    <option value="">Todos los proveedores</option>
                                    <?php
                                    $query_proveedores = "SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre";
                                    $result_proveedores = $conn->query($query_proveedores);
                                    while ($proveedor = $result_proveedores->fetch_assoc()):
                                    ?>
                                    <option value="<?php echo $proveedor['id']; ?>">
                                        <?php echo $proveedor['nombre']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Stock</label>
                                <select class="form-select" id="filtroStock">
                                    <option value="">Todos</option>
                                    <option value="sin_stock">Sin stock</option>
                                    <option value="bajo_stock">Stock bajo</option>
                                    <option value="stock_normal">Stock normal</option>
                                    <option value="stock_alto">Stock alto</option>
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
                                    <div>
                                        <button class="btn btn-outline-secondary" onclick="limpiarFiltros()">
                                            <i class="fas fa-eraser me-1"></i>Limpiar filtros
                                        </button>
                                    </div>
                                    <div>
                                        <button class="btn btn-primary" onclick="filtrarProductosModal()">
                                            <i class="fas fa-search me-1"></i>Buscar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de resultados -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="tablaProductosModal">
                        <thead class="table-dark">
                            <tr>
                                <th width="10%">Código</th>
                                <th width="25%">Producto</th>
                                <th width="20%">Proveedor</th>
                                <th width="10%">Categoría</th>
                                <th width="10%" class="text-center">Paq/Subp</th>
                                <th width="15%" class="text-center">Stock Actual</th>
                                <th width="10%" class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Re-ejecutar consulta para el modal
                            $query_modal = "SELECT p.*, pr.nombre as proveedor, 
                                           c.nombre as categoria_nombre,
                                           i.total_subpaquetes, i.paquetes_completos, 
                                           i.subpaquetes_sueltos, i.subpaquetes_por_paquete
                                           FROM productos p
                                           JOIN proveedores pr ON p.proveedor_id = pr.id
                                           JOIN categorias c ON p.categoria_id = c.id
                                           LEFT JOIN inventario i ON p.id = i.producto_id
                                           WHERE p.activo = 1
                                           ORDER BY pr.nombre, p.codigo";
                            $result_modal = $conn->query($query_modal);
                            while ($producto = $result_modal->fetch_assoc()): 
                                $stock_clase = 'bg-secondary';
                                $stock_texto = 'Sin stock';
                                $spp = $producto['subpaquetes_por_paquete'] ?? 10;
                                if ($producto['total_subpaquetes'] !== null) {
                                    if ($producto['total_subpaquetes'] < $spp * 2) {
                                        $stock_clase = 'bg-danger';
                                    } elseif ($producto['total_subpaquetes'] < $spp * 5) {
                                        $stock_clase = 'bg-warning';
                                    } else {
                                        $stock_clase = 'bg-success';
                                    }
                                    $stock_texto = $producto['total_subpaquetes'] . ' subp.';
                                }
                            ?>
                            <tr data-codigo="<?php echo $producto['codigo']; ?>"
                                data-nombre="<?php echo strtolower($producto['nombre_color']); ?>"
                                data-proveedor-id="<?php echo $producto['proveedor_id']; ?>"
                                data-stock="<?php echo $producto['total_subpaquetes'] ?: 0; ?>"
                                data-spp="<?php echo $spp; ?>">
                                <td>
                                    <strong class="text-primary"><?php echo $producto['codigo']; ?></strong>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="color-preview me-2" 
                                             style="width: 15px; height: 15px; background-color: #<?php echo substr(md5($producto['nombre_color']), 0, 6); ?>; 
                                                    border-radius: 3px;"></div>
                                        <?php echo $producto['nombre_color']; ?>
                                    </div>
                                </td>
                                <td><?php echo $producto['proveedor']; ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $producto['categoria_nombre']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?php echo $spp; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $stock_clase; ?> fs-6">
                                        <?php echo $stock_texto; ?>
                                    </span>
                                    <?php if ($producto['total_subpaquetes'] > 0): ?>
                                    <div class="small text-muted mt-1">
                                        <?php echo floor($producto['total_subpaquetes'] / $spp); ?> pqt + 
                                        <?php echo $producto['total_subpaquetes'] % $spp; ?> sueltos
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-success" 
                                            onclick="seleccionarProductoModal(<?php echo $producto['id']; ?>, <?php echo $spp; ?>)"
                                            data-bs-toggle="tooltip" title="Seleccionar este producto">
                                        <i class="fas fa-check me-1"></i>Seleccionar
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Mostrando <span id="contadorResultados"><?php echo $result_modal->num_rows; ?></span> productos encontrados
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cerrar
                </button>
                <button type="button" class="btn btn-primary" onclick="seleccionarPrimerProducto()">
                    <i class="fas fa-arrow-right me-1"></i>Seleccionar el primero
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales
let productosData = [];
let subpaquetesPorPaquete = <?php echo $subpaquetes_por_paquete; ?>;

// Inicializar datos de productos
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Cargar datos de productos para el modal
    cargarDatosProductos();
    
    // Inicializar cálculos si hay producto seleccionado
    if (<?php echo $producto_id ? 'true' : 'false'; ?>) {
        calcularTotalSubpaquetes();
        actualizarEstadisticas();
    }
    
    // Configurar validación del formulario
    const form = document.getElementById('formIngresarStock');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                form.classList.add('was-validated');
                mostrarNotificacion('Por favor complete todos los campos requeridos', 'warning');
                return;
            }
            
            // Validar que al menos haya un ingreso
            let paquetes = parseInt(document.getElementById('paquetesCompletos').value) || 0;
            let sueltos = parseInt(document.getElementById('subpaquetesSueltos').value) || 0;
            
            if (paquetes === 0 && sueltos === 0) {
                e.preventDefault();
                mostrarNotificacion('Debe ingresar al menos 1 subpaquete', 'warning');
                return;
            }
            
            // Mostrar confirmación
            if (!confirm('¿Está seguro de ingresar este stock al inventario?')) {
                e.preventDefault();
            }
        });
    }
    
    // Evento para el selector de producto
    const selectProducto = document.getElementById('selectProducto');
    if (selectProducto) {
        selectProducto.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const spp = parseInt(selectedOption.getAttribute('data-spp')) || 10;
            subpaquetesPorPaquete = spp;
        });
    }
});

function cargarProducto(productoId) {
    if (productoId) {
        window.location.href = 'ingresar_stock.php?producto_id=' + productoId;
    }
}

function calcularTotalSubpaquetes() {
    let paquetes = parseInt(document.getElementById('paquetesCompletos').value) || 0;
    let sueltos = parseInt(document.getElementById('subpaquetesSueltos').value) || 0;
    let costoPaquete = parseFloat(document.querySelector('input[name="costo_paquete"]').value) || 0;
    let spp = <?php echo $subpaquetes_por_paquete; ?>;
    
    // Obtener spp actualizado
    if (typeof subpaquetesPorPaquete !== 'undefined') {
        spp = subpaquetesPorPaquete;
    }
    
    // Ajustar sueltos si son iguales o mayores al límite
    if (sueltos >= spp) {
        paquetes += Math.floor(sueltos / spp);
        sueltos = sueltos % spp;
        document.getElementById('paquetesCompletos').value = paquetes;
        document.getElementById('subpaquetesSueltos').value = sueltos;
        
        // Mostrar notificación
        mostrarNotificacion(`Los subpaquetes se han convertido automáticamente a paquetes completos`, 'info');
    }
    
    let total = (paquetes * spp) + sueltos;
    let costoTotal = paquetes * costoPaquete;
    
    // Actualizar campos
    document.getElementById('totalSubpaquetes').textContent = total.toLocaleString('es-ES');
    document.getElementById('costoTotal').textContent = 'Bs ' + costoTotal.toLocaleString('es-ES', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    
    // Actualizar estadísticas
    actualizarEstadisticas();
}

function actualizarEstadisticas() {
    if (!<?php echo $producto_id ? 'true' : 'false'; ?>) return;
    
    let paquetes = parseInt(document.getElementById('paquetesCompletos').value) || 0;
    let sueltos = parseInt(document.getElementById('subpaquetesSueltos').value) || 0;
    let spp = <?php echo $subpaquetes_por_paquete; ?>;
    
    if (typeof subpaquetesPorPaquete !== 'undefined') {
        spp = subpaquetesPorPaquete;
    }
    
    let stockActual = <?php echo $producto_info['total_subpaquetes'] ?? 0; ?>;
    let nuevoStock = stockActual + (paquetes * spp) + sueltos;
    
    // Actualizar texto de estadísticas
    document.getElementById('estadisticasStock').textContent = nuevoStock.toLocaleString('es-ES') + ' subp.';
    
    // Actualizar barra de progreso
    let barra = document.getElementById('barraStock');
    let limiteReferencia = spp * 50; // Usar 50 paquetes como referencia
    let porcentaje = Math.min((nuevoStock / limiteReferencia) * 100, 100);
    barra.style.width = porcentaje + '%';
    
    // Cambiar color según nivel
    if (nuevoStock < spp * 5) {
        barra.className = 'progress-bar bg-danger';
    } else if (nuevoStock < spp * 10) {
        barra.className = 'progress-bar bg-warning';
    } else if (nuevoStock < spp * 20) {
        barra.className = 'progress-bar bg-info';
    } else {
        barra.className = 'progress-bar bg-success';
    }
}

function toggleUbicacionPersonalizada() {
    let select = document.getElementById('ubicacionSelect');
    let personalizada = document.getElementById('ubicacionPersonalizada');
    
    if (select.classList.contains('d-none')) {
        select.classList.remove('d-none');
        personalizada.classList.add('d-none');
        personalizada.value = '';
    } else {
        select.classList.add('d-none');
        personalizada.classList.remove('d-none');
        personalizada.focus();
    }
}

function buscarProductosModal() {
    const modal = new bootstrap.Modal(document.getElementById('modalBuscarProductos'));
    modal.show();
}

function cargarDatosProductos() {
    // Extraer datos de la tabla del modal
    const tabla = document.getElementById('tablaProductosModal');
    const filas = tabla.querySelectorAll('tbody tr');
    
    productosData = Array.from(filas).map(fila => {
        const boton = fila.querySelector('button');
        const onclickAttr = boton.getAttribute('onclick') || '';
        const idMatch = onclickAttr.match(/\d+/);
        const spp = parseInt(fila.getAttribute('data-spp')) || 10;
        
        return {
            id: idMatch ? idMatch[0] : null,
            codigo: fila.getAttribute('data-codigo'),
            nombre: fila.getAttribute('data-nombre'),
            proveedorId: fila.getAttribute('data-proveedor-id'),
            stock: parseInt(fila.getAttribute('data-stock')) || 0,
            spp: spp,
            elemento: fila
        };
    });
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
        if (filtroStock) {
            const spp = producto.spp || 10;
            switch(filtroStock) {
                case 'sin_stock':
                    if (producto.stock > 0) return false;
                    break;
                case 'bajo_stock':
                    if (producto.stock >= spp * 5 || producto.stock === 0) return false;
                    break;
                case 'stock_normal':
                    if (producto.stock < spp * 5 || producto.stock > spp * 20) return false;
                    break;
                case 'stock_alto':
                    if (producto.stock <= spp * 20) return false;
                    break;
            }
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
                return (a.proveedorId || '').toString().localeCompare((b.proveedorId || '').toString());
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

function limpiarFiltros() {
    document.getElementById('filtroCodigo').value = '';
    document.getElementById('filtroNombre').value = '';
    document.getElementById('filtroProveedor').value = '';
    document.getElementById('filtroStock').value = '';
    document.getElementById('filtroOrden').value = 'codigo';
    
    filtrarProductosModal();
}

function seleccionarProductoModal(productoId, spp) {
    // Actualizar variable global de subpaquetes por paquete
    subpaquetesPorPaquete = parseInt(spp) || 10;
    cargarProducto(productoId);
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalBuscarProductos'));
    if (modal) {
        modal.hide();
    }
}

function seleccionarPrimerProducto() {
    const filasVisibles = Array.from(document.querySelectorAll('#tablaProductosModal tbody tr'))
        .filter(fila => fila.style.display !== 'none');
    
    if (filasVisibles.length > 0) {
        const primerFila = filasVisibles[0];
        const boton = primerFila.querySelector('button');
        const onclickAttr = boton.getAttribute('onclick') || '';
        const idMatch = onclickAttr.match(/\d+/);
        const spp = parseInt(primerFila.getAttribute('data-spp')) || 10;
        
        if (idMatch) {
            seleccionarProductoModal(idMatch[0], spp);
        }
    } else {
        mostrarNotificacion('No hay productos que coincidan con los filtros', 'warning');
    }
}

function mostrarNotificacion(mensaje, tipo = 'info') {
    // Eliminar notificaciones anteriores
    const notificacionesAnteriores = document.querySelectorAll('.notificacion-flotante');
    notificacionesAnteriores.forEach(function(n) {
        n.remove();
    });
    
    // Crear notificación
    const notificacion = document.createElement('div');
    notificacion.className = `alert alert-${tipo} alert-dismissible fade show notificacion-flotante`;
    notificacion.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 350px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    notificacion.innerHTML = `
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notificacion);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        if (notificacion.parentNode) {
            notificacion.remove();
        }
    }, 5000);
}

// Función helper para tiempo transcurrido
function tiempoTranscurrido(fechaString) {
    if (!fechaString) return 'Recién';
    
    try {
        const fecha = new Date(fechaString);
        const ahora = new Date();
        const diffMs = ahora - fecha;
        const diffDias = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        const diffHoras = Math.floor(diffMs / (1000 * 60 * 60));
        const diffMinutos = Math.floor(diffMs / (1000 * 60));
        
        if (diffMinutos < 1) return 'Ahora';
        if (diffMinutos < 60) return `Hace ${diffMinutos} minutos`;
        if (diffHoras < 24) return `Hace ${diffHoras} horas`;
        if (diffDias === 1) return 'Ayer';
        if (diffDias < 7) return `Hace ${diffDias} días`;
        if (diffDias < 30) return `Hace ${Math.floor(diffDias / 7)} semanas`;
        return `Hace ${Math.floor(diffDias / 30)} meses`;
    } catch (e) {
        return 'Recién';
    }
}
</script>

<style>
.color-preview {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: 1px solid #dee2e6;
}

.list-group-item:hover {
    background-color: rgba(40, 167, 69, 0.05);
    transform: translateX(5px);
    transition: all 0.3s ease;
}

.progress {
    border-radius: 3px;
}

.border-start {
    border-left-width: 4px !important;
}

.card {
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-2px);
}

.table-hover tbody tr:hover {
    background-color: rgba(40, 167, 69, 0.1);
}

.modal-xl {
    max-width: 1300px;
}

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

@media (max-width: 768px) {
    .modal-xl {
        max-width: 95%;
        margin: 1rem auto;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .fs-5, .fs-4, .fs-3, .fs-2, .fs-1 {
        font-size: calc(1rem + 0.3vw) !important;
    }
    
    .input-group-lg .form-control,
    .input-group-lg .input-group-text {
        height: calc(2.5rem + 2px);
        font-size: 1rem;
    }
}

@media print {
    .btn, .modal, .card-footer, .list-group-item .btn {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .table {
        font-size: 12px !important;
    }
}
</style>

<?php require_once 'footer.php'; ?>