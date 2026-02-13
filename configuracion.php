<?php
session_start();

// configuracion.php - Configuración del sistema
$titulo_pagina = "Configuración del Sistema";
$icono_titulo = "fas fa-cog";
$breadcrumb = [
    ['text' => 'Configuración', 'link' => '#', 'active' => true]
];

require_once 'header.php';
require_once 'funciones.php';

// Verificar que sea administrador
verificarRol(['administrador']);

$mensaje = '';
$tipo_mensaje = '';

// Obtener configuración actual
$query_config = "SELECT * FROM sistema_config LIMIT 1";
$result_config = $conn->query($query_config);
if ($result_config->num_rows > 0) {
    $config = $result_config->fetch_assoc();
} else {
    // Configuración por defecto
    $config = [
        'empresa_nombre' => 'TIENDA DE LANAS',
        'moneda' => 'Bs',
        'telefono_empresa' => '',
        'direccion_empresa' => '',
        'impresora_termica' => 1
    ];
}

// Procesar actualización de configuración
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'actualizar') {
    $empresa_nombre = limpiar($_POST['empresa_nombre']);
    $telefono_empresa = limpiar($_POST['telefono_empresa']);
    $direccion_empresa = limpiar($_POST['direccion_empresa']);
    $impresora_termica = isset($_POST['impresora_termica']) ? 1 : 0;
    
    // Verificar si ya existe configuración
    if (isset($config['id'])) {
        $query_update = "UPDATE sistema_config SET 
                        empresa_nombre = ?, 
                        telefono_empresa = ?, 
                        direccion_empresa = ?, 
                        impresora_termica = ?
                        WHERE id = ?";
        $stmt = $conn->prepare($query_update);
        $stmt->bind_param("sssii", $empresa_nombre, $telefono_empresa, 
                         $direccion_empresa, $impresora_termica, $config['id']);
    } else {
        $query_insert = "INSERT INTO sistema_config 
                        (empresa_nombre, telefono_empresa, direccion_empresa, impresora_termica) 
                        VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query_insert);
        $stmt->bind_param("sssi", $empresa_nombre, $telefono_empresa, 
                         $direccion_empresa, $impresora_termica);
    }
    
    if ($stmt->execute()) {
        // Actualizar constante
        define('EMPRESA_NOMBRE', $empresa_nombre);
        
        $mensaje = "Configuración actualizada exitosamente";
        $tipo_mensaje = "success";
        
        // Recargar configuración
        $result_config = $conn->query($query_config);
        $config = $result_config->fetch_assoc();
    } else {
        $mensaje = "Error al actualizar configuración: " . $stmt->error;
        $tipo_mensaje = "danger";
    }
}

// Procesar backup de datos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'backup') {
    // Crear directorio de backups si no existe
    $backup_dir = 'backups/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    // Nombre del archivo de backup
    $backup_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Obtener todas las tablas
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    // Crear contenido del backup
    $backup_content = "-- Backup generado automáticamente\n";
    $backup_content .= "-- Sistema: " . SISTEMA_NOMBRE . "\n";
    $backup_content .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
    $backup_content .= "-- Usuario: " . $_SESSION['usuario_nombre'] . "\n\n";
    
    foreach ($tables as $table) {
        // Obtener estructura de la tabla
        $result = $conn->query("SHOW CREATE TABLE $table");
        $row = $result->fetch_row();
        $backup_content .= "\n-- Estructura de tabla: $table\n";
        $backup_content .= "DROP TABLE IF EXISTS `$table`;\n";
        $backup_content .= $row[1] . ";\n\n";
        
        // Obtener datos de la tabla
        $result = $conn->query("SELECT * FROM $table");
        if ($result->num_rows > 0) {
            $backup_content .= "-- Datos de tabla: $table\n";
            
            while ($row = $result->fetch_assoc()) {
                $columns = implode('`, `', array_keys($row));
                $values = array_map(function($value) use ($conn) {
                    if ($value === null) return 'NULL';
                    return "'" . $conn->real_escape_string($value) . "'";
                }, array_values($row));
                $values_str = implode(', ', $values);
                
                $backup_content .= "INSERT INTO `$table` (`$columns`) VALUES ($values_str);\n";
            }
            $backup_content .= "\n";
        }
    }
    
    // Guardar archivo
    if (file_put_contents($backup_file, $backup_content)) {
        $mensaje = "Backup creado exitosamente: " . basename($backup_file);
        $tipo_mensaje = "success";
    } else {
        $mensaje = "Error al crear backup";
        $tipo_mensaje = "danger";
    }
}

