<?php

namespace App\Controllers;

use App\DTOs\Response\HealthResponseDto;
use App\Routing\Http;
use App\Routing\Route;
use JMS\Serializer\SerializerBuilder;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controlador de ejemplo — muestra el patrón completo del framework.
 * Bórralo cuando ya no lo necesites.
 */
class HealthController
{
    private SerializerInterface $serializer;

    public function __construct()
    {
        $this->serializer = SerializerBuilder::create()->build();
    }

    #[Route(Http::GET, 'health')]
    public function check(): Response
    {
        $dto = new HealthResponseDto();
        $dto->success = true;
        $dto->message = 'API operativa.';
        $dto->php = PHP_VERSION;

        return (new JsonResponse(
            $this->serializer->serialize($dto, 'json'),
            Response::HTTP_OK,
            [],
            true
        ))->send();
    }
}
