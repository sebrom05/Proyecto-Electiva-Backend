<?php
namespace Model;

abstract class ActiveRecord {
    // Conexión a la base de datos (PDO)
    protected static $db;
    protected static $tabla = '';
    protected static $columnasDB = [];

    // Errores
    protected static $errores = [];

    // Asignar la conexión desde fuera (normalmente desde includes/config/database.php)
    public static function setDB($database) {
        self::$db = $database;
    }

    // Guardar: crear o actualizar
    public function guardar() {
        if (!empty($this->id)) {
            return $this->actualizar();
        } else {
            return $this->crear();
        }
    }

    // Crear registro
    public function crear() {
        $atributos = $this->atributos();
        $columnas = join(', ', array_keys($atributos));
        $placeholders = ':' . join(', :', array_keys($atributos));

        $query = "INSERT INTO " . static::$tabla . " ($columnas) VALUES ($placeholders)";
        $stmt = self::$db->prepare($query);

        foreach ($atributos as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        if ($stmt->execute()) {
            $this->id = self::$db->lastInsertId();
            return true;
        }

        return false;
    }

    // Actualizar registro
    public function actualizar() {
        $atributos = $this->atributos();
        $valores = [];
        foreach ($atributos as $key => $value) {
            $valores[] = "$key = :$key";
        }

        $query = "UPDATE " . static::$tabla . " SET " . join(', ', $valores) . " WHERE id = :id";
        $stmt = self::$db->prepare($query);

        foreach ($atributos as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->bindValue(':id', $this->id, \PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Eliminar registro
    public function eliminar() {
        $query = "DELETE FROM " . static::$tabla . " WHERE id = :id";
        $stmt = self::$db->prepare($query);
        $stmt->bindValue(':id', $this->id, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Obtener todos los registros
    public static function all() {
        $query = "SELECT * FROM " . static::$tabla;
        $stmt = self::$db->query($query);
        $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function($registro) {
            return static::crearObjeto($registro);
        }, $registros);
    }

    // Buscar por id
    public static function find($id) {
        $query = "SELECT * FROM " . static::$tabla . " WHERE id = :id LIMIT 1";
        $stmt = self::$db->prepare($query);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $registro = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $registro ? static::crearObjeto($registro) : null;
    }

    // Convertir arreglo en objeto del modelo
    protected static function crearObjeto($registro) {
        $objeto = new static;
        foreach ($registro as $key => $value) {
            if (property_exists($objeto, $key)) {
                $objeto->$key = $value;
            }
        }
        return $objeto;
    }

    // Obtener atributos del objeto (solo columnas válidas)
    public function atributos() {
        $atributos = [];
        foreach (static::$columnasDB as $columna) {
            if ($columna === 'id') continue;
            $atributos[$columna] = $this->$columna ?? null;
        }
        return $atributos;
    }

    // Validaciones (se reescriben en modelos hijos si se necesitan)
    public function validar() {
        static::$errores = [];
        return static::$errores;
    }

    public static function getErrores() {
        return static::$errores;
    }
}
