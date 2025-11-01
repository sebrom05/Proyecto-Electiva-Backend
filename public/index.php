<?php
// Permitir peticiones desde cualquier origen (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Definir la ruta base del proyecto
define('BASE_PATH', dirname(__DIR__));

//Incluir la conexión a la base de datos
require_once BASE_PATH . '/includes/config/database.php';

// Obtener la URL solicitada
$request = $_SERVER['REQUEST_URI'];
$request = strtok($request, '?');

// Llamar al router principal
require_once BASE_PATH . '/Router.php';
