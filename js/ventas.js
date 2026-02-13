// ventas.js - Funciones específicas para el módulo de ventas

var carrito = [];
var clienteSeleccionado = null;

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    cargarCarritoDesdeStorage();
    actualizarCarrito();
    
    // Event listeners
    document.getElementById('buscarProducto').addEventListener('input', buscarProductos);
    document.getElementById('cantidadProducto').addEventListener('change', actualizarPrecioPorCantidad);
    document.getElementById('btnAgregarCarrito').addEventListener('click', agregarAlCarrito);
    document.getElementById('btnFinalizarVenta').addEventListener('click', finalizarVenta);
    
    // Buscar cliente
    document.getElementById('buscarCliente').addEventListener('input', buscarClientes);
});

// Buscar productos
function buscarProductos() {
    var busqueda = document.getElementById('buscarProducto').value;
    
    if (busqueda.length >= 2) {
        fetch('ajax_buscar_productos.php?q=' + encodeURIComponent(busqueda))
            .then(response => response.json())
            .then(data => {
                mostrarResultadosBusqueda(data);
            });
    }
}

// Mostrar resultados de búsqueda
function mostrarResultadosBusqueda(productos) {
    var resultadosDiv = document.getElementById('resultadosBusqueda');
    resultadosDiv.innerHTML = '';
    
    if (productos.length === 0) {
        resultadosDiv.innerHTML = '<div class="alert alert-info">No se encontraron productos</div>';
        return;
    }
    
    productos.forEach(function(producto) {
        var div = document.createElement('div');
        div.className = 'list-group-item list-group-item-action';
        div.innerHTML = `
            <div class="d-flex justify-content-between">
                <div>
                    <strong>${producto.codigo}</strong> - ${producto.nombre_color}<br>
                    <small class="text-muted">${producto.proveedor} - ${producto.categoria}</small>
                </div>
                <div class="text-end">
                    <small>Stock: ${producto.stock}</small><br>
                    <small>Precio: ${formatearMoneda(producto.precio_menor)}</small>
                </div>
            </div>
        `;
        
        div.addEventListener('click', function() {
            seleccionarProducto(producto);
        });
        
        resultadosDiv.appendChild(div);
    });
}

// Seleccionar producto
function seleccionarProducto(producto) {
    document.getElementById('productoId').value = producto.id;
    document.getElementById('codigoProducto').value = producto.codigo;
    document.getElementById('nombreProducto').value = producto.nombre_color;
    document.getElementById('stockDisponible').value = producto.stock;
    document.getElementById('precioProducto').value = producto.precio_menor;
    
    // Limpiar resultados
    document.getElementById('resultadosBusqueda').innerHTML = '';
    document.getElementById('buscarProducto').value = '';
    
    // Focus en cantidad
    document.getElementById('cantidadProducto').focus();
}

// Actualizar precio según cantidad
function actualizarPrecioPorCantidad() {
    var cantidad = parseInt(document.getElementById('cantidadProducto').value) || 0;
    var productoId = document.getElementById('productoId').value;
    var precioActual = parseFloat(document.getElementById('precioProducto').value) || 0;
    
    if (productoId && cantidad > 0) {
        fetch('ajax_obtener_precio.php?id=' + productoId + '&cantidad=' + cantidad)
            .then(response => response.json())
            .then(data => {
                if (data.precio !== precioActual) {
                    document.getElementById('precioProducto').value = data.precio;
                    document.getElementById('infoPrecio').textContent = 
                        cantidad > 5 ? 'Precio al por mayor' : 'Precio al por menor';
                }
            });
    }
}

// Agregar al carrito
function agregarAlCarrito() {
    var productoId = document.getElementById('productoId').value;
    var codigo = document.getElementById('codigoProducto').value;
    var nombre = document.getElementById('nombreProducto').value;
    var cantidad = parseInt(document.getElementById('cantidadProducto').value) || 0;
    var precio = parseFloat(document.getElementById('precioProducto').value) || 0;
    var stock = parseInt(document.getElementById('stockDisponible').value) || 0;
    
    // Validaciones
    if (!productoId) {
        alert('Seleccione un producto primero');
        return;
    }
    
    if (cantidad <= 0) {
        alert('Ingrese una cantidad válida');
        return;
    }
    
    if (cantidad > stock) {
        alert('Stock insuficiente. Disponible: ' + stock);
        return;
    }
    
    // Verificar si ya existe en el carrito
    var index = carrito.findIndex(item => item.productoId == productoId && item.precio == precio);
    
    if (index !== -1) {
        // Actualizar cantidad
        carrito[index].cantidad += cantidad;
        carrito[index].subtotal = carrito[index].cantidad * carrito[index].precio;
    } else {
        // Agregar nuevo
        var item = {
            productoId: productoId,
            codigo: codigo,
            nombre: nombre,
            cantidad: cantidad,
            precio: precio,
            subtotal: cantidad * precio,
            horaExtraccion: new Date().toLocaleTimeString('es-BO', {hour: '2-digit', minute:'2-digit'})
        };
        carrito.push(item);
    }
    
    // Actualizar interfaz
    actualizarCarrito();
    guardarCarritoEnStorage();
    
    // Limpiar formulario
    document.getElementById('cantidadProducto').value = '1';
    document.getElementById('productoId').value = '';
    document.getElementById('codigoProducto').value = '';
    document.getElementById('nombreProducto').value = '';
    document.getElementById('stockDisponible').value = '';
    document.getElementById('precioProducto').value = '';
    document.getElementById('infoPrecio').textContent = '';
    
    // Focus en búsqueda
    document.getElementById('buscarProducto').focus();
}

