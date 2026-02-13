<?php

require_once 'config.php';
require_once 'funciones.php';

// Activar reporte de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);


while (ob_get_level()) ob_end_clean();

// Headers correctos
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Log para debugging
error_log("AJAX Request - Action: " . $action);

switch ($action) {
    case 'buscar_clientes':
        buscarClientes();
        break;
    case 'buscar_productos':
        buscarProductos();
        break;
    case 'nuevo_cliente':
        nuevoCliente();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida: ' . $action]);
}

function buscarClientes() {
    global $conn;
    
    $busqueda = $_GET['q'] ?? '';
    $busqueda = trim($busqueda);
    
    if (strlen($busqueda) < 1) {
        echo json_encode(['success' => false, 'message' => 'Búsqueda muy corta']);
        return;
    }
    
    // Query simplificada para prueba
    $query = "SELECT id, codigo, nombre, telefono, saldo_actual, limite_credito 
              FROM clientes 
              WHERE activo = 1 
                AND (nombre LIKE ? OR codigo LIKE ? OR telefono LIKE ?)
              ORDER BY nombre 
              LIMIT 10";
    
    $searchTerm = "%{$busqueda}%";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error en la consulta: ' . $conn->error]);
        return;
    }
    
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $clientes = [];
    while ($row = $result->fetch_assoc()) {
        $clientes[] = [
            'id' => (int)$row['id'],
            'codigo' => $row['codigo'],
            'nombre' => $row['nombre'],
            'telefono' => $row['telefono'] ?? '',
            'saldo_actual' => (float)($row['saldo_actual'] ?? 0),
            'limite_credito' => (float)($row['limite_credito'] ?? 0)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'clientes' => $clientes
    ]);
}

function buscarProductos() {
    global $conn;
    
    $busqueda = $_GET['q'] ?? '';
    $busqueda = trim($busqueda);
    
    if (strlen($busqueda) < 1) {
        echo json_encode(['success' => false, 'message' => 'Búsqueda muy corta']);
        return;
    }
    
    // Query simplificada para prueba
    $query = "SELECT 
                p.id, 
                p.codigo, 
                p.nombre_color as nombre,
                pr.nombre as proveedor,
                COALESCE(i.total_subpaquetes, 0) as stock,
                p.precio_menor,
                p.precio_mayor
              FROM productos p
              JOIN proveedores pr ON p.proveedor_id = pr.id
              LEFT JOIN inventario i ON p.id = i.producto_id
              WHERE p.activo = 1 
                AND (p.codigo LIKE ? OR p.nombre_color LIKE ? OR pr.nombre LIKE ?)
              ORDER BY p.codigo
              LIMIT 15";
    
    $searchTerm = "%{$busqueda}%";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error en la consulta: ' . $conn->error]);
        return;
    }
    
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = [
            'id' => (int)$row['id'],
            'codigo' => $row['codigo'],
            'nombre' => $row['nombre'],
            'proveedor' => $row['proveedor'],
            'stock' => (int)($row['stock'] ?? 0),
            'precio_menor' => (float)$row['precio_menor'],
            'precio_mayor' => (float)$row['precio_mayor']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'productos' => $productos
    ]);
}

function nuevoCliente() {
    global $conn;
    
    $nombre = $_POST['nombre'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $tipo_doc = $_POST['tipo_doc'] ?? 'DNI';
    $documento = $_POST['documento'] ?? '';
    
    $nombre = trim($nombre);
    
    if (empty($nombre)) {
        echo json_encode(['success' => false, 'message' => 'El nombre es requerido']);
        return;
    }
    
    // Generar código automático
    $query_codigo = "SELECT CONCAT('CLI', LPAD(COALESCE(MAX(CAST(SUBSTRING(codigo, 4) AS UNSIGNED)), 0) + 1, 3, '0')) as nuevo_codigo 
                     FROM clientes WHERE codigo LIKE 'CLI%'";
    $result = $conn->query($query_codigo);
    
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Error al generar código: ' . $conn->error]);
        return;
    }
    
    $row = $result->fetch_assoc();
    $codigo = $row['nuevo_codigo'] ?? 'CLI001';
    
    $query = "INSERT INTO clientes (codigo, nombre, telefono, tipo_documento, numero_documento, activo, fecha_registro) 
              VALUES (?, ?, ?, ?, ?, 1, CURDATE())";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error en prepare: ' . $conn->error]);
        return;
    }
    
    $stmt->bind_param("sssss", $codigo, $nombre, $telefono, $tipo_doc, $documento);
    
    if ($stmt->execute()) {
        $cliente_id = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'cliente_id' => $cliente_id,
            'codigo' => $codigo,
            'message' => 'Cliente registrado exitosamente'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al registrar cliente: ' . $stmt->error
        ]);
    }
}
?>