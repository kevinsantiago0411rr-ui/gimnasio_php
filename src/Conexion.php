<?php
// Activa la comprobación estricta de tipos[cite: 27]
declare(strict_types=1);

// Clase encargada de gestionar la conexión PDO hacia MySQL[cite: 34, 46]
class Conexion
{
    // Guarda una única instancia compartida de PDO
    private static ?PDO $instancia = null;

    // Retorna una conexión PDO activa configurada con excepciones y prepares reales[cite: 34, 46]
    public static function obtener(): PDO
    {
        // Crea la conexión únicamente si aún no existe
        if (self::$instancia === null) {
            // Carga los parámetros de conexión desde config/config.php[cite: 19]
            $config = require __DIR__ . '/../config/config.php';
            // Extrae el arreglo de base de datos
            $db = $config['db'];

            // Construye el DSN de conexión para el driver mysql de PDO[cite: 34]
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['name'],
                $db['charset']
            );

            // Instancia PDO con manejo de excepciones y consultas preparadas nativas[cite: 34, 46]
            self::$instancia = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        // Devuelve la instancia PDO lista para usar
        return self::$instancia;
    }
}