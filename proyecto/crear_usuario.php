<?php
session_start();
require_once "conexion.php";

// Función para registrar en la auditoría
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
            "isssssisssssss",
            $id_usuario,
            $tipo_usuario,
            $usuario_nombre,
            $accion,
            $resultado,
            $motivo,
            $objeto_afectado,
            $id_objeto,
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

// Verificar que es super_usuario
if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "super_usuario") {
    header("Location: index.php");
    exit();
}

$conn = conectar();
$mensaje_error = '';
$prefill = [];

// Procesar envío del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $prefill = $_POST;
    $rol_id = intval($_POST["rol_id"]);
    $dni = trim($_POST["dni"]);
    $nombre = trim($_POST["nombre"]);
    $apellido = trim($_POST["apellido"]);
    $email = trim($_POST["email"]);
    $telefono = trim($_POST["telefono"]);
    $direccion = trim($_POST["direccion"]);
    $password = $_POST["password"];
    $numero_legajo = isset($_POST["numero_legajo"]) ? trim($_POST["numero_legajo"]) : null;
    $activo = isset($_POST["activo"]) ? 1 : 0;

    // Validaciones comunes
    if ($rol_id <= 0) {
        $mensaje_error = 'Debe seleccionar un tipo de usuario válido.';
    } elseif (!preg_match('/^\d{8}$/', $dni)) {
        $mensaje_error = 'El DNI debe tener exactamente 8 dígitos.';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{2,50}$/', $nombre) || !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{2,50}$/', $apellido)) {
        $mensaje_error = 'Nombre y apellido deben tener solo letras y entre 2 y 50 caracteres.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje_error = 'El correo electrónico no es válido.';
    } elseif (!preg_match('/^\d{10}$/', $telefono)) {
        $mensaje_error = 'El teléfono debe tener exactamente 10 dígitos.';
    } elseif (strlen($password) < 8 || strlen($password) > 16) {
        $mensaje_error = 'La contraseña debe tener entre 8 y 16 caracteres.';
    } elseif ($rol_id != 1 && empty($numero_legajo)) { // Solo para roles que no son estudiante
        $mensaje_error = 'El número de legajo es obligatorio.';
    } elseif ($rol_id != 1 && !preg_match('/^[A-Z0-9]{3,20}$/', $numero_legajo)) {
        $mensaje_error = 'El número de legajo debe tener entre 3 y 20 caracteres, alfanumérico.';
    } else {
        // Verificar unicidad del DNI en TODAS las tablas de usuarios (más seguro y lógico)
        $tablas_usuario = ['super_usuario', 'secretarios', 'profesores', 'estudiante', 'directivos'];
        $dni_existe = false;
        foreach ($tablas_usuario as $tabla) {
            $check_dni = $conn->prepare("SELECT id FROM `$tabla` WHERE dni = ?");
            $check_dni->bind_param("s", $dni);
            $check_dni->execute();
            if ($check_dni->get_result()->num_rows > 0) {
                $dni_existe = true;
            }
            $check_dni->close();
            if ($dni_existe) break;
        }
        if ($dni_existe) {
            $mensaje_error = 'Ya existe un usuario con ese DNI.';
        } else {
            // Verificar unicidad del email en TODAS las tablas de usuarios
            $email_existe = false;
            foreach ($tablas_usuario as $tabla) {
                $check_email = $conn->prepare("SELECT id FROM `$tabla` WHERE email = ?");
                $check_email->bind_param("s", $email);
                $check_email->execute();
                if ($check_email->get_result()->num_rows > 0) {
                    $email_existe = true;
                }
                $check_email->close();
                if ($email_existe) break;
            }
            
            if ($email_existe) {
                $mensaje_error = 'Ya existe un usuario con ese correo electrónico.';
            } else {
                // Determinar la tabla destino y campos
                $tabla_destino = '';
                switch ($rol_id) {
                    case 1: $tabla_destino = 'estudiante'; break;
                    case 2: $tabla_destino = 'profesores'; break;
                    case 3: $tabla_destino = 'secretarios'; break;
                    case 4: $tabla_destino = 'directivos'; break;
                    case 5: $tabla_destino = 'super_usuario'; break;
                    default: $mensaje_error = 'Rol no válido.'; break;
                }

                if (empty($mensaje_error)) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    if ($rol_id == 1) {
                        // Solo para estudiantes: sin numero_legajo
                        $sql = "INSERT INTO $tabla_destino (dni, nombre, apellido, email, telefono, direccion, password_hash, rol_id, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssssssssi", $dni, $nombre, $apellido, $email, $telefono, $direccion, $password_hash, $rol_id, $activo);
                    } else {
                        // Para otros roles: con numero_legajo
                        $sql = "INSERT INTO $tabla_destino (dni, nombre, apellido, email, telefono, direccion, password_hash, rol_id, activo, numero_legajo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssssssssis", $dni, $nombre, $apellido, $email, $telefono, $direccion, $password_hash, $rol_id, $activo, $numero_legajo);
                    }

                    if ($stmt && $stmt->execute()) {
                        $nuevo_id = $conn->insert_id;
                        // Registrar en auditoría
                        $campos_auditoria = ['dni', 'nombre', 'apellido', 'email', 'telefono', 'direccion', 'rol_id', 'activo'];
                        foreach ($campos_auditoria as $campo) {
                            registrarAuditoria($conn, $_SESSION["id_usuario"], $_SESSION["rol"], $_SESSION["usuario"], 'CREAR_USUARIO', 'EXITO', null, $tabla_destino, $nuevo_id, $campo, null, $$campo);
                        }
                        if ($rol_id != 1) {
                            registrarAuditoria($conn, $_SESSION["id_usuario"], $_SESSION["rol"], $_SESSION["usuario"], 'CREAR_USUARIO', 'EXITO', null, $tabla_destino, $nuevo_id, 'numero_legajo', null, $numero_legajo);
                        }
                        header("Location: usuarios.php?mensaje=Usuario+creado+exitosamente.");
                        exit();
                    } else {
                        $mensaje_error = "Error al crear el usuario: " . ($stmt ? $stmt->error : $conn->error);
                    }
                    if ($stmt) $stmt->close();
                }
            }
        }
    }

    // Registrar error en auditoría
    if (!empty($mensaje_error)) {
        registrarAuditoria($conn, $_SESSION["id_usuario"], $_SESSION["rol"], $_SESSION["usuario"], 'CREAR_USUARIO', 'FALLIDO', $mensaje_error, 'usuarios', null, null, null, null);
    }
}

