<?php
require_once dirname(__DIR__, 2) . '/models/ActiveRecord.php';
use Model\ActiveRecord;

// Cargar variables desde el archivo .env
function cargarEnv($ruta)
{
    if (!file_exists($ruta)) return;
    $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        if (strpos(trim($linea), '#') === 0) continue;
        list($nombre, $valor) = explode('=', $linea, 2);
        $nombre = trim($nombre);
        $valor = trim($valor);
        // 🔹 Esto asegura que todas las formas funcionen:
        putenv("$nombre=$valor");
        $_ENV[$nombre] = $valor;
        $_SERVER[$nombre] = $valor;
    }
}


function conectarDB()
{
    // Cargar las variables de entorno
    cargarEnv(dirname(__DIR__, 2) . '/.env');
    error_log("🔍 DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NO'));
    error_log("🔍 DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NO'));
    error_log("🔍 DB_USER: " . ($_ENV['DB_USER'] ?? 'NO'));
    error_log("🔍 DB_PORT: " . ($_ENV['DB_PORT'] ?? 'NO'));
    error_log("🔍 DB_PASS: " . ($_ENV['DB_PASS'] ?? 'NO'));

    $servidor   = $_ENV['DB_HOST'];
    $usuario    = $_ENV['DB_USER'];
    $contrasena = $_ENV['DB_PASS'];
    $dbname     = $_ENV['DB_NAME'];
    $puerto = $_ENV['DB_PORT'];

    try {
        $conexion = new PDO("pgsql:host=$servidor;port=$puerto;dbname=$dbname", $usuario, $contrasena);
        $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conexion->exec("SET search_path TO public;");
        return $conexion;
    } catch (PDOException $e) {
        die("Error en la conexión: " . $e->getMessage());
    }
}

// Crear la conexión y asignarla al ActiveRecord
$conexion = conectarDB();
ActiveRecord::setDB($conexion);

// echo "✅ Conexión establecida correctamente";

