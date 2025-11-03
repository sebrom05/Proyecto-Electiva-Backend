<?php
require_once __DIR__ . '/../includes/app.php';

use Model\Usuario;

// Intentar obtener usuarios
try {
    $usuarios = Usuario::all();

    echo json_encode([
        'ok' => true,
        'mensaje' => 'Conexión y ActiveRecord funcionando correctamente ✅',
        'usuarios' => $usuarios
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
