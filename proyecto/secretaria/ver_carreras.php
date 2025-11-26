<?php
// ver_carreras.php
// Archivo para ver, modificar y eliminar carreras en el sistema SISCOL
// Ubicación: /proyecto/secretaria/ver_carreras.php

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
    $id_carrera = (int)($_POST['id_carrera'] ?? 0);
    
    if ($id_carrera > 0) {
        $conn = conectar();
        
        // Verificar si hay estudiantes inscriptos en la carrera
        $sql_check = "SELECT COUNT(*) as total FROM estudiante WHERE id_carrera = ? AND activo = 1";
        $stmt_check = $conn->prepare($sql_check);
        $tiene_estudiantes = false;
        if ($stmt_check) {
            $stmt_check->bind_param("i", $id_carrera);
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
                <span>No se puede ' . ($accion === 'delete' ? 'eliminar' : 'modificar') . ' esta carrera porque tiene estudiantes inscriptos.</span>
            </div>';
            // Registrar intento fallido
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, strtoupper($accion) . '_CARRERA', 'FALLIDO', 'Tiene estudiantes inscriptos', 'carreras', $id_carrera, null, null, null);
        } else {
            if ($accion === 'update') {
                // Obtener valores anteriores para auditoría
                $stmt_anterior = $conn->prepare("SELECT nombre, descripcion, duracion, estado FROM carreras WHERE id_carrera = ?");
                $stmt_anterior->bind_param("i", $id_carrera);
                $stmt_anterior->execute();
                $result_anterior = $stmt_anterior->get_result();
                $row_anterior = $result_anterior->fetch_assoc();
                $stmt_anterior->close();

                $nombre = trim($_POST['nombre'] ?? '');
                $descripcion = trim($_POST['descripcion'] ?? '');
                $duracion = (int)($_POST['duracion'] ?? 4);
                $estado = $_POST['estado'] ?? 'activa';
                
                if (empty($nombre)) {
                    $mensaje = '<div class="alert alert-error"><span>El nombre es obligatorio.</span></div>';
                    // Registrar intento fallido
                    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_CARRERA', 'FALLIDO', 'Nombre vacío', 'carreras', $id_carrera, null, null, null);
                } elseif ($duracion < 1 || $duracion > 10) {
                    $mensaje = '<div class="alert alert-error"><span>La duración debe ser entre 1 y 10 años.</span></div>';
                    // Registrar intento fallido
                    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_CARRERA', 'FALLIDO', 'Duración inválida', 'carreras', $id_carrera, null, null, null);
                } else {
                    $sql = "UPDATE carreras SET nombre = ?, descripcion = ?, duracion = ?, estado = ? WHERE id_carrera = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssisi", $nombre, $descripcion, $duracion, $estado, $id_carrera);
                        if ($stmt->execute()) {
                            $mensaje = '<div class="alert alert-success">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <span>Carrera actualizada exitosamente.</span>
                            </div>';
                            // Registrar éxito - Registrar cada campo modificado
                            if ($row_anterior['nombre'] !== $nombre) {
                                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_CARRERA', 'EXITO', null, 'carreras', $id_carrera, 'nombre', $row_anterior['nombre'], $nombre);
                            }
                            if ($row_anterior['descripcion'] !== $descripcion) {
                                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_CARRERA', 'EXITO', null, 'carreras', $id_carrera, 'descripcion', $row_anterior['descripcion'], $descripcion);
                            }
                            if ($row_anterior['duracion'] !== $duracion) {
                                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_CARRERA', 'EXITO', null, 'carreras', $id_carrera, 'duracion', $row_anterior['duracion'], $duracion);
                            }
                            if ($row_anterior['estado'] !== $estado) {
                                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_CARRERA', 'EXITO', null, 'carreras', $id_carrera, 'estado', $row_anterior['estado'], $estado);
                            }
                        } else {
                            $mensaje = '<div class="alert alert-error"><span>Error al actualizar: ' . $stmt->error . '</span></div>';
                            // Registrar intento fallido
                            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'UPDATE_CARRERA', 'FALLIDO', $stmt->error, 'carreras', $id_carrera, null, null, null);
                        }
                        $stmt->close();
                    }
                }
            } elseif ($accion === 'delete') {
                // Obtener nombre de la carrera para auditoría
                $stmt_nombre = $conn->prepare("SELECT nombre FROM carreras WHERE id_carrera = ?");
                $stmt_nombre->bind_param("i", $id_carrera);
                $stmt_nombre->execute();
                $result_nombre = $stmt_nombre->get_result();
                $nombre_carrera = '';
                if ($row_nombre = $result_nombre->fetch_assoc()) {
                    $nombre_carrera = $row_nombre['nombre'];
                }
                $stmt_nombre->close();

                // Primero, eliminar materias relacionadas (cascada manual)
                $sql_materias = "DELETE FROM materias WHERE id_carrera = ?";
                $stmt_materias = $conn->prepare($sql_materias);
                if ($stmt_materias) {
                    $stmt_materias->bind_param("i", $id_carrera);
                    $stmt_materias->execute();
                    $stmt_materias->close();
                }
                
                // Luego, eliminar la carrera
                $sql_carrera = "DELETE FROM carreras WHERE id_carrera = ?";
                $stmt_carrera = $conn->prepare($sql_carrera);
                if ($stmt_carrera) {
                    $stmt_carrera->bind_param("i", $id_carrera);
                    if ($stmt_carrera->execute()) {
                        $mensaje = '<div class="alert alert-success">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <span>Carrera y materias asociadas eliminadas exitosamente.</span>
                        </div>';
                        // Registrar éxito
                        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'DELETE_CARRERA', 'EXITO', null, 'carreras', $id_carrera, 'nombre', $nombre_carrera, null);
                        // Registrar también la eliminación de materias asociadas
                        // registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'DELETE_CARRERA', 'EXITO', null, 'materias', null, 'id_carrera', $id_carrera, null); // Opcional, si se quiere registrar la cascada
                    } else {
                        $mensaje = '<div class="alert alert-error"><span>Error al eliminar: ' . $stmt_carrera->error . '</span></div>';
                        // Registrar intento fallido
                        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'DELETE_CARRERA', 'FALLIDO', $stmt_carrera->error, 'carreras', $id_carrera, null, null, null);
                    }
                    $stmt_carrera->close();
                }
            }
        }
        
        if ($conn) $conn->close();
    }
}