// Obtener roles para el formulario
$roles = $conn->query("SELECT id, nombre FROM roles ORDER BY id ASC");
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear Usuario - SISCOL</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb2d);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 2rem;
}
.form-container {
    background: rgba(255, 255, 255, 0.95);
    color: #333;
    padding: 2rem;
    border-radius: 16px;
    width: 100%;
    max-width: 520px;
    box-shadow: 0px 5px 20px rgba(0,0,0,0.3);
}
.form-label { font-weight: bold; }
.btn-guardar {
    background: linear-gradient(90deg, #28a745, #218838);
    border: none;
    color: white;
    width: 48%;
    padding: 0.75rem;
    border-radius: 30px;
    font-weight: bold;
    transition: 0.3s;
}
.btn-guardar:hover { transform: scale(1.05); }
.btn-cancelar {
    background: #6c757d;
    border: none;
    color: white;
    width: 48%;
    padding: 0.75rem;
    border-radius: 30px;
    font-weight: bold;
    text-decoration: none;
    display: inline-block;
    transition: 0.3s;
}
.btn-cancelar:hover { background: #5a6268; transform: scale(1.05); }
h2 { text-align: center; margin-bottom: 1.5rem; color: #1a2a6c; font-weight: bold; }
#campo_legajo { display: none; } /* Ocultar inicialmente */
</style>
</head>
<body>

<div class="form-container">
    <h2>➕ Crear Nuevo Usuario</h2>
    <form action="" method="POST">
        <?php if ($mensaje_error): ?>
            <div class="alert alert-danger mb-3"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <!-- Selector de Rol (siempre visible) -->
        <div class="mb-3">
            <label for="rol_id" class="form-label">Tipo de Usuario *</label>
            <select class="form-control" id="rol_id" name="rol_id" required>
                <option value="">-- Seleccione un tipo de usuario --</option>
                <?php while($rol = $roles->fetch_assoc()): ?>
                    <option value="<?php echo $rol['id']; ?>" <?php echo (isset($prefill['rol_id']) && $prefill['rol_id'] == $rol['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(ucfirst($rol['nombre'])); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Campos comunes -->
        <div id="campos_dinamicos">
            <div class="mb-3">
                <label for="dni" class="form-label">DNI *</label>
                <input type="text" class="form-control" id="dni" name="dni" value="<?php echo htmlspecialchars($prefill['dni'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre *</label>
                <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($prefill['nombre'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="apellido" class="form-label">Apellido *</label>
                <input type="text" class="form-control" id="apellido" name="apellido" value="<?php echo htmlspecialchars($prefill['apellido'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Correo electrónico *</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($prefill['email'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="telefono" class="form-label">Teléfono *</label>
                <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($prefill['telefono'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="direccion" class="form-label">Dirección *</label>
                <input type="text" class="form-control" id="direccion" name="direccion" value="<?php echo htmlspecialchars($prefill['direccion'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Contraseña *</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3" id="campo_legajo">
                <label for="numero_legajo" class="form-label">Número de Legajo *</label>
                <input type="text" class="form-control" id="numero_legajo" name="numero_legajo" value="<?php echo htmlspecialchars($prefill['numero_legajo'] ?? ''); ?>" required>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="activo" name="activo" checked>
                <label class="form-check-label" for="activo">Activo</label>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-3">
            <button type="submit" class="btn-guardar">💾 Guardar</button>
            <a href="usuarios.php" class="btn-cancelar">❌ Cancelar</a>
        </div>
    </form>
</div>

<script>
// Mostrar u ocultar el campo de legajo según el rol seleccionado
function toggleLegajoField() {
    const rolSelect = document.getElementById('rol_id');
    const legajoContainer = document.getElementById('campo_legajo');
    const rolId = parseInt(rolSelect.value);

    if (rolId === 1) { // Si el rol es estudiante (id 1)
        legajoContainer.style.display = 'none';
        document.getElementById('numero_legajo').removeAttribute('required');
    } else {
        legajoContainer.style.display = 'block';
        document.getElementById('numero_legajo').setAttribute('required', 'required');
    }
}

// Ejecutar al cargar la página y al cambiar el rol
document.addEventListener('DOMContentLoaded', function() {
    const rolSelect = document.getElementById('rol_id');
    if (rolSelect) {
        rolSelect.addEventListener('change', toggleLegajoField);
        toggleLegajoField(); // Llamar al cargar para establecer el estado inicial
    }
});
</script>

</body>
</html>
