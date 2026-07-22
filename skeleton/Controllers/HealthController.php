<?php

namespace App\Controllers;

use App\DTOs\Response\HealthResponseDto;
use App\Routing\Http;
use App\Routing\Route;
use App\Support\Json;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controlador de ejemplo — muestra el patrón completo del framework.
 * Bórralo cuando ya no lo necesites.
 */
class HealthController
{
    #[Route(Http::GET, 'health')]
    public function check(): Response
    {
        $dto = new HealthResponseDto();
        $dto->success = true;
        $dto->message = 'API operativa.';
        $dto->php = PHP_VERSION;

        return Json::response($dto);
    }
}
