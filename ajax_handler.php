<?php
/**
 * AJAX HANDLER - Punto de Venta
 * Maneja todas las solicitudes AJAX del POS
 */

session_start();
require_once 'config.php';
require_once 'funciones.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    
    case 'buscar_productos':
        buscarProductos();
        break;
        
    case 'buscar_por_codigo':
        buscarPorCodigo();
        break;
        
    case 'buscar_clientes':
        buscarClientes();
        break;
        
    case 'nuevo_cliente':
        nuevoCliente();
        break;
        
    case 'finalizar_venta':
        finalizarVenta();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}

/**
 * BUSCAR PRODUCTOS POR TEXTO
 */
function buscarProductos() {
    global $conn;
    
    $busqueda = $_GET['q'] ?? '';
    $busqueda = '%' . $conn->real_escape_string($busqueda) . '%';
    
    $query = "SELECT 
                p.id, 
                p.codigo, 
                p.nombre_color as nombre,
                pr.nombre as proveedor,
                c.nombre as categoria,
                COALESCE(i.total_subpaquetes, 0) as stock,
                p.precio_menor,
                p.precio_mayor,
                p.activo
              FROM productos p
              JOIN proveedores pr ON p.proveedor_id = pr.id
              JOIN categorias c ON p.categoria_id = c.id
              LEFT JOIN inventario i ON p.id = i.producto_id
              WHERE (p.codigo LIKE ? OR p.nombre_color LIKE ? OR pr.nombre LIKE ?)
              AND p.activo = 1
              ORDER BY pr.nombre, p.codigo
              LIMIT 20";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $busqueda, $busqueda, $busqueda);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'productos' => $productos
    ]);
}

/**
 * BUSCAR PRODUCTO POR CÓDIGO EXACTO (BARRAS)
 */
function buscarPorCodigo() {
    global $conn;
    
    $codigo = $_GET['codigo'] ?? '';
    $codigo = $conn->real_escape_string($codigo);
    
    $query = "SELECT 
                p.id, 
                p.codigo, 
                p.nombre_color as nombre,
                pr.nombre as proveedor,
                c.nombre as categoria,
                COALESCE(i.total_subpaquetes, 0) as stock,
                p.precio_menor,
                p.precio_mayor
              FROM productos p
              JOIN proveedores pr ON p.proveedor_id = pr.id
              JOIN categorias c ON p.categoria_id = c.id
              LEFT JOIN inventario i ON p.id = i.producto_id
              WHERE p.codigo = ? AND p.activo = 1
              LIMIT 1";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'producto' => $row
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Producto no encontrado'
        ]);
    }
}

/**
 * BUSCAR CLIENTES
 */
function buscarClientes() {
    global $conn;
    
    $busqueda = $_GET['q'] ?? '';
    $busqueda = '%' . $conn->real_escape_string($busqueda) . '%';
    
    $query = "SELECT 
                id, 
                codigo, 
                nombre, 
                telefono,
                limite_credito,
                saldo_actual
              FROM clientes 
              WHERE (codigo LIKE ? OR nombre LIKE ? OR telefono LIKE ?)
              AND activo = 1
              ORDER BY nombre
              LIMIT 20";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $busqueda, $busqueda, $busqueda);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $clientes = [];
    while ($row = $result->fetch_assoc()) {
        $clientes[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'clientes' => $clientes
    ]);
}

/**
 * CREAR NUEVO CLIENTE RÁPIDO
 */
function nuevoCliente() {
    global $conn;
    
    $nombre = $_POST['nombre'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $tipo_doc = $_POST['tipo_doc'] ?? 'DNI';
    $documento = $_POST['documento'] ?? '';
    $ciudad = $_POST['ciudad'] ?? '';
    
    if (empty($nombre)) {
        echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
        return;
    }
    
    // Generar código de cliente
    $codigo = 'CLI' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    $query = "INSERT INTO clientes (
                codigo, nombre, ciudad, telefono, 
                tipo_documento, numero_documento,
                limite_credito, saldo_actual, activo
              ) VALUES (?, ?, ?, ?, ?, ?, 0, 0, 1)";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssss", $codigo, $nombre, $ciudad, $telefono, $tipo_doc, $documento);
    
    if ($stmt->execute()) {
        $cliente_id = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Cliente registrado exitosamente',
            'cliente_id' => $cliente_id,
            'codigo' => $codigo
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al registrar cliente: ' . $conn->error
        ]);
    }
}

/**
 * FINALIZAR VENTA - CORREGIDO Y MEJORADO
 */
