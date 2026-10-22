<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/**
 * Páginas HTML renderizadas en el servidor: el equivalente de Json para las
 * rutas #[Page].
 *
 *   #[Page(Http::GET, 'nosotros')]
 *   public function about(): Response
 *   {
 *       return View::render('pages.about', ['team' => $team], new Seo(title: 'Nosotros'));
 *   }
 *
 * La vista se renderiza completa en memoria antes de enviar nada: si falla a
 * mitad, el router todavía puede responder con la página 500 en lugar de
 * dejar medio documento en el navegador.
 */
final class View
{
    /** Solo métodos estáticos: no hay nada que instanciar. */
    private function __construct() {}

    /**
     * @param string              $view   Vista en notación de puntos: 'pages.home' → Views/pages/home.php
     * @param array<string,mixed> $data   Variables disponibles en la vista y en el layout
     * @param ?string             $layout Views/layouts/<layout>.php, o null para no envolver la vista
     */
    public static function render(
        string $view,
        array $data = [],
        ?Seo $seo = null,
        ?string $layout = 'main',
        int $status = Response::HTTP_OK,
    ): Response {
        return new Response(self::fetch($view, $data, $seo, $layout), $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ])->send();
    }

    /**
     * El HTML como cadena, sin enviarlo: para el cuerpo de un correo o para
     * devolver un fragmento a una petición AJAX.
     *
     * @param array<string,mixed> $data
     */
    public static function fetch(string $view, array $data = [], ?Seo $seo = null, ?string $layout = null): string
    {
        return new Template(self::root(), $seo ?? new Seo())->render($view, $data, $layout);
    }

    /**
     * Página de error Views/errors/<status>.php. La usa el router para los
     * 404 y 500 fuera del API; un controlador también puede llamarla.
     */
    public static function error(int $status): Response
    {
        $title = match (true) {
            $status === Response::HTTP_UNAUTHORIZED => 'Inicia sesión para continuar',
            $status === Response::HTTP_FORBIDDEN    => 'Acceso denegado',
            $status === Response::HTTP_NOT_FOUND    => 'Página no encontrada',
            $status >= 500                          => 'Error del servidor',
            default                                 => 'Error',
        };

        return self::render("errors.{$status}", ['status' => $status], new Seo(
            title: $title,
            robots: 'noindex, nofollow',
        ), status: $status);
    }

    private static function root(): string
    {
        return dirname(__DIR__) . '/Views';
    }
}
