<?php

?>
<ul class="nav-menu">
    <li>
        <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>
    </li>
    
    <li class="nav-section">
        <span>Ventas</span>
    </li>
    <li>
        <a href="ventas.php" class="nav-link <?php echo $current_page == 'ventas.php' ? 'active' : ''; ?>">
            <i class="fas fa-cash-register"></i>
            <span>Nueva Venta</span>
        </a>
    </li>
    <li>
        <a href="historial_ventas.php" class="nav-link <?php echo $current_page == 'historial_ventas.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span>Mis Ventas</span>
        </a>
    </li>
    
    <li class="nav-section">
        <span>Clientes</span>
    </li>
    <li>
        <a href="clientes.php" class="nav-link <?php echo $current_page == 'clientes.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-friends"></i>
            <span>Buscar Cliente</span>
        </a>
    </li>
    <li>
        <!--<a href="registrar_abono.php" class="nav-link <?php echo $current_page == 'registrar_abono.php' ? 'active' : ''; ?>">
            <i class="fas fa-money-bill-wave"></i>
            <span>Registrar Abono</span>
        </a>-->
    </li>
    
    <li class="nav-section">
        <span>Productos</span>
    </li>
    <li>
        <a href="inventario.php" class="nav-link <?php echo $current_page == 'inventario.php' ? 'active' : ''; ?>">
            <i class="fas fa-search"></i>
            <span>Buscar Productos</span>
        </a>
    </li>
    <li>
        <a href="otros_productos.php" class="nav-link <?php echo $current_page == 'otros_productos.php' ? 'active' : ''; ?>">
            <i class="fas fa-box-open"></i>
            <span>Productos Varios</span>
        </a>
    </li>
    
    <li class="nav-section">
        <span>Otros</span>
    </li>
    <li>
        <a href="movimientos_caja.php" class="nav-link <?php echo $current_page == 'movimientos_caja.php' ? 'active' : ''; ?>">
            <i class="fas fa-exchange-alt"></i>
            <span>Mis Movimientos</span>
        </a>
    </li>
</ul>