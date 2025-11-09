<?php
use Controllers\UsuarioController;

// Normaliza la URI y el método
$uri = strtolower($uri);
$method = strtoupper($method);

// Crear usuario (solo admin)
if (str_contains($uri, '/api/usuarios/crear') && $method === 'POST') {
    UsuarioController::crearUsuario();
    exit;
}
