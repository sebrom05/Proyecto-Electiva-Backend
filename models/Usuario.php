<?php
namespace Model;

class Usuario extends ActiveRecord {
    protected static $tabla = 'usuario';
    protected static $columnasDB = [
        'id',
        'id_rol_usuario',
        'nombre',
        'apellido',
        'email',
        'contraseña',
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
}