function finalizarVenta() {
    global $conn;
    
    // Obtener datos JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Datos no válidos']);
        return;
    }
    
    // Validar datos obligatorios
    if (empty($data['productos']) || !is_array($data['productos']) || count($data['productos']) == 0) {
        echo json_encode(['success' => false, 'message' => 'No hay productos en el carrito']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        // Generar código de venta
        $codigo_venta = generarCodigoVenta();
        
        // Determinar cliente
        $cliente_id = null;
        $cliente_contado = null;
        $es_venta_rapida = 0;
        
        if (isset($data['cliente_id']) && $data['cliente_id'] > 0) {
            $cliente_id = $data['cliente_id'];
            $es_venta_rapida = 0;
        } else {
            $cliente_contado = 'VENTA RÁPIDA';
            $es_venta_rapida = 1;
        }
        
        // Calcular tipo de venta (mayor/menor)
        $tipo_venta = 'menor';
        foreach ($data['productos'] as $prod) {
            if (($prod['cantidad'] ?? 0) > 5) {
                $tipo_venta = 'mayor';
                break;
            }
        }
        
        // Insertar venta
        $query_venta = "INSERT INTO ventas (
            codigo_venta, cliente_id, cliente_contado, vendedor_id,
            tipo_venta, tipo_pago, subtotal, descuento, total,
            pago_inicial, metodo_pago_inicial, referencia_pago_inicial,
            es_venta_rapida, estado, fecha, hora_inicio, observaciones
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', CURDATE(), CURTIME(), ?)";
        
        $stmt = $conn->prepare($query_venta);
        $vendedor_id = $_SESSION['usuario_id'];
        
        $stmt->bind_param(
            "sissssddddsisss",
            $codigo_venta,
            $cliente_id,
            $cliente_contado,
            $vendedor_id,
            $tipo_venta,
            $data['tipo_pago'],
            $data['subtotal'],
            $data['descuento'],
            $data['total'],
            $data['pago_inicial'],
            $data['metodo_pago'],
            $data['referencia_pago'],
            $es_venta_rapida,
            $data['observacion']
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Error al insertar venta: ' . $conn->error);
        }
        
        $venta_id = $conn->insert_id;
        
        // Insertar detalles de la venta
        foreach ($data['productos'] as $producto) {
            
            // Verificar stock antes de insertar
            if (!verificarStock($producto['producto_id'], $producto['cantidad'])) {
                throw new Exception('Stock insuficiente para producto ID: ' . $producto['producto_id']);
            }
            
            $query_detalle = "INSERT INTO venta_detalles (
                venta_id, producto_id, cantidad_subpaquetes,
                precio_unitario, subtotal, hora_extraccion, usuario_extraccion
            ) VALUES (?, ?, ?, ?, ?, CURTIME(), ?)";
            
            $stmt = $conn->prepare($query_detalle);
            $stmt->bind_param(
                "iiiddi",
                $venta_id,
                $producto['producto_id'],
                $producto['cantidad'],
                $producto['precio_unitario'],
                $producto['subtotal'],
                $vendedor_id
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Error al insertar detalle: ' . $conn->error);
            }
            
            // ACTUALIZAR INVENTARIO - Manualmente (en caso trigger no funcione)
            $query_update_inventario = "UPDATE inventario 
                SET 
                    paquetes_completos = FLOOR(((paquetes_completos * subpaquetes_por_paquete) + subpaquetes_sueltos - ?) / subpaquetes_por_paquete),
                    subpaquetes_sueltos = MOD(((paquetes_completos * subpaquetes_por_paquete) + subpaquetes_sueltos - ?), subpaquetes_por_paquete),
                    fecha_ultima_salida = CURDATE()
                WHERE producto_id = ?";
            
            $stmt = $conn->prepare($query_update_inventario);
            $stmt->bind_param("iii", $producto['cantidad'], $producto['cantidad'], $producto['producto_id']);
            $stmt->execute();
        }
        
        // Registrar movimiento de caja si hay pago
        if ($data['pago_inicial'] > 0) {
            $categoria = 'venta_contado';
            if ($data['tipo_pago'] == 'credito') {
                $categoria = 'pago_inicial';
            }
            
            $descripcion = $cliente_id ? 'Pago de cliente' : 'Venta rápida';
            
            $query_caja = "INSERT INTO movimientos_caja (
                tipo, categoria, monto, descripcion, referencia_venta,
                fecha, hora, usuario_id
            ) VALUES ('ingreso', ?, ?, ?, ?, CURDATE(), CURTIME(), ?)";
            
            $stmt = $conn->prepare($query_caja);
            $stmt->bind_param(
                "sdssi",
                $categoria,
                $data['pago_inicial'],
                $descripcion,
                $codigo_venta,
                $vendedor_id
            );
            $stmt->execute();
        }
        
        // Actualizar estado de la venta si está pagada completamente
        if ($data['pago_inicial'] >= $data['total']) {
            $query_update = "UPDATE ventas SET estado = 'pagada', hora_fin = CURTIME() WHERE id = ?";
            $stmt = $conn->prepare($query_update);
            $stmt->bind_param("i", $venta_id);
            $stmt->execute();
        } else {
            $query_update = "UPDATE ventas SET hora_fin = CURTIME() WHERE id = ?";
            $stmt = $conn->prepare($query_update);
            $stmt->bind_param("i", $venta_id);
            $stmt->execute();
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Venta registrada exitosamente',
            'venta_id' => $venta_id,
            'codigo_venta' => $codigo_venta
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}