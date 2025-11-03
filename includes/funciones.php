<?php
// ==============================
// 🔹 FUNCIONES GLOBALES
// ==============================

// Verifica si el usuario está autenticado
function estaAutenticado() {
    session_start();

    if (!isset($_SESSION['login']) || !$_SESSION['login']) {
        header('Location: /');
        exit;
    }
}

// Función de depuración (para pruebas)
function debuguear($variable) {
    echo "<pre>";
    var_dump($variable);
    echo "</pre>";
    exit;
}

// Sanitizar HTML (escapar para evitar XSS)
function s($html): string {
    return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
}
