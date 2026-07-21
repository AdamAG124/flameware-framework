<?php

namespace App\Routing;

enum Http: string
{
    case GET    = 'GET';
    case POST   = 'POST';
    case PUT    = 'PUT';
    case PATCH  = 'PATCH';
    case DELETE = 'DELETE';
    case HEAD   = 'HEAD';
}
