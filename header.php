<?php

require_once 'config.php';
verificarSesion();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($titulo_pagina) ? $titulo_pagina . ' - ' : ''; ?><?php echo SISTEMA_NOMBRE; ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="css/estilo.css">
</head>
<body>
    <!-- Overlay para móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Botón menú móvil -->
    <button class="mobile-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <!-- Logo y marca -->
        <div class="sidebar-brand">
            <div class="brand-logo">
                <i class="fas fa-store"></i>
            </div>
            <div class="brand-text">
                <h5 class="mb-0"><?php echo EMPRESA_NOMBRE; ?></h5>
                <small><?php echo SISTEMA_NOMBRE; ?></small>
            </div>
            <button class="sidebar-collapse" id="sidebarCollapse">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <!-- Información del usuario -->
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-info">
                <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></h6>
                <span class="user-role"><?php echo ucfirst($_SESSION['usuario_rol']); ?></span>
            </div>
        </div>
        
        <!-- Menú de navegación -->
        <nav class="sidebar-nav">
            <?php 
            if ($_SESSION['usuario_rol'] == 'administrador') {
                include 'sidebar_admin.php';
            } else {
                include 'sidebar_vendedor.php';
            }
            ?>
        </nav>
        
        <!-- Botón cerrar sesión -->
        <div class="sidebar-footer">
            <a href="logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar Sesión</span>
            </a>
        </div>
    </aside>
    
    <!-- CONTENIDO PRINCIPAL -->
    <main class="main-content" id="mainContent">
        <div class="content-container">
            <!-- Breadcrumb -->
            <?php if (isset($breadcrumb) && !empty($breadcrumb)): ?>
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="dashboard.php">
                            <i class="fas fa-home"></i> Inicio
                        </a>
                    </li>
                    <?php foreach ($breadcrumb as $item): ?>
                        <li class="breadcrumb-item <?php echo $item['active'] ? 'active' : ''; ?>">
                            <?php if ($item['active']): ?>
                                <?php echo $item['text']; ?>
                            <?php else: ?>
                                <a href="<?php echo $item['link']; ?>"><?php echo $item['text']; ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php endif; ?>
            
            <!-- Título de página -->
            <?php if (isset($titulo_pagina)): ?>
            <div class="page-header">
                <div class="page-title">
                    <?php if (isset($icono_titulo)): ?>
                        <i class="<?php echo $icono_titulo; ?>"></i>
                    <?php endif; ?>
                    <h2><?php echo $titulo_pagina; ?></h2>
                </div>
                <?php if (isset($acciones_titulo)): ?>
                    <div class="page-actions">
                        <?php echo $acciones_titulo; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Contenido de la página -->
            <div class="page-content">