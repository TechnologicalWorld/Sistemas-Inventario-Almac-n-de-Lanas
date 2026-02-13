<?php
session_start();

$titulo_pagina = "Gestión de Usuarios";
$icono_titulo = "fas fa-users";
$breadcrumb = [
    ['text' => 'Usuarios', 'link' => '#', 'active' => true]
];

require_once 'header.php';
require_once 'funciones.php';

// Verificar que sea administrador
verificarRol(['administrador']);

$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'crear':
            $codigo = limpiar($_POST['codigo']);
            $nombre = limpiar($_POST['nombre']);
            $usuario = limpiar($_POST['usuario']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $rol = limpiar($_POST['rol']);
            $telefono = limpiar($_POST['telefono']);
            
            // Verificar si el usuario ya existe
            $check_query = "SELECT id FROM usuarios WHERE usuario = ? OR codigo = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("ss", $usuario, $codigo);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $mensaje = "Error: El usuario o código ya existe";
                $tipo_mensaje = "danger";
            } else {
                $query = "INSERT INTO usuarios (codigo, nombre, usuario, password, rol, telefono) 
                         VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssss", $codigo, $nombre, $usuario, $password, $rol, $telefono);
                
                if ($stmt->execute()) {
                    $mensaje = "Usuario creado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al crear usuario: " . $stmt->error;
                    $tipo_mensaje = "danger";
                }
            }
            break;
            
        case 'editar':
            $id = $_POST['id'];
            $codigo = limpiar($_POST['codigo']);
            $nombre = limpiar($_POST['nombre']);
            $usuario = limpiar($_POST['usuario']);
            $rol = limpiar($_POST['rol']);
            $telefono = limpiar($_POST['telefono']);
            
            // Verificar si el usuario ya existe (excluyendo el actual)
            $check_query = "SELECT id FROM usuarios WHERE (usuario = ? OR codigo = ?) AND id != ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("ssi", $usuario, $codigo, $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $mensaje = "Error: El usuario o código ya está en uso por otro usuario";
                $tipo_mensaje = "danger";
            } else {
                $query = "UPDATE usuarios SET 
                         codigo = ?, nombre = ?, usuario = ?, rol = ?, telefono = ?
                         WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssssi", $codigo, $nombre, $usuario, $rol, $telefono, $id);
                
                if ($stmt->execute()) {
                    $mensaje = "Usuario actualizado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al actualizar usuario: " . $stmt->error;
                    $tipo_mensaje = "danger";
                }
            }
            break;
            
        case 'cambiar_estado':
            $id = $_POST['id'];
            $nuevo_estado = $_POST['nuevo_estado'];
            
            $query = "UPDATE usuarios SET activo = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $nuevo_estado, $id);
            
            if ($stmt->execute()) {
                $accion = $nuevo_estado == 1 ? 'activado' : 'desactivado';
                $mensaje = "Usuario $accion exitosamente";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al cambiar estado del usuario";
                $tipo_mensaje = "danger";
            }
            break;
            
        case 'resetear':
            $id = $_POST['id'];
            $nueva_password = password_hash('temp123', PASSWORD_DEFAULT);
            
            $query = "UPDATE usuarios SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $nueva_password, $id);
            
            if ($stmt->execute()) {
                $mensaje = "Contraseña reseteada exitosamente (temp123)";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al resetear contraseña";
                $tipo_mensaje = "danger";
            }
            break;
    }
}

