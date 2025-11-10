<?php
// ==============================
// 🔹 FUNCIONES GLOBALES
// ==============================

// Verifica si el usuario está autenticado
function verificarSesionAPI() {
    session_start();

    if (!isset($_SESSION['login']) || !$_SESSION['login']) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'message' => 'Debe iniciar sesión para continuar'
        ]);
        exit;
    }

    return $_SESSION['usuario'] ?? null;
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

// ==============================
// 🔹 Validar sesión y rol de administrador
// ==============================
function verificarAdmin() {
    session_start();

    if (!isset($_SESSION['login']) || !$_SESSION['login']) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'No autorizado. Inicie sesión.']);
        exit;
    }

    if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Acceso denegado. Solo administradores.']);
        exit;
    }
}

//Verificar Roles permitidos
function verificarRolesPermitidosPorID(array $rolesPermitidos) {
    session_start();
    error_log("🧩 Verificando roles - Sesión actual: " . json_encode($_SESSION ?? []));

    // 1️⃣ Verificar sesión activa
    if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']['autenticado'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'No autorizado. Inicie sesión.']);
        exit;
    }

    // 2️⃣ Verificar ID del rol
    $rolUsuario = $_SESSION['usuario']['rol_id'] ?? null;

    if (!in_array($rolUsuario, $rolesPermitidos)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Acceso denegado. Rol no autorizado.']);
        exit;
    }
}

