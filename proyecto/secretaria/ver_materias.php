<?php
// ver_materias.php
// Archivo para ver, modificar y eliminar materias en el sistema SISCOL
// Ubicación: /proyecto/secretaria/ver_materias.php

// Incluir el archivo de conexión
include '../conexion.php';

// Función para registrar en la auditoría (añadida aquí)
function registrarAuditoria($conn, $id_usuario, $tipo_usuario, $usuario_nombre, $accion, $resultado, $motivo = null, $objeto_afectado = null, $id_objeto = null, $campo_modificado = null, $valor_anterior = null, $valor_nuevo = null) {
    $ip_origen = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $session_id = session_id();

    $sql_auditoria = "INSERT INTO auditoria (
        id_usuario, tipo_usuario, usuario_nombre, accion, resultado, motivo_fallo,
        objeto_afectado, id_objeto, campo_modificado, valor_anterior, valor_nuevo,
        ip_origen, user_agent, session_id, fecha_hora
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(6))";

    $stmt_auditoria = $conn->prepare($sql_auditoria);
    if ($stmt_auditoria) {
        $stmt_auditoria->bind_param(
            "isssssisssssss", // 14 tipos: i s s s s s s i s s s s s s
            $id_usuario,
            $tipo_usuario,
            $usuario_nombre,
            $accion,
            $resultado,
            $motivo,
            $objeto_afectado,
            $id_objeto, // Este es un entero, por lo tanto, tipo 'i'
            $campo_modificado,
            $valor_anterior,
            $valor_nuevo,
            $ip_origen,
            $user_agent,
            $session_id
        );
        $stmt_auditoria->execute();
        $stmt_auditoria->close();
    }
}

// Verificar sesión y rol (opcional para este archivo, pero recomendable)
session_start();
if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] !== "secretaria") {
    header("Location: ../index.php");
    exit();
}

$usuario_nombre_sesion = $_SESSION["usuario"];
$id_usuario_sesion = $_SESSION["id_usuario"];
$tipo_usuario_sesion = $_SESSION["rol"]; // "secretaria"

// Inicializar variables
$mensaje = '';
$conn = null;

