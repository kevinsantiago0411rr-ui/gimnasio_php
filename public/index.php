<?php
// Activa la verificación estricta de tipos en este archivo
declare(strict_types=1);

// Carga la conexión PDO y el repositorio del sistema de gimnasios[cite: 16, 31]
require_once __DIR__ . '/../src/Conexion.php';
require_once __DIR__ . '/../src/GimnasioRepositorio.php';

// Escapa salidas HTML en UTF-8 para prevenir ataques XSS[cite: 27, 41]
$e = fn(?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

// Convierte hora (1-12), minuto (0-59) y periodo ('AM' o 'PM') a formato 24h 'HH:MM:SS'
function convertirAmPmAHora24(int $hora12, int $minuto, string $periodo): ?string
{
    if ($hora12 < 1 || $hora12 > 12 || $minuto < 0 || $minuto > 59) {
        return null;
    }
    $periodo = strtoupper(trim($periodo));
    if (!in_array($periodo, ['AM', 'PM'], true)) {
        return null;
    }

    if ($periodo === 'AM') {
        $hora24 = ($hora12 === 12) ? 0 : $hora12;
    } else {
        $hora24 = ($hora12 === 12) ? 12 : ($hora12 + 12);
    }

    return sprintf('%02d:%02d:00', $hora24, $minuto);
}

// Convierte una hora MySQL 'HH:MM:SS' a arreglo ['hora12' => int, 'minuto' => int, 'periodo' => 'AM'|'PM']
function convertirHora24AAmPm(string $hora24Sql): array
{
    $partes = explode(':', $hora24Sql);
    $h24    = (int) ($partes[0] ?? 8);
    $min    = (int) ($partes[1] ?? 0);

    $periodo = ($h24 >= 12) ? 'PM' : 'AM';
    $h12     = $h24 % 12;
    if ($h12 === 0) {
        $h12 = 12;
    }

    return [
        'hora12'  => $h12,
        'minuto'  => $min,
        'periodo' => $periodo,
    ];
}

// Formatea una hora 'HH:MM:SS' para mostrarla claramente con AM o PM en las tablas
function formatearHoraAmPm(string $hora24Sql): string
{
    $info = convertirHora24AAmPm($hora24Sql);
    return sprintf('%02d:%02d %s', $info['hora12'], $info['minuto'], $info['periodo']);
}

// Obtiene la fecha actual del servidor en formato YYYY-MM-DD
$fechaHoy = date('Y-m-d');

// Arreglo para mensajes de error (sin nota de eliminado)[cite: 27, 41]
$errores          = [];
$repo             = null;
$miembroEditar    = null;
$entrenadorEditar = null;
$sesionEditar     = null;

try {
    // Obtiene la conexión PDO e instancia el repositorio[cite: 31]
    $repo = new GimnasioRepositorio(Conexion::obtener());

    // Procesa el envío de formularios cuando el método es POST[cite: 27]
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';

        // Crea o actualiza un miembro en MySQL[cite: 27, 31]
        if ($accion === 'guardar_miembro') {
            $miembroId         = (int) ($_POST['miembro_id'] ?? 0);
            $nombre            = trim((string) ($_POST['nombre'] ?? ''));
            $apellido          = trim((string) ($_POST['apellido'] ?? ''));
            $fechaNacimiento   = trim((string) ($_POST['fecha_nacimiento'] ?? ''));
            $tipoMembresia     = trim((string) ($_POST['tipo_membresia'] ?? 'Mensual'));
            $membresiasValidas = ['Mensual', 'Trimestral', 'Anual', 'Pase Diario'];

            if ($nombre === '' || $apellido === '' || $fechaNacimiento === '') {
                $errores[] = 'Nombre, apellido y fecha de nacimiento son obligatorios.';
            } elseif (!in_array($tipoMembresia, $membresiasValidas, true)) {
                $errores[] = 'Tipo de membresía no válido.';
            } elseif ($miembroId > 0) {
                $repo->actualizarMiembro($miembroId, $nombre, $apellido, $fechaNacimiento, $tipoMembresia);
                header('Location: index.php');
                exit;
            } else {
                $repo->crearMiembro($nombre, $apellido, $fechaNacimiento, $tipoMembresia);
                header('Location: index.php');
                exit;
            }
        }

        // Elimina un miembro sin mostrar nota de eliminado[cite: 27]
        if ($accion === 'eliminar_miembro') {
            $miembroId = (int) ($_POST['miembro_id'] ?? 0);
            if ($miembroId > 0) {
                $repo->eliminarMiembro($miembroId);
                header('Location: index.php');
                exit;
            }
        }

        // Crea o actualiza un entrenador en MySQL[cite: 27, 31]
        if ($accion === 'guardar_entrenador') {
            $entrenadorId = (int) ($_POST['entrenador_id'] ?? 0);
            $nombre       = trim((string) ($_POST['nombre'] ?? ''));
            $especialidad = trim((string) ($_POST['especialidad'] ?? ''));
            $telefono     = trim((string) ($_POST['telefono'] ?? ''));
            $email        = trim((string) ($_POST['email'] ?? ''));

            if ($nombre === '' || $especialidad === '' || $telefono === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'Complete todos los campos del entrenador con un correo válido.';
            } elseif ($entrenadorId > 0) {
                $repo->actualizarEntrenador($entrenadorId, $nombre, $especialidad, $telefono, $email);
                header('Location: index.php');
                exit;
            } else {
                $repo->crearEntrenador($nombre, $especialidad, $telefono, $email);
                header('Location: index.php');
                exit;
            }
        }

        // Elimina un entrenador sin mostrar nota de eliminado[cite: 27]
        if ($accion === 'eliminar_entrenador') {
            $entrenadorId = (int) ($_POST['entrenador_id'] ?? 0);
            if ($entrenadorId > 0) {
                $repo->eliminarEntrenador($entrenadorId);
                header('Location: index.php');
                exit;
            }
        }

        // Crea o actualiza una sesión en MySQL validando fecha >= hoy y hora_fin > hora_inicio[cite: 27, 31]
        if ($accion === 'guardar_sesion') {
            $sesionId        = (int) ($_POST['sesion_id'] ?? 0);
            $miembroId       = (int) ($_POST['miembro_id'] ?? 0);
            $entrenadorId    = (int) ($_POST['entrenador_id'] ?? 0);
            $fechaSesion     = trim((string) ($_POST['fecha_sesion'] ?? ''));
            $duracionMinutos = (int) ($_POST['duracion_minutos'] ?? 60);
            $tipoSesion      = trim((string) ($_POST['tipo_sesion'] ?? ''));
            $notas           = trim((string) ($_POST['notas'] ?? ''));

            $fechaObj    = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaSesion);
            $fechaValida = ($fechaObj !== false && $fechaObj->format('Y-m-d') === $fechaSesion);

            $inicioHora12  = (int) ($_POST['inicio_hora12'] ?? 8);
            $inicioMinuto  = (int) ($_POST['inicio_minuto'] ?? 0);
            $inicioPeriodo = (string) ($_POST['inicio_periodo'] ?? 'AM');
            $horaInicioSql = convertirAmPmAHora24($inicioHora12, $inicioMinuto, $inicioPeriodo);

            $finHora12     = (int) ($_POST['fin_hora12'] ?? 9);
            $finMinuto     = (int) ($_POST['fin_minuto'] ?? 0);
            $finPeriodo    = (string) ($_POST['fin_periodo'] ?? 'AM');
            $horaFinSql    = convertirAmPmAHora24($finHora12, $finMinuto, $finPeriodo);

            if (
                $miembroId <= 0 ||
                $entrenadorId <= 0 ||
                !$fechaValida ||
                $horaInicioSql === null ||
                $horaFinSql === null ||
                $tipoSesion === '' ||
                $duracionMinutos <= 0
            ) {
                $errores[] = 'Seleccione miembro, entrenador, fecha válida, hora de inicio (AM/PM), hora final (AM/PM), duración y tipo de sesión válidos.';
            } elseif ($fechaSesion < $fechaHoy) {
                $errores[] = 'No se pueden agendar sesiones en fechas pasadas. Seleccione la fecha actual (' . $fechaHoy . ') o una fecha futura.';
            } elseif ($horaFinSql <= $horaInicioSql) {
                $errores[] = 'La hora final debe ser mayor que la hora de inicio.';
            } elseif ($sesionId > 0) {
                $repo->actualizarSesion($sesionId, $miembroId, $entrenadorId, $fechaSesion, $horaInicioSql, $horaFinSql, $duracionMinutos, $tipoSesion, $notas);
                header('Location: index.php');
                exit;
            } else {
                $repo->crearSesion($miembroId, $entrenadorId, $fechaSesion, $horaInicioSql, $horaFinSql, $duracionMinutos, $tipoSesion, $notas);
                header('Location: index.php');
                exit;
            }
        }

        // Elimina una sesión sin mostrar nota de eliminado[cite: 27]
        if ($accion === 'eliminar_sesion') {
            $sesionId = (int) ($_POST['sesion_id'] ?? 0);
            if ($sesionId > 0) {
                $repo->eliminarSesion($sesionId);
                header('Location: index.php');
                exit;
            }
        }
    }

    // Carga registro a editar según parámetro GET
    if ($repo && isset($_GET['editar_miembro'])) {
        $idEditar = (int) $_GET['editar_miembro'];
        if ($idEditar > 0) {
            $miembroEditar = $repo->obtenerMiembroPorId($idEditar);
        }
    }
    if ($repo && isset($_GET['editar_entrenador'])) {
        $idEditar = (int) $_GET['editar_entrenador'];
        if ($idEditar > 0) {
            $entrenadorEditar = $repo->obtenerEntrenadorPorId($idEditar);
        }
    }
    if ($repo && isset($_GET['editar_sesion'])) {
        $idEditar = (int) $_GET['editar_sesion'];
        if ($idEditar > 0) {
            $sesionEditar = $repo->obtenerSesionPorId($idEditar);
        }
    }
} catch (Throwable $ex) {
    // Registra el error técnico en el log del servidor sin exponer detalles sensibles
    error_log($ex->getMessage());
    $errores[] = ($ex instanceof PDOException && $ex->getCode() === '23000')
        ? 'Restricción de base de datos: verifique duplicados o registros relacionados.'
        : 'Error de conexión o consulta en la base de datos.';
}

