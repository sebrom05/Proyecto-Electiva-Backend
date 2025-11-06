<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/app.php';
define('BASE_PATH', __DIR__);

use Controllers\AuthController;
use Controllers\UsuarioController;

// Configuración CORS y cabecera JSON global
require_once BASE_PATH . '/includes/cors.php';


// Detectar método y ruta
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 🔹 Ya NO elimines /public, porque estás corriendo desde Router.php
$uri = $requestUri;

// Solo para depurar POST
if ($method === 'POST') {
    $data = file_get_contents('php://input');
    error_log("🔹 Método POST detectado en $uri");
    error_log("🔹 Contenido recibido: " . $data);
}

// Incluir módulos de rutas
require_once BASE_PATH . '/routes/auth.php';
require_once BASE_PATH . '/routes/registro.php';
require_once BASE_PATH . '/routes/usuario.php';

// (Más adelante: vehiculoRoutes.php, revisionRoutes.php, etc.)

// Si ninguna ruta coincidió, devolver 404
if (!headers_sent()) {
    http_response_code(404);
    echo json_encode([
        "ok" => false,
        "message" => "Ruta no encontrada",
        "ruta" => $uri,
        "metodo" => $method
    ]);
}
