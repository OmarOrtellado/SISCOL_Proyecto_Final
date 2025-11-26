<?php
session_start();
require_once "conexion.php";

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

// Verificar que es super_usuario
if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "super_usuario") {
    header("Location: index.html");
    exit();
}

$conn = conectar();

// Obtener roles disponibles
$roles = $conn->query("SELECT id, nombre FROM roles ORDER BY id ASC");

// Procesar envío del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($_POST["dni"]);
    $nombre = trim($_POST["nombre"]);
    $apellido = trim($_POST["apellido"]);
    $email = trim($_POST["email"]);
    $telefono = trim($_POST["telefono"]);
    $rol_id = intval($_POST["rol_id"]);
    $password = $_POST["password"];
    $activo = isset($_POST["activo"]) ? 1 : 0;
    $numero_legajo = trim($_POST["numero_legajo"]);
    $id_carrera = isset($_POST["id_carrera"]) ? intval($_POST["id_carrera"]) : null; // Solo para estudiantes

    // ===== Validaciones PHP =====
    if (!preg_match('/^\d{8}$/', $dni)) {
        $error_msg = 'El DNI debe tener exactamente 8 dígitos.';
        echo "<script>alert('$error_msg'); window.history.back();</script>";
        // Registrar intento fallido
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'FALLIDO', $error_msg, 'usuarios', null, 'dni', null, $dni);
        exit();
    }
    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{2,50}$/', $nombre) || !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{2,50}$/', $apellido)) {
        $error_msg = 'Nombre y apellido deben tener solo letras y entre 2 y 50 caracteres.';
        echo "<script>alert('$error_msg'); window.history.back();</script>";
        // Registrar intento fallido
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'FALLIDO', $error_msg, 'usuarios', null, 'nombre_apellido', null, $nombre . ' ' . $apellido);
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'El correo electrónico no es válido.';
        echo "<script>alert('$error_msg'); window.history.back();</script>";
        // Registrar intento fallido
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'FALLIDO', $error_msg, 'usuarios', null, 'email', null, $email);
        exit();
    }
    if (!preg_match('/^\d{10}$/', $telefono)) {
        $error_msg = 'El teléfono es obligatorio y debe tener exactamente 10 dígitos.';
        echo "<script>alert('$error_msg'); window.history.back();</script>";
        // Registrar intento fallido
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'FALLIDO', $error_msg, 'usuarios', null, 'telefono', null, $telefono);
        exit();
    }
    if (strlen($password) < 8 || strlen($password) > 16) {
        $error_msg = 'La contraseña debe tener entre 8 y 16 caracteres.';
        echo "<script>alert('$error_msg'); window.history.back();</script>";
        // Registrar intento fallido
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'FALLIDO', $error_msg, 'usuarios', null, 'password', null, '***'); // No se registra el valor real de la contraseña
        exit();
    }
    if (!preg_match('/^[A-Z0-9]{3,20}$/', $numero_legajo)) { // Ajusta el patrón según tu formato de legajo
        $error_msg = 'El número de legajo debe tener entre 3 y 20 caracteres, alfanumérico.';
        echo "<script>alert('$error_msg'); window.history.back();</script>";
        // Registrar intento fallido
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'FALLIDO', $error_msg, 'usuarios', null, 'numero_legajo', null, $numero_legajo);
        exit();
    }

    // Determinar la tabla destino según el rol_id
    $tabla_destino = '';
    $rol_nombre = '';
    switch ($rol_id) {
        case 1: // estudiante
            $tabla_destino = 'estudiante';
            $rol_nombre = 'estudiante';
            if ($id_carrera === null) {
                $error_msg = 'Debe seleccionar una carrera para el estudiante.';
                echo "<script>alert('$error_msg'); window.history.back();</script>";
                // Registrar intento fallido
                $usuario_nombre_sesion = $_SESSION["usuario"];
                $id_usuario_sesion = $_SESSION["id_usuario"];
                $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
                registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'FALLIDO', $error_msg, 'usuarios', null, 'id_carrera', null, null);
                exit();
            }
            break;
        case 2: // profesor
            $tabla_destino = 'profesores';
            $rol_nombre = 'profesor';
            break;
        case 3: // secretario
            $tabla_destino = 'secretarios';
            $rol_nombre = 'secretario';
            break;
        case 4: // director
            $tabla_destino = 'directivos';
            $rol_nombre = 'directivo';
            break;
        case 5: // super_usuario
            $tabla_destino = 'super_usuario';
            $rol_nombre = 'super_usuario';
            break;
        default:
            $error_msg = 'Rol no válido.';
            echo "<script>alert('$error_msg'); window.history.back();</script>";
            // Registrar intento fallido
            $usuario_nombre_sesion = $_SESSION["usuario"];
            $id_usuario_sesion = $_SESSION["id_usuario"];
            $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'FALLIDO', $error_msg, 'usuarios', null, 'rol_id', null, $rol_id);
            exit();
    }

    // Verificar si el DNI ya existe en la tabla destino
    $check_dni = $conn->prepare("SELECT id FROM $tabla_destino WHERE dni = ?");
    $check_dni->bind_param("s", $dni);
    $check_dni->execute();
    $check_dni->store_result();
    if ($check_dni->num_rows > 0) {
        $error_msg = "Ya existe un $rol_nombre con ese DNI en la base de datos.";
        echo "<script>alert('$error_msg'); window.history.back();</script>";
        // Registrar intento fallido
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'FALLIDO', $error_msg, $tabla_destino, null, 'dni', null, $dni);
        exit();
    }
    $check_dni->close();

    // Verificar si el email ya existe en la tabla destino
    $check_email = $conn->prepare("SELECT id FROM $tabla_destino WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    $check_email->store_result();
    if ($check_email->num_rows > 0) {
        $error_msg = "Ya existe un $rol_nombre con ese correo en la base de datos.";
        echo "<script>alert('$error_msg'); window.history.back();</script>";
        // Registrar intento fallido
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'FALLIDO', $error_msg, $tabla_destino, null, 'email', null, $email);
        exit();
    }
    $check_email->close();

    // Verificar si el numero_legajo ya existe en la tabla destino
    $check_legajo = $conn->prepare("SELECT id FROM $tabla_destino WHERE numero_legajo = ?");
    $check_legajo->bind_param("s", $numero_legajo);
    $check_legajo->execute();
    $check_legajo->store_result();
    if ($check_legajo->num_rows > 0) {
        $error_msg = "Ya existe un $rol_nombre con ese número de legajo en la base de datos.";
        echo "<script>alert('$error_msg'); window.history.back();</script>";
        // Registrar intento fallido
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'FALLIDO', $error_msg, $tabla_destino, null, 'numero_legajo', null, $numero_legajo);
        exit();
    }
    $check_legajo->close();

    // ===== Insertar usuario en la tabla correspondiente =====
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $sql = "";
    $params = "";
    $values = [];

    if ($tabla_destino === 'estudiante') {
        $sql = "INSERT INTO $tabla_destino (dni, nombre, apellido, email, telefono, password_hash, rol_id, activo, numero_legajo, id_carrera) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = "ssssssiiii";
        $values = [$dni, $nombre, $apellido, $email, $telefono, $password_hash, $rol_id, $activo, $numero_legajo, $id_carrera];
    } else {
        // Para profesores, secretarios, directivos, super_usuario
        $sql = "INSERT INTO $tabla_destino (dni, nombre, apellido, email, telefono, password_hash, rol_id, activo, numero_legajo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = "ssssssiii";
        $values = [$dni, $nombre, $apellido, $email, $telefono, $password_hash, $rol_id, $activo, $numero_legajo];
    }

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        $error_msg = "Error en la preparación de la consulta: " . $conn->error;
        die("Error: $error_msg");
        // Registrar intento fallido
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'FALLIDO', $error_msg, $tabla_destino, null, null, null, null);
        exit();
    }
    $stmt->bind_param($params, ...$values);

    if ($stmt->execute()) {
        $nuevo_id = $conn->insert_id; // Obtener el ID del nuevo usuario
        $success_msg = "Usuario creado correctamente en la tabla $tabla_destino";
        echo "<script>alert('$success_msg'); window.location='usuarios.php';</script>";
        // Registrar éxito
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'EXITO', null, $tabla_destino, $nuevo_id, 'dni', null, $dni);
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'EXITO', null, $tabla_destino, $nuevo_id, 'nombre', null, $nombre);
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'EXITO', null, $tabla_destino, $nuevo_id, 'apellido', null, $apellido);
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'EXITO', null, $tabla_destino, $nuevo_id, 'email', null, $email);
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'EXITO', null, $tabla_destino, $nuevo_id, 'telefono', null, $telefono);
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'EXITO', null, $tabla_destino, $nuevo_id, 'rol_id', null, $rol_id);
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'EXITO', null, $tabla_destino, $nuevo_id, 'activo', null, $activo);
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'EXITO', null, $tabla_destino, $nuevo_id, 'numero_legajo', null, $numero_legajo);
        if ($tabla_destino === 'estudiante') {
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'EXITO', null, $tabla_destino, $nuevo_id, 'id_carrera', null, $id_carrera);
        }
        exit();
    } else {
        $error_msg = "Error al crear usuario en la tabla $tabla_destino: " . $stmt->error;
        echo "<script>alert('$error_msg'); window.history.back();</script>";
        // Registrar intento fallido
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"
        registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'CREAR_USUARIO', 'FALLIDO', $error_msg, $tabla_destino, null, null, null, null);
        exit();
    }
    $stmt->close();
}