// Obtener lista de backups
$backups = [];
$backup_dir = 'backups/';
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $backups[] = [
                'nombre' => $file,
                'ruta' => $backup_dir . $file,
                'tamano' => filesize($backup_dir . $file),
                'fecha' => date('d/m/Y H:i:s', filemtime($backup_dir . $file))
            ];
        }
    }
}
?>

<?php if ($mensaje): ?>
<div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
    <?php echo $mensaje; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Configuración General</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="formConfiguracion">
                    <input type="hidden" name="action" value="actualizar">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre de la Empresa *</label>
                            <input type="text" class="form-control" name="empresa_nombre" 
                                   value="<?php echo htmlspecialchars($config['empresa_nombre']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" class="form-control" name="telefono_empresa" 
                                   value="<?php echo htmlspecialchars($config['telefono_empresa']); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Dirección</label>
                            <textarea class="form-control" name="direccion_empresa" rows="3"><?php echo htmlspecialchars($config['direccion_empresa']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Moneda</label>
                            <input type="text" class="form-control" value="Bs" readonly>
                            <div class="form-text">La moneda no puede ser cambiada</div>
                        </div>
                        <!--<div class="col-md-6 mb-3">
                            <label class="form-label">Impresora Térmica</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="impresora_termica" 
                                       id="impresora_termica" <?php echo ($config['impresora_termica'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="impresora_termica">
                                    Habilitar impresión térmica
                                </label>
                            </div>
                            <div class="form-text">Configuración para impresoras de 80mm</div>
                        </div> -->
                    </div>
                    
<!--                    <div class="row">
                        <div class="col-md-12">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Guardar Configuración
                                </button>
                            </div>
                        </div>
                    </div> -->
                </form>
            </div>
        </div>
        
        <!-- Configuración de impresora -->
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Configuración de Impresora Térmica</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Ancho de papel</label>
                        <select class="form-select" id="anchoPapel">
                            <option value="80" selected>80mm (Estándar)</option>
                            <option value="58">58mm (Mini)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fuente</label>
                        <select class="form-select" id="fuenteImpresora">
                            <option value="A" selected>Fuente A (Normal)</option>
                            <option value="B">Fuente B (Grande)</option>
                            <option value="C">Fuente C (Pequeña)</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cortar papel automáticamente</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="cortarPapel" checked>
                            <label class="form-check-label" for="cortarPapel">Habilitar</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Abrir cajón de dinero</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="abrirCajon">
                            <label class="form-check-label" for="abrirCajon">Habilitar</label>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="d-grid">
                            <button class="btn btn-outline-success" onclick="probarImpresora()">
                                <i class="fas fa-print me-2"></i>Probar Impresión
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Backup de datos -->
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">Backup de Datos</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Realice copias de seguridad periódicamente para proteger sus datos.
                </div>
                
                <form method="POST" action="" onsubmit="return confirm('¿Crear backup de la base de datos?')">
                    <input type="hidden" name="action" value="backup">
                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-database me-2"></i>Crear Backup Ahora
                        </button>
                    </div>
                </form>
                
                <h6>Backups Disponibles</h6>
                <div class="list-group" style="max-height: 300px; overflow-y: auto;">
                    <?php if (!empty($backups)): ?>
                        <?php foreach ($backups as $backup): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo $backup['nombre']; ?></h6>
                                <small><?php echo $backup['fecha']; ?></small>
                            </div>
                            <p class="mb-1">
                                <small>Tamaño: <?php echo round($backup['tamano'] / 1024, 2); ?> KB</small>
                            </p>
                            <div class="btn-group btn-group-sm mt-2">
                                <a href="<?php echo $backup['ruta']; ?>" class="btn btn-outline-primary" download>
                                    <i class="fas fa-download"></i>
                                </a>
                                <button class="btn btn-outline-success" onclick="restaurarBackup('<?php echo $backup['nombre']; ?>')">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="eliminarBackup('<?php echo $backup['nombre']; ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center text-muted p-3">
                        No hay backups disponibles
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Información del sistema -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Información del Sistema</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th>Versión:</th>
                        <td>1.0.0</td>
                    </tr>
                    <tr>
                        <th>PHP:</th>
                        <td><?php echo phpversion(); ?></td>
                    </tr>
                    <tr>
                        <th>MySQL:</th>
                        <td><?php echo $conn->server_info; ?></td>
                    </tr>
                    <tr>
                        <th>Servidor:</th>
                        <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                    </tr>
                    <tr>
                        <th>Usuarios:</th>
                        <td>
                            <?php
                            $query_usuarios = "SELECT COUNT(*) as total FROM usuarios";
                            $result_usuarios = $conn->query($query_usuarios);
                            echo $result_usuarios->fetch_assoc()['total'];
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Productos:</th>
                        <td>
                            <?php
                            $query_productos = "SELECT COUNT(*) as total FROM productos WHERE activo = 1";
                            $result_productos = $conn->query($query_productos);
                            echo $result_productos->fetch_assoc()['total'];
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Ventas Hoy:</th>
                        <td>
                            <?php
                            $query_ventas = "SELECT COUNT(*) as total FROM ventas WHERE fecha = CURDATE() AND anulado = 0";
                            $result_ventas = $conn->query($query_ventas);
                            echo $result_ventas->fetch_assoc()['total'];
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Logs del sistema -->
        <div class="card mt-4">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0">Logs Recientes</h6>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <div class="list-group-item">
                        <small><?php echo date('d/m/Y H:i:s'); ?></small><br>
                        <small><strong><?php echo $_SESSION['usuario_nombre']; ?></strong> accedió a configuración</small>
                    </div>
                    <div class="list-group-item">
                        <small><?php echo date('d/m/Y H:i:s', strtotime('-1 hour')); ?></small><br>
                        <small>Sistema iniciado correctamente</small>
                    </div>
                    <div class="list-group-item">
                        <small><?php echo date('d/m/Y H:i:s', strtotime('-2 hours')); ?></small><br>
                        <small>Último backup realizado</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación para restauración -->
<div class="modal fade" id="modalRestaurar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Restaurar Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>¡ADVERTENCIA!</strong><br>
                    Esta acción reemplazará TODOS los datos actuales con los del backup.
                    ¿Está seguro de continuar?
                </div>
                <input type="hidden" id="backupRestaurar">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" onclick="confirmarRestauracion()">Restaurar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Validar formulario de configuración
document.getElementById('formConfiguracion').addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});

// Probar impresión térmica
function probarImpresora() {
    mostrarLoading();
    
    // Crear contenido de prueba
    var contenido = '<div style="width: 80mm; font-family: monospace; font-size: 12px;">';
    contenido += '<div style="text-align: center;">';
    contenido += '<h3 style="margin: 5px 0;">' + document.querySelector('input[name="empresa_nombre"]').value + '</h3>';
    contenido += '<p style="margin: 2px 0;">' + document.querySelector('textarea[name="direccion_empresa"]').value + '</p>';
    contenido += '<p style="margin: 2px 0;">' + document.querySelector('input[name="telefono_empresa"]').value + '</p>';
    contenido += '</div>';
    contenido += '<hr style="border: 1px dashed #000; margin: 10px 0;">';
    contenido += '<div style="text-align: center;">';
    contenido += '<h4 style="margin: 10px 0;">PRUEBA DE IMPRESIÓN</h4>';
    contenido += '<p>Fecha: ' + new Date().toLocaleDateString() + '</p>';
    contenido += '<p>Hora: ' + new Date().toLocaleTimeString() + '</p>';
    contenido += '</div>';
    contenido += '<hr style="border: 1px dashed #000; margin: 10px 0;">';
    contenido += '<div style="text-align: center; font-size: 10px; margin-top: 20px;">';
    contenido += '<p>Esta es una prueba de impresión térmica</p>';
    contenido += '<p>Si puede leer este texto, la impresora está configurada correctamente</p>';
    contenido += '</div>';
    contenido += '</div>';
    
    // Abrir ventana de impresión
    var ventana = window.open('', '_blank');
    ventana.document.write(contenido);
    ventana.document.close();
    
    setTimeout(function() {
        ventana.print();
        ventana.close();
        ocultarLoading();
        alert('Prueba de impresión enviada');
    }, 500);
}

// Restaurar backup
function restaurarBackup(nombre) {
    document.getElementById('backupRestaurar').value = nombre;
    var modal = new bootstrap.Modal(document.getElementById('modalRestaurar'));
    modal.show();
}

function confirmarRestauracion() {
    var nombre = document.getElementById('backupRestaurar').value;
    
    mostrarLoading();
    
    fetch('ajax_restaurar_backup.php?archivo=' + encodeURIComponent(nombre))
        .then(response => response.json())
        .then(data => {
            ocultarLoading();
            
            if (data.success) {
                alert('Backup restaurado exitosamente. El sistema se reiniciará.');
                window.location.reload();
            } else {
                alert('Error al restaurar backup: ' + data.message);
            }
        })
        .catch(error => {
            ocultarLoading();
            alert('Error de conexión');
            console.error('Error:', error);
        });
    
    var modal = bootstrap.Modal.getInstance(document.getElementById('modalRestaurar'));
    modal.hide();
}

// Eliminar backup
function eliminarBackup(nombre) {
    if (confirm('¿Eliminar el backup ' + nombre + '?')) {
        fetch('ajax_eliminar_backup.php?archivo=' + encodeURIComponent(nombre))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Backup eliminado exitosamente');
                    window.location.reload();
                } else {
                    alert('Error al eliminar backup: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error de conexión');
                console.error('Error:', error);
            });
    }
}

