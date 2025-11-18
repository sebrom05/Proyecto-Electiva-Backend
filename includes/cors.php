<?php
// ==============================
// 🔹 CONFIGURACIÓN GLOBAL DE CORS (con soporte para sesiones PHP)
// ==============================

$allowed_origins = [
    "http://localhost:5173",
    "https://tecno-citas.vercel.app",
    "https://tecno-citas-4wxl5hfjw-sebastians-projects-c674c50a.vercel.app"
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
}
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");


// =========================
//  CONFIGURACIÓN DE SESIÓN PARA SERVIDOR EXTERNO
// =========================

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',       // IMPORTANTE: vacío si usas una IP
    'secure' => true,    // true si usas HTTPS
    'httponly' => true,
    'samesite' => 'None'  // REQUERIDO para cookies cross-site
]);

// Iniciar sesión UNA SOLA VEZ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