// Obtener carreras para el select de estudiantes
$carreras = $conn->query("SELECT id_carrera, nombre FROM carreras WHERE estado = 'activa' ORDER BY nombre ASC");
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
#id_carrera_container { display: none; } /* Ocultar inicialmente */
</style>
<script>
function validarFormulario() {
    const dni = document.getElementById('dni').value.trim();
    const nombre = document.getElementById('nombre').value.trim();
    const apellido = document.getElementById('apellido').value.trim();
    const email = document.getElementById('email').value.trim();
    const telefono = document.getElementById('telefono').value.trim();
    const password = document.getElementById('password').value.trim();
    const rol_id = document.getElementById('rol_id').value;
    const numero_legajo = document.getElementById('numero_legajo').value.trim();
    const id_carrera = document.getElementById('id_carrera').value;

    if(!/^\d{8}$/.test(dni)) { alert("DNI debe tener 8 dígitos"); return false; }
    if(!/^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{2,50}$/.test(nombre)) { alert("Nombre inválido"); return false; }
    if(!/^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{2,50}$/.test(apellido)) { alert("Apellido inválido"); return false; }
    if(!/^\d{10}$/.test(telefono)) { alert("Teléfono debe tener 10 dígitos"); return false; }
    if(password.length < 8 || password.length > 16) { alert("Contraseña debe tener entre 8 y 16 caracteres"); return false; }
    if(!/^[A-Z0-9]{3,20}$/.test(numero_legajo)) { alert("Número de legajo inválido"); return false; }
    // Validar carrera si es estudiante
    if(rol_id === '1' && id_carrera === '') { alert("Debe seleccionar una carrera para el estudiante"); return false; }
    return true;
}

