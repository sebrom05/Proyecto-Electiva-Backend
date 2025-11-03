<?php
use Controllers\AuthController;

// 🔹 Normalizamos ruta y método
$uri = strtolower($uri);
$method = strtoupper($method);

// 🔹 Detecta correctamente aunque haya prefijo (como /proyectofinal/backend/)
if (str_contains($uri, '/api/login') && $method === 'POST') {
    AuthController::loginApi();
    exit;
}

if (str_contains($uri, '/api/logout') && $method === 'POST') {
    AuthController::logoutApi();
    exit;
}

// Puedes dejar esto temporalmente para debug
if ($method === 'GET' && str_contains($uri, '/api/login')) {
    echo json_encode(["ok" => true, "message" => "Ruta de login activa (usa POST para autenticar)"]);
    exit;
}