// Exportar configuración
function exportarConfiguracion() {
    var config = {
        empresa: document.querySelector('input[name="empresa_nombre"]').value,
        telefono: document.querySelector('input[name="telefono_empresa"]').value,
        direccion: document.querySelector('textarea[name="direccion_empresa"]').value,
        impresora_termica: document.getElementById('impresora_termica').checked,
        ancho_papel: document.getElementById('anchoPapel').value,
        fuente: document.getElementById('fuenteImpresora').value,
        fecha_exportacion: new Date().toISOString()
    };
    
    var blob = new Blob([JSON.stringify(config, null, 2)], {type: 'application/json'});
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'configuracion_' + new Date().toISOString().split('T')[0] + '.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Importar configuración
function importarConfiguracion() {
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';
    
    input.onchange = function(e) {
        var file = e.target.files[0];
        var reader = new FileReader();
        
        reader.onload = function(e) {
            try {
                var config = JSON.parse(e.target.result);
                
                // Verificar estructura
                if (config.empresa && config.telefono !== undefined) {
                    if (confirm('¿Importar configuración desde archivo?')) {
                        document.querySelector('input[name="empresa_nombre"]').value = config.empresa;
                        document.querySelector('input[name="telefono_empresa"]').value = config.telefono;
                        document.querySelector('textarea[name="direccion_empresa"]').value = config.direccion;
                        document.getElementById('impresora_termica').checked = config.impresora_termica;
                        document.getElementById('anchoPapel').value = config.ancho_papel || '80';
                        document.getElementById('fuenteImpresora').value = config.fuente || 'A';
                        
                        alert('Configuración cargada. Recuerde guardar los cambios.');
                    }
                } else {
                    alert('Archivo de configuración inválido');
                }
            } catch (error) {
                alert('Error al leer archivo: ' + error.message);
            }
        };
        
        reader.readAsText(file);
    };
    
    input.click();
}
</script>

<?php require_once 'footer.php'; ?>