// Mostrar u ocultar el campo de carrera según el rol seleccionado
function toggleCarreraField() {
    const rolSelect = document.getElementById('rol_id');
    const carreraContainer = document.getElementById('id_carrera_container');
    const rolId = rolSelect.value;

    if (rolId === '1') { // Si el rol es estudiante (id 1)
        carreraContainer.style.display = 'block';
    } else {
        carreraContainer.style.display = 'none';
    }
}

// Ejecutar al cargar la página y al cambiar el rol
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('rol_id').addEventListener('change', toggleCarreraField);
    toggleCarreraField(); // Llamar una vez al cargar para establecer el estado inicial
});
</script>
</head>
<body>

<div class="form-container">
    <h2>➕ Crear Nuevo Usuario</h2>
    <form action="" method="POST" onsubmit="return validarFormulario();">
      <div class="mb-3">
        <label for="dni" class="form-label">DNI</label>
        <input type="text" class="form-control" id="dni" name="dni" required>
      </div>
      <div class="mb-3">
        <label for="nombre" class="form-label">Nombre</label>
        <input type="text" class="form-control" id="nombre" name="nombre" required>
      </div>
      <div class="mb-3">
        <label for="apellido" class="form-label">Apellido</label>
        <input type="text" class="form-control" id="apellido" name="apellido" required>
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Correo electrónico</label>
        <input type="email" class="form-control" id="email" name="email" required>
      </div>
      <div class="mb-3">
        <label for="telefono" class="form-label">Teléfono</label>
        <input type="text" class="form-control" id="telefono" name="telefono" required>
      </div>
      <div class="mb-3">
        <label for="numero_legajo" class="form-label">Número de Legajo</label>
        <input type="text" class="form-control" id="numero_legajo" name="numero_legajo" required>
      </div>
      <div class="mb-3">
        <label for="rol_id" class="form-label">Rol</label>
        <select class="form-control" id="rol_id" name="rol_id" required>
          <?php $roles->data_seek(0); // Reiniciar el puntero del resultado ?>
          <?php while($rol = $roles->fetch_assoc()): ?>
            <option value="<?php echo $rol['id']; ?>"><?php echo htmlspecialchars($rol['nombre']); ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="mb-3" id="id_carrera_container">
        <label for="id_carrera" class="form-label">Carrera (solo para estudiantes)</label>
        <select class="form-control" id="id_carrera" name="id_carrera">
            <option value="">-- Seleccione una carrera --</option>
            <?php while($carrera = $carreras->fetch_assoc()): ?>
                <option value="<?php echo $carrera['id_carrera']; ?>"><?php echo htmlspecialchars($carrera['nombre']); ?></option>
            <?php endwhile; ?>
        </select>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Contraseña</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <div class="mb-3 form-check">
        <input type="checkbox" class="form-check-input" id="activo" name="activo" checked>
        <label class="form-check-label" for="activo">Activo</label>
      </div>
      <div class="d-flex justify-content-between mt-3">
        <button type="submit" class="btn-guardar">💾 Guardar</button>
        <a href="usuarios.php" class="btn-cancelar">❌ Cancelar</a>
      </div>
    </form>
</div>

</body>
</html>

<?php $conn->close(); ?>