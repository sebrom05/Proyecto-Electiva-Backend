<?php
namespace Model;

class Revision extends ActiveRecord {
    protected static $tabla = 'revision';
    protected static $columnasDB = ['id', 'id_cita', 'id_detalle_inspeccion', 'fecha_inspeccion'];

    public $id;
    public $id_cita;
    public $id_detalle_inspeccion;
    public $fecha_inspeccion;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->id_cita = $args['id_cita'] ?? null;
        $this->id_detalle_inspeccion = $args['id_detalle_inspeccion'] ?? null;
        $this->fecha_inspeccion = $args['fecha_inspeccion'] ?? date('Y-m-d');
    }
}
