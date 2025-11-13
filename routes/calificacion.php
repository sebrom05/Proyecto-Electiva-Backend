<?php
use Controllers\CalificacionController;

$uri = strtolower($uri);
$method = strtoupper($method);

// ...lo que ya tengas...

// 🔹 Estadísticas de calificaciones (admin/técnico)
if (str_contains($uri, '/api/calificaciones/estadisticas') && $method === 'GET') {
    CalificacionController::estadisticasAdmin();
    exit;
}

// 🔹 Cambiar la visibilidad de un comentario (solo admin)
if (str_contains($uri, '/api/calificaciones/visibilidad') && $method === 'POST') {
    CalificacionController::actualizarVisibilidad();
    exit;
}


if (str_contains($uri, '/api/calificaciones/publicas') && $method === 'GET') {
    CalificacionController::listarPublicos();
    exit;
}

if (str_contains($uri, '/api/calificaciones/crear') && $method === 'POST') {
    CalificacionController::crear();
    exit;
}
