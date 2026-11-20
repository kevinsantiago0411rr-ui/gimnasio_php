<?php
// Activa la comprobación estricta de tipos en este archivo[cite: 27]
declare(strict_types=1);

// Establece la zona horaria local para fechas y horas
date_default_timezone_set('America/Guayaquil');

// Retorna los parámetros de conexión a MySQL en MAMP[cite: 8, 19]
return [
    'db' => [
        // Dirección del servidor MySQL local
        'host'    => '127.0.0.1',
        // Puerto de MySQL en MAMP (cambiar a 3306 si usas el puerto estándar)[cite: 8]
        'port'    => '8889',
        // Nombre de la base de datos del gimnasio
        'name'    => 'gimnasio_db',
        // Usuario por defecto de MySQL en MAMP
        'user'    => 'root',
        // Contraseña por defecto de MySQL en MAMP
        'pass'    => 'root',
        // Codificación UTF-8 completa para tildes, eñes y símbolos[cite: 43]
        'charset' => 'utf8mb4',
    ],
];