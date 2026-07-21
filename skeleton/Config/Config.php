<?php

namespace App\Config;

use Dotenv\Dotenv;

class Config
{
    /**
     * Variables sin las cuales el API no puede arrancar.
     */
    private const REQUIRED = ['BASE_PATH', 'ERROR_LOGS_DIR'];

    /**
     * Carga .env.local y expone cada variable como constante global.
     *
     * Solo se definen las claves presentes en el archivo, no las del entorno
     * del sistema, así que agregar una variable nueva al .env.local basta para
     * tenerla disponible como constante sin tocar esta clase.
     */
    public function defineEnvirmentVariables(): void
    {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__), '.env.local');
        $variables = $dotenv->load();
        $dotenv->required(self::REQUIRED)->notEmpty();

        foreach (array_keys($variables) as $key) {
            if (!defined($key)) {
                define($key, $_ENV[$key]);
            }
        }

        $this->configureErrorLogging();
    }

    private function configureErrorLogging(): void
    {
        $logFile = ERROR_LOGS_DIR;

        // Resolvemos rutas relativas contra la raíz del proyecto para que los
        // logs no dependan del working directory desde el que se invoque.
        if (!str_starts_with($logFile, DIRECTORY_SEPARATOR)) {
            $logFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . $logFile;
        }

        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        ini_set('error_log', $logFile);
        ini_set('log_errors', '1');
        ini_set('display_errors', '0');
    }
}
