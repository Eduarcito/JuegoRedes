<?php
/**
 * Configuración de conexión a la base de datos
 * Este archivo puede ser modificado para cada entorno
 */

// Configuración de la base de datos
$db_config = [
    // Configuración para entorno local (XAMPP)
    'local' => [
        'host' => 'localhost',
        'db_name' => 'ruleta_americana',
        'username' => 'root', // Asegúrate de que este usuario tenga permisos
        'password' => '', // Si usas 'redes', cambia aquí y en MySQL
        'port' => 3306
    ],
    
    // Configuración para entorno de producción (VPS)
    'production' => [
        'host' => 'nombre_servidor_vps', // Cambiar por la IP o hostname del VPS
        'db_name' => 'ruleta_americana',
        'username' => 'usuario_vps',     // Cambiar por el usuario real
        'password' => 'password_vps',    // Cambiar por la contraseña real
        'port' => 3306
    ]
];

// Detectar el entorno actual
function determinarEntorno() {
    $server_name = $_SERVER['SERVER_NAME'] ?? '';
    
    if ($server_name == 'localhost' || $server_name == '127.0.0.1') {
        return 'local';
    } else {
        return 'production';
    }
}

// Establecer la configuración según el entorno
$entorno_actual = determinarEntorno();
$db_config = $db_config[$entorno_actual];

// Conectar a la base de datos usando PDO
try {
    $conn = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['db_name']};port={$db_config['port']};charset=utf8",
        $db_config['username'],
        $db_config['password']
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
