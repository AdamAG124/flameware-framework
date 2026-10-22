<?php

namespace App\Routing;

class Router
{
    private $routes = []; // Array para almacenar las rutas definidas

    private string $apiBasePath = ''; // Prefijo del API: lo que cae aquí siempre responde JSON

    /** @var ?callable(int): void */
    private $webErrorHandler = null; // Renderiza 404/500 en HTML para las rutas web

    public function addRoute($method, $path, $callback, string $scope = 'api')
    {
        // Convierte la ruta en un patrón regex para coincidir con segmentos dinámicos
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_%+-]+)', (string) $path);
        $pattern = '#^' . $pattern . '$#'; // Agrega delimitadores al patrón regex

        $this->routes[] = [
            'method' => strtoupper((string) $method), // Convierte el método HTTP a mayúsculas
            'path' => $path, // Ruta original
            'pattern' => $pattern, // Patrón regex para coincidir
            'callback' => $callback, // Función de callback a ejecutar
            'scope' => $scope, // 'api' (#[Route]) o 'web' (#[Page])
        ];
    }

    /**
     * Registra quién dibuja los errores de las páginas web. Sin handler, el
     * router responde JSON en todos los casos, igual que un proyecto solo API.
     *
     * @param callable(int): void $handler recibe el código HTTP (404 o 500)
     */
    public function setWebErrorHandler(callable $handler): void
    {
        $this->webErrorHandler = $handler;
    }

    public function dispatch($requestUri, $requestMethod)
    {
        $requestPath = (string) parse_url((string) $requestUri, PHP_URL_PATH); // Extrae la ruta del URI de la solicitud
        $scope = null; // Scope de la ruta que coincidió, para saber cómo responder un error

        try {
            foreach ($this->routes as $route) {
                // Verifica si el método de la solicitud coincide
                // Una página nunca atiende URLs del API, ni siquiera /api sin barra final
                if ($route['scope'] === 'web' && $this->isApiPath($requestPath)) {
                    continue;
                }

                if ($route['method'] === strtoupper((string) $requestMethod)) {
                    // Verifica si la ruta coincide con el patrón regex
                    if (preg_match($route['pattern'], $requestPath, $matches)) {
                        $scope = $route['scope'];
                        // Extrae los parámetros nombrados de las coincidencias
                        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                        // Decodifica los parámetros codificados en URL
                        $params = array_map('urldecode', $params);
                        return call_user_func($route['callback'], $params); // Ejecuta el callback con los parámetros
                    }
                }
            }

            // Ninguna ruta coincidió. Fuera del prefijo del API es un visitante
            // navegando, así que recibe la página 404 si el proyecto tiene vistas.
            if (!$this->isApiPath($requestPath) && $this->renderWebError(404)) {
                return;
            }

            // Dentro del API (o sin vistas) respondemos 404 en JSON directamente,
            // sin depender de ningún archivo o vista externa.
            http_response_code(404);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => 'Not Found',
            ]);
            return;
        } catch (\ValidatorException $e) {
            // Error conocido: input inválido — llega aquí solo si un endpoint
            // no tiene su propio try-catch, es el safety net del router
            http_response_code(400);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => $e->__toString(),
            ]);
        } catch (\Throwable $e) {
            // Error desconocido: bug, RuntimeException, TypeError, Error del engine
            $this->logThrowable($e);

            // Una página web que falla muestra la 500 en HTML, no un JSON
            if ($scope === 'web' && $this->renderWebError(500)) {
                return;
            }

            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => 'Internal Server Error. Please try again later',
            ]);
        }
    }

    public function loadFromArray(array $routes, \Psr\Container\ContainerInterface $container, string $basePath = '', string $webBasePath = '/'): void
    {
        $this->apiBasePath = $basePath;

        // Las rutas del API se registran primero: así una página con comodín
        // como #[Page(Http::GET, '{slug}')] nunca le gana a un endpoint.
        // Un caché anterior a #[Page] no trae el quinto elemento: todo es 'api'.
        foreach (['api' => $basePath, 'web' => $webBasePath] as $scope => $prefix) {
            foreach ($routes as $entry) {
                [$httpMethod, $path, $class, $action] = $entry;

                if (($entry[4] ?? 'api') !== $scope) {
                    continue;
                }

                $this->addRoute($httpMethod, $prefix . $path, static function (array $params) use ($container, $class, $action) {
                    $container->get($class)->$action($params);
                }, $scope);
            }
        }
    }

    public function getAllRoutes()
    {
        return $this->routes;
    }

    private function isApiPath(string $requestPath): bool
    {
        // Sin BASE_PATH configurado no hay forma de distinguir: se asume API.
        // La barra añadida hace que /mi-api/api cuente como /mi-api/api/.
        return $this->apiBasePath === '' || str_starts_with(rtrim($requestPath, '/') . '/', $this->apiBasePath);
    }

    /**
     * Devuelve false cuando no hay handler web y el llamador debe responder JSON.
     */
    private function renderWebError(int $status): bool
    {
        if ($this->webErrorHandler === null) {
            return false;
        }

        try {
            ($this->webErrorHandler)($status);
        } catch (\Throwable $e) {
            // La propia página de error falló: texto plano como último recurso
            $this->logThrowable($e);

            http_response_code($status);
            header('Content-Type: text/plain; charset=UTF-8');
            echo $status === 404 ? 'Not Found' : 'Internal Server Error';
        }

        return true;
    }

    private function logThrowable(\Throwable $e): void
    {
        error_log(sprintf(
            '[ROUTER] %s: %s in %s:%d | Trace: %s',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
    }
}
