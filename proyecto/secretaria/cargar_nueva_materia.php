<?php
// crear_nueva_materia.php
// Archivo básico para crear una nueva materia en el sistema SISCOL
// Ubicación: /proyecto/secretaria/crear_nueva_materia.php

// Incluir el archivo de conexión (ruta relativa desde secretaria/ a la raíz del proyecto)
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

// Cargar carreras para el select (incluyendo duracion)
$carreras = [];
$conn = conectar();
$sql_carreras = "SELECT id_carrera, nombre, duracion FROM carreras WHERE estado = 'activa' ORDER BY nombre";
$result_carreras = $conn->query($sql_carreras);
if ($result_carreras) {
    while ($row = $result_carreras->fetch_assoc()) {
        $carreras[] = $row;
    }
}
$conn->close();

// Manejar el formulario (POST)
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $id_carrera = (int)($_POST['id_carrera'] ?? 0);
    $año = (int)($_POST['año'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;

    // Validaciones básicas
    if (empty($nombre)) {
        $mensaje = '<p style="color: red;">El nombre es obligatorio.</p>';
        // Registrar intento fallido
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'FALLIDO', 'Nombre vacío', 'materias', null, null, null, null);
    } elseif ($id_carrera <= 0) {
        $mensaje = '<p style="color: red;">Debe seleccionar una carrera válida.</p>';
        // Registrar intento fallido
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'FALLIDO', 'Carrera inválida', 'materias', null, null, null, null);
    } elseif ($año < 1) {
        $mensaje = '<p style="color: red;">Debe seleccionar un año válido.</p>';
        // Registrar intento fallido
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'FALLIDO', 'Año inválido', 'materias', null, null, null, null);
    } else {
        // Conectar a la BD para validar año contra duracion de la carrera
        $conn = conectar();
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
            $mensaje = '<p style="color: red;">El año seleccionado (' . $año . ') excede la duración de la carrera (' . $duracion_carrera . ' años).</p>';
            // Registrar intento fallido
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'FALLIDO', 'Año excede duración de la carrera', 'materias', null, null, null, null);
        } else {
            // Insertar con prepared statement (incluyendo año)
            $sql = "INSERT INTO materias (nombre, id_carrera, activo, año) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("siii", $nombre, $id_carrera, $activo, $año);
                if ($stmt->execute()) {
                    $nuevo_id = $conn->insert_id;
                    $mensaje = '<p style="color: green;">Materia creada exitosamente con ID: ' . $nuevo_id . '.</p>';
                    // Registrar éxito
                    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'EXITO', null, 'materias', $nuevo_id, 'nombre', null, $nombre);
                    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'EXITO', null, 'materias', $nuevo_id, 'id_carrera', null, $id_carrera);
                    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'EXITO', null, 'materias', $nuevo_id, 'activo', null, $activo);
                    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'EXITO', null, 'materias', $nuevo_id, 'año', null, $año);
                    // Opcional: Limpiar formulario después de éxito
                    $_POST = [];
                } else {
                    $mensaje = '<p style="color: red;">Error al crear la materia: ' . $stmt->error . '</p>';
                    // Registrar intento fallido
                    registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'FALLIDO', $stmt->error, 'materias', null, null, null, null);
                }
                $stmt->close();
            } else {
                $mensaje = '<p style="color: red;">Error en la preparación de la consulta: ' . $conn->error . '</p>';
                // Registrar intento fallido
                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_MATERIA', 'FALLIDO', $conn->error, 'materias', null, null, null, null);
            }
        }
        
        // Cerrar conexión
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISCOL - Crear Nueva Materia</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        form { border: 1px solid #ccc; padding: 20px; border-radius: 5px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; margin-top: 15px; margin-right: 10px; }
        button:hover { background: #0056b3; }
        .btn-atras { background: #6c757d; }
        .btn-atras:hover { background: #545b62; }
        .header { text-align: center; color: #007bff; }
        .nav-buttons { margin-top: 20px; text-align: center; }
        .checkbox-group { margin-top: 5px; }
    </style>
</head>
<body>
    <h1 class="header">SISCOL - Secretaría</h1>
    <h2>Crear Nueva Materia</h2>
    
    <?php if ($mensaje): ?>
        <?php echo $mensaje; ?>
        <hr>
    <?php endif; ?>

    <form method="POST" action="" id="materiaForm">
        <label for="nombre">Nombre de la Materia:</label>
        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required maxlength="100">

        <label for="id_carrera">Carrera:</label>
        <select id="id_carrera" name="id_carrera" required onchange="updateYearOptions()">
            <option value="">Seleccione una carrera</option>
            <?php foreach ($carreras as $carrera): ?>
            <option value="<?php echo $carrera['id_carrera']; ?>" data-duracion="<?php echo $carrera['duracion']; ?>" <?php echo (($_POST['id_carrera'] ?? 0) == $carrera['id_carrera']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($carrera['nombre']); ?> (<?php echo $carrera['duracion']; ?> años)
            </option>
            <?php endforeach; ?>
        </select>

        <label for="año">Año de la Carrera:</label>
        <select id="año" name="año" required>
            <option value="">Seleccione un año</option>
        </select>

        <label for="activo">
            <div class="checkbox-group">
                <input type="checkbox" id="activo" name="activo" value="1" <?php echo (isset($_POST['activo']) || !isset($_POST['nombre'])) ? 'checked' : ''; ?>>
                Activa (por defecto)
            </div>
        </label>

        <button type="submit">Crear Materia</button>
    </form>

    <div class="nav-buttons">
        <button type="button" class="btn-atras" onclick="if (confirmUnsaved()) window.location.href='../panel_secretaria.php'">ATRÁS</button>
        <a href="ver_materias.php" style="text-decoration: none;"><button type="button">Ver Lista de Materias</button></a>
    </div>

    <script>
        let formChanged = false;

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const inputs = form.querySelectorAll('input, select');

            // Detectar cambios en los campos del formulario
            inputs.forEach(input => {
                if (input.tagName === 'INPUT' || input.tagName === 'SELECT') {
                    input.addEventListener('input', () => { formChanged = true; });
                    input.addEventListener('change', () => { formChanged = true; });
                }
            });

            // Función para confirmar salida
            window.confirmUnsaved = function() {
                if (formChanged) {
                    return confirm('¿Hay datos sin guardar. ¿Está seguro de que desea salir?');
                }
                return true;
            };
        });

        function updateYearOptions() {
            const carreraSelect = document.getElementById('id_carrera');
            const añoSelect = document.getElementById('año');
            const selectedOption = carreraSelect.options[carreraSelect.selectedIndex];
            
            // Limpiar opciones previas
            añoSelect.innerHTML = '<option value="">Seleccione un año</option>';
            
            if (selectedOption.value && selectedOption.dataset.duracion) {
                const duracion = parseInt(selectedOption.dataset.duracion);
                for (let i = 1; i <= duracion; i++) {
                    const option = document.createElement('option');
                    option.value = i;
                    option.textContent = i;
                    <?php if (isset($_POST['año'])): ?>
                    if (i == <?php echo (int)($_POST['año']); ?>) {
                        option.selected = true;
                    }
                    <?php endif; ?>
                    añoSelect.appendChild(option);
                }
            }
        }

        // Si hay POST y selección previa, actualizar opciones al cargar
        <?php if (isset($_POST['id_carrera']) && $_POST['id_carrera'] > 0): ?>
        updateYearOptions();
        <?php endif; ?>
    </script>
</body>
</html>