// main.js - Funciones JavaScript globales

// Inicializar tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Inicializar popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});

// Función para confirmar eliminación
function confirmarEliminacion(event, mensaje = "¿Está seguro de eliminar este registro?") {
    if (!confirm(mensaje)) {
        event.preventDefault();
        return false;
    }
    mostrarLoading();
    return true;
}

// Función para copiar al portapapeles
function copiarAlPortapapeles(texto) {
    navigator.clipboard.writeText(texto).then(function() {
        alert('Copiado al portapapeles: ' + texto);
    }, function(err) {
        console.error('Error al copiar: ', err);
    });
}

// Función para formatear número con separadores
function formatearNumero(numero) {
    return new Intl.NumberFormat('es-ES').format(numero);
}

// Función para formatear moneda
function formatearMoneda(monto) {
    return new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'BOB',
        minimumFractionDigits: 2
    }).format(monto);
}

// Validación de formularios
function validarFormulario(formId) {
    var form = document.getElementById(formId);
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return false;
    }
    mostrarLoading();
    return true;
}

// Función para buscar en tablas
function buscarEnTabla(inputId, tableId) {
    var input = document.getElementById(inputId);
    var filter = input.value.toUpperCase();
    var table = document.getElementById(tableId);
    var tr = table.getElementsByTagName("tr");
    
    for (var i = 0; i < tr.length; i++) {
        var td = tr[i].getElementsByTagName("td");
        var mostrar = false;
        
        for (var j = 0; j < td.length; j++) {
            if (td[j]) {
                var txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    mostrar = true;
                    break;
                }
            }
        }
        
        if (mostrar) {
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
}

// Función para calcular totales
function calcularTotal() {
    var subtotal = 0;
    var descuento = 0;
    
    // Calcular subtotal de productos
    var filasProductos = document.querySelectorAll('.fila-producto');
    filasProductos.forEach(function(fila) {
        var cantidad = parseFloat(fila.querySelector('.cantidad').value) || 0;
        var precio = parseFloat(fila.querySelector('.precio').value) || 0;
        subtotal += cantidad * precio;
    });
    
    // Calcular descuento si existe
    var inputDescuento = document.getElementById('descuento');
    if (inputDescuento) {
        descuento = parseFloat(inputDescuento.value) || 0;
    }
    
    var total = subtotal - descuento;
    
    // Actualizar valores en pantalla
    document.getElementById('subtotal').textContent = formatearMoneda(subtotal);
    document.getElementById('total').textContent = formatearMoneda(total);
    
    return total;
}

// Función para actualizar cantidad y precio
function actualizarPrecioCantidad(selectId, cantidadId, precioId, productoId) {
    var select = document.getElementById(selectId);
    var cantidad = document.getElementById(cantidadId);
    var precio = document.getElementById(precioId);
    
    if (select && cantidad && precio) {
        // Aquí se haría una petición AJAX para obtener el precio según cantidad
        // Por ahora, solo un ejemplo
        if (cantidad.value > 5) {
            // Precio mayor
            precio.value = '75.00';
        } else {
            // Precio menor
            precio.value = '78.00';
        }
    }
}

// Función para mostrar/ocultar secciones
function toggleSeccion(seccionId, mostrar = true) {
    var seccion = document.getElementById(seccionId);
    if (seccion) {
        seccion.style.display = mostrar ? 'block' : 'none';
    }
}

// Función para generar PDF del recibo
function imprimirRecibo(ventaId) {
    window.open('generar_recibo.php?id=' + ventaId, '_blank');
}

// Función para exportar a Excel
function exportarExcel(tablaId, nombreArchivo) {
    var tabla = document.getElementById(tablaId);
    var html = tabla.outerHTML;
    
    // Crear un blob con el contenido HTML
    var blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    
    // Crear un enlace de descarga
    var enlace = document.createElement('a');
    enlace.href = URL.createObjectURL(blob);
    enlace.download = nombreArchivo + '.xls';
    enlace.click();
}

// Funciones para manejo de sesión
function verificarSesionActiva() {
    // Verificar cada 5 minutos si la sesión sigue activa
    setInterval(function() {
        fetch('verificar_sesion.php')
            .then(response => response.json())
            .then(data => {
                if (!data.activa) {
                    window.location.href = 'login.php?expirado=1';
                }
            });
    }, 300000); // 5 minutos
}

// Inicializar verificaciones
document.addEventListener('DOMContentLoaded', function() {
    verificarSesionActiva();
});