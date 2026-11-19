<?php

use App\Auth\DatabaseJwtStorage;
use PHPOpenSourceSaver\JWTAuth\Providers\Auth\Illuminate;
use PHPOpenSourceSaver\JWTAuth\Providers\JWT\Lcobucci;

return [
    'secret' => env('JWT_SECRET'),
    'ttl' => null,
    'refresh_ttl' => 0,
    'algo' => 'HS256',
    'required_claims' => ['iss', 'iat', 'nbf', 'sub', 'jti', 'aud', 'pwd'],
    'blacklist_enabled' => true,
    'blacklist_grace_period' => 0,
    'providers' => [
        'storage' => DatabaseJwtStorage::class,
        'jwt' => Lcobucci::class,
        'auth' => Illuminate::class,
    ],
];
