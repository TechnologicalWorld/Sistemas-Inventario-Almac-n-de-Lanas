<?php

session_start();

if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit();
}

require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = limpiar($_POST['usuario']);
    $password = $_POST['password'];
    
    $query = "SELECT id, nombre, usuario, password, rol, activo 
              FROM usuarios 
              WHERE usuario = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verificar si el usuario está activo primero
        if ($user['activo'] != 1) {
            $error = "Usuario inactivo. Contacte al administrador.";
        } else {
            // Verificación de contraseña mejorada
            $password_verificado = false;
            
            // Verificar si es una contraseña hasheada válida
            if (password_verify($password, $user['password'])) {
                $password_verificado = true;
            }
            // Verificación temporal para credenciales por defecto (desarrollo)
            elseif ($usuario == 'admin' && $password == 'admin123') {
                $password_verificado = true;
            }
            elseif ($usuario == 'vendedor' && $password == 'vendedor123') {
                $password_verificado = true;
            }
            
            if ($password_verificado) {
                // Crear sesión
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $user['nombre'];
                $_SESSION['usuario_rol'] = $user['rol'];
                
                // Actualizar último login
                $update_query = "UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                
                // Redirigir al dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Usuario o contraseña incorrectos.";
            }
        }
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SISTEMA_NOMBRE; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
    <style>
        .login-body {
            background: linear-gradient(135deg, #80bd74 0%, #084b17 100%);
            min-height: 100vh;
        }
        .login-card {
            border-radius: 15px;
            border: none;
        }
        .login-logo {
            max-width: 120px;
            height: auto;
        }
        .card-header {
            border-radius: 15px 15px 0 0 !important;
        }
    </style>
</head>
<body class="login-body">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5">
                <div class="card login-card shadow-lg">
                    <div class="card-header text-center bg-success text-white py-3">
                        <h4 class="mb-0"><?php echo EMPRESA_NOMBRE; ?></h4>
                        <small><?php echo SISTEMA_NOMBRE; ?></small>
                    </div>
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <img src="img/logo.png" alt="Logo" class="login-logo mb-3" onerror="this.style.display='none'">
                            <h5 class="text-muted">Iniciar Sesión</h5>
                        </div>
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="usuario" class="form-label">Usuario</label>
                                <input type="text" class="form-control" id="usuario" name="usuario" 
                                       placeholder="Ingrese su usuario" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Ingrese su contraseña" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg">
                                    Iniciar Sesión
                                </button>
                            </div>
                        </form>
                        
                        <!-- <div class="mt-4 text-center">
                            <p class="text-muted mb-2"><small>Credenciales por defecto para pruebas:</small></p>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="card bg-light border-0">
                                        <div class="card-body p-2">
                                            <small class="text-dark fw-bold">Administrador</small><br>
                                            <small class="text-muted">admin / admin123</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="card bg-light border-0">
                                        <div class="card-body p-2">
                                            <small class="text-dark fw-bold">Vendedor</small><br>
                                            <small class="text-muted">vendedor / vendedor123</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div> -->
                    </div>
                    <div class="card-footer text-center text-muted bg-light">
                        <small>&copy; <?php echo date('Y'); ?> - <?php echo EMPRESA_NOMBRE; ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>