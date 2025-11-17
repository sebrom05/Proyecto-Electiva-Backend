<?php
// ==============================
// 🔹 CONFIGURACIÓN GLOBAL DE CORS (con soporte para sesiones PHP)
// ==============================

$frontend = 'https://tecno-citas.vercel.app';

// Cabeceras principales
header("Access-Control-Allow-Origin: $frontend");
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

session_start();


// Preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
