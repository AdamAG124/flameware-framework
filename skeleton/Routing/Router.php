<?php

namespace App\Routing;

class Router
{
    private $routes = []; // Array para almacenar las rutas definidas

    public function addRoute($method, $path, $callback)
    {
        // Convierte la ruta en un patrón regex para coincidir con segmentos dinámicos
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_%+-]+)', (string) $path);
        $pattern = '#^' . $pattern . '$#'; // Agrega delimitadores al patrón regex

        $this->routes[] = [
            'method' => strtoupper((string) $method), // Convierte el método HTTP a mayúsculas
            'path' => $path, // Ruta original
            'pattern' => $pattern, // Patrón regex para coincidir
            'callback' => $callback, // Función de callback a ejecutar
        ];
    }

    public function dispatch($requestUri, $requestMethod)
    {
        try {
            $requestPath = parse_url((string) $requestUri, PHP_URL_PATH); // Extrae la ruta del URI de la solicitud

            foreach ($this->routes as $route) {
                // Verifica si el método de la solicitud coincide
                if ($route['method'] === strtoupper((string) $requestMethod)) {
                    // Verifica si la ruta coincide con el patrón regex
                    if (preg_match($route['pattern'], $requestPath, $matches)) {
                        // Extrae los parámetros nombrados de las coincidencias
                        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                        // Decodifica los parámetros codificados en URL
                        $params = array_map('urldecode', $params);
                        return call_user_func($route['callback'], $params); // Ejecuta el callback con los parámetros
                    }
                }
            }

            // Ninguna ruta coincidió: respondemos 404 en JSON directamente,
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
            error_log(sprintf(
                '[ROUTER] %s: %s in %s:%d | Trace: %s',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            ));

            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => 'Internal Server Error. Please try again later',
            ]);
        }
    }

    public function loadFromArray(array $routes, \Psr\Container\ContainerInterface $container, string $basePath = ''): void
    {
        foreach ($routes as [$httpMethod, $path, $class, $action]) {
            $this->addRoute($httpMethod, $basePath . $path, static function (array $params) use ($container,$class, $action) {
                $container->get($class)->$action($params);
            });
        }
    }

    public function getAllRoutes()
    {
        return $this->routes;
    }
}
