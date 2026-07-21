<?php

use App\Config\{Config, Dependencies};
use App\Routing\Router;
use DI\ContainerBuilder;

require_once __DIR__ . '/vendor/autoload.php';

class ApiGateWay
{
    private Config $config;
    private Router $router;

    public function __construct()
    {
        $this->router = new Router();
        $this->config = new Config();
    }

    public function initializeApi(): void
    {
        if ($this->configureCors()) {
            return;
        }

        $this->config->defineEnvirmentVariables();

        require_once __DIR__ . '/bootstrap.php';

        $builder = new ContainerBuilder();
        $builder->addDefinitions((new Dependencies())->interfaceMap());
        $container = $builder->build();

        $this->router->loadFromArray(require __DIR__ . '/Storage/route-cache.php', $container, BASE_PATH);
        $this->router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
    }

    /**
     * Devuelve true cuando la petición es un preflight y no debe seguir al router.
     */
    private function configureCors(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($origin !== '' && $this->isOriginAllowed($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Max-Age: 86400');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            return true;
        }

        return false;
    }

    /**
     * En desarrollo se acepta cualquier localhost. Para producción, define
     * ALLOWED_ORIGINS en .env.local como una lista separada por comas:
     *   ALLOWED_ORIGINS=https://midominio.com,https://www.midominio.com
     */
    private function isOriginAllowed(string $origin): bool
    {
        $allowed = $_ENV['ALLOWED_ORIGINS'] ?? '';

        if ($allowed !== '') {
            $whitelist = array_map('trim', explode(',', $allowed));
            return in_array($origin, $whitelist, true);
        }

        return (bool) preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin);
    }
}

new ApiGateWay()->initializeApi();
