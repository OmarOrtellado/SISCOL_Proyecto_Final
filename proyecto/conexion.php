<?php
function conectar() {
    $host = 'localhost';
    $db   = 'proysiscol';
    $user = 'root';
    $pass = '';

    // Crear conexión (usar $db)
    $conn = new mysqli($host, $user, $pass, $db);

    // Verificar conexión
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }

    // --- A�0�9adir esta l��nea ---
    // Establecer el charset para la conexión a UTF-8
    // utf8mb4 es el conjunto de caracteres recomendado para soportar completamente UTF-8
    $conn->set_charset("utf8mb4");

    return $conn;
}
?>