<?php
// descripción_carrera.php
// Archivo para mostrar detalles de una carrera y sus materias en el sistema SISCOL
// Ubicación: /proyecto/secretaria/descripción_carrera.php

// Incluir el archivo de conexión
include '../conexion.php';

$id_carrera = (int)($_GET['id'] ?? 0);
$carrera = null;
$materias_por_año = [];

if ($id_carrera > 0) {
    $conn = conectar();
    
    // Obtener detalles de la carrera
    $sql_carrera = "SELECT id_carrera, nombre, descripcion, duracion, estado, fecha_creacion FROM carreras WHERE id_carrera = ?";
    $stmt_carrera = $conn->prepare($sql_carrera);
    if ($stmt_carrera) {
        $stmt_carrera->bind_param("i", $id_carrera);
        $stmt_carrera->execute();
        $result_carrera = $stmt_carrera->get_result();
        $carrera = $result_carrera->fetch_assoc();
        $stmt_carrera->close();
    }
    
    // Obtener materias asociadas (nombre y año)
    if ($carrera) {
        $sql_materias = "SELECT nombre, año FROM materias WHERE id_carrera = ? ORDER BY año ASC, fecha_creacion ASC";
        $stmt_materias = $conn->prepare($sql_materias);
        if ($stmt_materias) {
            $stmt_materias->bind_param("i", $id_carrera);
            $stmt_materias->execute();
            $result_materias = $stmt_materias->get_result();
            while ($row_materia = $result_materias->fetch_assoc()) {
                $año = (int)$row_materia['año'];
                if (!isset($materias_por_año[$año])) {
                    $materias_por_año[$año] = [];
                }
                $materias_por_año[$año][] = $row_materia['nombre'];
            }
            $stmt_materias->close();
        }
    }
    
    $conn->close();
}

if (!$carrera) {
    $error = 'Carrera no encontrada o ID inválido.';
}

// Función para obtener el nombre del año en texto
function getNombreAño($numero) {
    $nombres = [
        1 => 'PRIMER AÑO',
        2 => 'SEGUNDO AÑO',
        3 => 'TERCER AÑO',
        4 => 'CUARTO AÑO',
        5 => 'QUINTO AÑO',
        6 => 'SEXTO AÑO',
        7 => 'SÉPTIMO AÑO',
        8 => 'OCTAVO AÑO',
        9 => 'NOVENO AÑO',
        10 => 'DÉCIMO AÑO'
    ];
    return $nombres[$numero] ?? 'AÑO ' . $numero;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISCOL - Descripción de Carrera</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .header { text-align: center; color: #007bff; margin-bottom: 30px; }
        .detail-section { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 16px; }
        .detail-label { font-weight: bold; color: #007bff; }
        .materias-section { background: #f8f9fa; padding: 20px; border-radius: 5px; }
        .materias-section h4 { color: #007bff; margin-bottom: 10px; }
        .año-section { margin-bottom: 20px; }
        .año-section h5 { color: #28a745; margin-bottom: 10px; border-bottom: 1px solid #dee2e6; padding-bottom: 5px; }
        .año-section ul { list-style-type: none; padding: 0; margin: 0; }
        .año-section li { padding: 8px; border-bottom: 1px solid #dee2e6; }
        .año-section li:last-child { border-bottom: none; }
        .no-materias { color: #6c757d; font-style: italic; text-align: center; padding: 20px; }
        .btn-atras { background: #6c757d; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px; text-decoration: none; display: inline-block; margin-top: 20px; }
        .btn-atras:hover { background: #545b62; }
        .error { color: red; text-align: center; font-size: 18px; margin: 50px 0; }
    </style>
</head>
<body>
    <h1 class="header">SISCOL - Secretaría</h1>
    
    <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <a href="ver_carreras.php" class="btn-atras">ATRÁS</a>
    <?php else: ?>
        <h2>Detalles de la Carrera: <?php echo htmlspecialchars($carrera['nombre']); ?></h2>
        
        <div class="detail-section">
            <div class="detail-row">
                <span class="detail-label">ID:</span>
                <span><?php echo htmlspecialchars($carrera['id_carrera']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Nombre:</span>
                <span><?php echo htmlspecialchars($carrera['nombre']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Descripción:</span>
                <span><?php echo nl2br(htmlspecialchars($carrera['descripcion'] ?? 'Sin descripción.')); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Duración (años):</span>
                <span><?php echo htmlspecialchars($carrera['duracion']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Estado:</span>
                <span><?php echo htmlspecialchars(ucfirst($carrera['estado'])); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Fecha Creación:</span>
                <span><?php echo date('d/m/Y', strtotime($carrera['fecha_creacion'])); ?></span>
            </div>
        </div>
        
        <div class="materias-section">
            <h4>Materias de la carrera</h4>
            <?php 
            $hay_materias = false;
            for ($i = 1; $i <= $carrera['duracion']; $i++): 
                if (isset($materias_por_año[$i]) && !empty($materias_por_año[$i])): 
                    $hay_materias = true;
            ?>
                <div class="año-section">
                    <h5><?php echo getNombreAño($i); ?></h5>
                    <ul>
                        <?php foreach ($materias_por_año[$i] as $materia): ?>
                            <li><?php echo htmlspecialchars($materia); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php 
                endif; 
            endfor; 
            ?>
            <?php if (!$hay_materias): ?>
                <div class="no-materias">No hay materias asociadas a esta carrera.</div>
            <?php endif; ?>
        </div>
        
        <a href="ver_carreras.php" class="btn-atras">ATRÁS</a>
    <?php endif; ?>
</body>
</html>