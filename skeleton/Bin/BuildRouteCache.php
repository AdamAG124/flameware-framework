#!/usr/bin/env php
<?php

/**
 * Constructor del caché de rutas.
 *
 * Escanea todos los controladores bajo la raíz del proyecto, lee los atributos
 * #[Route] por Reflection y escribe un array PHP en Storage/route-cache.php.
 *
 * Uso:
 *   php Bin/BuildRouteCache.php
 *   composer routes
 *
 * Se ejecuta automáticamente tras cada composer install / update mediante los
 * scripts declarados en composer.json.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Routing\ControllerDiscovery;

$srcDir  = realpath(__DIR__ . '/../');
$outFile = __DIR__ . '/../Storage/route-cache.php';

$routes = ControllerDiscovery::discover($srcDir);

$export = var_export($routes, true);
$php    = "<?php\n// Generado por Bin/BuildRouteCache.php — no editar manualmente.\nreturn {$export};\n";

file_put_contents($outFile, $php);

$count = count($routes);
$pages = count(array_filter($routes, static fn (array $route): bool => $route[4] === 'web'));
$detail = $pages > 0 ? " ({$pages} página(s) web)" : '';
echo "Caché de rutas construido: {$count} ruta(s){$detail} → Storage/route-cache.php\n";
