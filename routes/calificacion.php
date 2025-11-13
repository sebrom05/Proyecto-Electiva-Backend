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