// Consultar carreras existentes con conteo de estudiantes y materias
$carreras = [];
$conn = conectar();
$sql = "SELECT c.id_carrera, c.nombre, c.descripcion, c.duracion, c.estado, c.fecha_creacion,
        (SELECT COUNT(*) FROM estudiante e WHERE e.id_carrera = c.id_carrera AND e.activo = 1) as total_estudiantes,
        (SELECT COUNT(*) FROM materias m WHERE m.id_carrera = c.id_carrera AND m.activo = 1) as total_materias
        FROM carreras c 
        ORDER BY c.fecha_creacion DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
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
    <title>SISCOL - Ver Carreras</title>
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
            max-width: 1400px;
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
            vertical-align: top;
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

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-secondary {
            background: #e2e3e5;
            color: #383d41;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .descripcion-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #6c757d;
            font-size: 13px;
        }

        .stats-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-edit, .btn-delete, .btn-view {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 2px;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-view:hover {
            background: #138496;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(23, 162, 184, 0.3);
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
            max-width: 600px;
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
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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

            .btn-edit, .btn-delete, .btn-view {
                padding: 6px 12px;
                font-size: 11px;
                display: block;
                width: 100%;
                margin: 2px 0;
            }

            .descripcion-preview {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 SISCOL - Sistema de Control Escolar</h1>
            <h2>Gestión de Carreras</h2>
        </div>

        <div class="top-bar">
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Buscar por nombre, descripción o estado..." onkeyup="filterTable()">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            </div>
            <button class="btn btn-crear" onclick="window.location.href='cargar_nueva_carrera.php'">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Nueva Carrera
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

            <?php if (empty($carreras)): ?>
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                        <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                    </svg>
                    <h3>No hay carreras registradas</h3>
                    <p>Comienza agregando la primera carrera al sistema</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table id="carrerasTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Duración</th>
                                <th>Estadísticas</th>
                                <th>Estado</th>
                                <th>Fecha Creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($carreras as $carrera): ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars($carrera['id_carrera']); ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($carrera['nombre']); ?></strong></td>
                                <td>
                                    <div class="descripcion-preview" title="<?php echo htmlspecialchars($carrera['descripcion'] ?? ''); ?>">
                                        <?php 
                                        $desc = $carrera['descripcion'] ?? 'Sin descripción';
                                        echo htmlspecialchars(strlen($desc) > 80 ? substr($desc, 0, 80) . '...' : $desc);
                                        ?>
                                    </div>
                                </td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($carrera['duracion']); ?> años</span></td>
                                <td>
                                    <div class="stats-group">
                                        <?php if ($carrera['total_estudiantes'] > 0): ?>
                                            <span class="badge badge-warning">👥 <?php echo $carrera['total_estudiantes']; ?> estudiantes</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">👥 Sin estudiantes</span>
                                        <?php endif; ?>
                                        <span class="badge badge-secondary">📚 <?php echo $carrera['total_materias']; ?> materias</span>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $estado = $carrera['estado'];
                                    $badge_class = 'badge-secondary';
                                    $icon = '○';
                                    if ($estado === 'activa') {
                                        $badge_class = 'badge-success';
                                        $icon = '✓';
                                    } elseif ($estado === 'suspendida') {
                                        $badge_class = 'badge-danger';
                                        $icon = '⊗';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $icon; ?> <?php echo ucfirst($estado); ?></span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($carrera['fecha_creacion'])); ?></td>
                                <td>
                                    <a href="descripcion_carrera.php?id=<?php echo $carrera['id_carrera']; ?>" style="text-decoration: none;">
                                        <button class="btn-view">👁️ Ver</button>
                                    </a>
                                    <button 
                                        class="btn-edit" 
                                        onclick="openEditModal(<?php echo $carrera['id_carrera']; ?>, '<?php echo htmlspecialchars(addslashes($carrera['nombre'])); ?>', '<?php echo htmlspecialchars(addslashes($carrera['descripcion'] ?? '')); ?>', <?php echo $carrera['duracion']; ?>, '<?php echo $carrera['estado']; ?>')"
                                        <?php echo ($carrera['total_estudiantes'] > 0) ? 'disabled title="No se puede modificar: tiene estudiantes inscriptos"' : ''; ?>>
                                        ✏️ Modificar
                                    </button>
                                    <button 
                                        class="btn-delete" 
                                        onclick="openDeleteModal(<?php echo $carrera['id_carrera']; ?>, '<?php echo htmlspecialchars($carrera['nombre']); ?>')"
                                        <?php echo ($carrera['total_estudiantes'] > 0) ? 'disabled title="No se puede eliminar: tiene estudiantes inscriptos"' : ''; ?>>
                                        🗑️ Eliminar
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
                <h3>✏️ Modificar Carrera</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST" action="" id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="update">
                    <input type="hidden" name="id_carrera" id="edit_id">
                    
                    <div class="form-group">
                        <label for="edit_nombre">Nombre de la Carrera:</label>
                        <input type="text" id="edit_nombre" name="nombre" required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_descripcion">Descripción:</label>
                        <textarea id="edit_descripcion" name="descripcion" maxlength="1000" placeholder="Ingrese una descripción detallada de la carrera..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_duracion">Duración (en años):</label>
                        <input type="number" id="edit_duracion" name="duracion" min="1" max="10" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_estado">Estado:</label>
                        <select id="edit_estado" name="estado">
                            <option value="activa">✓ Activa</option>
                            <option value="inactiva">○ Inactiva</option>
                            <option value="suspendida">⊗ Suspendida</option>
                        </select>
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
                    <input type="hidden" name="id_carrera" id="delete_id">
                    <p id="deleteMessage" style="font-size: 16px; line-height: 1.6;"></p>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeModal('deleteModal')">Cancelar</button>
                    <button type="submit" class="btn btn-delete">Eliminar Carrera</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, nombre, descripcion, duracion, estado) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_descripcion').value = descripcion;
            document.getElementById('edit_duracion').value = duracion;
            document.getElementById('edit_estado').value = estado;
            document.getElementById('editModal').style.display = 'block';
        }

        function openDeleteModal(id, nombre) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteMessage').innerHTML = '¿Está seguro de que desea eliminar la carrera "<strong>' + nombre + '</strong>"?<br><br>⚠️ Esta acción eliminará también todas las materias asociadas y no se puede deshacer.';
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('carrerasTable');
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