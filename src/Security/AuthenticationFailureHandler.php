<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationFailureHandler as LexikAuthenticationFailureHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(private readonly LexikAuthenticationFailureHandler $lexikHandler)
    {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse(
                ['error' => 'Trop de tentatives. Réessayez dans 15 minutes.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        return $this->lexikHandler->onAuthenticationFailure($request, $exception);
    }
}
