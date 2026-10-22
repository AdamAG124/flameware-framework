<?php

namespace App\Routing;

/**
 * Ruta de una página web renderizada en el servidor.
 *
 * Funciona igual que #[Route], pero el router la registra bajo WEB_BASE_PATH
 * en lugar de BASE_PATH: #[Page(Http::GET, 'nosotros')] responde en
 * /mi-sitio/nosotros, no en /mi-sitio/api/nosotros. Así las URLs que indexan
 * los buscadores quedan limpias sin tocar las del API.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Page
{
    public function __construct(
        public readonly Http $method,
        public readonly string $path,
    ) {}
}
