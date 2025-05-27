<?php
/**
 * Clase para gestionar el ranking completa y corregida
 */
class Ranking {
    // Propiedades de la base de datos
    private $conn;
    private $table_name = "Ranking";
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Método para obtener el ranking global
    public function obtenerRankingGlobal($limit = 10) {
        try {
            // Consulta para obtener el ranking ordenado por puntaje
            $query = "SELECT r.ranking_id, r.usuario_id, u.nombre_usuario, r.puntaje_total, r.partidas_jugadas
                    FROM " . $this->table_name . " r
                    JOIN Usuarios u ON r.usuario_id = u.usuario_id
                    ORDER BY r.puntaje_total DESC
                    LIMIT 0, ?";
            
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Vincular valores
            $stmt->bindParam(1, $limit, PDO::PARAM_INT);
            
            // Ejecutar la consulta
            $stmt->execute();
            
            return $stmt;
        } catch(PDOException $exception) {
            // Para debug, guardar el error en un archivo
            file_put_contents('error_ranking.log', date('Y-m-d H:i:s') . ': ' . $exception->getMessage() . "\n", FILE_APPEND);
            
            // Devolver un objeto con 0 filas para evitar errores
            return $this->conn->query("SELECT 1 WHERE 0");
        }
    }
    
    // Método para obtener la posición de un usuario en el ranking
    public function obtenerPosicionUsuario($usuario_id) {
        try {
            // Primero obtenemos el puntaje del usuario
            $query_puntaje = "SELECT puntaje_total 
                            FROM " . $this->table_name . " 
                            WHERE usuario_id = ?";
            
            $stmt_puntaje = $this->conn->prepare($query_puntaje);
            $stmt_puntaje->bindParam(1, $usuario_id);
            $stmt_puntaje->execute();
            
            // Si el usuario no existe en el ranking, devolvemos 0
            if ($stmt_puntaje->rowCount() == 0) {
                return 0;
            }
            
            $row_puntaje = $stmt_puntaje->fetch(PDO::FETCH_ASSOC);
            $puntaje_usuario = $row_puntaje['puntaje_total'];
            
            // Ahora contamos cuántos usuarios tienen un puntaje mayor
            $query_posicion = "SELECT COUNT(*) as posicion
                              FROM " . $this->table_name . "
                              WHERE puntaje_total > ?";
            
            $stmt_posicion = $this->conn->prepare($query_posicion);
            $stmt_posicion->bindParam(1, $puntaje_usuario);
            $stmt_posicion->execute();
            
            $row_posicion = $stmt_posicion->fetch(PDO::FETCH_ASSOC);
            
            // La posición es: cantidad de usuarios con mayor puntaje + 1
            $posicion = $row_posicion['posicion'] + 1;
            
            return $posicion;
        } catch(PDOException $exception) {
            // Para debug, guardar el error en un archivo
            file_put_contents('error_ranking.log', date('Y-m-d H:i:s') . ': ' . $exception->getMessage() . "\n", FILE_APPEND);
            return 0; // En caso de error, devolvemos 0
        }
    }
    
    // Método para actualizar el ranking de un usuario
    public function actualizarRankingUsuario($usuario_id, $puntaje_adicional) {
        try {
            // Consulta para actualizar el ranking
            $query = "UPDATE " . $this->table_name . "
                     SET puntaje_total = puntaje_total + ?,
                         partidas_jugadas = partidas_jugadas + 1,
                         ultima_actualizacion = NOW()
                     WHERE usuario_id = ?";
            
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Vincular valores
            $stmt->bindParam(1, $puntaje_adicional);
            $stmt->bindParam(2, $usuario_id);
            
            // Ejecutar la consulta
            return $stmt->execute();
        } catch(PDOException $exception) {
            // Para debug, guardar el error en un archivo
            file_put_contents('error_ranking.log', date('Y-m-d H:i:s') . ': ' . $exception->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }
    
    // Método para obtener las estadísticas de un usuario
    public function obtenerEstadisticasUsuario($usuario_id) {
        try {
            // Consulta para obtener estadísticas
            $query = "SELECT r.puntaje_total, r.partidas_jugadas,
                           (SELECT COUNT(*) + 1 FROM " . $this->table_name . " WHERE puntaje_total > r.puntaje_total) as posicion
                     FROM " . $this->table_name . " r
                     WHERE r.usuario_id = ?";
            
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Vincular valores
            $stmt->bindParam(1, $usuario_id);
            
            // Ejecutar la consulta
            $stmt->execute();
            
            // Obtener el resultado
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            // Para debug, guardar el error en un archivo
            file_put_contents('error_ranking.log', date('Y-m-d H:i:s') . ': ' . $exception->getMessage() . "\n", FILE_APPEND);
            return [
                'puntaje_total' => 0,
                'partidas_jugadas' => 0,
                'posicion' => 0
            ];
        }
    }
}
