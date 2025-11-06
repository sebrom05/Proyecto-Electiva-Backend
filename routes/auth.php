<?php
use Controllers\AuthController;

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

// Puedes dejar esto temporalmente para debug
if ($method === 'GET' && str_contains($uri, '/api/login')) {
    echo json_encode(["ok" => true, "message" => "Ruta de login activa (usa POST para autenticar)"]);
    exit;
}
