<?php
session_start();
require_once "../conexion.php";

if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "profesor") {
    header("Location: ../index.html");
    exit();
}

$conn = conectar();

// Procesar la solicitud de regularización vía AJAX (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'regularizar' && isset($_POST['id_analitico'])) {
    $conn_ajax = conectar();
    $id_analitico = intval($_POST['id_analitico']);
    $id_materia = intval($_POST['id_materia']);

    // Verificar que el analitico pertenezca a una materia asignada al profesor y no esté ya regularizado/aprobado
    $check = $conn_ajax->prepare("
        SELECT 1 
        FROM analitico a
        JOIN asignaciones asig ON a.id_materia = asig.id_materia
        WHERE a.id = ? AND asig.id_profesor = ? AND asig.activo = 1 AND a.activo = 1 AND a.regular = 0 AND a.aprobada = 0
    ");
    $check->bind_param("ii", $id_analitico, $_SESSION["id_usuario"]);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // Actualizar el estado de regularidad
        $sql = "UPDATE analitico SET regular = 1 WHERE id = ?";
        $stmt = $conn_ajax->prepare($sql);
        $stmt->bind_param("i", $id_analitico);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Estudiante regularizado correctamente.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al regularizar al estudiante.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: No tienes permiso, el estudiante ya está regularizado o aprobado.']);
    }
    $conn_ajax->close();
    exit(); // Finalizar la ejecución para la solicitud AJAX
}

// Procesar la solicitud de carga de calificación vía AJAX (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'cargar_nota' && isset($_POST['id_analitico'])) {
    $conn_ajax = conectar();
    $id_analitico = intval($_POST['id_analitico']);
    $id_materia = intval($_POST['id_materia']);
    $nota = floatval($_POST['nota']);

    if ($nota >= 0 && $nota <= 10) {
        // Verificar que el analitico pertenezca a una materia asignada al profesor,
        // que esté inscripto para final y NO tenga una calificación final ya cargada
        $check = $conn_ajax->prepare("
            SELECT 1 
            FROM analitico a
            JOIN asignaciones asig ON a.id_materia = asig.id_materia
            WHERE a.id = ? AND asig.id_profesor = ? AND asig.activo = 1 AND a.activo = 1 AND a.inscripto_para_final = 1 AND a.calificacion_final IS NULL
        ");
        $check->bind_param("ii", $id_analitico, $_SESSION["id_usuario"]);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            // Actualizar la calificación final y el estado de aprobación
            $sql = "UPDATE analitico SET calificacion_final = ?, aprobada = 1 WHERE id = ?";
            $stmt = $conn_ajax->prepare($sql);
            $stmt->bind_param("di", $nota, $id_analitico);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Calificación cargada correctamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al cargar la calificación.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: No tienes permiso, el estudiante no está inscripto para final o ya tiene una calificación.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'La nota debe estar entre 0 y 10.']);
    }
    $conn_ajax->close();
    exit(); // Finalizar la ejecución para la solicitud AJAX
}


// Obtener materias asignadas al profesor
$sql_materias = "SELECT m.id, m.nombre 
                 FROM asignaciones a
                 JOIN materias m ON a.id_materia = m.id
                 WHERE a.id_profesor = ? AND a.activo = 1 AND m.activo = 1
                 ORDER BY m.nombre";
$stmt_materias = $conn->prepare($sql_materias);
$stmt_materias->bind_param("i", $_SESSION["id_usuario"]);
$stmt_materias->execute();
$materias_result = $stmt_materias->get_result();
$materias = $materias_result->fetch_all(MYSQLI_ASSOC);

// Determinar la materia a usar: la única asignada o la seleccionada
$id_materia = null;
$nombre_materia = '';
$mostrar_selector = true;

if (count($materias) == 1) {
    // Si solo tiene una materia, usarla por defecto
    $id_materia = $materias[0]['id'];
    $nombre_materia = $materias[0]['nombre'];
    $mostrar_selector = false;
} else {
    // Si tiene más de una, usar la seleccionada (o ninguna si no se ha seleccionado aún)
    $id_materia = isset($_GET['materia']) ? intval($_GET['materia']) : 0;
    if ($id_materia > 0) {
        // Verificar que la materia seleccionada pertenezca al profesor
        $nombre_materia = '';
        foreach ($materias as $mat) {
            if ($mat['id'] == $id_materia) {
                $nombre_materia = $mat['nombre'];
                break;
            }
        }
        if (!$nombre_materia) {
            // Si la materia no pertenece al profesor, resetear
            $id_materia = 0;
        }
    }
}

