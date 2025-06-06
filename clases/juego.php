<?php
/**
 * Clases principales para el juego de Ruleta Americana
 */

require_once 'database.php';

/**
 * Clase para gestionar partidas
 */
class Partida {
    // Propiedades de la base de datos
    private $conn;
    private $table_name = "Partidas";
    
    // Propiedades del objeto Partida
    public $partida_id;
    public $usuario_id;
    public $fecha_inicio;
    public $fecha_fin;
    public $monedas_iniciales;
    public $monedas_finales;
    public $estado;
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Método para iniciar una nueva partida
    public function iniciar() {
        try {
            // Preparar la consulta
            $query = "CALL sp_iniciar_partida(:usuario_id, @partida_id); SELECT @partida_id as partida_id;";
            $stmt = $this->conn->prepare($query);
            
            // Vincular valores
            $stmt->bindParam(":usuario_id", $this->usuario_id);
            
            // Ejecutar la consulta
            $stmt->execute();
            
            // Obtener el ID de la partida creada
            $stmt->nextRowset();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->partida_id = $row['partida_id'];
            
            return true;
        } catch(PDOException $exception) {
            echo "Error al iniciar partida: " . $exception->getMessage();
            return false;
        }
    }
    
    // Método para finalizar una partida
    public function finalizar() {
        try {
            // Preparar la consulta
            $query = "CALL sp_finalizar_partida(:partida_id)";
            $stmt = $this->conn->prepare($query);
            
            // Vincular valores
            $stmt->bindParam(":partida_id", $this->partida_id);
            
            // Ejecutar la consulta
            if($stmt->execute()) {
                return true;
            }
            
            return false;
        } catch(PDOException $exception) {
            echo "Error al finalizar partida: " . $exception->getMessage();
            return false;
        }
    }
    
    // Método para obtener los detalles de una partida
    public function obtenerDetalles() {
        // Consulta para obtener los detalles de la partida
        $query = "SELECT * FROM " . $this->table_name . " WHERE partida_id = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Vincular valores
        $stmt->bindParam(1, $this->partida_id);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        // Obtener el registro
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Asignar valores a las propiedades del objeto
        $this->usuario_id = $row['usuario_id'];
        $this->fecha_inicio = $row['fecha_inicio'];
        $this->fecha_fin = $row['fecha_fin'];
        $this->monedas_iniciales = $row['monedas_iniciales'];
        $this->monedas_finales = $row['monedas_finales'];
        $this->estado = $row['estado'];
    }
}

/**
 * Clase para gestionar apuestas
 */
class Apuesta {
    // Propiedades de la base de datos
    private $conn;
    private $table_name = "Apuestas";
    
    // Propiedades del objeto Apuesta
    public $apuesta_id;
    public $partida_id;
    public $numero_apostado;
    public $cantidad_apostada;
    public $numero_resultado;
    public $ganancia;
    public $fecha_apuesta;
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Método para registrar una apuesta
    public function registrar() {
        try {
            // Preparar la consulta
            $query = "CALL sp_registrar_apuesta(:partida_id, :numero_apostado, :cantidad_apostada)";
            $stmt = $this->conn->prepare($query);
            
            // Sanitizar
            $this->numero_apostado = htmlspecialchars(strip_tags($this->numero_apostado));
            $this->cantidad_apostada = htmlspecialchars(strip_tags($this->cantidad_apostada));
            
            // Vincular valores
            $stmt->bindParam(":partida_id", $this->partida_id);
            $stmt->bindParam(":numero_apostado", $this->numero_apostado);
            $stmt->bindParam(":cantidad_apostada", $this->cantidad_apostada);
            
            // Ejecutar la consulta
            if($stmt->execute()) {
                return true;
            }
            
            return false;
        } catch(PDOException $exception) {
            echo "Error al registrar apuesta: " . $exception->getMessage();
            return false;
        }
    }

    // NUEVO: Método para registrar una apuesta con resultado predeterminado (actualizado para tipos de apuesta)
public function registrarConResultado($numero_resultado, $tipo_apuesta = 'numero') {
    try {
        // Preparar la consulta con el nuevo SP
        $query = "CALL sp_registrar_apuesta_con_resultado(:partida_id, :tipo_apuesta, :numero_apostado, :cantidad_apostada, :numero_resultado)";
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar
        $tipo_apuesta = htmlspecialchars(strip_tags($tipo_apuesta));
        $numero_resultado = htmlspecialchars(strip_tags($numero_resultado));
        $this->cantidad_apostada = htmlspecialchars(strip_tags($this->cantidad_apostada));
        
        // Para apuestas que no son a número específico, numero_apostado será NULL
        $numero_apostado_param = null;
        if($tipo_apuesta === 'numero') {
            $numero_apostado_param = htmlspecialchars(strip_tags($this->numero_apostado));
        }
        
        // Vincular valores
        $stmt->bindParam(":partida_id", $this->partida_id);
        $stmt->bindParam(":tipo_apuesta", $tipo_apuesta);
        $stmt->bindParam(":numero_apostado", $numero_apostado_param);
        $stmt->bindParam(":cantidad_apostada", $this->cantidad_apostada);
        $stmt->bindParam(":numero_resultado", $numero_resultado);
        
        // Ejecutar la consulta
        if($stmt->execute()) {
            // Obtener los datos de la apuesta registrada
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if($row) {
                $this->apuesta_id = $row['apuesta_id'];
                $this->numero_resultado = $row['numero_resultado'];
                $this->ganancia = $row['ganancia'];
                $this->fecha_apuesta = $row['fecha_apuesta'];
            }
            return true;
        }
        
        return false;
    } catch(PDOException $exception) {
        echo "Error al registrar apuesta con resultado: " . $exception->getMessage();
        return false;
    }
}
    
    // Método para obtener las apuestas de una partida
    public function obtenerApuestasDePartida() {
        // Consulta para obtener todas las apuestas de una partida
        $query = "SELECT * FROM " . $this->table_name . " WHERE partida_id = ? ORDER BY fecha_apuesta ASC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Vincular valores
        $stmt->bindParam(1, $this->partida_id);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
}