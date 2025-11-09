<?php
use Controllers\VehiculoController;

// Normaliza la URI y el método
$uri = strtolower($uri);
$method = strtoupper($method);

// ==============================
// 🔹 RUTAS PARA VEHÍCULOS
// ==============================

// Listar vehículos (solo Admin y Técnico)
if (str_contains($uri, '/api/vehiculos/listar') && $method === 'GET') {
    VehiculoController::listarVehiculos();
    exit;
}

// Cambiar estado (activar / desactivar)
if (str_contains($uri, '/api/vehiculos/cambiar-estado') && $method === 'PUT') {
    VehiculoController::cambiarEstadoVehiculo();
    exit;
}

// Editar vehículo
if (str_contains($uri, '/api/vehiculos/editar') && $method === 'PUT') {
    VehiculoController::editarVehiculo();
    exit;
}
// (Más adelante agregarás aquí crear, actualizar, eliminar, etc.)
