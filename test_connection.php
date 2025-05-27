<?php
try {
    $conn = new PDO("mysql:host=localhost;dbname=ruleta_americana", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Conexión exitosa!";
} catch(PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
}
?>