<?php
session_start();
require_once "../conexion.php";

if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "profesor") {
    header("Location: ../index.html");
    exit();
}

$conn = conectar();
$mensaje = "";

// Procesar la solicitud de regularización vía AJAX (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'regularizar' && isset($_POST['id_analitico'])) {
    $id_analitico = intval($_POST['id_analitico']);
    $id_materia = intval($_POST['id_materia']);

    // Verificar que el analitico pertenezca a una materia asignada al profesor y no esté ya regularizado/aprobado
    $check = $conn->prepare("
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
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_analitico);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Estudiante regularizado correctamente.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al regularizar al estudiante.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: No tienes permiso, el estudiante ya está regularizado o aprobado.']);
    }
    exit(); // Finalizar la ejecución para la solicitud AJAX
}

// Obtener materia seleccionada
$id_materia = isset($_GET['materia']) ? intval($_GET['materia']) : 0;

// Obtener materias asignadas al profesor
$sql_materias = "SELECT m.id, m.nombre 
                 FROM asignaciones a
                 JOIN materias m ON a.id_materia = m.id
                 WHERE a.id_profesor = ? AND a.activo = 1 AND m.activo = 1
                 ORDER BY m.nombre";
$stmt_materias = $conn->prepare($sql_materias);
$stmt_materias->bind_param("i", $_SESSION["id_usuario"]);
$stmt_materias->execute();
$materias = $stmt_materias->get_result();

