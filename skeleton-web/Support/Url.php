<?php

namespace App\Support;

/**
 * Las URLs del sitio: rutas de página, archivos de Public/ y su forma absoluta.
 *
 * Devuelve cadenas sin escapar. Dentro de una vista se usan a través de
 * $this->url() y $this->asset(), que ya las entregan escapadas para un atributo.
 */
final class Url
{
    /** Solo métodos estáticos: no hay nada que instanciar. */
    private function __construct() {}

    /** Ruta de una página: Url::to('nosotros') → /mi-sitio/nosotros */
    public static function to(string $path = ''): string
    {
        return self::webBasePath() . ltrim($path, '/');
    }

    /**
     * Archivo de Public/: Url::asset('css/app.css') → /mi-sitio/Public/css/app.css?v=1729...
     *
     * El ?v= es la fecha de modificación del archivo: el navegador puede
     * cachearlo para siempre y aun así recibe la versión nueva tras un cambio.
     */
    public static function asset(string $path, bool $version = true): string
    {
        $path = ltrim($path, '/');
        $url  = self::to('Public/' . $path);
        $file = dirname(__DIR__) . '/Public/' . $path;

        if ($version && is_file($file)) {
            $url .= '?v=' . filemtime($file);
        }

        return $url;
    }

    /**
     * Forma absoluta de una ruta, que es la que exigen el canonical y Open
     * Graph. Lo que ya es absoluto se devuelve tal cual.
     */
    public static function absolute(string $path): string
    {
        if (self::isAbsolute($path)) {
            return $path;
        }

        return self::origin() . '/' . ltrim($path, '/');
    }

    public static function isAbsolute(string $url): bool
    {
        return (bool) preg_match('#^(https?:)?//#i', $url);
    }

    /** URL absoluta de la petición actual, sin query string. */
    public static function current(): string
    {
        return self::absolute((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    }

    /**
     * Esquema y dominio públicos. En producción salen de APP_URL y no de la
     * petición: el Host lo controla el cliente, y un canonical construido con
     * él se podría falsificar.
     */
    private static function origin(): string
    {
        $configured = defined('APP_URL') ? (string) constant('APP_URL') : '';

        if ($configured !== '') {
            $parts = parse_url($configured);
            $port  = isset($parts['port']) ? ':' . $parts['port'] : '';

            return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $port;
        }

        $https = !in_array($_SERVER['HTTPS'] ?? '', ['', 'off'], true);

        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    private static function webBasePath(): string
    {
        $base = defined('WEB_BASE_PATH') ? (string) constant('WEB_BASE_PATH') : '/';

        return rtrim($base, '/') . '/';
    }
}
