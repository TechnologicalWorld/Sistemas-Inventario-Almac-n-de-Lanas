<?php
session_start();

$titulo_pagina = "Registrar Gasto";
$icono_titulo = "fas fa-receipt";
$breadcrumb = [
    ['text' => 'Caja', 'link' => 'movimientos_caja.php', 'active' => false],
    ['text' => 'Registrar Gasto', 'link' => '#', 'active' => true]
];

require_once 'config.php';
require_once 'funciones.php';
require_once 'header.php';

// Verificar permisos (solo administradores)
verificarRol(['administrador']);

$mensaje = '';
$tipo_mensaje = '';

// Categorías de gastos permitidas
$categorias_gastos = [
    'gasto_almuerzo' => 'Almuerzo',
    'gasto_varios' => 'Gastos Varios',
    'otros' => 'Otros Gastos'
];

// Procesar registro de gasto
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validar campos requeridos
    $categoria = isset($_POST['categoria']) ? limpiar($_POST['categoria']) : '';
    $descripcion = isset($_POST['descripcion']) ? limpiar($_POST['descripcion']) : '';
    $monto = isset($_POST['monto']) ? floatval($_POST['monto']) : 0;
    $fecha = isset($_POST['fecha']) ? limpiar($_POST['fecha']) : date('Y-m-d');
    $observaciones = isset($_POST['observaciones']) ? limpiar($_POST['observaciones']) : '';

    // Validar categoría permitida
    if (!array_key_exists($categoria, $categorias_gastos)) {
        $mensaje = "Categoría de gasto no válida";
        $tipo_mensaje = "danger";
    }
    // Validar monto
    elseif ($monto <= 0) {
        $mensaje = "El monto debe ser mayor a cero";
        $tipo_mensaje = "danger";
    }
    // Validar descripción
    elseif (empty($descripcion)) {
        $mensaje = "La descripción es requerida";
        $tipo_mensaje = "danger";
    }
    else {
        // Registrar en movimientos de caja
        $query = "INSERT INTO movimientos_caja 
                 (tipo, categoria, monto, descripcion, fecha, hora, usuario_id, observaciones) 
                 VALUES ('gasto', ?, ?, ?, ?, CURTIME(), ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sdssis", $categoria, $monto, $descripcion, $fecha, $_SESSION['usuario_id'], $observaciones);
        
        if ($stmt->execute()) {
            $mensaje = "Gasto registrado exitosamente";
            $tipo_mensaje = "success";
            
            // Limpiar formulario
            $_POST = [];
        } else {
            $mensaje = "Error al registrar gasto: " . $stmt->error;
            $tipo_mensaje = "danger";
        }
    }
}

// Obtener gastos del día (con JOIN para nombre de usuario)
$query_gastos_hoy = "SELECT mc.*, u.nombre as usuario_nombre 
                    FROM movimientos_caja mc
                    JOIN usuarios u ON mc.usuario_id = u.id
                    WHERE mc.tipo = 'gasto' AND mc.fecha = CURDATE() 
                    ORDER BY mc.hora DESC";
$result_gastos_hoy = $conn->query($query_gastos_hoy);

// Obtener total de gastos del día
$query_total_hoy = "SELECT COALESCE(SUM(monto), 0) as total 
                   FROM movimientos_caja 
                   WHERE tipo = 'gasto' AND fecha = CURDATE()";
$result_total_hoy = $conn->query($query_total_hoy);
$total_hoy = $result_total_hoy->fetch_assoc()['total'];

// Obtener gastos del mes
$query_gastos_mes = "SELECT COALESCE(SUM(monto), 0) as total 
                    FROM movimientos_caja 
                    WHERE tipo = 'gasto' 
                    AND MONTH(fecha) = MONTH(CURDATE()) 
                    AND YEAR(fecha) = YEAR(CURDATE())";
$result_gastos_mes = $conn->query($query_gastos_mes);
$total_mes = $result_gastos_mes->fetch_assoc()['total'];
?>

