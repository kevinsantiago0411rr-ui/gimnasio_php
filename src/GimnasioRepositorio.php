<?php
// Activa la verificación estricta de tipos en este archivo
declare(strict_types=1);

// Clase repositorio con consultas preparadas PDO para gimnasio_db
class GimnasioRepositorio
{
    // Recibe la conexión PDO por inyección en el constructor
    public function __construct(private PDO $pdo) {}

    // Reinicia AUTO_INCREMENT a 1 solo cuando la tabla queda sin registros
    private function reiniciarAutoIncrementSiVacia(string $tabla): void
    {
        // Lista blanca estricta de tablas permitidas
        $tablasPermitidas = ['Miembros', 'Entrenadores', 'SesionesEntrenamiento'];
        if (!in_array($tabla, $tablasPermitidas, true)) {
            return;
        }

        // Verifica si quedan filas en la tabla
        $total = (int) $this->pdo->query("SELECT COUNT(*) FROM {$tabla}")->fetchColumn();
        if ($total === 0) {
            // Reinicia el correlativo en 1 cuando no hay registros
            $this->pdo->exec("ALTER TABLE {$tabla} AUTO_INCREMENT = 1");
        }
    }

    // Registra un nuevo miembro en la tabla Miembros y retorna su ID
    public function crearMiembro(string $nombre, string $apellido, string $fechaNacimiento, string $tipoMembresia): int
    {
        // Prepara y ejecuta el INSERT parametrizado con PDO
        $stmt = $this->pdo->prepare(
            'INSERT INTO Miembros (nombre, apellido, fecha_nacimiento, tipo_membresia)
             VALUES (:nombre, :apellido, :fecha_nacimiento, :tipo_membresia)'
        );
        $stmt->execute([
            ':nombre'           => $nombre,
            ':apellido'         => $apellido,
            ':fecha_nacimiento' => $fechaNacimiento,
            ':tipo_membresia'   => $tipoMembresia,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // Obtiene un miembro por su ID para cargarlo en el formulario de edición
    public function obtenerMiembroPorId(int $miembroId): ?array
    {
        // Prepara el SELECT parametrizado por miembro_id
        $stmt = $this->pdo->prepare(
            'SELECT miembro_id, nombre, apellido, fecha_nacimiento, tipo_membresia
             FROM Miembros WHERE miembro_id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $miembroId]);
        $fila = $stmt->fetch();
        return $fila !== false ? $fila : null;
    }

    // Actualiza un miembro existente en MySQL
    public function actualizarMiembro(int $miembroId, string $nombre, string $apellido, string $fechaNacimiento, string $tipoMembresia): bool
    {
        // Prepara y ejecuta el UPDATE parametrizado
        $stmt = $this->pdo->prepare(
            'UPDATE Miembros
             SET nombre = :nombre, apellido = :apellido, fecha_nacimiento = :fecha_nacimiento, tipo_membresia = :tipo_membresia
             WHERE miembro_id = :id'
        );
        return $stmt->execute([
            ':id'               => $miembroId,
            ':nombre'           => $nombre,
            ':apellido'         => $apellido,
            ':fecha_nacimiento' => $fechaNacimiento,
            ':tipo_membresia'   => $tipoMembresia,
        ]);
    }

    // Lista todos los miembros ordenados ascendentemente por ID
    public function listarMiembros(): array
    {
        // Ejecuta el SELECT y devuelve las filas como arreglo asociativo
        return $this->pdo->query(
            'SELECT miembro_id, nombre, apellido, fecha_nacimiento, tipo_membresia
             FROM Miembros ORDER BY miembro_id ASC'
        )->fetchAll();
    }

    // Elimina un miembro por su ID y reinicia a 1 si la tabla queda vacía
    public function eliminarMiembro(int $miembroId): bool
    {
        // Prepara y ejecuta el DELETE parametrizado
        $stmt = $this->pdo->prepare('DELETE FROM Miembros WHERE miembro_id = :id');
        $ok = $stmt->execute([':id' => $miembroId]);
        $this->reiniciarAutoIncrementSiVacia('Miembros');
        return $ok;
    }

    // Registra un nuevo entrenador en la tabla Entrenadores y retorna su ID
    public function crearEntrenador(string $nombre, string $especialidad, string $telefono, string $email): int
    {
        // Prepara y ejecuta el INSERT parametrizado
        $stmt = $this->pdo->prepare(
            'INSERT INTO Entrenadores (nombre, especialidad, telefono, email)
             VALUES (:nombre, :especialidad, :telefono, :email)'
        );
        $stmt->execute([
            ':nombre'       => $nombre,
            ':especialidad' => $especialidad,
            ':telefono'     => $telefono,
            ':email'        => $email,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // Obtiene un entrenador por su ID para cargarlo en el formulario de edición
    public function obtenerEntrenadorPorId(int $entrenadorId): ?array
    {
        // Prepara el SELECT parametrizado por entrenador_id
        $stmt = $this->pdo->prepare(
            'SELECT entrenador_id, nombre, especialidad, telefono, email
             FROM Entrenadores WHERE entrenador_id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $entrenadorId]);
        $fila = $stmt->fetch();
        return $fila !== false ? $fila : null;
    }

    // Actualiza un entrenador existente en MySQL
    public function actualizarEntrenador(int $entrenadorId, string $nombre, string $especialidad, string $telefono, string $email): bool
    {
        // Prepara y ejecuta el UPDATE parametrizado
        $stmt = $this->pdo->prepare(
            'UPDATE Entrenadores
             SET nombre = :nombre, especialidad = :especialidad, telefono = :telefono, email = :email
             WHERE entrenador_id = :id'
        );
        return $stmt->execute([
            ':id'           => $entrenadorId,
            ':nombre'       => $nombre,
            ':especialidad' => $especialidad,
            ':telefono'     => $telefono,
            ':email'        => $email,
        ]);
    }

    // Elimina un entrenador por su ID y reinicia a 1 si la tabla queda vacía
    public function eliminarEntrenador(int $entrenadorId): bool
    {
        // Prepara y ejecuta el DELETE parametrizado
        $stmt = $this->pdo->prepare('DELETE FROM Entrenadores WHERE entrenador_id = :id');
        $ok = $stmt->execute([':id' => $entrenadorId]);
        $this->reiniciarAutoIncrementSiVacia('Entrenadores');
        return $ok;
    }

    // Lista todos los entrenadores ordenados ascendentemente por ID
    public function listarEntrenadores(): array
    {
        // Ejecuta el SELECT y devuelve las filas como arreglo asociativo
        return $this->pdo->query(
            'SELECT entrenador_id, nombre, especialidad, telefono, email
             FROM Entrenadores ORDER BY entrenador_id ASC'
        )->fetchAll();
    }

    // Registra una nueva sesión de entrenamiento en SesionesEntrenamiento
    public function crearSesion(
        int $miembroId,
        int $entrenadorId,
        string $fechaSesion,
        string $horaInicio,
        string $horaFin,
        int $duracionMinutos,
        string $tipoSesion,
        ?string $notas
    ): int {
        // Prepara y ejecuta el INSERT parametrizado con PDO
        $stmt = $this->pdo->prepare(
            'INSERT INTO SesionesEntrenamiento
                (miembro_id, entrenador_id, fecha_sesion, hora_inicio, hora_fin, duracion_minutos, tipo_sesion, notas)
             VALUES
                (:miembro_id, :entrenador_id, :fecha_sesion, :hora_inicio, :hora_fin, :duracion_minutos, :tipo_sesion, :notas)'
        );
        $stmt->execute([
            ':miembro_id'       => $miembroId,
            ':entrenador_id'    => $entrenadorId,
            ':fecha_sesion'     => $fechaSesion,
            ':hora_inicio'      => $horaInicio,
            ':hora_fin'         => $horaFin,
            ':duracion_minutos' => $duracionMinutos,
            ':tipo_sesion'      => $tipoSesion,
            ':notas'            => $notas !== '' ? $notas : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // Obtiene una sesión por su ID para cargarla en el formulario de edición
    public function obtenerSesionPorId(int $sesionId): ?array
    {
        // Prepara el SELECT parametrizado por sesion_id
        $stmt = $this->pdo->prepare(
            'SELECT sesion_id, miembro_id, entrenador_id, fecha_sesion, hora_inicio, hora_fin, duracion_minutos, tipo_sesion, notas
             FROM SesionesEntrenamiento WHERE sesion_id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $sesionId]);
        $fila = $stmt->fetch();
        return $fila !== false ? $fila : null;
    }

    // Actualiza una sesión existente en SesionesEntrenamiento
    public function actualizarSesion(
        int $sesionId,
        int $miembroId,
        int $entrenadorId,
        string $fechaSesion,
        string $horaInicio,
        string $horaFin,
        int $duracionMinutos,
        string $tipoSesion,
        ?string $notas
    ): bool {
        // Prepara y ejecuta el UPDATE parametrizado con PDO
        $stmt = $this->pdo->prepare(
            'UPDATE SesionesEntrenamiento
             SET miembro_id = :miembro_id,
                 entrenador_id = :entrenador_id,
                 fecha_sesion = :fecha_sesion,
                 hora_inicio = :hora_inicio,
                 hora_fin = :hora_fin,
                 duracion_minutos = :duracion_minutos,
                 tipo_sesion = :tipo_sesion,
                 notas = :notas
             WHERE sesion_id = :id'
        );
        return $stmt->execute([
            ':id'               => $sesionId,
            ':miembro_id'       => $miembroId,
            ':entrenador_id'    => $entrenadorId,
            ':fecha_sesion'     => $fechaSesion,
            ':hora_inicio'      => $horaInicio,
            ':hora_fin'         => $horaFin,
            ':duracion_minutos' => $duracionMinutos,
            ':tipo_sesion'      => $tipoSesion,
            ':notas'            => $notas !== '' ? $notas : null,
        ]);
    }

    // Elimina una sesión por su ID y reinicia a 1 si la tabla queda vacía
    public function eliminarSesion(int $sesionId): bool
    {
        // Prepara y ejecuta el DELETE parametrizado
        $stmt = $this->pdo->prepare('DELETE FROM SesionesEntrenamiento WHERE sesion_id = :id');
        $ok = $stmt->execute([':id' => $sesionId]);
        $this->reiniciarAutoIncrementSiVacia('SesionesEntrenamiento');
        return $ok;
    }

    // Lista las sesiones en orden ascendente de ID con datos de miembro y entrenador
    public function listarSesiones(): array
    {
        // Une SesionesEntrenamiento con Miembros y Entrenadores
        return $this->pdo->query(
            'SELECT
                s.sesion_id, s.fecha_sesion, s.hora_inicio, s.hora_fin, s.duracion_minutos,
                s.tipo_sesion, s.notas,
                CONCAT(m.nombre, " ", m.apellido) AS miembro_nombre,
                m.tipo_membresia,
                e.nombre AS entrenador_nombre, e.especialidad
             FROM SesionesEntrenamiento s
             INNER JOIN Miembros m ON s.miembro_id = m.miembro_id
             INNER JOIN Entrenadores e ON s.entrenador_id = e.entrenador_id
             ORDER BY s.sesion_id ASC'
        )->fetchAll();
    }
}