$miembros     = $repo ? $repo->listarMiembros() : [];
$entrenadores = $repo ? $repo->listarEntrenadores() : [];
$sesiones     = $repo ? $repo->listarSesiones() : [];

$fechaSesionValor = $sesionEditar['fecha_sesion'] ?? $fechaHoy;

$inicioAmPm = $sesionEditar
    ? convertirHora24AAmPm((string) $sesionEditar['hora_inicio'])
    : ['hora12' => 8, 'minuto' => 0, 'periodo' => 'AM'];

$finAmPm = $sesionEditar
    ? convertirHora24AAmPm((string) $sesionEditar['hora_fin'])
    : ['hora12' => 9, 'minuto' => 0, 'periodo' => 'AM'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Gestión de Gimnasios</title>
    <style>
        /* Fondo en tonalidad celeste con texto oscuro legible */
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            min-height: 100vh;
            color: #0c4a6e;
            background-color: #e0f2fe;
        }

        /* Títulos principales en celeste oscuro */
        h1, h2 {
            color: #0369a1;
        }

        /* Cuadrícula adaptable para los formularios */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(310px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }

        /* Tarjetas en tonalidad celeste claro con borde definido */
        .card {
            background: #f0f9ff;
            padding: 18px;
            border-radius: 10px;
            border: 2px solid #7dd3fc;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.12);
        }

        /* Etiquetas de los campos */
        label {
            display: block;
            margin-top: 10px;
            font-size: 14px;
            font-weight: bold;
            color: #0c4a6e;
        }

        /* Entradas y selectores */
        input, select {
            width: 100%;
            padding: 8px;
            margin-top: 4px;
            border-radius: 5px;
            border: 1px solid #38bdf8;
            background: #ffffff;
            color: #0f172a;
            box-sizing: border-box;
        }

        /* Fila para seleccionar hora (1-12), minutos (00-59) y periodo AM/PM */
        .ampm-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin-top: 4px;
        }

        /* Botones en tonalidad celeste intenso con texto blanco */
        button, a.btn-link {
            display: inline-block;
            margin-top: 14px;
            padding: 9px 14px;
            border: none;
            border-radius: 5px;
            background: #0284c7;
            color: #ffffff;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        /* Efecto hover en botones */
        button:hover, a.btn-link:hover {
            background: #0369a1;
        }

        /* Botones compactos dentro de las tablas */
        button.btn-sm, a.btn-sm {
            margin-top: 0;
            margin-right: 6px;
            padding: 5px 10px;
            font-size: 12px;
            background: #0284c7;
        }

        /* Botón cancelar edición */
        a.btn-cancelar {
            margin-left: 8px;
            background: #64748b;
        }

        /* Tablas con fondo blanco y bordes celestes */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            margin-bottom: 24px;
            background: #ffffff;
            border: 1px solid #7dd3fc;
            border-radius: 8px;
            overflow: hidden;
        }

        /* Celdas de encabezado y datos */
        th, td {
            border: 1px solid #bae6fd;
            padding: 9px;
            text-align: left;
            font-size: 14px;
            color: #0f172a;
        }

        /* Encabezado de tabla en celeste intenso con texto blanco */
        th {
            background: #0284c7;
            color: #ffffff;
        }

        /* Caja de mensaje de error */
        .err {
            background: #fee2e2;
            color: #7f1d1d;
            border: 1px solid #fca5a5;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 14px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Título principal -->
    <h1>Sistema de Gestión de Gimnasios</h1>

    <!-- Mensajes de error -->
    <?php foreach ($errores as $err): ?>
        <div class="err"><?= $e($err) ?></div>
    <?php endforeach; ?>

    <!-- Formularios para miembros, entrenadores y sesiones -->
    <div class="grid">
        <!-- Registrar o editar miembro -->
        <div class="card">
            <h2><?= $miembroEditar ? 'Editar Miembro #' . (int) $miembroEditar['miembro_id'] : 'Registrar Miembro' ?></h2>
            <form method="post" action="index.php">
                <input type="hidden" name="accion" value="guardar_miembro">
                <input type="hidden" name="miembro_id" value="<?= $miembroEditar ? (int) $miembroEditar['miembro_id'] : 0 ?>">
                <label>Nombre</label>
                <input type="text" name="nombre" value="<?= $e($miembroEditar['nombre'] ?? '') ?>" required>
                <label>Apellido</label>
                <input type="text" name="apellido" value="<?= $e($miembroEditar['apellido'] ?? '') ?>" required>
                <label>Fecha de nacimiento</label>
                <input type="date" name="fecha_nacimiento" value="<?= $e($miembroEditar['fecha_nacimiento'] ?? '') ?>" required>
                <label>Tipo de membresía</label>
                <?php $membActual = $miembroEditar['tipo_membresia'] ?? 'Mensual'; ?>
                <select name="tipo_membresia">
                    <option value="Mensual" <?= $membActual === 'Mensual' ? 'selected' : '' ?>>Mensual</option>
                    <option value="Trimestral" <?= $membActual === 'Trimestral' ? 'selected' : '' ?>>Trimestral</option>
                    <option value="Anual" <?= $membActual === 'Anual' ? 'selected' : '' ?>>Anual</option>
                    <option value="Pase Diario" <?= $membActual === 'Pase Diario' ? 'selected' : '' ?>>Pase Diario</option>
                </select>
                <button type="submit"><?= $miembroEditar ? 'Actualizar Miembro' : 'Guardar Miembro' ?></button>
                <?php if ($miembroEditar): ?>
                    <a href="index.php" class="btn-link btn-cancelar">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Registrar o editar entrenador -->
        <div class="card">
            <h2><?= $entrenadorEditar ? 'Editar Entrenador #' . (int) $entrenadorEditar['entrenador_id'] : 'Registrar Entrenador' ?></h2>
            <form method="post" action="index.php">
                <input type="hidden" name="accion" value="guardar_entrenador">
                <input type="hidden" name="entrenador_id" value="<?= $entrenadorEditar ? (int) $entrenadorEditar['entrenador_id'] : 0 ?>">
                <label>Nombre completo</label>
                <input type="text" name="nombre" value="<?= $e($entrenadorEditar['nombre'] ?? '') ?>" required>
                <label>Especialidad</label>
                <input type="text" name="especialidad" value="<?= $e($entrenadorEditar['especialidad'] ?? '') ?>" required>
                <label>Teléfono</label>
                <input type="text" name="telefono" value="<?= $e($entrenadorEditar['telefono'] ?? '') ?>" required>
                <label>Email</label>
                <input type="email" name="email" value="<?= $e($entrenadorEditar['email'] ?? '') ?>" required>
                <button type="submit"><?= $entrenadorEditar ? 'Actualizar Entrenador' : 'Guardar Entrenador' ?></button>
                <?php if ($entrenadorEditar): ?>
                    <a href="index.php" class="btn-link btn-cancelar">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Programar o editar sesión de entrenamiento -->
        <div class="card">
            <h2><?= $sesionEditar ? 'Editar Sesión #' . (int) $sesionEditar['sesion_id'] : 'Programar Sesión de Entrenamiento' ?></h2>
            <form method="post" action="index.php">
                <input type="hidden" name="accion" value="guardar_sesion">
                <input type="hidden" name="sesion_id" value="<?= $sesionEditar ? (int) $sesionEditar['sesion_id'] : 0 ?>">

                <label>Miembro</label>
                <?php $miembroSel = (int) ($sesionEditar['miembro_id'] ?? 0); ?>
                <select name="miembro_id" required>
                    <option value="">-- Seleccione --</option>
                    <?php foreach ($miembros as $m): ?>
                        <?php $mid = (int) $m['miembro_id']; ?>
                        <option value="<?= $mid ?>" <?= $mid === $miembroSel ? 'selected' : '' ?>>
                            <?= $e($m['nombre'] . ' ' . $m['apellido']) ?> (<?= $e($m['tipo_membresia']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Entrenador</label>
                <?php $entrenadorSel = (int) ($sesionEditar['entrenador_id'] ?? 0); ?>
                <select name="entrenador_id" required>
                    <option value="">-- Seleccione --</option>
                    <?php foreach ($entrenadores as $en): ?>
                        <?php $eid = (int) $en['entrenador_id']; ?>
                        <option value="<?= $eid ?>" <?= $eid === $entrenadorSel ? 'selected' : '' ?>>
                            <?= $e($en['nombre']) ?> - <?= $e($en['especialidad']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Fecha de sesión (desde hoy en adelante)</label>
                <input type="date" name="fecha_sesion" min="<?= $e($fechaHoy) ?>" value="<?= $e($fechaSesionValor) ?>" required>

                <label>Hora de inicio (AM / PM)</label>
                <div class="ampm-row">
                    <select name="inicio_hora12" aria-label="Hora de inicio" required>
                        <?php for ($h = 1; $h <= 12; $h++): ?>
                            <option value="<?= $h ?>" <?= $inicioAmPm['hora12'] === $h ? 'selected' : '' ?>>
                                <?= sprintf('%02d h', $h) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <select name="inicio_minuto" aria-label="Minutos de inicio" required>
                        <?php for ($min = 0; $min < 60; $min++): ?>
                            <option value="<?= $min ?>" <?= $inicioAmPm['minuto'] === $min ? 'selected' : '' ?>>
                                <?= sprintf('%02d min', $min) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <select name="inicio_periodo" aria-label="Periodo AM o PM de inicio" required>
                        <option value="AM" <?= $inicioAmPm['periodo'] === 'AM' ? 'selected' : '' ?>>AM</option>
                        <option value="PM" <?= $inicioAmPm['periodo'] === 'PM' ? 'selected' : '' ?>>PM</option>
                    </select>
                </div>

                <label>Hora final (AM / PM)</label>
                <div class="ampm-row">
                    <select name="fin_hora12" aria-label="Hora final" required>
                        <?php for ($h = 1; $h <= 12; $h++): ?>
                            <option value="<?= $h ?>" <?= $finAmPm['hora12'] === $h ? 'selected' : '' ?>>
                                <?= sprintf('%02d h', $h) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <select name="fin_minuto" aria-label="Minutos finales" required>
                        <?php for ($min = 0; $min < 60; $min++): ?>
                            <option value="<?= $min ?>" <?= $finAmPm['minuto'] === $min ? 'selected' : '' ?>>
                                <?= sprintf('%02d min', $min) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <select name="fin_periodo" aria-label="Periodo AM o PM final" required>
                        <option value="AM" <?= $finAmPm['periodo'] === 'AM' ? 'selected' : '' ?>>AM</option>
                        <option value="PM" <?= $finAmPm['periodo'] === 'PM' ? 'selected' : '' ?>>PM</option>
                    </select>
                </div>

                <label>Duración (minutos)</label>
                <input type="number" name="duracion_minutos" value="<?= (int) ($sesionEditar['duracion_minutos'] ?? 60) ?>" min="1" max="480" required>

                <label>Tipo de sesión</label>
                <input type="text" name="tipo_sesion" value="<?= $e($sesionEditar['tipo_sesion'] ?? '') ?>" required>

                <label>Notas</label>
                <input type="text" name="notas" value="<?= $e($sesionEditar['notas'] ?? '') ?>">

                <button type="submit"><?= $sesionEditar ? 'Actualizar Sesión' : 'Programar Sesión' ?></button>
                <?php if ($sesionEditar): ?>
                    <a href="index.php" class="btn-link btn-cancelar">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Tabla de miembros registrados -->
    <h2>Miembros Registrados</h2>
    <table>
        <thead><tr><th>ID</th><th>Nombre y Apellido</th><th>Fecha Nacimiento</th><th>Membresía</th><th>Acciones</th></tr></thead>
        <tbody>
            <?php foreach ($miembros as $m): ?>
                <tr>
                    <td><?= (int) $m['miembro_id'] ?></td>
                    <td><?= $e($m['nombre'] . ' ' . $m['apellido']) ?></td>
                    <td><?= $e($m['fecha_nacimiento']) ?></td>
                    <td><?= $e($m['tipo_membresia']) ?></td>
                    <td>
                        <a href="index.php?editar_miembro=<?= (int) $m['miembro_id'] ?>" class="btn-link btn-sm">Editar</a>
                        <form method="post" action="index.php" style="display:inline;">
                            <input type="hidden" name="accion" value="eliminar_miembro">
                            <input type="hidden" name="miembro_id" value="<?= (int) $m['miembro_id'] ?>">
                            <button type="submit" class="btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Tabla de entrenadores registrados -->
    <h2>Entrenadores</h2>
    <table>
        <thead><tr><th>ID</th><th>Nombre</th><th>Especialidad</th><th>Teléfono</th><th>Email</th><th>Acciones</th></tr></thead>
        <tbody>
            <?php foreach ($entrenadores as $en): ?>
                <tr>
                    <td><?= (int) $en['entrenador_id'] ?></td>
                    <td><?= $e($en['nombre']) ?></td>
                    <td><?= $e($en['especialidad']) ?></td>
                    <td><?= $e($en['telefono']) ?></td>
                    <td><?= $e($en['email']) ?></td>
                    <td>
                        <a href="index.php?editar_entrenador=<?= (int) $en['entrenador_id'] ?>" class="btn-link btn-sm">Editar</a>
                        <form method="post" action="index.php" style="display:inline;">
                            <input type="hidden" name="accion" value="eliminar_entrenador">
                            <input type="hidden" name="entrenador_id" value="<?= (int) $en['entrenador_id'] ?>">
                            <button type="submit" class="btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Tabla de sesiones de entrenamiento -->
    <h2>Sesiones de Entrenamiento</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Hora Inicio</th>
                <th>Hora Final</th>
                <th>Duración</th>
                <th>Tipo de Sesión</th>
                <th>Miembro</th>
                <th>Entrenador</th>
                <th>Notas</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sesiones as $s): ?>
                <tr>
                    <td><?= (int) $s['sesion_id'] ?></td>
                    <td><?= $e($s['fecha_sesion']) ?></td>
                    <td><?= $e(formatearHoraAmPm((string) $s['hora_inicio'])) ?></td>
                    <td><?= $e(formatearHoraAmPm((string) $s['hora_fin'])) ?></td>
                    <td><?= (int) $s['duracion_minutos'] ?> min</td>
                    <td><?= $e($s['tipo_sesion']) ?></td>
                    <td><?= $e($s['miembro_nombre']) ?></td>
                    <td><?= $e($s['entrenador_nombre']) ?> (<?= $e($s['especialidad']) ?>)</td>
                    <td><?= $e($s['notas'] ?? '-') ?></td>
                    <td>
                        <a href="index.php?editar_sesion=<?= (int) $s['sesion_id'] ?>" class="btn-link btn-sm">Editar</a>
                        <form method="post" action="index.php" style="display:inline;">
                            <input type="hidden" name="accion" value="eliminar_sesion">
                            <input type="hidden" name="sesion_id" value="<?= (int) $s['sesion_id'] ?>">
                            <button type="submit" class="btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>