<?php if ($mensaje): ?>
<div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
    <div class="d-flex align-items-center">
        <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
        <span><?php echo $mensaje; ?></span>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 mb-4">
        <div class="card shadow">
            <div class="card-header bg-danger text-white py-3">
                <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Registrar Nuevo Gasto</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="formRegistrarGasto" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Categoría del Gasto <span class="text-danger">*</span></label>
                            <select class="form-select" name="categoria" required>
                                <option value="">Seleccionar categoría...</option>
                                <?php foreach ($categorias_gastos as $valor => $nombre): ?>
                                <option value="<?php echo $valor; ?>" 
                                    <?php echo ($_POST['categoria'] ?? '') == $valor ? 'selected' : ''; ?>>
                                    <?php echo $nombre; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Seleccione una categoría</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Fecha <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha" 
                                   value="<?php echo $_POST['fecha'] ?? date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">Ingrese una fecha válida</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Descripción <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="descripcion" 
                                   value="<?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?>" 
                                   placeholder="Ej: Almuerzo personal, Material de oficina..." required maxlength="255">
                            <div class="invalid-feedback">La descripción es requerida</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Monto (Bs) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Bs</span>
                                <input type="number" class="form-control" name="monto" 
                                       value="<?php echo $_POST['monto'] ?? ''; ?>" 
                                       step="0.01" min="0.01" required placeholder="0.00">
                                <div class="invalid-feedback">Ingrese un monto válido</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="3" 
                                      placeholder="Detalles adicionales del gasto..."><?php echo htmlspecialchars($_POST['observaciones'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="fas fa-save me-2"></i>Registrar Gasto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <!-- Gastos frecuentes -->
        <div class="card shadow">
            <div class="card-header bg-info text-white py-3">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Gastos Rápidos</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-danger text-start" 
                            onclick="seleccionarGastoRapido('Almuerzo personal', 25, 'gasto_almuerzo')">
                        <i class="fas fa-utensils me-2"></i>Almuerzo (25 Bs)
                    </button>
                    <button type="button" class="btn btn-outline-warning text-start" 
                            onclick="seleccionarGastoRapido('Material de oficina', 15, 'gasto_varios')">
                        <i class="fas fa-paperclip me-2"></i>Material Oficina (15 Bs)
                    </button>
                    <button type="button" class="btn btn-outline-secondary text-start" 
                            onclick="seleccionarGastoRapido('Transporte', 10, 'gasto_varios')">
                        <i class="fas fa-car me-2"></i>Transporte (10 Bs)
                    </button>
                    <button type="button" class="btn btn-outline-primary text-start" 
                            onclick="seleccionarGastoRapido('Internet/Móvil', 50, 'otros')">
                        <i class="fas fa-wifi me-2"></i>Internet (50 Bs)
                    </button>
                </div>
            </div>
        </div>
    <!-- Resumen de gastos -->
        <div class="card shadow mb-4">
            <div class="card-header bg-warning text-dark py-3">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Resumen de Gastos</h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="display-4 text-danger"><?php echo formatearMoneda($total_hoy); ?></div>
                    <p class="text-muted">Gastos Hoy</p>
                </div>
                
                <div class="text-center">
                    <div class="h3 text-warning"><?php echo formatearMoneda($total_mes); ?></div>
                    <p class="text-muted">Gastos Este Mes</p>
                </div>
                
                <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Los gastos se descuentan automáticamente del balance de caja.
                    </small>
                </div>
            </div>
        </div>
        

    </div>
</div>


<script>
// Funciones de utilidad (si no están definidas globalmente)
function mostrarLoading(mensaje = 'Procesando...') {
    let loading = document.getElementById('loadingOverlay');
    if (!loading) {
        loading = document.createElement('div');
        loading.id = 'loadingOverlay';
        loading.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 99999;
        `;
        loading.innerHTML = `
            <div class="text-center text-white">
                <div class="spinner-border mb-3" style="width: 3rem; height: 3rem;"></div>
                <h6>${mensaje}</h6>
            </div>
        `;
        document.body.appendChild(loading);
    }
}

function ocultarLoading() {
    const loading = document.getElementById('loadingOverlay');
    if (loading) loading.remove();
}

function mostrarMensaje(tipo, mensaje) {
    // Si existe la función global, úsala; de lo contrario usa alert
    if (typeof window.mostrarMensajeGlobal === 'function') {
        window.mostrarMensajeGlobal(tipo, mensaje);
    } else {
        alert(mensaje);
    }
}

// Función para seleccionar gasto rápido
function seleccionarGastoRapido(descripcion, monto, categoria) {
    document.querySelector('input[name="descripcion"]').value = descripcion;
    document.querySelector('input[name="monto"]').value = monto;
    document.querySelector('select[name="categoria"]').value = categoria;
    
    // Marcar como válido si es necesario
    document.querySelector('input[name="descripcion"]').classList.remove('is-invalid');
    document.querySelector('input[name="monto"]').classList.remove('is-invalid');
    document.querySelector('select[name="categoria"]').classList.remove('is-invalid');
    
    // Enfocar en el campo de observaciones
    setTimeout(() => {
        document.querySelector('textarea[name="observaciones"]').focus();
    }, 100);
}

// Función para eliminar gasto
function eliminarGasto(id, descripcion) {
    if (confirm('¿Está seguro de eliminar el siguiente gasto?\n\n"' + descripcion + '"\n\nEsta acción no se puede deshacer.')) {
        mostrarLoading('Eliminando gasto...');
        
        fetch('ajax_eliminar_gasto.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + encodeURIComponent(id)
        })
        .then(response => response.json())
        .then(data => {
            ocultarLoading();
            if (data.success) {
                mostrarMensaje('success', 'Gasto eliminado exitosamente');
                // Recargar la página para actualizar la lista
                setTimeout(() => location.reload(), 1500);
            } else {
                mostrarMensaje('danger', data.message || 'Error al eliminar el gasto');
            }
        })
        .catch(error => {
            ocultarLoading();
            console.error('Error:', error);
            mostrarMensaje('danger', 'Error de conexión al eliminar el gasto');
        });
    }
}

// Validación del formulario con Bootstrap 5
(function() {
    'use strict';
    
    // Obtener el formulario
    const form = document.getElementById('formRegistrarGasto');
    
    if (form) {
        form.addEventListener('submit', function(event) {
            let isValid = true;
            
            // Validar categoría
            const categoria = form.querySelector('select[name="categoria"]');
            if (!categoria.value) {
                categoria.classList.add('is-invalid');
                isValid = false;
            } else {
                categoria.classList.remove('is-invalid');
            }
            
            // Validar fecha
            const fecha = form.querySelector('input[name="fecha"]');
            if (!fecha.value) {
                fecha.classList.add('is-invalid');
                isValid = false;
            } else {
                fecha.classList.remove('is-invalid');
            }
            
            // Validar descripción
            const descripcion = form.querySelector('input[name="descripcion"]');
            if (!descripcion.value.trim()) {
                descripcion.classList.add('is-invalid');
                isValid = false;
            } else {
                descripcion.classList.remove('is-invalid');
            }
            
            // Validar monto
            const monto = form.querySelector('input[name="monto"]');
            const montoVal = parseFloat(monto.value);
            if (!monto.value || montoVal <= 0) {
                monto.classList.add('is-invalid');
                isValid = false;
            } else {
                monto.classList.remove('is-invalid');
            }
            
            if (!isValid) {
                event.preventDefault();
                event.stopPropagation();
                return false;
            }
            
            form.classList.add('was-validated');
            return true;
        });
    }
})();

// Auto-completar fecha actual si está vacía
document.addEventListener('DOMContentLoaded', function() {
    const fechaInput = document.querySelector('input[name="fecha"]');
    if (fechaInput && !fechaInput.value) {
        const hoy = new Date().toISOString().split('T')[0];
        fechaInput.value = hoy;
    }
    
    // Agregar tooltips si existen
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<style>
.border-left-success { border-left: 0.25rem solid #28a745 !important; }
.border-left-danger { border-left: 0.25rem solid #dc3545 !important; }
.border-left-warning { border-left: 0.25rem solid #ffc107 !important; }
.border-left-info { border-left: 0.25rem solid #17a2b8 !important; }

.card-header.bg-gradient {
    background: linear-gradient(45deg, #f8f9fc, #e9ecef);
}

.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,0.02) !important;
}

.gasto-row td {
    vertical-align: middle;
}

.btn-outline-danger, .btn-outline-warning, .btn-outline-secondary, .btn-outline-primary {
    transition: all 0.2s;
}

.btn-outline-danger:hover {
    background-color: #dc3545;
    color: white;
}

.btn-outline-warning:hover {
    background-color: #ffc107;
    color: black;
}

.btn-outline-secondary:hover {
    background-color: #6c757d;
    color: white;
}

.btn-outline-primary:hover {
    background-color: #007bff;
    color: white;
}

@media (max-width: 768px) {
    .display-4 {
        font-size: 2.5rem;
    }
}
</style>

<?php 
// Liberar resultados
if (isset($result_gastos_hoy)) $result_gastos_hoy->free();
if (isset($result_total_hoy)) $result_total_hoy->free();
if (isset($result_gastos_mes)) $result_gastos_mes->free();

require_once 'footer.php'; 
?>