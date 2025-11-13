<?php
use Controllers\RevisionController;

$uri = strtolower($uri);
$method = strtoupper($method);

// Listar todos los parámetros técnicos
if (str_contains($uri, '/api/parametros/listar') && $method === 'GET') {
    RevisionController::listarParametros();
    exit;
}
