-- Script para crear la base de datos de Ruleta Americana
-- Creación de la base de datos
DROP DATABASE ruleta_americana;
CREATE DATABASE IF NOT EXISTS ruleta_americana;
USE ruleta_americana;

-- Tabla de Usuarios
CREATE TABLE IF NOT EXISTS Usuarios (
    usuario_id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    contraseña VARCHAR(255) NOT NULL,
    fecha_registro DATETIME NOT NULL,
    saldo_actual DECIMAL(10,2) DEFAULT 0,
    estado TINYINT DEFAULT 1 COMMENT '1=activo, 0=inactivo, 2=bloqueado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de Partidas
CREATE TABLE IF NOT EXISTS Partidas (
    partida_id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NULL,
    monedas_iniciales INT NOT NULL DEFAULT 5,
    monedas_finales INT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'en_progreso',
    FOREIGN KEY (usuario_id) REFERENCES Usuarios(usuario_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de Apuestas
CREATE TABLE IF NOT EXISTS Apuestas (
    apuesta_id INT AUTO_INCREMENT PRIMARY KEY,
    partida_id INT NOT NULL,
    numero_apostado INT NOT NULL COMMENT '0-36, 37=00',
    cantidad_apostada INT NOT NULL,
    numero_resultado INT NOT NULL,
    ganancia INT NOT NULL DEFAULT 0,
    fecha_apuesta DATETIME NOT NULL,
    FOREIGN KEY (partida_id) REFERENCES Partidas(partida_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de Ranking
CREATE TABLE IF NOT EXISTS Ranking (
    ranking_id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    puntaje_total INT NOT NULL DEFAULT 0,
    partidas_jugadas INT NOT NULL DEFAULT 0,
    ultima_actualizacion DATETIME NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES Usuarios(usuario_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de Configuración
CREATE TABLE IF NOT EXISTS Configuracion (
    config_id INT PRIMARY KEY DEFAULT 1,
    max_monedas_iniciales INT NOT NULL DEFAULT 5,
    max_jugadas_por_partida INT NOT NULL DEFAULT 5,
    multiplicador_ganancia INT NOT NULL DEFAULT 35
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Crear índices para optimizar consultas
CREATE INDEX idx_partidas_usuario ON Partidas(usuario_id);
CREATE INDEX idx_apuestas_partida ON Apuestas(partida_id);
CREATE INDEX idx_ranking_puntaje ON Ranking(puntaje_total DESC);

-- Insertar configuración inicial
INSERT INTO Configuracion (config_id, max_monedas_iniciales, max_jugadas_por_partida, multiplicador_ganancia) 
VALUES (1, 5, 5, 35);

-- Procedimiento almacenado para registrar un nuevo usuario
DELIMITER //
CREATE PROCEDURE sp_registrar_usuario(
    IN p_nombre_usuario VARCHAR(50),
    IN p_email VARCHAR(100),
    IN p_contraseña VARCHAR(255)
)
BEGIN
    DECLARE v_usuario_id INT;
    
    START TRANSACTION;
    
    -- Insertar el nuevo usuario
    INSERT INTO Usuarios (nombre_usuario, email, contraseña, fecha_registro, saldo_actual, estado)
    VALUES (p_nombre_usuario, p_email, p_contraseña, NOW(), 0, 1);
    
    -- Obtener el ID del usuario recién creado
    SET v_usuario_id = LAST_INSERT_ID();
    
    -- Crear entrada en el ranking para el nuevo usuario
    INSERT INTO Ranking (usuario_id, puntaje_total, partidas_jugadas, ultima_actualizacion)
    VALUES (v_usuario_id, 0, 0, NOW());
    
    COMMIT;
END //
DELIMITER ;

-- Procedimiento almacenado para iniciar una nueva partida
DELIMITER //
CREATE PROCEDURE sp_iniciar_partida(
    IN p_usuario_id INT,
    OUT p_partida_id INT
)
BEGIN
    DECLARE v_monedas_iniciales INT;
    
    -- Obtener monedas iniciales de la configuración
    SELECT max_monedas_iniciales INTO v_monedas_iniciales FROM Configuracion WHERE config_id = 1;
    
    -- Iniciar la partida
    INSERT INTO Partidas (usuario_id, fecha_inicio, monedas_iniciales, estado)
    VALUES (p_usuario_id, NOW(), v_monedas_iniciales, 'en_progreso');
    
    -- Devolver el ID de la partida creada
    SET p_partida_id = LAST_INSERT_ID();
END //
DELIMITER ;

-- Procedimiento almacenado para registrar una apuesta
DELIMITER //
CREATE PROCEDURE sp_registrar_apuesta(
    IN p_partida_id INT,
    IN p_numero_apostado INT,
    IN p_cantidad_apostada INT
)
BEGIN
    DECLARE v_numero_resultado INT;
    DECLARE v_ganancia INT;
    DECLARE v_multiplicador INT;
    
    -- Obtener el multiplicador de la configuración
    SELECT multiplicador_ganancia INTO v_multiplicador FROM Configuracion WHERE config_id = 1;
    
    -- Generar un número aleatorio para la ruleta (0-37, donde 37 representa el 00)
    SET v_numero_resultado = FLOOR(RAND() * 38);
    
    -- Calcular ganancia
    IF v_numero_resultado = p_numero_apostado THEN
        SET v_ganancia = p_cantidad_apostada * v_multiplicador;
    ELSE
        SET v_ganancia = 0;
    END IF;
    
    -- Registrar la apuesta
    INSERT INTO Apuestas (partida_id, numero_apostado, cantidad_apostada, numero_resultado, ganancia, fecha_apuesta)
    VALUES (p_partida_id, p_numero_apostado, p_cantidad_apostada, v_numero_resultado, v_ganancia, NOW());
END //
DELIMITER ;

-- Procedimiento almacenado para finalizar una partida
DELIMITER //
CREATE PROCEDURE sp_finalizar_partida(
    IN p_partida_id INT
)
BEGIN
    DECLARE v_usuario_id INT;
    DECLARE v_monedas_iniciales INT;
    DECLARE v_monedas_finales INT;
    
    -- Obtener información de la partida
    SELECT usuario_id, monedas_iniciales 
    INTO v_usuario_id, v_monedas_iniciales
    FROM Partidas 
    WHERE partida_id = p_partida_id;
    
    -- Calcular monedas finales
    SELECT v_monedas_iniciales + COALESCE(SUM(ganancia), 0) - COALESCE(SUM(cantidad_apostada), 0)
    INTO v_monedas_finales
    FROM Apuestas
    WHERE partida_id = p_partida_id;
    
    -- Si el resultado es negativo, establecerlo en 0
    IF v_monedas_finales < 0 THEN
        SET v_monedas_finales = 0;
    END IF;
    
    START TRANSACTION;
    
    -- Actualizar la partida como finalizada
    UPDATE Partidas
    SET fecha_fin = NOW(),
        monedas_finales = v_monedas_finales,
        estado = 'finalizada'
    WHERE partida_id = p_partida_id;
    
    -- Actualizar el ranking del usuario
    UPDATE Ranking
    SET puntaje_total = puntaje_total + v_monedas_finales,
        partidas_jugadas = partidas_jugadas + 1,
        ultima_actualizacion = NOW()
    WHERE usuario_id = v_usuario_id;
    
    COMMIT;
END //
DELIMITER ;

-- Modificaciones a la base de datos para soportar diferentes tipos de apuestas

USE ruleta_americana;

-- Primero, modificar la tabla de Apuestas para agregar tipo de apuesta
ALTER TABLE Apuestas 
ADD COLUMN tipo_apuesta VARCHAR(20) NOT NULL DEFAULT 'numero' COMMENT 'numero, par, impar, rojo, negro' AFTER partida_id,
MODIFY COLUMN numero_apostado VARCHAR(10) NULL COMMENT 'Número específico o NULL para otros tipos';

-- Actualizar la tabla de Configuración para incluir multiplicadores específicos
ALTER TABLE Configuracion
ADD COLUMN multiplicador_color DECIMAL(3,1) NOT NULL DEFAULT 1.5 COMMENT 'Multiplicador para apuestas a color',
ADD COLUMN multiplicador_paridad DECIMAL(3,1) NOT NULL DEFAULT 1.5 COMMENT 'Multiplicador para apuestas par/impar';

-- Actualizar la configuración existente
UPDATE Configuracion 
SET multiplicador_color = 1.5, 
    multiplicador_paridad = 1.5 
WHERE config_id = 1;

-- Eliminar el procedimiento anterior
DROP PROCEDURE IF EXISTS sp_registrar_apuesta_con_resultado;

-- Crear el nuevo procedimiento almacenado mejorado
DELIMITER //
CREATE PROCEDURE sp_registrar_apuesta_con_resultado(
    IN p_partida_id INT,
    IN p_tipo_apuesta VARCHAR(20),
    IN p_numero_apostado VARCHAR(10),
    IN p_cantidad_apostada INT,
    IN p_numero_resultado INT
)
BEGIN
    DECLARE v_ganancia INT DEFAULT 0;
    DECLARE v_multiplicador_numero INT;
    DECLARE v_multiplicador_color DECIMAL(3,1);
    DECLARE v_multiplicador_paridad DECIMAL(3,1);
    DECLARE v_es_rojo BOOLEAN DEFAULT FALSE;
    DECLARE v_es_par BOOLEAN DEFAULT FALSE;
    DECLARE v_apuesta_gana BOOLEAN DEFAULT FALSE;
    
    -- Obtener los multiplicadores de la configuración
    SELECT multiplicador_ganancia, multiplicador_color, multiplicador_paridad 
    INTO v_multiplicador_numero, v_multiplicador_color, v_multiplicador_paridad
    FROM Configuracion WHERE config_id = 1;
    
    -- Determinar si el número resultado es rojo
    IF p_numero_resultado IN (1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36) THEN
        SET v_es_rojo = TRUE;
    END IF;
    
    -- Determinar si el número resultado es par (excluyendo 0 y 00)
    IF p_numero_resultado > 0 AND p_numero_resultado < 37 AND MOD(p_numero_resultado, 2) = 0 THEN
        SET v_es_par = TRUE;
    END IF;
    
    -- Calcular si la apuesta gana según el tipo
    CASE p_tipo_apuesta
        WHEN 'numero' THEN
            -- Para apuesta a número específico
            IF p_numero_apostado IS NOT NULL AND CAST(p_numero_apostado AS UNSIGNED) = p_numero_resultado THEN
                SET v_apuesta_gana = TRUE;
                SET v_ganancia = p_cantidad_apostada * v_multiplicador_numero;
            END IF;
            
        WHEN 'rojo' THEN
            -- Para apuesta a rojo (0 y 00 no son rojos)
            IF p_numero_resultado > 0 AND p_numero_resultado < 37 AND v_es_rojo = TRUE THEN
                SET v_apuesta_gana = TRUE;
                SET v_ganancia = FLOOR(p_cantidad_apostada * v_multiplicador_color);
            END IF;
            
        WHEN 'negro' THEN
            -- Para apuesta a negro (0 y 00 no son negros)
            IF p_numero_resultado > 0 AND p_numero_resultado < 37 AND v_es_rojo = FALSE THEN
                SET v_apuesta_gana = TRUE;
                SET v_ganancia = FLOOR(p_cantidad_apostada * v_multiplicador_color);
            END IF;
            
        WHEN 'par' THEN
            -- Para apuesta a par (0 y 00 no cuentan)
            IF v_es_par = TRUE THEN
                SET v_apuesta_gana = TRUE;
                SET v_ganancia = FLOOR(p_cantidad_apostada * v_multiplicador_paridad);
            END IF;
            
        WHEN 'impar' THEN
            -- Para apuesta a impar (0 y 00 no cuentan)
            IF p_numero_resultado > 0 AND p_numero_resultado < 37 AND v_es_par = FALSE THEN
                SET v_apuesta_gana = TRUE;
                SET v_ganancia = FLOOR(p_cantidad_apostada * v_multiplicador_paridad);
            END IF;
    END CASE;
    
    -- Registrar la apuesta
    INSERT INTO Apuestas (partida_id, tipo_apuesta, numero_apostado, cantidad_apostada, numero_resultado, ganancia, fecha_apuesta)
    VALUES (p_partida_id, p_tipo_apuesta, p_numero_apostado, p_cantidad_apostada, p_numero_resultado, v_ganancia, NOW());
    
    -- Devolver los datos de la apuesta registrada
    SELECT 
        LAST_INSERT_ID() as apuesta_id,
        p_numero_resultado as numero_resultado,
        v_ganancia as ganancia,
        NOW() as fecha_apuesta;
END //
DELIMITER ;