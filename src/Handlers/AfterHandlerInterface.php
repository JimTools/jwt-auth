<?php

declare(strict_types=1);

namespace JimTools\JwtAuth\Handlers;

use Psr\Http\Message\ResponseInterface;

interface AfterHandlerInterface
{
    /**
     * @param array{decoded: array<string, mixed>, token: string}|array{uri: string, message: string} $arguments
     */
    public function __invoke(ResponseInterface $response, array $arguments): ResponseInterface;
}
