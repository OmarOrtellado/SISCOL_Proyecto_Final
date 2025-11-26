<?php
session_start();
require_once "../conexion.php";

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

// Verificar rol de secretaria
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "secretaria") {
    header("Location: ../index.php");
    exit();
}

$id_usuario_sesion = $_SESSION["id_usuario"];
$tipo_usuario_sesion = $_SESSION["rol"]; // "secretaria"
$usuario_nombre_sesion = $_SESSION["usuario"];

$conn = conectar();
if (!$conn) {
    die("<div class='alert alert-danger'>Error de conexión a la base de datos.</div>");
}

$mensaje = '';

// ================================
// PROCESAR FORMULARIO DE AGREGADO
// ================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
    $id_materia = (int)($_POST['id_materia'] ?? 0);
    $id_materia_correlativa = (int)($_POST['id_materia_correlativa'] ?? 0);
    $tipo_condicion = $_POST['tipo_condicion'] ?? 'aprobada';

    if ($id_materia <= 0 || $id_materia_correlativa <= 0) {
        $mensaje = '<div class="alert alert-error">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span>Selecciona materias válidas.</span>
        </div>';
        // Registrar intento fallido
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'AGREGAR_CORRELATIVIDAD', 'FALLIDO', 'Materias inválidas', 'correlatividades', null, null, null, null);
    } elseif ($id_materia === $id_materia_correlativa) {
        $mensaje = '<div class="alert alert-warning">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <span>Una materia no puede ser correlativa de sí misma.</span>
        </div>';
        // Registrar intento fallido
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'AGREGAR_CORRELATIVIDAD', 'FALLIDO', 'Materia es idéntica a correlativa', 'correlatividades', null, null, null, null);
    } else {
        // Insertar o reactivar
        $stmt = $conn->prepare("
            INSERT INTO correlatividades (id_materia, id_materia_correlativa, tipo_condicion, activo)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE activo = 1, tipo_condicion = VALUES(tipo_condicion)
        ");
        $stmt->bind_param("iis", $id_materia, $id_materia_correlativa, $tipo_condicion);
        if ($stmt->execute()) {
            // Verificar si fue INSERT o UPDATE para determinar el id_objeto si es necesario
            // En este caso, el ID de la correlatividad puede no ser trivial de obtener con ON DUPLICATE KEY UPDATE
            // Pero registramos la acción de todas formas
            $mensaje = '<div class="alert alert-success">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span>Correlatividad guardada correctamente.</span>
            </div>';
            // Registrar éxito - en este caso, no tenemos el ID exacto de la correlatividad si fue un UPDATE
            // Podríamos registrar el par de materias como identificador lógico o buscar el ID antes
            // Para simplificar, registramos la acción con los datos de las materias como contexto
            // Si se insertó un nuevo registro, se podría obtener el ID con $conn->insert_id
            // Si fue un UPDATE, no se puede obtener un ID nuevo, pero el UPDATE en sí es la acción.
            // La forma más precisa es obtener el ID antes o después de la operación si es crítico.
            // Para ON DUPLICATE KEY UPDATE, si se inserta, $conn->insert_id da el ID nuevo. Si se actualiza, da 0 o el ID anterior si se cambia la PK.
            // La operación es más compleja, pero asumiremos que se insertó o actualizó con éxito.
            // Si la operación es INSERT, $conn->insert_id tendrá el nuevo ID.
            // Si es UPDATE, $conn->insert_id puede no ser útil para identificar la correlatividad específica si no cambió su ID.
            // En este caso, asumiremos que el éxito se refiere a la operación en general y no a un ID específico insertado (puede que no haya sido insertado nuevo).
            // Para fines de auditoría, la acción es "agregar/actualizar correlatividad entre X e Y con condición Z".
            // No podemos registrar un único id_objeto fiable aquí si puede ser UPDATE.
            // Por lo tanto, registramos la acción principal sin un id_objeto específico si no es trivial obtenerlo.
            // Si la tabla tiene una clave primaria compuesta o un ID auto-incremental, se puede manejar diferente.
            // La tabla correlatividades parece tener un ID auto-incremental (id).
            // Con ON DUPLICATE KEY UPDATE, si inserta, $conn->insert_id es el nuevo ID.
            // Si actualiza, $conn->affected_rows = 2 (UPDATE y INSERT simulados) o 1 (solo UPDATE si no cambian valores).
            // Si solo actualiza valores (no inserta), $conn->insert_id puede no cambiar o no ser el ID del registro actualizado si no es nuevo.
            // Lo más seguro es obtener el ID *antes* si es UPDATE o *después* si es INSERT, pero ON DUPLICATE KEY UPDATE lo complica.
            // Para simplificar, y dado que el par (id_materia, id_materia_correlativa) es único, registramos la acción.
            // Si se desea un id_objeto, se podría hacer una consulta adicional para obtenerlo, o usar LAST_INSERT_ID() en MySQL si aplica.
            // Por ahora, registramos la acción con éxito, sin id_objeto específico si no es trivial.
            // registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'AGREGAR_CORRELATIVIDAD', 'EXITO', null, 'correlatividades', null, null, null, null); // Opción 1: sin ID
            // Opción 2: intentar obtener el ID si fue insertado
            $fue_insertado = ($conn->affected_rows == 1 && $conn->insert_id > 0); // Asumiendo que INSERT incrementa affected_rows en 1 y setea insert_id
            // En ON DUPLICATE KEY UPDATE: si inserta, affected_rows = 1, insert_id = nuevo_id
            //                              si actualiza (y cambian valores), affected_rows = 2, insert_id = 0 (o el id anterior si se actualiza la PK)
            //                              si actualiza pero no cambian valores, affected_rows = 0, insert_id = 0
            // Por lo tanto, si affected_rows = 1, es INSERT y conn->insert_id es el ID.
            // Si affected_rows = 2, es UPDATE, y no tenemos un nuevo ID.
            // Si affected_rows = 0, es UPDATE sin cambios.
            // La lógica se vuelve compleja. Lo más simple es registrar la acción con los datos que *sabemos* se usaron.
            // Podríamos registrar el intento de agregar/actualizar con éxito, pero sin id_objeto si no es claro.
            // O, podemos intentar obtener el ID real de la correlatividad *después* de la operación.
            // Dado que ON DUPLICATE KEY UPDATE puede complicar el uso de $conn->insert_id, lo mejor es buscar el ID real.
            $stmt_buscar_id = $conn->prepare("SELECT id FROM correlatividades WHERE id_materia = ? AND id_materia_correlativa = ?");
            $stmt_buscar_id->bind_param("ii", $id_materia, $id_materia_correlativa);
            $stmt_buscar_id->execute();
            $result_buscar = $stmt_buscar_id->get_result();
            $id_corr_registrada = null;
            if ($row_corr = $result_buscar->fetch_assoc()) {
                $id_corr_registrada = (int)$row_corr['id'];
            }
            $stmt_buscar_id->close();

            // Registrar éxito
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'AGREGAR_CORRELATIVIDAD', 'EXITO', null, 'correlatividades', $id_corr_registrada, 'tipo_condicion', null, $tipo_condicion);
            // Registrar también los IDs de las materias como campos relevantes
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'AGREGAR_CORRELATIVIDAD', 'EXITO', null, 'correlatividades', $id_corr_registrada, 'id_materia', null, $id_materia);
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'AGREGAR_CORRELATIVIDAD', 'EXITO', null, 'correlatividades', $id_corr_registrada, 'id_materia_correlativa', null, $id_materia_correlativa);
            // Registrar el cambio de activo a 1 si era 0 (implícito en ON DUPLICATE KEY UPDATE)
            // registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'AGREGAR_CORRELATIVIDAD', 'EXITO', null, 'correlatividades', $id_corr_registrada, 'activo', null, 1);

        } else {
            $mensaje = '<div class="alert alert-error">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                <span>Error al guardar la correlatividad.</span>
            </div>';
            // Registrar intento fallido
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'AGREGAR_CORRELATIVIDAD', 'FALLIDO', $stmt->error, 'correlatividades', null, null, null, null);
        }
        $stmt->close();
    }
}

