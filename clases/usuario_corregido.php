<?php
/**
 * Clase Usuario corregida para el juego de Ruleta Americana
 * Versión con parámetros fijos para evitar errores de parámetros no definidos
 */

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
            // Debug - Guardar los valores que estamos intentando insertar
            file_put_contents('registro_debug.log', "Intentando registrar: \n" . 
                             "Usuario: " . $this->nombre_usuario . "\n" .
                             "Email: " . $this->email . "\n", FILE_APPEND);
            
            // Sanitizar valores
            $nombre_sanitizado = htmlspecialchars(strip_tags($this->nombre_usuario));
            $email_sanitizado = htmlspecialchars(strip_tags($this->email));
            $password_hash = password_hash($this->contraseña, PASSWORD_DEFAULT);
            
            // Fecha actual en formato SQL
            $fecha_actual = date('Y-m-d H:i:s');
            
            // Insertar usuario con valores directos para evitar problemas de parámetros
            $query = "INSERT INTO Usuarios (nombre_usuario, email, contraseña, fecha_registro, saldo_actual, estado) 
                      VALUES (?, ?, ?, ?, 0, 1)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $nombre_sanitizado);
            $stmt->bindParam(2, $email_sanitizado);
            $stmt->bindParam(3, $password_hash);
            $stmt->bindParam(4, $fecha_actual);
            
            // Ejecutar la consulta
            if($stmt->execute()) {
                // Obtener el ID del usuario recién creado
                $this->usuario_id = $this->conn->lastInsertId();
                
                // Crear entrada en el ranking para el nuevo usuario
                $ranking_query = "INSERT INTO Ranking (usuario_id, puntaje_total, partidas_jugadas, ultima_actualizacion) 
                                  VALUES (?, 0, 0, ?)";
                
                $ranking_stmt = $this->conn->prepare($ranking_query);
                $ranking_stmt->bindParam(1, $this->usuario_id);
                $ranking_stmt->bindParam(2, $fecha_actual);
                $ranking_stmt->execute();
                
                return true;
            }
            
            return false;
        } catch(PDOException $exception) {
            // Para debug, guardar el error en un archivo
            file_put_contents('error_log.txt', date('Y-m-d H:i:s') . ': ' . $exception->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }
    
    // Método para verificar si un usuario existe
    public function usuarioExiste() {
        try {
            // Sanitizar valores
            $nombre_sanitizado = htmlspecialchars(strip_tags($this->nombre_usuario));
            $email_sanitizado = htmlspecialchars(strip_tags($this->email));
            
            // Consulta para verificar si el email o nombre de usuario ya existen
            $query = "SELECT usuario_id FROM Usuarios 
                      WHERE nombre_usuario = ? OR email = ? 
                      LIMIT 0,1";
            
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $nombre_sanitizado);
            $stmt->bindParam(2, $email_sanitizado);
            
            // Ejecutar la consulta
            $stmt->execute();
            
            // Si existe al menos un registro
            if($stmt->rowCount() > 0) {
                return true;
            }
            
            return false;
        } catch(PDOException $exception) {
            // Para debug
            file_put_contents('error_log.txt', date('Y-m-d H:i:s') . ': ' . $exception->getMessage() . "\n", FILE_APPEND);
            return true; // Si hay un error, asumimos que existe para evitar duplicados
        }
    }
    
    // Método para iniciar sesión
    public function login() {
        try {
            // Sanitizar valor
            $nombre_sanitizado = htmlspecialchars(strip_tags($this->nombre_usuario));
            
            // Consulta para verificar las credenciales
            $query = "SELECT usuario_id, nombre_usuario, email, contraseña 
                      FROM Usuarios 
                      WHERE nombre_usuario = ? 
                      LIMIT 0,1";
            
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $nombre_sanitizado);
            
            // Ejecutar la consulta
            $stmt->execute();
            
            // Si existe el usuario
            if($stmt->rowCount() > 0) {
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
            // Para debug
            file_put_contents('error_log.txt', date('Y-m-d H:i:s') . ': ' . $exception->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }
}
