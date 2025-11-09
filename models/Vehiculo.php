<?php
namespace Model;

use Model\ActiveRecord;

class Vehiculo extends ActiveRecord {

    // ==============================
    // 🔹 Configuración de la tabla
    // ==============================
    protected static $tabla = 'vehiculo';

    protected static $columnasDB = [
        'id',
        'id_usuario',
        'placa',
        'marca',
        'modelo',
        'color',
        'imagen',
        'activo',
        'fecha_registro',
        'fecha_tecnomecanica'
    ];

    // ==============================
    // 🔹 Atributos públicos
    // ==============================
    public $id;
    public $id_usuario;
    public $placa;
    public $marca;
    public $modelo;
    public $color;
    public $imagen;
    public $activo;
    public $fecha_registro;
    public $fecha_tecnomecanica;

    // ==============================
    // 🔹 Constructor
    // ==============================
    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->id_usuario = $args['id_usuario'] ?? null;
        $this->placa = strtoupper(trim($args['placa'] ?? ''));
        $this->marca = $args['marca'] ?? '';
        $this->modelo = $args['modelo'] ?? '';
        $this->color = $args['color'] ?? '';
        $this->imagen = $args['imagen'] ?? null;
        $this->activo = $args['activo'] ?? true;
        $this->fecha_registro = $args['fecha_registro'] ?? date('Y-m-d H:i:s');
        $this->fecha_tecnomecanica = $args['fecha_tecnomecanica'] ?? null;
    }

    // ==============================
    // 🔹 Validación de datos
    // ==============================
    public function validar()
    {
        static::$errores = [];

        // 🔸 Placa obligatoria y formato válido (ej: ABC123)
        if (!$this->placa || !preg_match('/^[A-Z]{3}\d{3}$/', $this->placa)) {
            static::$errores[] = "La placa es obligatoria y debe tener formato válido (ABC123).";
        }

        // 🔸 Marca obligatoria
        if (!$this->marca || strlen(trim($this->marca)) < 2) {
            static::$errores[] = "La marca es obligatoria.";
        }

        // 🔸 Modelo obligatorio
        if (!$this->modelo || !is_numeric($this->modelo) || $this->modelo < 1950) {
            static::$errores[] = "Debe ingresar un modelo válido (año mayor a 1950).";
        }

        // 🔸 Color obligatorio
        if (!$this->color) {
            static::$errores[] = "El color del vehículo es obligatorio.";
        }

        // 🔸 Usuario asociado obligatorio
        if (empty($this->id_usuario)) {
            static::$errores[] = "Debe asociar un propietario válido.";
        }

        return static::$errores;
    }
}