// Actualizar carrito en pantalla
function actualizarCarrito() {
    var tbody = document.getElementById('carritoBody');
    tbody.innerHTML = '';
    
    var subtotal = 0;
    
    carrito.forEach(function(item, index) {
        var tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.codigo}</td>
            <td>${item.nombre}</td>
            <td class="text-center">${item.cantidad}</td>
            <td class="text-end">${formatearMoneda(item.precio)}</td>
            <td class="text-end">${formatearMoneda(item.subtotal)}</td>
            <td>${item.horaExtraccion}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-danger" onclick="eliminarDelCarrito(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        subtotal += item.subtotal;
    });
    
    // Actualizar totales
    document.getElementById('subtotalCarrito').textContent = formatearMoneda(subtotal);
    document.getElementById('totalCarrito').textContent = formatearMoneda(subtotal);
    
    // Habilitar/deshabilitar botón finalizar
    document.getElementById('btnFinalizarVenta').disabled = carrito.length === 0;
}

// Eliminar del carrito
function eliminarDelCarrito(index) {
    if (confirm('¿Eliminar este producto del carrito?')) {
        carrito.splice(index, 1);
        actualizarCarrito();
        guardarCarritoEnStorage();
    }
}

// Buscar clientes
function buscarClientes() {
    var busqueda = document.getElementById('buscarCliente').value;
    
    if (busqueda.length >= 2) {
        fetch('ajax_buscar_clientes.php?q=' + encodeURIComponent(busqueda))
            .then(response => response.json())
            .then(data => {
                mostrarResultadosClientes(data);
            });
    }
}

// Mostrar resultados de clientes
function mostrarResultadosClientes(clientes) {
    var resultadosDiv = document.getElementById('resultadosClientes');
    resultadosDiv.innerHTML = '';
    
    clientes.forEach(function(cliente) {
        var div = document.createElement('div');
        div.className = 'list-group-item list-group-item-action';
        div.innerHTML = `
            <div class="d-flex justify-content-between">
                <div>
                    <strong>${cliente.codigo}</strong> - ${cliente.nombre}<br>
                    <small class="text-muted">${cliente.telefono || 'Sin teléfono'}</small>
                </div>
                <div class="text-end">
                    <small>Límite: ${formatearMoneda(cliente.limite_credito)}</small><br>
                    <small>Saldo: ${formatearMoneda(cliente.saldo_actual)}</small>
                </div>
            </div>
        `;
        
        div.addEventListener('click', function() {
            seleccionarCliente(cliente);
        });
        
        resultadosDiv.appendChild(div);
    });
}

// Seleccionar cliente
function seleccionarCliente(cliente) {
    clienteSeleccionado = cliente;
    document.getElementById('clienteId').value = cliente.id;
    document.getElementById('nombreCliente').value = cliente.nombre;
    document.getElementById('infoCliente').innerHTML = `
        <small class="text-muted">
            Código: ${cliente.codigo} | 
            Teléfono: ${cliente.telefono || 'No especificado'} | 
            Saldo: ${formatearMoneda(cliente.saldo_actual)}
        </small>
    `;
    
    document.getElementById('resultadosClientes').innerHTML = '';
    document.getElementById('buscarCliente').value = '';
}

// Finalizar venta
function finalizarVenta() {
    if (carrito.length === 0) {
        alert('El carrito está vacío');
        return;
    }
    
    var tipoPago = document.querySelector('input[name="tipoPago"]:checked');
    if (!tipoPago) {
        alert('Seleccione un tipo de pago');
        return;
    }
    
    var datos = {
        clienteId: document.getElementById('clienteId').value,
        tipoPago: tipoPago.value,
        productos: carrito
    };
    
    mostrarLoading();
    
    fetch('ajax_finalizar_venta.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(datos)
    })
    .then(response => response.json())
    .then(data => {
        ocultarLoading();
        
        if (data.success) {
            alert('Venta registrada exitosamente\nCódigo: ' + data.codigoVenta);
            
            // Imprimir recibo si se solicita
            if (confirm('¿Desea imprimir el recibo?')) {
                imprimirRecibo(data.ventaId);
            }
            
            // Limpiar todo
            carrito = [];
            clienteSeleccionado = null;
            actualizarCarrito();
            localStorage.removeItem('carritoVenta');
            
            // Redireccionar o limpiar formulario
            window.location.href = 'ventas.php?success=1';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        ocultarLoading();
        alert('Error al procesar la venta');
        console.error('Error:', error);
    });
}

// Guardar carrito en localStorage
function guardarCarritoEnStorage() {
    localStorage.setItem('carritoVenta', JSON.stringify(carrito));
}

// Cargar carrito desde localStorage
function cargarCarritoDesdeStorage() {
    var carritoGuardado = localStorage.getItem('carritoVenta');
    if (carritoGuardado) {
        carrito = JSON.parse(carritoGuardado);
    }
}

// Función para venta rápida (sin cliente)
function activarVentaRapida() {
    document.getElementById('seleccionCliente').style.display = 'none';
    document.getElementById('ventaRapidaInfo').style.display = 'block';
    document.getElementById('clienteId').value = '';
    clienteSeleccionado = null;
}

// Función para desactivar venta rápida
function desactivarVentaRapida() {
    document.getElementById('seleccionCliente').style.display = 'block';
    document.getElementById('ventaRapidaInfo').style.display = 'none';
    document.getElementById('buscarCliente').focus();
}