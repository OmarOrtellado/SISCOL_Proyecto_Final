<?php
// cargar_nueva_carrera.php
// Archivo básico para cargar una nueva carrera en el sistema SISCOL
// Ubicación: /proyecto/secretaria/cargar_nueva_carrera.php

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
        // Cadena de tipos: i s s s s s s i s s s s s s (14 caracteres)
        // id_usuario (i), tipo_usuario (s), usuario_nombre (s), accion (s), resultado (s), motivo (s), objeto_afectado (s), id_objeto (i),
        // campo_modificado (s), valor_anterior (s), valor_nuevo (s), ip_origen (s), user_agent (s), session_id (s)
        $stmt_auditoria->bind_param(
            "isssssisssssss", // Cambiado de "issssssssssssss" a "isssssisssssss" para reflejar que id_objeto es 'i'
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

// Manejar el formulario (POST)
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $duracion = (int)($_POST['duracion'] ?? 4);
    $estado = $_POST['estado'] ?? 'activa';

    // Validaciones básicas
    if (empty($nombre)) {
        $mensaje = '<p style="color: red;">El nombre es obligatorio.</p>';
        // Registrar intento fallido - se agregan los parámetros faltantes como null
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_CARRERA', 'FALLIDO', 'Nombre vacío', 'carreras', null, null, null, null);
    } elseif ($duracion < 1 || $duracion > 10) {  // Ejemplo de validación simple
        $mensaje = '<p style="color: red;">La duración debe ser entre 1 y 10 años.</p>';
        // Registrar intento fallido - se agregan los parámetros faltantes como null
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_CARRERA', 'FALLIDO', 'Duración inválida', 'carreras', null, null, null, null);
    } else {
        // Conectar a la BD
        $conn = conectar();
        
        // Insertar con prepared statement (usando mysqli)
        $sql = "INSERT INTO carreras (nombre, descripcion, duracion, estado) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssis", $nombre, $descripcion, $duracion, $estado);
            if ($stmt->execute()) {
                $nuevo_id = $conn->insert_id;
                $mensaje = '<p style="color: green;">Carrera creada exitosamente con ID: ' . $nuevo_id . '.</p>';
                // Registrar éxito - se agregan los parámetros faltantes como null
                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_CARRERA', 'EXITO', null, 'carreras', $nuevo_id, 'nombre', null, $nombre);
                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_CARRERA', 'EXITO', null, 'carreras', $nuevo_id, 'descripcion', null, $descripcion);
                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_CARRERA', 'EXITO', null, 'carreras', $nuevo_id, 'duracion', null, $duracion);
                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_CARRERA', 'EXITO', null, 'carreras', $nuevo_id, 'estado', null, $estado);
                // Opcional: Limpiar formulario después de éxito
                $_POST = [];
            } else {
                $mensaje = '<p style="color: red;">Error al crear la carrera: ' . $stmt->error . '</p>';
                // Registrar intento fallido - se agregan los parámetros faltantes como null
                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_CARRERA', 'FALLIDO', $stmt->error, 'carreras', null, null, null, null);
            }
            $stmt->close();
        } else {
            $mensaje = '<p style="color: red;">Error en la preparación de la consulta: ' . $conn->error . '</p>';
            // Registrar intento fallido - se agregan los parámetros faltantes como null
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_CARRERA', 'FALLIDO', $conn->error, 'carreras', null, null, null, null);
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
    <title>SISCOL - Cargar Nueva Carrera</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        form { border: 1px solid #ccc; padding: 20px; border-radius: 5px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input, textarea, select { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; margin-top: 15px; margin-right: 10px; }
        button:hover { background: #0056b3; }
        .btn-atras { background: #6c757d; }
        .btn-atras:hover { background: #545b62; }
        .header { text-align: center; color: #007bff; }
        .nav-buttons { margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <h1 class="header">SISCOL - Secretaría</h1>
    <h2>Cargar Nueva Carrera</h2>
    
    <?php if ($mensaje): ?>
        <?php echo $mensaje; ?>
        <hr>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="nombre">Nombre de la Carrera:</label>
        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required maxlength="100">

        <label for="descripcion">Descripción:</label>
        <textarea id="descripcion" name="descripcion" rows="4" maxlength="1000"><?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?></textarea>

        <label for="duracion">Duración (años):</label>
        <input type="number" id="duracion" name="duracion" value="<?php echo htmlspecialchars($_POST['duracion'] ?? '4'); ?>" min="1" max="10" required>

        <label for="estado">Estado:</label>
        <select id="estado" name="estado">
            <option value="activa" <?php echo (($_POST['estado'] ?? 'activa') === 'activa') ? 'selected' : ''; ?>>Activa</option>
            <option value="inactiva" <?php echo (($_POST['estado'] ?? '') === 'inactiva') ? 'selected' : ''; ?>>Inactiva</option>
            <option value="suspendida" <?php echo (($_POST['estado'] ?? '') === 'suspendida') ? 'selected' : ''; ?>>Suspendida</option>
        </select>

        <button type="submit">Crear Carrera</button>
    </form>

    <div class="nav-buttons">
        <button type="button" class="btn-atras">ATRÁS</button>
    </div>

    <script>
        let formChanged = false;

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const inputs = form.querySelectorAll('input, textarea, select');

            // Detectar cambios en los campos del formulario
            inputs.forEach(input => {
                // Para inputs de texto/number/textarea
                if (input.tagName === 'INPUT' || input.tagName === 'TEXTAREA') {
                    input.addEventListener('input', () => { formChanged = true; });
                }
                // Para select
                if (input.tagName === 'SELECT') {
                    input.addEventListener('change', () => { formChanged = true; });
                }
            });

            // Manejar clic en botón ATRÁS
            const btnAtras = document.querySelector('.btn-atras');
            btnAtras.addEventListener('click', function(e) {
                if (formChanged) {
                    if (!confirm('¿Hay datos sin guardar. ¿Está seguro de que desea salir?')) {
                        e.preventDefault();
                        return false;
                    }
                }
                // Si no hay cambios o se confirma, redirigir
                window.location.href = '../panel_secretaria.php';
            });
        });
    </script>
</body>
</html>