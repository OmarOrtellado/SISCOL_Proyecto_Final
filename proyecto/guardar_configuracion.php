<?php
session_start();
require_once "conexion.php";

// Crear la conexi車n
$conn = conectar();

// Verificaci車n de sesi車n y permisos
if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "super_usuario") {
    header("Location: index.html");
    exit();
}

// Validar que lleguen todos los datos requeridos
if (!isset($_POST['nombre_institucion'], $_POST['direccion'], $_POST['telefono'], $_POST['email'], $_POST['lema'], $_POST['anio_lectivo'])) {
    die("Faltan datos del formulario.");
}

// Subida de logo
$logo = null;
if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    // Asegurar carpeta de destino
    $carpetaDestino = "uploads/";
    if (!is_dir($carpetaDestino)) {
        mkdir($carpetaDestino, 0777, true);
    }

    $nombreLogo = time() . "_" . basename($_FILES["logo"]["name"]);
    $rutaDestino = $carpetaDestino . $nombreLogo;

    if (move_uploaded_file($_FILES["logo"]["tmp_name"], $rutaDestino)) {
        $logo = $nombreLogo;
    }
}

// Construir SQL din芍mico
$sql = "UPDATE configuracion_sistema 
        SET nombre_institucion=?, direccion=?, telefono=?, email=?, lema=?, anio_lectivo=?";
$params = [
    $_POST['nombre_institucion'],
    $_POST['direccion'],
    $_POST['telefono'],
    $_POST['email'],
    $_POST['lema'],
    (int)$_POST['anio_lectivo'] // asegurar que sea n迆mero
];
$types = "sssssi"; // tipos: 5 strings + 1 int

if ($logo) {
    $sql .= ", logo=?";
    $params[] = $logo;
    $types .= "s";
}

$sql .= " WHERE id=1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error en prepare: " . $conn->error);
}

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    header("Location: configuracion.php?msg=ok");
    exit();
} else {
    echo "Error al guardar configuraci車n: " . $stmt->error;
}