// ================================
// PROCESAR DESACTIVACIÓN
// ================================
if (isset($_GET['desactivar'])) {
    $id_corr = (int)$_GET['desactivar'];
    // Obtener datos anteriores para auditoría
    $stmt_datos_anteriores = $conn->prepare("SELECT id_materia, id_materia_correlativa, tipo_condicion FROM correlatividades WHERE id = ?");
    $stmt_datos_anteriores->bind_param("i", $id_corr);
    $stmt_datos_anteriores->execute();
    $result_datos = $stmt_datos_anteriores->get_result();
    $datos_anteriores = null;
    if ($row_datos = $result_datos->fetch_assoc()) {
        $datos_anteriores = $row_datos;
    }
    $stmt_datos_anteriores->close();

    if ($datos_anteriores) {
        $stmt = $conn->prepare("UPDATE correlatividades SET activo = 0 WHERE id = ?");
        $stmt->bind_param("i", $id_corr);
        if ($stmt->execute()) {
            // Registrar éxito de desactivación
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'DESACTIVAR_CORRELATIVIDAD', 'EXITO', null, 'correlatividades', $id_corr, 'activo', 1, 0);
            // Opcional: Registrar también los datos de la correlatividad que se desactivó como contexto
            // registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'DESACTIVAR_CORRELATIVIDAD', 'EXITO', null, 'correlatividades', $id_corr, 'id_materia', null, $datos_anteriores['id_materia']);
            // registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'DESACTIVAR_CORRELATIVIDAD', 'EXITO', null, 'correlatividades', $id_corr, 'id_materia_correlativa', null, $datos_anteriores['id_materia_correlativa']);
            // registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'DESACTIVAR_CORRELATIVIDAD', 'EXITO', null, 'correlatividades', $id_corr, 'tipo_condicion', null, $datos_anteriores['tipo_condicion']);
        } else {
            // Si falla el UPDATE, también registrar en auditoría
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'DESACTIVAR_CORRELATIVIDAD', 'FALLIDO', $stmt->error, 'correlatividades', $id_corr, null, null, null);
        }
        $stmt->close();
    } else {
        // Si no se encontró la correlatividad para desactivar
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'DESACTIVAR_CORRELATIVIDAD', 'FALLIDO', 'Correlatividad no encontrada', 'correlatividades', $id_corr, null, null, null);
    }
    header("Location: gestionar_correlatividades.php");
    exit();
}

