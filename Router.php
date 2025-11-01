<?php
// Cargar las clases necesarias
require_once BASE_PATH . '/models/ActiveRecord.php';
require_once BASE_PATH . '/models/Usuario.php';

use Controllers\UsuarioController;

// Detectar método HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Quitar el posible prefijo del proyecto (por ejemplo, /backend/public)
$uri = str_replace('/backend/public', '', $request);

// Rutas básicas
switch (true) {
    // Ruta: GET /api/usuarios
    case preg_match('/\/api\/usuarios$/', $uri) && $method === 'GET':
        require_once BASE_PATH . '/controllers/UsuarioController.php';
        $controller = new UsuarioController();
        $controller->listar();
        break;

    default:
        http_response_code(404);
        echo json_encode([
            "ok" => false,
            "message" => "Ruta no encontrada",
            "ruta" => $uri,
            "metodo" => $method
        ]);
        break;
}
