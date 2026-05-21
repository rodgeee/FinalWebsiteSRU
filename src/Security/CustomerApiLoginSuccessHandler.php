<?php

namespace App\Security;

use App\Entity\Customer;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * POST /api/login — return a plain { "token": "..." } payload for the mobile app.
 */
final class CustomerApiLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();
        if (!$user instanceof Customer) {
            return new JsonResponse(['message' => 'This app is for customer accounts only.'], 403);
        }

        return new JsonResponse([
            'token' => $this->jwtManager->create($user),
        ]);
    }
}
