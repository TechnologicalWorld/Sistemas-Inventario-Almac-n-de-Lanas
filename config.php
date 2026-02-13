<?php
//session_start();

// Cargar variables de entorno desde .env si existe
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
    }
}

// Configuración de la base de datos (desde .env o valores por defecto)
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'sistema_lanas');

// Configuración del sistema - SOLO DEFINIR UNA VEZ AL INICIO
define('SISTEMA_NOMBRE', 'Gestión de Lanas');
define('MONEDA', 'Bs');

// define('EMPRESA_NOMBRE', 'LANAS');
// define('TELEFONO_EMPRESA', '');
// define('DIRECCION_EMPRESA', '');
// define('IMPRESORA_TERMICA', 1);

// Crear conexión
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Establecer charset
$conn->set_charset("utf8mb4");

// Cargar configuración de la empresa desde la BD - AHORA SÍ DEFINIMOS
$query_config = "SELECT * FROM sistema_config LIMIT 1";
$result_config = $conn->query($query_config);
if ($result_config && $result_config->num_rows > 0) {
    $config_empresa = $result_config->fetch_assoc();
    
    // DEFINIR POR PRIMERA VEZ (aquí no hay warning porque no estaban definidas antes)
    define('EMPRESA_NOMBRE', $config_empresa['empresa_nombre'] ?? 'LANAS');
    define('TELEFONO_EMPRESA', $config_empresa['telefono_empresa'] ?? '');
    define('DIRECCION_EMPRESA', $config_empresa['direccion_empresa'] ?? '');
    define('IMPRESORA_TERMICA', $config_empresa['impresora_termica'] ?? 1);
} else {
    // Si no hay configuración en BD, definir valores por defecto
    define('EMPRESA_NOMBRE', 'LANAS');
    define('TELEFONO_EMPRESA', '');
    define('DIRECCION_EMPRESA', '');
    define('IMPRESORA_TERMICA', 1);
}

// Función para verificar sesión
function verificarSesion() {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: login.php");
        exit();
    }
}

// Función para verificar rol
function verificarRol($rolesPermitidos = []) {
    if (!isset($_SESSION['usuario_rol']) || !in_array($_SESSION['usuario_rol'], $rolesPermitidos)) {
        header("Location: dashboard.php");
        exit();
    }
}

// Función para sanear datos
function limpiar($dato) {
    global $conn;
    return $conn->real_escape_string(trim($dato));
}

// Función para formatear fecha
function formatearFecha($fecha) {
    return date('d/m/Y', strtotime($fecha));
}

// Función para formatear hora
function formatearHora($hora) {
    return date('h:i A', strtotime($hora));
}

// Función para formatear moneda
function formatearMoneda($monto) {
    return number_format($monto, 2, ',', '.') . ' ' . MONEDA;
}

// Variables de sesión
$usuario_id = $_SESSION['usuario_id'] ?? null;
$usuario_nombre = $_SESSION['usuario_nombre'] ?? null;
$usuario_rol = $_SESSION['usuario_rol'] ?? null;
?>