// Procesar acciones (UPDATE o DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id_materia = (int)($_POST['id_materia'] ?? 0);
    
    if ($id_materia > 0) {
        $conn = conectar();
        
        // Verificar si hay estudiantes inscriptos en la materia
        $sql_check = "SELECT COUNT(*) as total FROM analitico WHERE id_materia = ? AND activo = 1";
        $stmt_check = $conn->prepare($sql_check);
        $tiene_estudiantes = false;
        if ($stmt_check) {
            $stmt_check->bind_param("i", $id_materia);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($row_check = $result_check->fetch_assoc()) {
                $tiene_estudiantes = ($row_check['total'] > 0);
            }
            $stmt_check->close();
        }
        
        if ($tiene_estudiantes && ($accion === 'update' || $accion === 'delete')) {
            $mensaje = '<div class="alert alert-error">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span>No se puede ' . ($accion === 'delete' ? 'eliminar' : 'modificar') . ' esta materia porque tiene estudiantes inscriptos.</span>
            </div>';
            // Registrar intento fallido
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, strtoupper($accion) . '_MATERIA', 'FALLIDO', 'Tiene estudiantes inscriptos', 'materias', $id_materia, null, null, null);
        } else {
            if ($accion === 'update') {
                // Obtener valores anteriores para auditoría
                $stmt_anterior = $conn->prepare("SELECT nombre, id_carrera, activo, año FROM materias WHERE id = ?");
                $stmt_anterior->bind_param("i", $id_materia);
                $stmt_anterior->execute();
                $result_anterior = $stmt_anterior->get_result();
                $row_anterior = $result_anterior->fetch_assoc();
                $stmt_anterior->close();

                $nombre = trim($_POST['nombre'] ?? '');
                $id_carrera = (int)($_POST['id_carrera'] ?? 0);
                $año = (int)($_POST['año'] ?? 0);
                $activo = (int)($_POST['activo'] ?? 1);
                
                if (empty($nombre)) {
                    $mensaje = '<div class="alert alert-error"><span>El nombre es obligatorio.</span></div>';
                    // Registrar intento fallido
                    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_MATERIA', 'FALLIDO', 'Nombre vacío', 'materias', $id_materia, null, null, null);
                } elseif ($id_carrera <= 0) {
                    $mensaje = '<div class="alert alert-error"><span>Debe seleccionar una carrera válida.</span></div>';
                    // Registrar intento fallido
                    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_MATERIA', 'FALLIDO', 'Carrera inválida', 'materias', $id_materia, null, null, null);
                } elseif ($año < 1) {
                    $mensaje = '<div class="alert alert-error"><span>Debe seleccionar un año válido.</span></div>';
                    // Registrar intento fallido
                    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_MATERIA', 'FALLIDO', 'Año inválido', 'materias', $id_materia, null, null, null);
                } else {
                    // Validar año contra duracion de la carrera
                    $duracion_carrera = 0;
                    $sql_duracion = "SELECT duracion FROM carreras WHERE id_carrera = ?";
                    $stmt_dur = $conn->prepare($sql_duracion);
                    if ($stmt_dur) {
                        $stmt_dur->bind_param("i", $id_carrera);
                        $stmt_dur->execute();
                        $result_dur = $stmt_dur->get_result();
                        if ($row_dur = $result_dur->fetch_assoc()) {
                            $duracion_carrera = (int)$row_dur['duracion'];
                        }
                        $stmt_dur->close();
                    }

                    if ($año > $duracion_carrera) {
                        $mensaje = '<div class="alert alert-error"><span>El año seleccionado (' . $año . ') excede la duración de la carrera (' . $duracion_carrera . ' años).</span></div>';
                        // Registrar intento fallido
                        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_MATERIA', 'FALLIDO', 'Año excede duración de la carrera', 'materias', $id_materia, null, null, null);
                    } else {
                        $sql = "UPDATE materias SET nombre = ?, id_carrera = ?, activo = ?, año = ?, fecha_modificacion = CURRENT_TIMESTAMP WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param("siiiii", $nombre, $id_carrera, $activo, $año, $id_materia);
                            if ($stmt->execute()) {
                                $mensaje = '<div class="alert alert-success">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    <span>Materia actualizada exitosamente.</span>
                                </div>';
                                // Registrar éxito - Registrar cada campo modificado
                                if ($row_anterior['nombre'] !== $nombre) {
                                    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_MATERIA', 'EXITO', null, 'materias', $id_materia, 'nombre', $row_anterior['nombre'], $nombre);
                                }
                                if ($row_anterior['id_carrera'] !== $id_carrera) {
                                    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_MATERIA', 'EXITO', null, 'materias', $id_materia, 'id_carrera', $row_anterior['id_carrera'], $id_carrera);
                                }
                                if ($row_anterior['activo'] !== $activo) {
                                    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_MATERIA', 'EXITO', null, 'materias', $id_materia, 'activo', $row_anterior['activo'], $activo);
                                }
                                if ($row_anterior['año'] !== $año) {
                                    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_MATERIA', 'EXITO', null, 'materias', $id_materia, 'año', $row_anterior['año'], $año);
                                }
                            } else {
                                $mensaje = '<div class="alert alert-error"><span>Error al actualizar: ' . $stmt->error . '</span></div>';
                                // Registrar intento fallido
                                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_MATERIA', 'FALLIDO', $stmt->error, 'materias', $id_materia, null, null, null);
                            }
                            $stmt->close();
                        }
                    }
                }
            } elseif ($accion === 'delete') {
                // Obtener nombre de la materia para auditoría
                $stmt_nombre = $conn->prepare("SELECT nombre FROM materias WHERE id = ?");
                $stmt_nombre->bind_param("i", $id_materia);
                $stmt_nombre->execute();
                $result_nombre = $stmt_nombre->get_result();
                $nombre_materia = '';
                if ($row_nombre = $result_nombre->fetch_assoc()) {
                    $nombre_materia = $row_nombre['nombre'];
                }
                $stmt_nombre->close();

                $sql = "DELETE FROM materias WHERE id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $id_materia);
                    if ($stmt->execute()) {
                        $mensaje = '<div class="alert alert-success">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <span>Materia eliminada exitosamente.</span>
                        </div>';
                        // Registrar éxito
                        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'DELETE_MATERIA', 'EXITO', null, 'materias', $id_materia, 'nombre', $nombre_materia, null);
                    } else {
                        $mensaje = '<div class="alert alert-error"><span>Error al eliminar: ' . $stmt->error . '</span></div>';
                        // Registrar intento fallido
                        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'DELETE_MATERIA', 'FALLIDO', $stmt->error, 'materias', $id_materia, null, null, null);
                    }
                    $stmt->close();
                }
            }
        }
        
        if ($conn) $conn->close();
    }
}

