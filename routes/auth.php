<?php
use Controllers\AuthController;
use Controllers\AdminController;

// 🔹 Normalizamos ruta y método
$uri = strtolower($uri);
$method = strtoupper($method);

// 🔹 Detecta correctamente aunque haya prefijo (como /proyectofinal/backend/)
if (str_contains($uri, '/api/auth/login') && $method === 'POST') {
    AuthController::loginApi();
    exit;
}

if (str_contains($uri, '/api/auth/logout') && $method === 'POST') {
    AuthController::logoutApi();
    exit;
}

// 🔹 VERIFICAR SESIÓN
if (str_contains($uri, '/api/auth/verificar') && $method === 'GET') {
    AuthController::verificarSesionApi();
    exit;
}


//Acceso a rutas del admin
if (str_contains($uri, '/api/admin/estadisticas') && $method === 'GET') {
    AdminController::obtenerEstadisticas();
    exit;
}

if (str_contains($uri, '/api/admin/usuarios') && $method === 'GET') {
    AdminController::listarUsuarios();
    exit;
}

if (str_contains($uri, '/api/admin/usuario/eliminar') && $method === 'DELETE') {
    AdminController::eliminarUsuario();
    exit;
}

if (str_contains($uri, '/api/admin/usuario/activar') && $method === 'PUT') {
    AdminController::activarUsuario();
    exit;
}


// Puedes dejar esto temporalmente para debug
if ($method === 'GET' && str_contains($uri, '/api/login')) {
    echo json_encode(["ok" => true, "message" => "Ruta de login activa (usa POST para autenticar)"]);
    exit;
}
