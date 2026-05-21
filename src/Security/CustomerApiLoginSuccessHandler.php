<?php

namespace App\Security;

use App\Entity\Customer;
use App\Service\Api\ApiResponseFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * POST /api/login — API envelope (data.token) plus legacy top-level token for older app builds.
 */
final class CustomerApiLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ApiResponseFactory $api,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();
        if (!$user instanceof Customer) {
            return $this->api->error('This app is for customer accounts only.', 403);
        }

        try {
            $jwt = $this->jwtManager->create($user);
        } catch (\Throwable) {
            return $this->api->error('Could not start your session. Please try again.', 500);
        }

        $body = json_decode($this->api->success(['token' => $jwt])->getContent(), true);
        if (!is_array($body)) {
            return $this->api->success(['token' => $jwt]);
        }

        $body['token'] = $jwt;

        return new JsonResponse($body, 200);
    }
}
