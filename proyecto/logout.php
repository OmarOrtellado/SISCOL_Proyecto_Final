<?php
session_start();

// Incluir la conexión
require_once "conexion.php";

// Función para registrar en la auditoría (definida localmente en este archivo)
function registrarAuditoria($conn, $id_usuario, $tipo_usuario, $usuario_nombre, $accion, $resultado, $motivo = null, $objeto_afectado = null, $id_objeto = null, $campo_modificado = null, $valor_anterior = null, $valor_nuevo = null) {
    $ip_origen = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $session_id = session_id(); // Capturar el ID de sesión ANTES de destruirla

    $sql_auditoria = "INSERT INTO auditoria (
        id_usuario, tipo_usuario, usuario_nombre, accion, resultado, motivo_fallo,
        objeto_afectado, id_objeto, campo_modificado, valor_anterior, valor_nuevo,
        ip_origen, user_agent, session_id, fecha_hora
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(6))";

    $stmt_auditoria = $conn->prepare($sql_auditoria);
    if ($stmt_auditoria) {
        // Cadena de tipos: i s s s s s s i s s s s s s (14 caracteres)
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

// --- REGISTRO DE AUDITORÍA ANTES DE DESTRUIR LA SESIÓN ---
// Verificar si existen las variables de sesión antes de usarlas
if (isset($_SESSION['id_usuario']) && isset($_SESSION['rol']) && isset($_SESSION['usuario'])) {
    $conn = conectar(); // Usar la función del archivo conexion.php
    if ($conn) { // Verificar que la conexión sea exitosa
        // Obtener los datos de la sesión
        $id_usuario_sesion = $_SESSION['id_usuario'];
        $tipo_usuario_sesion = $_SESSION['rol']; // Por ejemplo: 'estudiante', 'profesor', 'secretaria', 'director', 'super_usuario'
        $usuario_nombre_sesion = $_SESSION['usuario'];

        // Registrar el evento de LOGOUT
        // La acción es 'LOGOUT', el resultado es 'EXITO', y no hay objeto afectado específico ni campos modificados.
        // El id_objeto podría ser el id del usuario que cierra sesión.
        registrarAuditoria(
            $conn,
            $id_usuario_sesion,
            $tipo_usuario_sesion,
            $usuario_nombre_sesion,
            'LOGOUT', // Acción
            'EXITO', // Resultado
            null,    // Motivo (no aplica para LOGOUT exitoso)
            'sesion', // Objeto afectado (opcional, se puede poner 'sesion')
            null,    // ID del objeto afectado (no es un registro específico de una tabla de negocio, sino la sesión en sí, opcional poner el id_usuario)
            null,    // Campo modificado (no aplica)
            null,    // Valor anterior (no aplica)
            null     // Valor nuevo (no aplica)
        );

        $conn->close(); // Cerrar la conexión después de registrar
    }
    // Si la conexión falla, no se puede registrar el logout. Se podría lograr en un archivo de log local temporalmente si es crítico.
    // Por ahora, simplemente continuamos con la destrucción de la sesión.
}
// --- FIN REGISTRO DE AUDITORÍA ---

// Finalizar la sesión PHP
session_unset();  // Libera todas las variables de sesión
session_destroy(); // Destruye la sesión

// Redirigir a la página de inicio de sesión
header("Location: index.html"); // Asegúrate de que esta sea la URL correcta a tu página de login
exit();
?>