<?php

/**
 * Arranque de Eloquent (illuminate/database).
 *
 * Se ejecuta después de Config::defineEnvirmentVariables(), así que las
 * constantes DB_* ya están disponibles.
 *
 * Si DB_DATABASE está vacío o ausente, el ORM no se inicializa: un API sin
 * base de datos funciona igual sin tener que borrar este archivo.
 *
 * Eloquent conecta de forma perezosa — addConnection() no abre la conexión,
 * eso ocurre en la primera consulta.
 */

use Illuminate\Database\Capsule\Manager as Capsule;

if (!defined('DB_DATABASE') || DB_DATABASE === '') {
    return;
}

$capsule = new Capsule();

$capsule->addConnection([
    'driver'    => defined('DB_CONNECTION') ? DB_CONNECTION : 'mysql',
    'host'      => defined('DB_HOST') ? DB_HOST : '127.0.0.1',
    'port'      => defined('DB_PORT') ? DB_PORT : '3306',
    'database'  => DB_DATABASE,
    'username'  => defined('DB_USERNAME') ? DB_USERNAME : 'root',
    'password'  => defined('DB_PASSWORD') ? DB_PASSWORD : '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);

// Deja el capsule accesible de forma estática (Capsule::table(...)) y activa
// Eloquent para que los modelos de App\Models puedan usarse directamente.
$capsule->setAsGlobal();
$capsule->bootEloquent();
