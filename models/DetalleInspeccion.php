<?php
namespace Model;

class DetalleInspeccion extends ActiveRecord {
    protected static $tabla = 'detalle_inspeccion';
    protected static $columnasDB = ['id', 'resultado', 'observaciones', 'recomendaciones', 'efectividad_numero'];

    public $id;
    public $resultado;
    public $observaciones;
    public $recomendaciones;
    public $efectividad_numero;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->resultado = $args['resultado'] ?? 0;
        $this->observaciones = $args['observaciones'] ?? '';
        $this->recomendaciones = $args['recomendaciones'] ?? '';
        $this->efectividad_numero = $args['efectividad_numero'] ?? 0;
    }
}
