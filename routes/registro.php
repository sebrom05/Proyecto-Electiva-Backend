<?php
use Controllers\RegistroController;

$uri = strtolower($uri);
$method = strtoupper($method);

// // 🔹 GET solo para prueba (opcional)
// if (str_contains($uri, '/api/auth/registro') && $method === 'GET') {
//     echo json_encode(['ok' => true, 'message' => 'Ruta de registro activa']);
//     exit;
// }

// 🔹 GET solo para evitar error de frontend
if (str_contains($uri, '/api/auth/registro') && $method === 'GET') {
    echo json_encode(['ok' => true, 'message' => 'Ruta activa']);
    exit;
}

// 🔹 GET /api/auth/verificar  (solo prueba, opcional)
if (str_contains($uri, '/api/auth/verificar') && $method === 'GET') {
    echo json_encode(['ok' => true, 'message' => 'Ruta de verificación activa']);
    exit;
}

// 🔹 POST para registrar usuario
if (str_contains($uri, '/api/auth/registro') && $method === 'POST') {
    RegistroController::registroApi();
    exit;
}

// 🔹 POST para verificar código
if (str_contains($uri, '/api/auth/verificar') && $method === 'POST') {
    RegistroController::verificarCodigoApi();
    exit;
}
