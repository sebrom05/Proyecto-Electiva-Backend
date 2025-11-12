<?php
use Controllers\CitaController;

$uri = strtolower($uri);
$method = strtoupper($method);

// Estados de cita
if (str_contains($uri, '/api/citas/estados') && $method === 'GET') {
    CitaController::listarEstados();
    exit;
}

// Listar (admin/técnico)
if (str_contains($uri, '/api/citas/listar') && $method === 'GET') {
    CitaController::listarCitas();
    exit;
}

// Crear (admin/cliente)
if (str_contains($uri, '/api/citas/crear') && $method === 'POST') {
    CitaController::crearCita();
    exit;
}

// Cambiar estado (admin/técnico)
if (str_contains($uri, '/api/citas/estado') && $method === 'POST') {
    CitaController::cambiarEstado();
    exit;
}

// (Opcional) Listar solo del cliente autenticado
if (str_contains($uri, '/api/citas/mis-citas') && $method === 'GET') {
    CitaController::listarCitasPorUsuario();
    exit;
}

// Obtener cita por ID
if (str_contains($uri, '/api/citas/ver') && $method === 'GET') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        CitaController::obtenerCitaPorId($id);
    } else {
        echo json_encode(['ok' => false, 'message' => 'ID requerido']);
    }
    exit;
}


// Eliminar cita (admin/técnico)
if (str_contains($uri, '/api/citas/eliminar') && $method === 'POST') {
    CitaController::eliminarCita();
    exit;
}



