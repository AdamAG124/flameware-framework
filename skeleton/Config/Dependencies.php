<?php

namespace App\Config;

use function DI\autowire;

class Dependencies
{
    /**
     * Mapa de interfaz => implementación para el contenedor PHP-DI.
     *
     * Las clases concretas sin interfaz no necesitan registrarse: PHP-DI las
     * resuelve por autowiring leyendo los type hints del constructor.
     *
     * Ejemplo:
     *   \App\Services\IUserService::class => autowire(\App\Services\UserService::class),
     */
    public function interfaceMap(): array
    {
        return [
            //
        ];
    }
}
