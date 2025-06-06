<?php
/**
 * Archivo de conexión a la base de datos MySQL
 * Para el Sistema de Ruleta Americana
 */

// Clase para manejar la conexión a la base de datos
class Database {
    // Propiedades de configuración de la base de datos
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;
    private $port;
    
    // Constructor con parámetros por defecto para entorno local (XAMPP)
    public function __construct($config = null) {
        // Si no se proporciona configuración, usar valores por defecto (XAMPP)
        if($config === null) {
            // Detectar si estamos en el servidor de producción o local
            $server_name = $_SERVER['SERVER_NAME'] ?? '';
            
            // Configuración basada en el entorno (producción o desarrollo)
            if ($server_name == 'localhost') {
                // Configuración para entorno local (XAMPP)
                $this->host = "localhost";
                $this->db_name = "ruleta_americana";
                $this->username = "root";
                $this->password = "";
                $this->port = 3306;
                
            } else {
                // Configuración para entorno de producción (VPS)
                // Estos valores deberías cambiarlos por los de tu VPS
                $this->host = "localhost";
                $this->db_name = "ruleta_americana";
                $this->username = "redes2";
                $this->password = "redes2";
                $this->port = 3306;
            }
        } else {
            // Usar configuración proporcionada
            $this->host = $config['host'];
            $this->db_name = $config['db_name'];
            $this->username = $config['username'];
            $this->password = $config['password'];
            $this->port = $config['port'] ?? 3306;
        }
    }
    
    // Método para conectar a la base de datos
    public function getConnection() {
        $this->conn = null;
        
        try {
            // Crear una nueva conexión PDO
            $this->conn = new PDO(
                "mysql:host=" . $this->host . 
                ";port=" . $this->port . 
                ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password
            );
            
            // Configurar PDO para lanzar excepciones en caso de error
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Establecer charset utf8
            $this->conn->exec("set names utf8");
            
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
    
    // Método para cerrar la conexión
    public function closeConnection() {
        $this->conn = null;
    }
}

// Archivo de configuración externo (opcional)
function getConfigFromFile() {
    $config_file = __DIR__ . '/config.php';
    if (file_exists($config_file)) {
        include $config_file;
        if (isset($db_config) && is_array($db_config)) {
            return $db_config;
        }
    }
    return null;
}

// Uso general para obtener una conexión
function obtenerConexion() {
    $config = getConfigFromFile();
    $database = new Database($config);
    return $database->getConnection();
}
