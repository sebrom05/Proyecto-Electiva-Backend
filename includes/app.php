<?php
// ==============================
// 🔹 ARCHIVO PRINCIPAL DE CONFIGURACIÓN
// ==============================

// Autocargar clases (PSR-4)
require_once __DIR__ . '/../vendor/autoload.php';

// Incluir funciones globales
require_once __DIR__ . '/funciones.php';

// Conexión a la base de datos
require_once __DIR__ . '/config/database.php';

// Definir constantes globales
define('CARPETA_IMAGENES', __DIR__ . '/../imagenes/');

// Conectar el ActiveRecord con la BD
use Model\ActiveRecord;
ActiveRecord::setDB(conectarDB());
