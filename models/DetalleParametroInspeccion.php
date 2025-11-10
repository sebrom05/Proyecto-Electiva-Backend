<?php
namespace Model;

class DetalleParametroInspeccion extends ActiveRecord {
    protected static $tabla = 'detalle_parametro_inspeccion';
    protected static $columnasDB = ['id', 'id_parametro_inspeccion', 'id_detalle_inspeccion', 'valor_medicion', 'resultado_parametro'];

    public $id;
    public $id_parametro_inspeccion;
    public $id_detalle_inspeccion;
    public $valor_medicion;
    public $resultado_parametro;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->id_parametro_inspeccion = $args['id_parametro_inspeccion'] ?? null;
        $this->id_detalle_inspeccion = $args['id_detalle_inspeccion'] ?? null;
        $this->valor_medicion = $args['valor_medicion'] ?? '';
        $this->resultado_parametro = $args['resultado_parametro'] ?? '';
    }
}
