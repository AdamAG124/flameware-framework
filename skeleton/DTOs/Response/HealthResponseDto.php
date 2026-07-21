<?php

namespace App\DTOs\Response;

use JMS\Serializer\Annotation as Serializer;

class HealthResponseDto
{
    #[Serializer\Type("bool")]
    #[Serializer\SerializedName("success")]
    public bool $success;

    #[Serializer\Type("string")]
    #[Serializer\SerializedName("message")]
    public string $message;

    #[Serializer\Type("string")]
    #[Serializer\SerializedName("php")]
    public string $php;
}
