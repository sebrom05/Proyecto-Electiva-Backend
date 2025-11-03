<?php
namespace Model;

abstract class ActiveRecord {
    // ==============================
    // 🔹 CONFIGURACIÓN BASE
    // ==============================
    protected static $db;
    protected static $tabla = '';
    protected static $columnasDB = [];
    protected static $errores = [];

    // ==============================
    // 🔹 CONEXIÓN BASE DE DATOS
    // ==============================
    public static function setDB($database) {
        self::$db = $database;
    }

    // ==============================
    // 🔹 GUARDAR (Crear o Actualizar)
    // ==============================
    public function guardar() {
        if (!empty($this->id)) {
            return $this->actualizar();
        } else {
            return $this->crear();
        }
    }

    // ==============================
    // 🔹 CREAR REGISTRO
    // ==============================
    public function crear() {
        $atributos = $this->sanitizarAtributos();
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

    // ==============================
    // 🔹 ACTUALIZAR REGISTRO
    // ==============================
    public function actualizar() {
        $atributos = $this->sanitizarAtributos();
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

    // ==============================
    // 🔹 SANITIZAR ATRIBUTOS
    // ==============================
    public function sanitizarAtributos() {
        $atributos = $this->atributos();
        $sanitizado = [];

        foreach ($atributos as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
            $sanitizado[$key] = $value;
        }

        return $sanitizado;
    }

    // ==============================
    // 🔹 MANEJO DE IMÁGENES
    // ==============================
    public function setImagen($imagenTmp) {
        // 1️⃣ Si existe un ID, se asume que puede haber una imagen previa → se borra
        if (!is_null($this->id) && !empty($this->imagen)) {
            $this->borrarImagen();
        }

        // 2️⃣ Si se recibe una imagen temporal (por $_FILES)
        if ($imagenTmp && is_uploaded_file($imagenTmp['tmp_name'])) {
            // Carpeta destino
            $carpetaImagenes = dirname(__DIR__) . '/imagenes/';
            if (!is_dir($carpetaImagenes)) mkdir($carpetaImagenes, 0755, true);

            // Generar nombre único
            $nombreImagen = md5(uniqid(rand(), true)) . '.jpg';

            // Mover archivo
            move_uploaded_file($imagenTmp['tmp_name'], $carpetaImagenes . $nombreImagen);

            // Asignar nombre al objeto
            $this->imagen = $nombreImagen;
        }
    }

    // 🔹 Eliminar la imagen física
    public function borrarImagen() {
        if (empty($this->imagen)) return;

        $rutaImagen = dirname(__DIR__) . '/imagenes/' . $this->imagen;
        if (file_exists($rutaImagen)) {
            unlink($rutaImagen);
        }
    }

    // ==============================
    // 🔹 ELIMINAR REGISTRO
    // ==============================
    public function eliminar() {
        $this->borrarImagen(); // eliminar imagen asociada si existe

        $query = "DELETE FROM " . static::$tabla . " WHERE id = :id";
        $stmt = self::$db->prepare($query);
        $stmt->bindValue(':id', $this->id, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    // ==============================
    // 🔹 BUSCAR POR ID
    // ==============================
    public static function find($id) {
        $query = "SELECT * FROM " . static::$tabla . " WHERE id = :id LIMIT 1";
        $stmt = self::$db->prepare($query);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $registro = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $registro ? static::crearObjeto($registro) : null;
    }

    // ==============================
    // 🔹 BUSCAR POR EMAIL
    // ==============================
    public static function findByEmail($email) {
        $query = "SELECT * FROM " . static::$tabla . " WHERE email = :email LIMIT 1";
        $stmt = self::$db->prepare($query);
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        $registro = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $registro ? static::crearObjeto($registro) : null;
    }

    // ==============================
    // 🔹 CONSULTAS PERSONALIZADAS
    // ==============================
    public static function consultarSQL($query, $params = []) {
        $stmt = self::$db->prepare($query);
        $stmt->execute($params);
        $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function($registro) {
            return static::crearObjeto($registro);
        }, $registros);
    }

    // ==============================
    // 🔹 OBTENER TODOS LOS REGISTROS
    // ==============================
    public static function all() {
        $query = "SELECT * FROM " . static::$tabla;
        $stmt = self::$db->query($query);
        $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function($registro) {
            return static::crearObjeto($registro);
        }, $registros);
    }

    // ==============================
    // 🔹 OBTENER UN NÚMERO LIMITADO DE REGISTROS
    // ==============================
    public static function get($cantidad) {
        $query = "SELECT * FROM " . static::$tabla . " LIMIT " . (int)$cantidad;
        $stmt = self::$db->query($query);
        $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function($registro) {
            return static::crearObjeto($registro);
        }, $registros);
    }

    // ==============================
    // 🔹 CREAR OBJETO DESDE ARRAY
    // ==============================
    protected static function crearObjeto($registro) {
        $objeto = new static;
        foreach ($registro as $key => $value) {
            if (property_exists($objeto, $key)) {
                $objeto->$key = $value;
            }
        }
        return $objeto;
    }

    // ==============================
    // 🔹 SINCRONIZAR OBJETO EN MEMORIA
    // ==============================
    public function sincronizar($args = []) {
        foreach ($args as $key => $value) {
            if (property_exists($this, $key) && !is_null($value)) {
                $this->$key = $value;
            }
        }
    }

    // ==============================
    // 🔹 MAPEO DE ATRIBUTOS
    // ==============================
    public function atributos() {
        $atributos = [];
        foreach (static::$columnasDB as $columna) {
            if ($columna === 'id') continue;
            $atributos[$columna] = $this->$columna ?? null;
        }
        return $atributos;
    }

    // ==============================
    // 🔹 VALIDACIONES Y ERRORES
    // ==============================
    public static function getErrores() {
        return static::$errores;
    }

    public function validar() {
        static::$errores = [];
        return static::$errores;
    }
}