// Consultar materias existentes (con JOIN a carreras, incluyendo año y duracion, y conteo de estudiantes)
$materias = [];
$carreras = [];
$conn = conectar();
$sql_materias = "SELECT m.id, m.nombre, m.año, m.activo, m.fecha_creacion, m.id_carrera, 
                 c.nombre AS carrera, c.duracion,
                 (SELECT COUNT(*) FROM analitico a WHERE a.id_materia = m.id AND a.activo = 1) as total_estudiantes
                 FROM materias m 
                 JOIN carreras c ON m.id_carrera = c.id_carrera 
                 ORDER BY m.fecha_creacion DESC";
$result_materias = $conn->query($sql_materias);
if ($result_materias) {
    while ($row = $result_materias->fetch_assoc()) {
        $materias[] = $row;
    }
}

// Cargar carreras para el select en modal de edición (con duracion)
$sql_carreras = "SELECT id_carrera, nombre, duracion FROM carreras WHERE estado = 'activa' ORDER BY nombre";
$result_carreras = $conn->query($sql_carreras);
if ($result_carreras) {
    while ($row = $result_carreras->fetch_assoc()) {
        $carreras[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISCOL - Ver Materias</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            position: relative;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .header h2 {
            font-size: 18px;
            font-weight: 400;
            opacity: 0.95;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-container {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-container input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-container input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-crear {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-crear:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        .btn-atras {
            background: #6c757d;
            color: white;
        }

        .btn-atras:hover {
            background: #545b62;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.4);
        }

        .content {
            padding: 30px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-secondary {
            background: #e2e3e5;
            color: #383d41;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .btn-edit, .btn-delete {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 2px;
        }

        .btn-edit {
            background: #ffc107;
            color: #000;
        }

        .btn-edit:hover:not(:disabled) {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover:not(:disabled) {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
        }

        .btn-edit:disabled, .btn-delete:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 20px;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .close:hover {
            transform: scale(1.2);
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 20px 30px;
            background: #f8f9fa;
            border-radius: 0 0 15px 15px;
        }

        .btn-save {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-cancel:hover {
            background: #545b62;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-container {
                order: -1;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px 8px;
            }

            .btn-edit, .btn-delete {
                padding: 6px 12px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 SISCOL - Sistema de Control Escolar</h1>
            <h2>Gestión de Materias</h2>
        </div>

        <div class="top-bar">
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Buscar por nombre, carrera o año..." onkeyup="filterTable()">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            </div>
            <button class="btn btn-crear" onclick="window.location.href='cargar_nueva_materia.php'">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Nueva Materia
            </button>
            <button class="btn btn-atras" onclick="window.location.href='../panel_secretaria.php'">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Volver
            </button>
        </div>

        <div class="content">
            <?php if ($mensaje): ?>
                <?php echo $mensaje; ?>
            <?php endif; ?>

            <?php if (empty($materias)): ?>
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                    </svg>
                    <h3>No hay materias registradas</h3>
                    <p>Comienza agregando la primera materia al sistema</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table id="materiasTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Carrera</th>
                                <th>Año</th>
                                <th>Estudiantes</th>
                                <th>Estado</th>
                                <th>Fecha Creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materias as $materia): ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars($materia['id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($materia['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($materia['carrera']); ?></td>
                                <td><span class="badge badge-secondary">Año <?php echo htmlspecialchars($materia['año']); ?></span></td>
                                <td>
                                    <?php if ($materia['total_estudiantes'] > 0): ?>
                                        <span class="badge badge-warning"><?php echo $materia['total_estudiantes']; ?> inscriptos</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Sin inscriptos</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($materia['activo'] == 1): ?>
                                        <span class="badge badge-success">✓ Activa</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">○ Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($materia['fecha_creacion'])); ?></td>
                                <td>
                                    <button 
                                        class="btn-edit" 
                                        onclick="openEditModal(<?php echo $materia['id']; ?>, '<?php echo htmlspecialchars(addslashes($materia['nombre'])); ?>', <?php echo $materia['id_carrera']; ?>, <?php echo $materia['año']; ?>, <?php echo $materia['activo']; ?>, <?php echo $materia['duracion']; ?>)"
                                        <?php echo ($materia['total_estudiantes'] > 0) ? 'disabled title="No se puede modificar: tiene estudiantes inscriptos"' : ''; ?>>
                                        Modificar
                                    </button>
                                    <button 
                                        class="btn-delete" 
                                        onclick="openDeleteModal(<?php echo $materia['id']; ?>, '<?php echo htmlspecialchars($materia['nombre']); ?>')"
                                        <?php echo ($materia['total_estudiantes'] > 0) ? 'disabled title="No se puede eliminar: tiene estudiantes inscriptos"' : ''; ?>>
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para Editar -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Modificar Materia</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST" action="" id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="update">
                    <input type="hidden" name="id_materia" id="edit_id">
                    
                    <div class="form-group">
                        <label for="edit_nombre">Nombre de la Materia:</label>
                        <input type="text" id="edit_nombre" name="nombre" required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_id_carrera">Carrera:</label>
                        <select id="edit_id_carrera" name="id_carrera" required onchange="updateEditYearOptions()">
                            <option value="">Seleccione una carrera</option>
                            <?php foreach ($carreras as $carrera): ?>
                            <option value="<?php echo $carrera['id_carrera']; ?>" data-duracion="<?php echo $carrera['duracion']; ?>">
                                <?php echo htmlspecialchars($carrera['nombre']); ?> (<?php echo $carrera['duracion']; ?> años)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_año">Año de la Carrera:</label>
                        <select id="edit_año" name="año" required>
                            <option value="">Seleccione un año</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="edit_activo" name="activo" value="1">
                            <span>Materia activa</span>
                        </label>
                    </div>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeModal('editModal')">Cancelar</button>
                    <button type="submit" class="btn btn-save">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Eliminar -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🗑️ Confirmar Eliminación</h3>
                <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            </div>
            <form method="POST" action="" id="deleteForm">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="delete">
                    <input type="hidden" name="id_materia" id="delete_id">
                    <p id="deleteMessage" style="font-size: 16px; line-height: 1.6;"></p>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeModal('deleteModal')">Cancelar</button>
                    <button type="submit" class="btn btn-delete">Eliminar Materia</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, nombre, id_carrera, año, activo, duracion) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_id_carrera').value = id_carrera;
            document.getElementById('edit_activo').checked = (activo == 1);
            
            const editCarreraSelect = document.getElementById('edit_id_carrera');
            const currentOption = Array.from(editCarreraSelect.options).find(opt => opt.value == id_carrera);
            if (currentOption) {
                currentOption.selected = true;
                updateEditYearOptions();
                if (año > 0) {
                    document.getElementById('edit_año').value = año;
                }
            }
            
            document.getElementById('editModal').style.display = 'block';
        }

        function updateEditYearOptions() {
            const carreraSelect = document.getElementById('edit_id_carrera');
            const añoSelect = document.getElementById('edit_año');
            const selectedOption = carreraSelect.options[carreraSelect.selectedIndex];
            
            añoSelect.innerHTML = '<option value="">Seleccione un año</option>';
            
            if (selectedOption.value && selectedOption.dataset.duracion) {
                const duracion = parseInt(selectedOption.dataset.duracion);
                for (let i = 1; i <= duracion; i++) {
                    const option = document.createElement('option');
                    option.value = i;
                    option.textContent = 'Año ' + i;
                    añoSelect.appendChild(option);
                }
            }
        }

        function openDeleteModal(id, nombre) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteMessage').innerHTML = '¿Está seguro de que desea eliminar la materia "<strong>' + nombre + '</strong>"?<br><br>⚠️ Esta acción no se puede deshacer.';
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('materiasTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let visible = false;
                for (let j = 1; j < td.length - 1; j++) {
                    if (td[j] && td[j].textContent.toLowerCase().indexOf(filter) > -1) {
                        visible = true;
                        break;
                    }
                }
                tr[i].style.display = visible ? '' : 'none';
            }
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>