// ================================
// OBTENER DATOS PARA EL FORMULARIO Y LA LISTA
// ================================

// Carreras y materias (organizadas por carrera)
$carreras_stmt = $conn->prepare("
    SELECT c.id_carrera, c.nombre, m.id, m.nombre AS materia, m.año
    FROM carreras c
    INNER JOIN materias m ON m.id_carrera = c.id_carrera
    WHERE c.estado = 'activa' AND m.activo = 1
    ORDER BY c.nombre, m.año, m.nombre
");
$carreras_stmt->execute();
$materias_por_carrera = [];
$result = $carreras_stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $materias_por_carrera[$row['id_carrera']]['nombre'] = $row['nombre'];
    $materias_por_carrera[$row['id_carrera']]['materias'][] = $row;
}
$carreras_stmt->close();

// Correlatividades activas
$correlativas_stmt = $conn->prepare("
    SELECT corr.id, 
           m1.nombre AS materia, 
           m2.nombre AS correlativa,
           corr.tipo_condicion,
           c1.nombre AS carrera
    FROM correlatividades corr
    INNER JOIN materias m1 ON corr.id_materia = m1.id
    INNER JOIN materias m2 ON corr.id_materia_correlativa = m2.id
    INNER JOIN carreras c1 ON m1.id_carrera = c1.id_carrera
    WHERE corr.activo = 1
    ORDER BY c1.nombre, m1.nombre, m2.nombre
");
$correlativas_stmt->execute();
$correlativas = $correlativas_stmt->get_result();
$correlativas_array = [];
while ($row = $correlativas->fetch_assoc()) {
    $correlativas_array[] = $row;
}
$correlativas_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISCOL - Gestionar Correlatividades</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-title {
            flex: 1;
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

        .btn-atras {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            backdrop-filter: blur(10px);
        }

        .btn-atras:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .content {
            padding: 30px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
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

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 30px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }

        .form-select,
        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
            background: white;
        }

        .form-select:focus,
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-select optgroup {
            font-weight: 600;
            color: #667eea;
            font-style: normal;
        }

        .form-select option {
            padding: 8px;
            color: #333;
        }

        .btn-save {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 14px 32px;
            font-size: 16px;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        .search-container {
            margin-bottom: 20px;
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

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th {
            background: #f8f9fa;
            color: #333;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e0e0e0;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .btn-desactivar {
            padding: 8px 16px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-desactivar:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
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

        .empty-state h3 {
            margin-bottom: 10px;
            color: #495057;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-title">
                <h1>🎓 SISCOL - Sistema de Control Escolar</h1>
                <h2>📚 Gestión de Correlatividades</h2>
            </div>
            <button class="btn btn-atras" onclick="window.location.href='../panel_secretaria.php'">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Volver al Panel
            </button>
        </div>

        <div class="content">
            <?php if ($mensaje): ?>
                <?php echo $mensaje; ?>
            <?php endif; ?>

            <!-- Formulario de carga -->
            <div class="card">
                <div class="card-header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Agregar Nueva Correlatividad
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="accion" value="agregar">

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Materia que requiere correlatividad</label>
                                <select name="id_materia" class="form-select" required>
                                    <option value="">-- Selecciona una materia --</option>
                                    <?php foreach ($materias_por_carrera as $carrera_id => $datos): ?>
                                        <optgroup label="<?= htmlspecialchars($datos['nombre']) ?>">
                                            <?php foreach ($datos['materias'] as $m): ?>
                                                <option value="<?= $m['id'] ?>">
                                                    Año <?= $m['año'] ?> - <?= htmlspecialchars($m['materia']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Materia correlativa (requisito)</label>
                                <select name="id_materia_correlativa" class="form-select" required>
                                    <option value="">-- Selecciona una materia --</option>
                                    <?php foreach ($materias_por_carrera as $carrera_id => $datos): ?>
                                        <optgroup label="<?= htmlspecialchars($datos['nombre']) ?>">
                                            <?php foreach ($datos['materias'] as $m): ?>
                                                <option value="<?= $m['id'] ?>">
                                                    Año <?= $m['año'] ?> - <?= htmlspecialchars($m['materia']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Condición requerida</label>
                            <select name="tipo_condicion" class="form-select" required>
                                <option value="aprobada">✓ Aprobada (nota ≥ 6)</option>
                                <option value="regular">○ Regular (cursada, sin necesidad de aprobar)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-save">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Guardar Correlatividad
                        </button>
                    </form>
                </div>
            </div>

            <!-- Listado de correlatividades activas -->
            <div class="card">
                <div class="card-header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg>
                    Correlatividades Activas
                </div>
                <div class="card-body">
                    <div class="search-container">
                        <input type="text" id="searchInput" placeholder="Buscar por materia, correlativa o carrera..." onkeyup="filterTable()">
                        <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                    </div>

                    <?php if (count($correlativas_array) > 0): ?>
                        <div class="table-container">
                            <table id="correlativasTable">
                                <thead>
                                    <tr>
                                        <th>Carrera</th>
                                        <th>Materia</th>
                                        <th>Requiere</th>
                                        <th>Condición</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($correlativas_array as $corr): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($corr['carrera']) ?></td>
                                            <td><strong><?= htmlspecialchars($corr['materia']) ?></strong></td>
                                            <td><?= htmlspecialchars($corr['correlativa']) ?></td>
                                            <td>
                                                <?php if ($corr['tipo_condicion'] === 'aprobada'): ?>
                                                    <span class="badge badge-success">✓ Aprobada</span>
                                                <?php else: ?>
                                                    <span class="badge badge-info">○ Regular</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?desactivar=<?= $corr['id'] ?>" 
                                                   class="btn-desactivar"
                                                   onclick="return confirm('¿Desactivar esta correlatividad?')">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <line x1="15" y1="9" x2="9" y2="15"></line>
                                                        <line x1="9" y1="9" x2="15" y2="15"></line>
                                                    </svg>
                                                    Desactivar
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="12" y1="18" x2="12" y2="12"></line>
                                <line x1="9" y1="15" x2="15" y2="15"></line>
                            </svg>
                            <h3>No hay correlatividades activas</h3>
                            <p>Utiliza el formulario superior para agregar la primera correlatividad</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('correlativasTable');
            
            if (!table) return;
            
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let visible = false;
                
                // Buscar en todas las columnas excepto la última (Acción)
                for (let j = 0; j < td.length - 1; j++) {
                    if (td[j] && td[j].textContent.toLowerCase().indexOf(filter) > -1) {
                        visible = true;
                        break;
                    }
                }
                tr[i].style.display = visible ? '' : 'none';
            }
        }
    </script>
</body>
</html>