// Si hay materia seleccionada, obtener estudiantes
$estudiantes = null;
$nombre_materia = "";
$fechas_asistencia = []; // Array para almacenar las fechas únicas de asistencia para la materia
if ($id_materia > 0) {
    // Verificar que la materia seleccionada pertenezca al profesor
    $sql_check = "SELECT m.nombre 
                  FROM asignaciones a
                  JOIN materias m ON a.id_materia = m.id
                  WHERE a.id_profesor = ? AND m.id = ? AND a.activo = 1 AND m.activo = 1";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $_SESSION["id_usuario"], $id_materia);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($row_mat = $result_check->fetch_assoc()) {
        $nombre_materia = $row_mat['nombre'];
        
        // Obtener fechas únicas de asistencia para la materia
        $sql_fechas = "SELECT DISTINCT fecha FROM asistencias WHERE id_materia = ? ORDER BY fecha";
        $stmt_fechas = $conn->prepare($sql_fechas);
        $stmt_fechas->bind_param("i", $id_materia);
        $stmt_fechas->execute();
        $result_fechas = $stmt_fechas->get_result();
        while ($row_fecha = $result_fechas->fetch_assoc()) {
            $fechas_asistencia[] = $row_fecha['fecha'];
        }

        // Obtener estudiantes inscritos vía tabla 'analitico' junto con su estado
        // y calcular el porcentaje de asistencia
        $sql_estudiantes = "SELECT e.id as id_estudiante, a.id as id_analitico, e.dni, e.nombre, e.apellido, e.email, e.telefono, a.regular, a.aprobada, a.inscripto_para_final, a.calificacion_final,
                                   (SELECT COUNT(*) FROM asistencias WHERE id_usuario = e.id AND id_materia = ? AND presente = 1) as asistencias,
                                   (SELECT COUNT(*) FROM asistencias WHERE id_usuario = e.id AND id_materia = ? AND (presente = 0 OR ausente = 1)) as inasistencias,
                                   (SELECT COUNT(*) FROM asistencias WHERE id_usuario = e.id AND id_materia = ?) as total_clases
                            FROM analitico a
                            JOIN estudiante e ON a.id_estudiante = e.id
                            WHERE a.id_materia = ? AND a.activo = 1 AND e.activo = 1
                            ORDER BY e.apellido, e.nombre";
        $stmt = $conn->prepare($sql_estudiantes);
        $stmt->bind_param("iiii", $id_materia, $id_materia, $id_materia, $id_materia);
        $stmt->execute();
        $estudiantes = $stmt->get_result();
    } else {
        // Si la materia no pertenece al profesor, resetear
        $id_materia = 0;
        $nombre_materia = "";
    }
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
    /* Estilo para el contenedor del mensaje de éxito */
    #mensajeAlerta {
        display: none;
        margin-top: 15px;
    }
    .porcentaje-asistencia {
        cursor: pointer;
        font-weight: bold;
    }
    .porcentaje-alto { color: #28a745; } /* Verde */
    .porcentaje-medio { color: #ffc107; } /* Amarillo */
    .porcentaje-bajo { color: #dc3545; }  /* Rojo */
  </style>
</head>
<body>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="h3">Lista de Estudiantes por Materia</h2>
      <a href="../panel_profesor.php" class="btn btn-outline-secondary">Volver al Panel</a>
    </div>

    <!-- Selector de materia -->
    <div class="card mb-4">
      <div class="card-body">
        <form method="GET">
          <div class="row">
            <div class="col-md-10">
              <select name="materia" class="form-control" required onchange="this.form.submit()">
                <option value="">-- Seleccione una materia --</option>
                <?php while($mat = $materias->fetch_assoc()): ?>
                <option value="<?php echo $mat['id']; ?>" <?php echo ($id_materia == $mat['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($mat['nombre']); ?>
                </option>
                <?php endwhile; ?>
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

    <!-- Contenedor para mensajes de alerta -->
    <div id="mensajeAlerta" class="alert" role="alert">
        <!-- El contenido se inserta dinámicamente -->
    </div>

    <!-- Lista de estudiantes -->
    <?php if($estudiantes && $estudiantes->num_rows > 0): ?>
    <div class="card">
      <div class="card-header">
        <h5>Estudiantes en: <?php echo htmlspecialchars($nombre_materia); ?></h5>
        <small class="text-muted">Total: <?php echo $estudiantes->num_rows; ?> estudiantes</small>
      </div>
      <div class="card-body">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th>DNI</th>
              <th>Apellido y Nombre</th>
              <th>Asistencia</th> <!-- Nueva columna -->
              <th>Estado</th> <!-- Nueva columna para estado de regularidad -->
            </tr>
          </thead>
          <tbody>
            <?php while($est = $estudiantes->fetch_assoc()): ?>
            <?php
            // Cálculo del porcentaje de asistencia
            $total_clases = $est['total_clases'];
            $asistencias = $est['asistencias'];
            $inasistencias = $est['inasistencias'];
            $porcentaje = 0;
            if ($total_clases > 0) {
                $porcentaje = ($asistencias / $total_clases) * 100;
            }
            // Clase CSS para color según porcentaje
            $clase_porcentaje = 'porcentaje-bajo';
            if ($porcentaje >= 75) {
                $clase_porcentaje = 'porcentaje-alto';
            } elseif ($porcentaje >= 50) {
                $clase_porcentaje = 'porcentaje-medio';
            }

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
              <td><?php echo htmlspecialchars($est['dni']); ?></td>
              <td><?php echo htmlspecialchars($est['apellido'] . ', ' . $est['nombre']); ?></td>
              <td>
                  <span class="porcentaje-asistencia <?php echo $clase_porcentaje; ?>" 
                        data-bs-toggle="modal" 
                        data-bs-target="#detalleAsistenciaModal"
                        data-estudiante-id="<?php echo $est['id_estudiante']; ?>"
                        data-estudiante-nombre="<?php echo htmlspecialchars($est['apellido'] . ', ' . $est['nombre']); ?>"
                        data-materia-id="<?php echo $id_materia; ?>"
                        data-porcentaje="<?php echo number_format($porcentaje, 2); ?>"
                        data-total="<?php echo $total_clases; ?>"
                        data-asistencias="<?php echo $asistencias; ?>"
                        data-inasistencias="<?php echo $inasistencias; ?>">
                      <?php echo number_format($porcentaje, 2); ?>%
                  </span>
              </td>
              <td id="estado_<?php echo $est['id_analitico']; ?>">
                  <span class="badge <?php echo $clase_estado; ?>"><?php echo $estado_texto; ?></span>
                  <?php if ($est['calificacion_final'] !== null): ?>
                      <div class="small mt-1">Nota: <strong><?php echo number_format($est['calificacion_final'], 2); ?></strong></div>
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
        No hay estudiantes inscritos en esta materia o no tienes permiso para verla.
    </div>
    <?php endif; ?>

    <!-- Nuevo Modal para Detalle de Asistencia -->
    <div class="modal fade" id="detalleAsistenciaModal" tabindex="-1" aria-labelledby="detalleAsistenciaModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl"> <!-- Modal más grande -->
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="detalleAsistenciaModalLabel">Detalle de Asistencia</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
              <h6 id="tituloDetalleAsistencia">Asistencia para: <span id="nombreEstudianteDetalle"></span> - Materia: <span id="nombreMateriaDetalle"></span></h6>
              <p>Porcentaje actual: <strong id="porcentajeActualDetalle"></strong></p>
              <p>Total Clases: <span id="totalClasesDetalle"></span> | Asistencias: <span id="asistenciasDetalle"></span> | Inasistencias: <span id="inasistenciasDetalle"></span></p>
              
              <div class="mb-3">
                  <label for="seleccionarFechaDetalle" class="form-label">Seleccionar Fecha:</label>
                  <select class="form-control" id="seleccionarFechaDetalle">
                      <!-- Las opciones se llenan dinámicamente con JS -->
                  </select>
              </div>

              <div id="detalleFechaCargando" class="text-center" style="display: none;">
                  <div class="spinner-border" role="status">
                    <span class="visually-hidden">Cargando...</span>
                  </div>
              </div>
              <div id="detalleFechaContenido">
                  <!-- Contenido de la asistencia por fecha se carga aquí -->
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // --- Lógica para el Nuevo Modal de Detalle de Asistencia ---
    let estudianteIdDetalle = null;
    let materiaIdDetalle = null;
    let fechasDisponibles = <?php echo json_encode($fechas_asistencia); ?>; // Pasar fechas PHP a JS

    var detalleAsistenciaModal = document.getElementById('detalleAsistenciaModal');
    detalleAsistenciaModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        estudianteIdDetalle = button.getAttribute('data-estudiante-id');
        var estudianteNombre = button.getAttribute('data-estudiante-nombre');
        materiaIdDetalle = button.getAttribute('data-materia-id'); // Asegúrate de pasar el id_materia
        var porcentaje = button.getAttribute('data-porcentaje');
        var total = button.getAttribute('data-total');
        var asistencias = button.getAttribute('data-asistencias');
        var inasistencias = button.getAttribute('data-inasistencias');

        document.getElementById('nombreEstudianteDetalle').textContent = estudianteNombre;
        document.getElementById('porcentajeActualDetalle').textContent = porcentaje + '%';
        document.getElementById('totalClasesDetalle').textContent = total;
        document.getElementById('asistenciasDetalle').textContent = asistencias;
        document.getElementById('inasistenciasDetalle').textContent = inasistencias;

        // Llenar el select de fechas
        const selectFecha = document.getElementById('seleccionarFechaDetalle');
        selectFecha.innerHTML = ''; // Limpiar opciones anteriores
        fechasDisponibles.forEach(fecha => {
            const option = document.createElement('option');
            option.value = fecha;
            option.textContent = fecha; // Puedes formatear la fecha aquí si lo deseas
            selectFecha.appendChild(option);
        });

        if (fechasDisponibles.length > 0) {
            // Cargar detalle de la primera fecha por defecto
            cargarDetalleFecha(fechasDisponibles[0]);
        } else {
            document.getElementById('detalleFechaContenido').innerHTML = '<p class="text-muted">No se han tomado asistencias para esta materia aún.</p>';
        }
    });

    // Evento para cuando se cambia la fecha en el select
    document.getElementById('seleccionarFechaDetalle').addEventListener('change', function() {
        const fechaSeleccionada = this.value;
        if (fechaSeleccionada) {
            cargarDetalleFecha(fechaSeleccionada);
        }
    });

    // Función para cargar el detalle de asistencia para una fecha específica
    function cargarDetalleFecha(fecha) {
        if (!estudianteIdDetalle || !materiaIdDetalle || !fecha) return;

        document.getElementById('detalleFechaCargando').style.display = 'block';
        document.getElementById('detalleFechaContenido').innerHTML = '';

        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'accion=detalle_asistencia&id_estudiante=' + encodeURIComponent(estudianteIdDetalle) + '&id_materia=' + encodeURIComponent(materiaIdDetalle) + '&fecha=' + encodeURIComponent(fecha)
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('detalleFechaCargando').style.display = 'none';
            const contenidoDiv = document.getElementById('detalleFechaContenido');
            if (data.success) {
                contenidoDiv.innerHTML = data.html;
            } else {
                contenidoDiv.innerHTML = '<p class="text-danger">Error al cargar el detalle: ' + data.message + '</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('detalleFechaCargando').style.display = 'none';
            document.getElementById('detalleFechaContenido').innerHTML = '<p class="text-danger">Ocurrió un error inesperado al cargar el detalle.</p>';
        });
    }

    // Procesar la solicitud de detalle de asistencia vía AJAX (PHP)
    // Esta lógica debe estar en el PHP principal o en un archivo separado
    // Se ha movido al inicio del archivo PHP para que funcione en el mismo script.
    // if (typeof window === 'undefined') { ... } <- Esto no se usa aquí, ya está en PHP.
  </script>
</body>
</html>
<?php 
$stmt_materias->close();
if (isset($stmt_check)) $stmt_check->close();
if (isset($stmt)) $stmt->close();
if (isset($stmt_fechas)) $stmt_fechas->close();
$conn->close(); 
?>