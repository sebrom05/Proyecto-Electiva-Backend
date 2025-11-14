<?php
// ==============================
// 🔹 CONFIGURACIÓN GLOBAL DE CORS (con soporte para sesiones PHP)
// ==============================

$frontend = 'http://localhost:5173';

// Cabeceras principales
header("Access-Control-Allow-Origin: $frontend");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

// Preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
