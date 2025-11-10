<?php
namespace Model;

use Model\ActiveRecord;

class Cita extends ActiveRecord {
    protected static $tabla = 'cita';
    protected static $columnasDB = ['id', 'id_vehiculo', 'id_estado_cita', 'fecha', 'hora', 'fecha_registro_cita'];

    public $id;
    public $id_vehiculo;
    public $id_estado_cita;
    public $fecha;
    public $hora;
    public $fecha_registro_cita;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->id_vehiculo = $args['id_vehiculo'] ?? null;
        $this->id_estado_cita = $args['id_estado_cita'] ?? 1; // 1 = Pendiente
        $this->fecha = $args['fecha'] ?? null;
        $this->hora = $args['hora'] ?? null;
        $this->fecha_registro_cita = $args['fecha_registro_cita'] ?? date('Y-m-d H:i:s');
    }
}
