<?php

namespace App\Support;

use JMS\Serializer\SerializerBuilder;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las dos direcciones del JSON del API: el cuerpo de la petición hidratado en
 * un DTO de entrada, y un DTO de salida serializado en la respuesta.
 *
 * Existe para que ningún controlador vuelva a escribir la construcción de un
 * JsonResponse. No es una capa sobre la librería: son las mismas llamadas de
 * siempre, escritas una vez y con nombre.
 *
 * El serializador es estático y perezoso a propósito: construirlo compila el
 * grafo de metadatos y no es gratis. Antes se construía uno por controlador
 * instanciado; ahora hay como mucho uno por petición, y solo si algo lo usa.
 */
final class Json
{
    private static ?SerializerInterface $serializer = null;

    /** Solo métodos estáticos: no hay nada que instanciar. */
    private function __construct() {}

    /**
     * Cuerpo de la petición hidratado en el DTO de entrada.
     *
     * @template T of object
     * @param  class-string<T> $dtoClass
     * @return T
     */
    public static function body(string $dtoClass): object
    {
        return self::serializer()->deserialize(
            Request::createFromGlobals()->getContent(),
            $dtoClass,
            'json',
        );
    }

    /**
     * Respuesta serializada por JMS —respetando los #[Serializer\Type] del
     * DTO—, así que el payload llega a JsonResponse ya como cadena y por eso
     * el último argumento es true.
     */
    public static function response(mixed $payload, int $status = Response::HTTP_OK): Response
    {
        return new JsonResponse(self::serializer()->serialize($payload, 'json'), $status, [], true)->send();
    }

    /** 204 sin cuerpo: la respuesta de un DELETE correcto. */
    public static function noContent(): Response
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT)->send();
    }

    /** Error con el mismo formato que usa el router para sus 404 y 500. */
    public static function error(string $message, int $status): Response
    {
        return new JsonResponse(['success' => false, 'message' => $message], $status)->send();
    }

    private static function serializer(): SerializerInterface
    {
        return self::$serializer ??= SerializerBuilder::create()->build();
    }
}