// Obtener lista de usuarios
$query = "SELECT id, codigo, nombre, usuario, rol, activo, telefono, 
          DATE_FORMAT(fecha_creacion, '%d/%m/%Y') as fecha_creacion,
          DATE_FORMAT(ultimo_login, '%d/%m/%Y %H:%i') as ultimo_login
          FROM usuarios ORDER BY nombre";
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

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Lista de Usuarios</h5>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
                    <i class="fas fa-plus me-2"></i>Nuevo Usuario
                </button>
            </div>
            <div class="card-body">
                <!-- Buscador -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control" id="buscarUsuarios" 
                                   placeholder="Buscar usuario...">
                        </div>
                    </div>
                    <div class="col-md-8 text-end">
                        <div class="btn-group">
                            <button class="btn btn-outline-secondary" onclick="exportarUsuarios()">
                                <i class="fas fa-file-export me-1"></i>Exportar
                            </button>
                            <button class="btn btn-outline-info" onclick="imprimirTabla()">
                                <i class="fas fa-print me-1"></i>Imprimir
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="tablaUsuarios">
                        <thead class="table-light">
                            <tr>
                                <th width="80">Código</th>
                                <th>Nombre</th>
                                <th>Usuario</th>
                                <th width="120">Rol</th>
                                <th width="120">Teléfono</th>
                                <th width="100">Estado</th>
                                <th width="150">Último Login</th>
                                <th width="160">Fecha Creación</th>
                                <th width="140" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($usuario = $result->fetch_assoc()): ?>
                                <tr data-search="<?php echo strtolower($usuario['codigo'] . ' ' . $usuario['nombre'] . ' ' . $usuario['usuario'] . ' ' . $usuario['telefono']); ?>">
                                    <td>
                                        <span class="badge bg-dark"><?php echo $usuario['codigo']; ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo $usuario['nombre']; ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $usuario['usuario']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $usuario['rol'] == 'administrador' ? 'danger' : 'info'; ?>">
                                            <i class="fas fa-<?php echo $usuario['rol'] == 'administrador' ? 'shield-alt' : 'user-tag'; ?> me-1"></i>
                                            <?php echo ucfirst($usuario['rol']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($usuario['telefono']): ?>
                                            <a href="tel:<?php echo $usuario['telefono']; ?>" class="text-decoration-none">
                                                <i class="fas fa-phone me-1"></i><?php echo $usuario['telefono']; ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($usuario['activo'] == 1): ?>
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
                                            <?php echo $usuario['ultimo_login'] ?: 'Nunca'; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo $usuario['fecha_creacion']; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEditarUsuario"
                                                    onclick="cargarDatosUsuario(<?php echo htmlspecialchars(json_encode($usuario)); ?>)"
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning"
                                                    onclick="resetearPassword(<?php echo $usuario['id']; ?>, '<?php echo addslashes($usuario['nombre']); ?>')"
                                                    title="Resetear Contraseña">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-<?php echo $usuario['activo'] ? 'danger' : 'success'; ?>"
                                                    onclick="cambiarEstadoUsuario(<?php echo $usuario['id']; ?>, <?php echo $usuario['activo']; ?>, '<?php echo addslashes($usuario['nombre']); ?>')"
                                                    title="<?php echo $usuario['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                                <i class="fas fa-<?php echo $usuario['activo'] ? 'ban' : 'check'; ?>"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <h5>No hay usuarios registrados</h5>
                                            <p class="text-muted">Comienza creando tu primer usuario</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="9" class="text-muted small">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <i class="fas fa-info-circle me-1"></i>
                                            Total: <?php echo $result->num_rows; ?> usuarios
                                        </div>
                                        <div>
                                            <i class="fas fa-user-shield me-1"></i>
                                            Administradores: 
                                            <?php 
                                                // Contar administradores
                                                $admin_query = "SELECT COUNT(*) as total FROM usuarios WHERE rol = 'administrador'";
                                                $admin_result = $conn->query($admin_query);
                                                $admin_count = $admin_result->fetch_assoc()['total'];
                                                echo $admin_count;
                                            ?>
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

<!-- Modal Nuevo Usuario -->
<div class="modal fade" id="modalNuevoUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="formNuevoUsuario">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Nuevo Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="crear">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="codigo" required 
                                   pattern="[A-Za-z0-9]{3,20}" 
                                   title="Código de 3 a 20 caracteres alfanuméricos">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Usuario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="usuario" required
                                   pattern="[A-Za-z0-9]{3,20}"
                                   title="Usuario de 3 a 20 caracteres alfanuméricos">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="nuevaPassword" required
                                       minlength="6">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('nuevaPassword')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol <span class="text-danger">*</span></label>
                            <select class="form-select" name="rol" required>
                                <option value="">Seleccionar...</option>
                                <option value="administrador">Administrador</option>
                                <option value="vendedor">Vendedor</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" name="telefono" 
                                   pattern="[0-9]{7,15}"
                                   title="Número telefónico válido (7-15 dígitos)">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>Crear Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Usuario -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="formEditarUsuario">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Editar Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="editar">
                    <input type="hidden" name="id" id="editUsuarioId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="codigo" id="editCodigo" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre" id="editNombre" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Usuario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="usuario" id="editUsuario" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol <span class="text-danger">*</span></label>
                            <select class="form-select" name="rol" id="editRol" required>
                                <option value="administrador">Administrador</option>
                                <option value="vendedor">Vendedor</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" name="telefono" id="editTelefono">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado Actual</label>
                            <div class="alert alert-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span id="estadoActual"></span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                                            onclick="mostrarCambiarEstado()">
                                        <i class="fas fa-exchange-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección para cambiar contraseña (opcional) -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="accordion" id="accordionPassword">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" 
                                                data-bs-toggle="collapse" data-bs-target="#collapsePassword">
                                            <i class="fas fa-key me-2"></i>Cambiar Contraseña (Opcional)
                                        </button>
                                    </h2>
                                    <div id="collapsePassword" class="accordion-collapse collapse" 
                                         data-bs-parent="#accordionPassword">
                                        <div class="accordion-body">
                                            <div class="mb-3">
                                                <label class="form-label">Nueva Contraseña</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" 
                                                           name="nueva_password" id="nuevaPasswordEdit">
                                                    <button class="btn btn-outline-secondary" type="button" 
                                                            onclick="togglePassword('nuevaPasswordEdit')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted">Dejar en blanco para mantener la actual</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function cargarDatosUsuario(usuario) {
    document.getElementById('editUsuarioId').value = usuario.id;
    document.getElementById('editCodigo').value = usuario.codigo;
    document.getElementById('editNombre').value = usuario.nombre;
    document.getElementById('editUsuario').value = usuario.usuario;
    document.getElementById('editRol').value = usuario.rol;
    document.getElementById('editTelefono').value = usuario.telefono || '';
    
    // Mostrar estado actual
    const estadoTexto = usuario.activo == 1 ? 
        '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Activo</span>' :
        '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Inactivo</span>';
    document.getElementById('estadoActual').innerHTML = estadoTexto;
}

function resetearPassword(id, nombre) {
    if (confirm(`¿Resetear contraseña para ${nombre}?\n\nLa nueva contraseña será: temp123\nEl usuario deberá cambiarla en su próximo inicio de sesión.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const inputAction = document.createElement('input');
        inputAction.type = 'hidden';
        inputAction.name = 'action';
        inputAction.value = 'resetear';
        form.appendChild(inputAction);
        
        const inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'id';
        inputId.value = id;
        form.appendChild(inputId);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function cambiarEstadoUsuario(id, estadoActual, nombre) {
    const nuevoEstado = estadoActual == 1 ? 0 : 1;
    const accion = nuevoEstado == 1 ? 'activar' : 'desactivar';
    const mensaje = estadoActual == 1 ? 
        `¿Desactivar al usuario ${nombre}?\n\nEl usuario no podrá iniciar sesión hasta que sea reactivado.` :
        `¿Activar al usuario ${nombre}?\n\nEl usuario podrá iniciar sesión nuevamente.`;
    
    if (confirm(mensaje)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const inputAction = document.createElement('input');
        inputAction.type = 'hidden';
        inputAction.name = 'action';
        inputAction.value = 'cambiar_estado';
        form.appendChild(inputAction);
        
        const inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'id';
        inputId.value = id;
        form.appendChild(inputId);
        
        const inputNuevoEstado = document.createElement('input');
        inputNuevoEstado.type = 'hidden';
        inputNuevoEstado.name = 'nuevo_estado';
        inputNuevoEstado.value = nuevoEstado;
        form.appendChild(inputNuevoEstado);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function mostrarCambiarEstado() {
    const id = document.getElementById('editUsuarioId').value;
    const nombre = document.getElementById('editNombre').value;
    const estadoActual = document.getElementById('estadoActual').textContent.includes('Activo') ? 1 : 0;
    
    cambiarEstadoUsuario(id, estadoActual, nombre);
}

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.parentNode.querySelector('button');
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Buscar en la tabla
document.getElementById('buscarUsuarios').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#tablaUsuarios tbody tr');
    
    rows.forEach(row => {
        const searchText = row.getAttribute('data-search') || '';
        if (searchText.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

function exportarUsuarios() {
    // Implementar exportación a Excel/CSV
    alert('Funcionalidad de exportación en desarrollo');
}

function imprimirTabla() {
    const printContent = document.getElementById('tablaUsuarios').outerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <html>
            <head>
                <title>Lista de Usuarios - <?php echo EMPRESA_NOMBRE; ?></title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    @media print {
                        .no-print { display: none !important; }
                    }
                    body { padding: 20px; }
                    h1 { margin-bottom: 20px; }
                </style>
            </head>
            <body>
                <h1 class="text-center">Lista de Usuarios</h1>
                <p class="text-center text-muted"><?php echo date('d/m/Y H:i'); ?></p>
                ${printContent}
                <div class="mt-4 text-center text-muted">
                    <small>Impreso desde <?php echo SISTEMA_NOMBRE; ?></small>
                </div>
            </body>
        </html>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

// Validación de formularios
document.getElementById('formNuevoUsuario').addEventListener('submit', function(e) {
    const password = document.getElementById('nuevaPassword').value;
    if (password.length < 6) {
        e.preventDefault();
        alert('La contraseña debe tener al menos 6 caracteres');
        return false;
    }
    return true;
});

// Auto-generar código si está vacío
document.querySelector('input[name="codigo"]').addEventListener('blur', function() {
    if (!this.value.trim()) {
        // Generar código automático (ejemplo)
        const random = Math.floor(Math.random() * 1000);
        this.value = 'USR' + random.toString().padStart(3, '0');
    }
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
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

.btn-group .btn {
    border-radius: 4px !important;
    margin-right: 2px;
}

.input-group-text {
    background-color: #f8f9fa;
}

.accordion-button:not(.collapsed) {
    background-color: #e7f1ff;
    color: #0d6efd;
}
</style>

<?php require_once 'footer.php'; ?>