// Si hay materia seleccionada (o por defecto), obtener estudiantes
$estudiantes = null;
if ($id_materia > 0) {
    // Obtener estudiantes inscritos vía tabla 'analitico' junto con su estado
    $sql_estudiantes = "SELECT e.id as id_estudiante, a.id as id_analitico, e.dni, e.nombre, e.apellido, e.email, e.telefono, a.regular, a.aprobada, a.inscripto_para_final, a.calificacion_final
                        FROM analitico a
                        JOIN estudiante e ON a.id_estudiante = e.id
                        WHERE a.id_materia = ? AND a.activo = 1 AND e.activo = 1
                        ORDER BY e.apellido, e.nombre";
    $stmt = $conn->prepare($sql_estudiantes);
    $stmt->bind_param("i", $id_materia);
    $stmt->execute();
    $estudiantes = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lista de Estudiantes - SISCOL</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
    }
    .card {
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
      border: 1px solid rgba(0, 0, 0, 0.125);
    }
    .table th {
      border-top: 0;
    }
    .buscador-container {
        margin-bottom: 1rem;
    }
    /* Estilo para el contenedor del mensaje de éxito */
    #mensajeAlerta {
        display: none;
        margin-top: 15px;
    }
    .accion-desactivada {
        opacity: 0.5;
        cursor: not-allowed;
    }
  </style>
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="h3">Lista de Estudiantes por Materia</h2>
      <a href="../panel_profesor.php" class="btn btn-outline-secondary">Volver al Panel</a>
    </div>

    <!-- Contenedor para mensajes de alerta -->
    <div id="mensajeAlerta" class="alert" role="alert">
        <!-- El contenido se inserta dinámicamente -->
    </div>

    <!-- Selector de materia (solo si tiene más de una) -->
    <?php if ($mostrar_selector): ?>
    <div class="card mb-4">
      <div class="card-body">
        <h5>Seleccionar Materia</h5>
        <form method="GET">
          <div class="row">
            <div class="col-md-10">
              <select name="materia" class="form-control" required onchange="this.form.submit()">
                <option value="">-- Seleccione una materia --</option>
                <?php foreach($materias as $mat): ?>
                <option value="<?php echo $mat['id']; ?>" <?php echo ($id_materia == $mat['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($mat['nombre']); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <!-- Botón oculto para navegadores sin JS, por si acaso -->
            <div class="col-md-2 d-none">
              <button type="submit" class="btn btn-primary w-100">Ver</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Barra de búsqueda (siempre visible cuando hay materia seleccionada o por defecto) -->
    <?php if ($id_materia > 0): ?>
    <div class="card mb-3 buscador-container">
      <div class="card-body">
        <input type="text" id="buscadorEstudiantes" class="form-control" placeholder="Buscar por DNI, Apellido o Nombre...">
      </div>
    </div>
    <?php endif; ?>

    <!-- Lista de estudiantes -->
    <?php if($estudiantes && $estudiantes->num_rows > 0): ?>
    <div class="card">
      <div class="card-header">
        <h5>Estudiantes en: <?php echo htmlspecialchars($nombre_materia); ?></h5>
        <small class="text-muted">Total: <?php echo $estudiantes->num_rows; ?> estudiantes</small>
      </div>
      <div class="card-body">
        <table class="table table-striped table-hover" id="tablaEstudiantes">
          <thead>
            <tr>
              <th>DNI</th>
              <th>Apellido y Nombre</th>
              <th>Email</th>
              <th>Teléfono</th>
              <th>Estado</th> <!-- Nueva columna para estado de regularidad -->
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php while($est = $estudiantes->fetch_assoc()): ?>
            <?php
            // Determinar el estado del estudiante
            $estado_texto = 'No Regular';
            $clase_estado = 'bg-secondary';
            if ($est['aprobada'] == 1) {
                $estado_texto = 'Aprobado';
                $clase_estado = 'bg-success';
            } elseif ($est['inscripto_para_final'] == 1) {
                $estado_texto = 'Inscripto Final';
                $clase_estado = 'bg-info';
            } elseif ($est['regular'] == 1) {
                $estado_texto = 'Regular';
                $clase_estado = 'bg-warning';
            }
            ?>
            <tr id="fila_<?php echo $est['id_analitico']; ?>">
              <td class="dato-estudiante"><?php echo htmlspecialchars($est['dni']); ?></td>
              <td class="dato-estudiante"><?php echo htmlspecialchars($est['apellido'] . ', ' . $est['nombre']); ?></td>
              <td class="dato-estudiante"><?php echo htmlspecialchars($est['email']); ?></td>
              <td class="dato-estudiante"><?php echo htmlspecialchars($est['telefono']); ?></td>
              <td class="dato-estudiante" id="estado_<?php echo $est['id_analitico']; ?>">
                  <span class="badge <?php echo $clase_estado; ?>"><?php echo $estado_texto; ?></span>
                  <?php if ($est['calificacion_final'] !== null): ?>
                      <div class="small mt-1">Nota: <strong><?php echo number_format($est['calificacion_final'], 2); ?></strong></div>
                  <?php endif; ?>
              </td>
              <td class="dato-estudiante" id="accion_<?php echo $est['id_analitico']; ?>">
                  <?php if ($est['regular'] == 1 && $est['aprobada'] == 0 && $est['inscripto_para_final'] == 0): ?>
                      <!-- Mostrar botón de regularizar si no es regular ni aprobado ni inscripto -->
                      <button type="button" 
                              class="btn btn-sm btn-warning"
                              data-bs-toggle="modal" 
                              data-bs-target="#confirmarRegularizarModal"
                              data-estudiante-id="<?php echo $est['id_analitico']; ?>"
                              data-estudiante-nombre="<?php echo htmlspecialchars($est['apellido'] . ', ' . $est['nombre']); ?>"
                              data-materia-nombre="<?php echo htmlspecialchars($nombre_materia); ?>">
                         Regularizar
                      </button>
                  <?php elseif ($est['regular'] == 1 && $est['aprobada'] == 0 && $est['inscripto_para_final'] == 1 && $est['calificacion_final'] === null): ?>
                      <!-- Mostrar formulario para cargar nota si está inscripto para final y no tiene nota -->
                      <form method="POST" class="d-inline" id="formNota_<?php echo $est['id_analitico']; ?>">
                          <input type="hidden" name="accion" value="cargar_nota">
                          <input type="hidden" name="id_analitico" value="<?php echo $est['id_analitico']; ?>">
                          <input type="hidden" name="id_materia" value="<?php echo $id_materia; ?>">
                          <div class="input-group input-group-sm" style="width: 200px;">
                            <input type="number" name="nota" class="form-control" min="0" max="10" step="0.01" placeholder="Nota" required>
                            <button type="button" class="btn btn-success" onclick="cargarNota(<?php echo $est['id_analitico']; ?>)">Cargar</button>
                          </div>
                      </form>
                  <?php else: ?>
                      <!-- Mostrar mensaje o botón deshabilitado en otros estados -->
                      <span class="text-muted">-</span>
                  <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php elseif($id_materia > 0): ?>
    <div class="alert alert-info" role="alert">
        No hay estudiantes inscritos en esta materia.
    </div>
    <?php elseif (!$mostrar_selector && count($materias) == 1): ?>
    <!-- Mensaje si tiene una sola materia pero no hay estudiantes -->
    <div class="alert alert-info" role="alert">
        No hay estudiantes inscritos en la materia <?php echo htmlspecialchars($nombre_materia); ?>.
    </div>
    <?php endif; ?>

    <!-- Modal de Confirmación (mantiene el ID original) -->
    <div class="modal fade" id="confirmarRegularizarModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Confirmar Regularidad</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
              ¿Está seguro de que desea regularizar al estudiante <strong id="nombreEstudianteModal"></strong> en la materia <strong id="nombreMateriaModal"></strong>?
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-warning" id="botonConfirmarRegularizar">Sí, Regularizar</button>
          </div>
        </div>
      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Variables para almacenar el ID del estudiante y la materia al abrir el modal
    let estudianteIdParaRegularizar = null;
    let materiaIdParaRegularizar = <?php echo $id_materia; ?>; // Pasar el ID de la materia actual desde PHP

    // Evento que se dispara cuando se muestra el modal
    var confirmarRegularizarModal = document.getElementById('confirmarRegularizarModal');
    confirmarRegularizarModal.addEventListener('show.bs.modal', function (event) {
      // Botón que disparó el modal
      var button = event.relatedTarget;
      // Extraer información del botón
      estudianteIdParaRegularizar = button.getAttribute('data-estudiante-id');
      var estudianteNombre = button.getAttribute('data-estudiante-nombre');
      var materiaNombre = button.getAttribute('data-materia-nombre');
      
      // Actualizar el contenido del modal
      var nombreEstudianteModal = document.getElementById('nombreEstudianteModal');
      var nombreMateriaModal = document.getElementById('nombreMateriaModal');

      nombreEstudianteModal.textContent = estudianteNombre;
      nombreMateriaModal.textContent = materiaNombre;
    });

    // Evento para el botón "Sí, Regularizar" dentro del modal
    document.getElementById('botonConfirmarRegularizar').addEventListener('click', function() {
        if (estudianteIdParaRegularizar && materiaIdParaRegularizar) {
            // Ocultar el modal
            var modal = bootstrap.Modal.getInstance(confirmarRegularizarModal);
            modal.hide();

            // Hacer la solicitud AJAX para regularizar
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'accion=regularizar&id_analitico=' + encodeURIComponent(estudianteIdParaRegularizar) + '&id_materia=' + encodeURIComponent(materiaIdParaRegularizar)
            })
            .then(response => response.json())
            .then(data => {
                const alertaDiv = document.getElementById('mensajeAlerta');
                if (data.success) {
                    // Actualizar la interfaz de usuario
                    // Cambiar el estado visual en la tabla
                    document.getElementById('estado_' + estudianteIdParaRegularizar).innerHTML = '<span class="badge bg-warning">Regular</span>';
                    // Cambiar la acción visual en la tabla
                    document.getElementById('accion_' + estudianteIdParaRegularizar).innerHTML = '<span class="text-muted">-</span>';
                    // Mostrar mensaje de éxito
                    alertaDiv.className = 'alert alert-success';
                    alertaDiv.textContent = data.message;
                } else {
                    // Mostrar mensaje de error
                    alertaDiv.className = 'alert alert-danger';
                    alertaDiv.textContent = data.message;
                }
                alertaDiv.style.display = 'block';
                // Hacer scroll suave hacia el mensaje
                alertaDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            })
            .catch(error => {
                console.error('Error:', error);
                const alertaDiv = document.getElementById('mensajeAlerta');
                alertaDiv.className = 'alert alert-danger';
                alertaDiv.textContent = 'Ocurrió un error inesperado.';
                alertaDiv.style.display = 'block';
                alertaDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        }
    });

    // Función para filtrar la tabla
    document.getElementById('buscadorEstudiantes').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#tablaEstudiantes tbody tr');

        rows.forEach(row => {
            const cells = row.querySelectorAll('.dato-estudiante');
            let match = false;

            cells.forEach(cell => {
                if (cell.textContent.toLowerCase().includes(searchTerm)) {
                    match = true;
                }
            });

            if (match) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Función para cargar la nota
    function cargarNota(id_analitico) {
        const form = document.getElementById('formNota_' + id_analitico);
        const formData = new FormData(form);
        const nota = parseFloat(formData.get('nota'));

        if (isNaN(nota) || nota < 0 || nota > 10) {
            alert('La nota debe ser un número entre 0 y 10.');
            return;
        }

        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            // Convertir FormData a string
            body: new URLSearchParams(formData).toString()
        })
        .then(response => response.json())
        .then(data => {
            const alertaDiv = document.getElementById('mensajeAlerta');
            if (data.success) {
                // Actualizar la interfaz de usuario
                // Cambiar el estado visual en la tabla
                document.getElementById('estado_' + id_analitico).innerHTML = '<span class="badge bg-success">Aprobado</span><div class="small mt-1">Nota: <strong>' + nota.toFixed(2) + '</strong></div>';
                // Cambiar la acción visual en la tabla
                document.getElementById('accion_' + id_analitico).innerHTML = '<span class="text-muted">-</span>';
                alertaDiv.className = 'alert alert-success';
                alertaDiv.textContent = data.message;
            } else {
                alertaDiv.className = 'alert alert-danger';
                alertaDiv.textContent = data.message;
            }
            alertaDiv.style.display = 'block';
            alertaDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        })
        .catch(error => {
            console.error('Error:', error);
            const alertaDiv = document.getElementById('mensajeAlerta');
            alertaDiv.className = 'alert alert-danger';
            alertaDiv.textContent = 'Ocurrió un error inesperado.';
            alertaDiv.style.display = 'block';
            alertaDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }

  </script>
</body>
</html>
<?php 
$stmt_materias->close();
if (isset($stmt)) $stmt->close();
$conn->close(); 
?>