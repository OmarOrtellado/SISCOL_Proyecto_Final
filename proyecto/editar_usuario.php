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

// Obtener ID del usuario a editar
if (!isset($_GET['id'])) {
    header("Location: usuarios.php");
    exit();
}

$id = intval($_GET['id']);

// Obtener datos del usuario
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

if (!$usuario) {
    echo "<script>alert('Usuario no encontrado'); window.location='usuarios.php';</script>";
    exit();
}

// Procesar envío del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($_POST["dni"]);
    $nombre = trim($_POST["nombre"]);
    $apellido = trim($_POST["apellido"]);
    $email = trim($_POST["email"]);
    $telefono = trim($_POST["telefono"]);
    $rol_id = intval($_POST["rol_id"]);
    $activo = isset($_POST["activo"]) ? 1 : 0;

    // Obtener valores anteriores para auditoría
    $dni_anterior = $usuario['dni'];
    $nombre_anterior = $usuario['nombre'];
    $apellido_anterior = $usuario['apellido'];
    $email_anterior = $usuario['email'];
    $telefono_anterior = $usuario['telefono'];
    $rol_id_anterior = $usuario['rol_id'];
    $activo_anterior = $usuario['activo'];

    // Cambiar contraseña si se ingresó
    if (!empty($_POST["password"])) {
        $password_hash = password_hash($_POST["password"], PASSWORD_DEFAULT);
        $sql = "UPDATE usuarios SET dni=?, nombre=?, apellido=?, email=?, telefono=?, rol_id=?, activo=?, password_hash=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssiisi", $dni, $nombre, $apellido, $email, $telefono, $rol_id, $activo, $password_hash, $id);
    } else {
        $sql = "UPDATE usuarios SET dni=?, nombre=?, apellido=?, email=?, telefono=?, rol_id=?, activo=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssiis", $dni, $nombre, $apellido, $email, $telefono, $rol_id, $activo, $id);
    }

    if ($stmt->execute()) {
        $mensaje_exito = "Usuario actualizado correctamente";
        echo "<script>alert('$mensaje_exito'); window.location='usuarios.php';</script>";

        // Registrar éxito - solo para campos que han cambiado
        $usuario_nombre_sesion = $_SESSION["usuario"];
        $id_usuario_sesion = $_SESSION["id_usuario"];
        $tipo_usuario_sesion = $_SESSION["rol"]; // "super_usuario"

        if ($dni !== $dni_anterior) {
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'EDITAR_USUARIO', 'EXITO', null, 'usuarios', $id, 'dni', $dni_anterior, $dni);
        }
        if ($nombre !== $nombre_anterior) {
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'EDITAR_USUARIO', 'EXITO', null, 'usuarios', $id, 'nombre', $nombre_anterior, $nombre);
        }
        if ($apellido !== $apellido_anterior) {
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'EDITAR_USUARIO', 'EXITO', null, 'usuarios', $id, 'apellido', $apellido_anterior, $apellido);
        }
        if ($email !== $email_anterior) {
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'EDITAR_USUARIO', 'EXITO', null, 'usuarios', $id, 'email', $email_anterior, $email);
        }
        if ($telefono !== $telefono_anterior) {
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'EDITAR_USUARIO', 'EXITO', null, 'usuarios', $id, 'telefono', $telefono_anterior, $telefono);
        }
        if ($rol_id !== $rol_id_anterior) {
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'EDITAR_USUARIO', 'EXITO', null, 'usuarios', $id, 'rol_id', $rol_id_anterior, $rol_id);
        }
        if ($activo !== $activo_anterior) {
            registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'EDITAR_USUARIO', 'EXITO', null, 'usuarios', $id, 'activo', $activo_anterior, $activo);
        }
        // Si se cambió la contraseña, se podría registrar, pero generalmente no se registra el hash como valor_nuevo por seguridad.
        // Se podría registrar un evento genérico como "contraseña_actualizada" si se desea.
        // if (!empty($_POST["password"])) {
        //     registrarAuditoria($conn, $id_usuario_sesion, $tipo_usuario_sesion, $usuario_nombre_sesion, 'EDITAR_USUARIO', 'EXITO', null, 'usuarios', $id, 'password', null, '***');
        // }

        exit();
    } else {
        $mensaje_error = "Error al actualizar usuario: " . $stmt->error;
        echo "<script>alert('$mensaje_error'); window.location='usuarios.php';</script>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Usuario - SISCOL</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family: Arial; background-color: #1E3A5F; color: white; min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 2rem; }
    .form-container { background-color: rgba(0,0,0,0.7); padding: 2rem; border-radius: 12px; width: 100%; max-width: 500px; }
    .form-label { font-weight: bold; }
    .btn-guardar { background-color: #556B2F; border: none; color: white; width: 100%; padding: 0.75rem; border-radius: 8px; font-weight: bold; transition: 0.3s; }
    .btn-guardar:hover { background-color: #6B8E23; transform: scale(1.03); }
    h2 { text-align: center; margin-bottom: 1.5rem; }
  </style>
</head>
<body>

  <div class="form-container">
    <h2>Editar Usuario</h2>
    <form action="" method="POST">
      <div class="mb-3">
        <label for="dni" class="form-label">DNI</label>
        <input type="text" class="form-control" id="dni" name="dni" value="<?php echo htmlspecialchars($usuario['dni']); ?>" required>
      </div>
      <div class="mb-3">
        <label for="nombre" class="form-label">Nombre</label>
        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
      </div>
      <div class="mb-3">
        <label for="apellido" class="form-label">Apellido</label>
        <input type="text" class="form-control" id="apellido" name="apellido" value="<?php echo htmlspecialchars($usuario['apellido']); ?>" required>
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Correo electrónico</label>
        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
      </div>
      <div class="mb-3">
        <label for="telefono" class="form-label">Teléfono</label>
        <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($usuario['telefono']); ?>">
      </div>
      <div class="mb-3">
        <label for="rol_id" class="form-label">Rol</label>
        <select class="form-control" id="rol_id" name="rol_id" required>
          <?php
          $roles->data_seek(0); // Reiniciar puntero
          while($rol = $roles->fetch_assoc()):
          ?>
            <option value="<?php echo $rol['id']; ?>" <?php if($rol['id']==$usuario['rol_id']) echo 'selected'; ?>>
              <?php echo htmlspecialchars($rol['nombre']); ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Nueva Contraseña (opcional)</label>
        <input type="password" class="form-control" id="password" name="password" placeholder="Dejar en blanco para no cambiar">
      </div>
      <div class="mb-3 form-check">
        <input type="checkbox" class="form-check-input" id="activo" name="activo" <?php if($usuario['activo']) echo 'checked'; ?>>
        <label class="form-check-label" for="activo">Activo</label>
      </div>
      <button type="submit" class="btn-guardar">Actualizar Usuario</button>
    </form>
  </div>

</body>
</html>