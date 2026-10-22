<?php

namespace App\Routing;

use ReflectionClass;
use ReflectionMethod;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class ControllerDiscovery
{
    public static function discover(string $srcDir): array
    {
        $routes  = [];
        $srcDir  = rtrim($srcDir, DIRECTORY_SEPARATOR);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();

            // Only scan files inside a Controllers/ directory
            if (!str_contains($realPath, DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            // Derive FQCN from path: src/Blog/Controllers/BlogController.php → App\Blog\Controllers\BlogController
            $relative = substr($realPath, strlen($srcDir) + 1);
            $fqcn     = 'App\\' . substr(str_replace(DIRECTORY_SEPARATOR, '\\', $relative), 0, -4);

            try {
                if (!class_exists($fqcn, true)) {
                    continue;
                }

                $rc = new ReflectionClass($fqcn);

                foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    // #[Route] cuelga de BASE_PATH (API) y #[Page] de WEB_BASE_PATH (vistas)
                    foreach ([Route::class => 'api', Page::class => 'web'] as $attribute => $scope) {
                        foreach ($method->getAttributes($attribute) as $attr) {
                            /** @var Route|Page $route */
                            $route    = $attr->newInstance();
                            $routes[] = [$route->method->value, $route->path, $fqcn, $method->getName(), $scope];
                        }
                    }
                }
            } catch (\Throwable) {
                // Skip unloadable classes (missing deps at cache-build time)
                continue;
            }
        }

        return $routes;
    }
}
