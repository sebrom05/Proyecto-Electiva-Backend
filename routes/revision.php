<?php
use Controllers\RevisionController;

$uri = strtolower($uri);
$method = strtoupper($method);

// Crear revisión (admin/técnico)
if (str_contains($uri, '/api/revision/crear') && $method === 'POST') {
    RevisionController::crearRevision();
    exit;
}

// Ver revisión por cita
if (str_contains($uri, '/api/revision/ver') && $method === 'GET') {
    RevisionController::verRevision();
    exit;
}

if (str_contains($uri, '/api/revision/listar') && $method === 'GET') {
    RevisionController::listarRevisiones();
    exit;
}
