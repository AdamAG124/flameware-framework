<?php

namespace App\Controllers;

use App\Routing\Http;
use App\Routing\Page;
use App\Support\Seo;
use App\Support\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controlador de ejemplo de una página web — muestra el patrón completo.
 * Bórralo cuando ya no lo necesites.
 *
 * #[Page] cuelga de WEB_BASE_PATH, así que '' es la portada del sitio.
 * Los datos llegan del Service como siempre; el controlador solo elige la
 * vista y declara el SEO de la página.
 */
class HomeController
{
    #[Page(Http::GET, '')]
    public function index(): Response
    {
        return View::render('pages.home', [
            'headline' => 'Tu sitio está funcionando',
            'features' => [
                ['title' => 'Renderizado en servidor', 'body' => 'El HTML sale completo desde PHP.'],
                ['title' => 'SEO por página', 'body' => 'Title, description, Open Graph y canonical desde el controlador.'],
                ['title' => 'Componentes', 'body' => 'Piezas reutilizables con sus propias props.'],
            ],
        ], new Seo(
            title: 'Inicio',
            description: 'Sitio construido sobre Flameware con renderizado en servidor.',
            keywords: ['flameware', 'php', 'ssr'],
            jsonLd: [
                '@context' => 'https://schema.org',
                '@type'    => 'WebSite',
                'name'     => 'Flameware',
            ],
        ));
    }
}
