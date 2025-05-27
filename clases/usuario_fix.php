<?php
/**
 * Clases principales para el juego de Ruleta Americana
 * Versión corregida de la clase Usuario
 */

// Clase para gestionar usuarios
class Usuario {
    // Propiedades de la base de datos
    private $conn;
    private $table_name = "Usuarios";
    
    // Propiedades del objeto Usuario
    public $usuario_id;
    public $nombre_usuario;
    public $email;
    public $contraseña;
    public $fecha_registro;
    public $saldo_actual;
    public $estado;
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Método para registrar un nuevo usuario
    public function registrar() {
        try {
            // Preparar la consulta directa en lugar de usar el procedimiento almacenado
            $query = "INSERT INTO " . $this->table_name . " 
                    (nombre_usuario, email, contraseña, fecha_registro, saldo_actual, estado) 
                    VALUES (:nombre_usuario, :email, :contraseña, NOW(), 0, 1)";
            
            // Hash de la contraseña
            $password_hash = password_hash($this->contraseña, PASSWORD_DEFAULT);
            
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Sanitizar y vincular valores
            $this->nombre_usuario = htmlspecialchars(strip_tags($this->nombre_usuario));
            $this->email = htmlspecialchars(strip_tags($this->email));
            
            $stmt->bindParam(":nombre_usuario", $this->nombre_usuario);
            $stmt->bindParam(":email", $this->email);
            $stmt->bindParam(":contraseña", $password_hash);
            
            // Ejecutar la consulta
            if($stmt->execute()) {
                // Obtener el ID del usuario recién creado
                $this->usuario_id = $this->conn->lastInsertId();
                
                // Crear entrada en el ranking para el nuevo usuario
                $this->crearEntradaRanking();
                
                return true;
            }
            
            return false;
        } catch(PDOException $exception) {
            // Para debug, guardar el error en un archivo
            file_put_contents('error_log.txt', date('Y-m-d H:i:s') . ': ' . $exception->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }
    
    // Método para crear una entrada en el ranking para un nuevo usuario
    private function crearEntradaRanking() {
        $query = "INSERT INTO Ranking (usuario_id, puntaje_total, partidas_jugadas, ultima_actualizacion) 
                VALUES (:usuario_id, 0, 0, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":usuario_id", $this->usuario_id);
        $stmt->execute();
    }
    
    // Método para verificar si un usuario existe
    public function usuarioExiste() {
        try {
            // Consulta para verificar si el email o nombre de usuario ya existen
            $query = "SELECT usuario_id FROM " . $this->table_name . " 
                    WHERE nombre_usuario = :nombre_usuario OR email = :email 
                    LIMIT 0,1";
            
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Sanitizar
            $this->nombre_usuario = htmlspecialchars(strip_tags($this->nombre_usuario));
            $this->email = htmlspecialchars(strip_tags($this->email));
            
            // Vincular valores
            $stmt->bindParam(":nombre_usuario", $this->nombre_usuario);
            $stmt->bindParam(":email", $this->email);
            
            // Ejecutar la consulta
            $stmt->execute();
            
            // Obtener número de filas
            $num = $stmt->rowCount();
            
            // Si existe al menos un registro
            if($num > 0) {
                return true;
            }
            
            return false;
        } catch(PDOException $exception) {
            // Para debug, guardar el error en un archivo
            file_put_contents('error_log.txt', date('Y-m-d H:i:s') . ': ' . $exception->getMessage() . "\n", FILE_APPEND);
            return true; // Si hay un error, asumimos que existe para evitar duplicados
        }
    }
    
    // Método para iniciar sesión
    public function login() {
        try {
            // Consulta para verificar las credenciales
            $query = "SELECT usuario_id, nombre_usuario, email, contraseña 
                    FROM " . $this->table_name . " 
                    WHERE nombre_usuario = :nombre_usuario 
                    LIMIT 0,1";
            
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Sanitizar
            $this->nombre_usuario = htmlspecialchars(strip_tags($this->nombre_usuario));
            
            // Vincular valores
            $stmt->bindParam(":nombre_usuario", $this->nombre_usuario);
            
            // Ejecutar la consulta
            $stmt->execute();
            
            // Obtener número de filas
            $num = $stmt->rowCount();
            
            // Si existe el usuario
            if($num > 0) {
                // Obtener los valores
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Asignar valores a las propiedades del objeto
                $this->usuario_id = $row['usuario_id'];
                $this->nombre_usuario = $row['nombre_usuario'];
                $this->email = $row['email'];
                $db_password = $row['contraseña'];
                
                // Verificar si la contraseña es correcta
                if(password_verify($this->contraseña, $db_password)) {
                    return true;
                }
            }
            
            return false;
        } catch(PDOException $exception) {
            // Para debug, guardar el error en un archivo
            file_put_contents('error_log.txt', date('Y-m-d H:i:s') . ': ' . $exception->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }
}
