<?php
// ==============================================
// 🔹 FRONT CONTROLLER – Redirige todo al Router
// ==============================================

// Habilita errores visibles (solo en desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//Cargar cors
require_once dirname(__DIR__) . '/includes/cors.php';

//Cargar funciones globales (NO cargan CORS)
require_once dirname(__DIR__) . '/includes/funciones.php';

// Ruta al archivo Router.php
$routerPath = dirname(__DIR__) . '/Router.php';

// Verifica que exista y lo carga
if (file_exists($routerPath)) {
    require_once $routerPath;
} else {
    http_response_code(500);
    echo json_encode([
        'error' => 'Router.php no encontrado',
        'path' => $routerPath
    ]);
}
