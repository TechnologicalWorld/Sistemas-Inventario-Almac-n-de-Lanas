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
        <span>Gestión de Usuarios</span>
    </li>
    <li>
        <a href="usuarios.php" class="nav-link <?php echo $current_page == 'usuarios.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Usuarios</span>
        </a>
    </li>
    
    <li class="nav-section">
        <span>Proveedores y Compras</span>
    </li>
    <li>
        <a href="proveedores.php" class="nav-link <?php echo $current_page == 'proveedores.php' ? 'active' : ''; ?>">
            <i class="fas fa-truck"></i>
            <span>Proveedores</span>
        </a>
    </li>
    <li>
        <a href="categorias.php" class="nav-link <?php echo $current_page == 'categorias.php' ? 'active' : ''; ?>">
            <i class="fas fa-tags"></i>
            <span>Categorías</span>
        </a>
    </li>
    <li>
        <a href="productos.php" class="nav-link <?php echo $current_page == 'productos.php' ? 'active' : ''; ?>">
            <i class="fas fa-palette"></i>
            <span>Productos</span>
        </a>
    </li>
    <li>
        <a href="ingresar_stock.php" class="nav-link <?php echo $current_page == 'ingresar_stock.php' ? 'active' : ''; ?>">
            <i class="fas fa-boxes"></i>
            <span>Ingresar Stock</span>
        </a>
    </li>
    
    <li class="nav-section">
        <span>Inventario</span>
    </li>
    <li>
        <a href="inventario.php" class="nav-link <?php echo $current_page == 'inventario.php' ? 'active' : ''; ?>">
            <i class="fas fa-warehouse"></i>
            <span>Ver Inventario</span>
        </a>
    </li>
    <li>
        <a href="ajustar_inventario.php" class="nav-link <?php echo $current_page == 'ajustar_inventario.php' ? 'active' : ''; ?>">
            <i class="fas fa-sliders-h"></i>
            <span>Ajustar Inventario</span>
        </a>
    </li>
    
    <li class="nav-section">
        <span>Ventas</span>
    </li>
    <li>
        <a href="clientes.php" class="nav-link <?php echo $current_page == 'clientes.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-friends"></i>
            <span>Clientes</span>
        </a>
    </li>
    <li>
        <a href="ventas.php" class="nav-link <?php echo $current_page == 'ventas.php' ? 'active' : ''; ?>">
            <i class="fas fa-cash-register"></i>
            <span>Punto de Venta</span>
        </a>
    </li>
    <li>
        <a href="historial_ventas.php" class="nav-link <?php echo $current_page == 'historial_ventas.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span>Historial de Ventas</span>
        </a>
    </li>
    
    <li class="nav-section">
        <span>Cuentas por Cobrar</span>
    </li>
    <li>
        <a href="cuentas_cobrar.php" class="nav-link <?php echo $current_page == 'cuentas_cobrar.php' ? 'active' : ''; ?>">
            <i class="fas fa-hand-holding-usd"></i>
            <span>Clientes con Deuda</span>
        </a>
    </li>
    <!--<li>
        <a href="registrar_abono.php" class="nav-link <?php echo $current_page == 'registrar_abono.php' ? 'active' : ''; ?>">
            <i class="fas fa-money-bill-wave"></i>
            <span>Registrar Abono</span>
        </a>
    </li>-->
    
    <li class="nav-section">
        <span>Cuentas por Pagar</span>
    </li>
    <li>
        <a href="cuentas_pagar.php" class="nav-link <?php echo $current_page == 'cuentas_pagar.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Proveedores por Pagar</span>
        </a>
    </li>
    <li>
        <a href="registrar_pago_proveedor.php" class="nav-link <?php echo $current_page == 'registrar_pago_proveedor.php' ? 'active' : ''; ?>">
            <i class="fas fa-credit-card"></i>
            <span>Pagar a Proveedor</span>
        </a>
    </li>
    
    <li class="nav-section">
        <span>Caja y Movimientos</span>
    </li>
    <li>
        <a href="movimientos_caja.php" class="nav-link <?php echo $current_page == 'movimientos_caja.php' ? 'active' : ''; ?>">
            <i class="fas fa-exchange-alt"></i>
            <span>Movimientos de Caja</span>
        </a>
    </li>
    <li>
        <a href="registrar_gasto.php" class="nav-link <?php echo $current_page == 'registrar_gasto.php' ? 'active' : ''; ?>">
            <i class="fas fa-receipt"></i>
            <span>Registrar Gasto</span>
        </a>
    </li>
    
    <li class="nav-section">
        <span>Otros</span>
    </li>
    <li>
        <a href="otros_productos.php" class="nav-link <?php echo $current_page == 'otros_productos.php' ? 'active' : ''; ?>">
            <i class="fas fa-box-open"></i>
            <span>Productos Varios</span>
        </a>
    </li>
    <li>
        <a href="reportes.php" class="nav-link <?php echo $current_page == 'reportes.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reportes</span>
        </a>
    </li>
    <li>
        <a href="configuracion.php" class="nav-link <?php echo $current_page == 'configuracion.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Configuración</span>
        </a>
    </li>
</ul>