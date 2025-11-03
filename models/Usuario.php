<?php
namespace Model;

use Model\ActiveRecord;

class Usuario extends ActiveRecord {
    protected static $tabla = 'usuario'; // nombre exacto de tu tabla en PostgreSQL

    protected static $columnasDB = [
        'id',
        'id_rol_usuario',
        'nombre',
        'apellido',
        'email',
        'contraseña', // puedes dejarla así, y el controlador la manejará bien
        'edad',
        'documento',
        'telefono',
        'fecha_ingreso'
    ];

    public $id;
    public $id_rol_usuario;
    public $nombre;
    public $apellido;
    public $email;
    public $contraseña;
    public $edad;
    public $documento;
    public $telefono;
    public $fecha_ingreso;

    // 🔹 Constructor: inicializa el objeto con los datos recibidos
    public function __construct($args = []){
        $this->id = $args['id'] ?? null;
        $this->id_rol_usuario = $args['id_rol_usuario'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->apellido = $args['apellido'] ?? '';
        $this->email = $args['email'] ?? '';
        $this->contraseña = $args['contraseña'] ?? '';
        $this->edad = $args['edad'] ?? null;
        $this->documento = $args['documento'] ?? '';
        $this->telefono = $args['telefono'] ?? '';
        $this->fecha_ingreso = $args['fecha_ingreso'] ?? date('Y-m-d H:i:s');
    }

    public function validar() {
        static::$errores = [];

        // 🔹 Nombre obligatorio
        if (!$this->nombre || strlen(trim($this->nombre)) < 3) {
            static::$errores[] = "El nombre es obligatorio y debe tener al menos 3 caracteres.";
        }

        // 🔹 Apellido obligatorio
        if (!$this->apellido || strlen(trim($this->apellido)) < 3) {
            static::$errores[] = "El apellido es obligatorio y debe tener al menos 3 caracteres.";
        }

        // 🔹 Email obligatorio y formato correcto
        if (!$this->email) {
            static::$errores[] = "El correo electrónico es obligatorio.";
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            static::$errores[] = "El formato del correo electrónico no es válido.";
        }

        // 🔹 Contraseña obligatoria (solo al crear)
        if (empty($this->id)) { // solo valida en registros nuevos
            if (!$this->contraseña || strlen($this->contraseña) < 6) {
                static::$errores[] = "La contraseña debe tener al menos 6 caracteres.";
            }
        }

        // 🔹 Edad (opcional, pero si existe debe ser número positivo)
        if (!empty($this->edad) && (!is_numeric($this->edad) || $this->edad < 0)) {
            static::$errores[] = "La edad debe ser un número válido.";
        }

        // 🔹 Teléfono (opcional, pero si se pone debe tener formato válido)
        if (!empty($this->telefono) && !preg_match('/^[0-9]{7,10}$/', $this->telefono)) {
            static::$errores[] = "El teléfono debe tener entre 7 y 10 dígitos numéricos.";
        }

        return static::$errores;
    }

}
