<?php

namespace App\Controller\Api;

use App\Service\Google\GoogleCustomerAuthResult;
use App\Service\Google\GoogleCustomerAuthService;
use App\Service\Google\GoogleIdTokenVerifier;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GoogleAuthController extends AbstractController
{
    public function __construct(
        private readonly GoogleIdTokenVerifier $idTokenVerifier,
        private readonly GoogleCustomerAuthService $googleCustomerAuth,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[Route('/api/login/google', name: 'api_login_google', methods: ['POST'])]
    public function loginWithGoogle(Request $request): JsonResponse
    {
        $body = $this->decodeJson($request);
        $idToken = trim((string) ($body['idToken'] ?? ''));
        $action = (string) ($body['action'] ?? 'login');
        if ($action !== 'login' && $action !== 'signup') {
            $action = 'login';
        }

        if ($idToken === '') {
            return $this->json(['message' => 'Google idToken is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $profile = $this->idTokenVerifier->verify($idToken);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->googleCustomerAuth->authenticate($profile, $action);

        return match ($result->status) {
            GoogleCustomerAuthResult::STATUS_JWT_READY => $this->json([
                'token' => $this->jwtManager->create($result->customer),
            ]),
            GoogleCustomerAuthResult::STATUS_SIGNUP_PENDING => $this->json(
                ['message' => $result->message],
                Response::HTTP_CREATED
            ),
            GoogleCustomerAuthResult::STATUS_UNVERIFIED_SIGNUP_RESENT => $this->json(
                ['message' => $result->message],
                Response::HTTP_OK
            ),
            GoogleCustomerAuthResult::STATUS_UNVERIFIED_LOGIN => $this->json(
                ['message' => $result->message],
                Response::HTTP_FORBIDDEN
            ),
            GoogleCustomerAuthResult::STATUS_NOT_REGISTERED => $this->json(
                ['message' => $result->message],
                Response::HTTP_UNPROCESSABLE_ENTITY
            ),
            default => $this->json(['message' => 'Google sign-in failed.'], Response::HTTP_INTERNAL_SERVER_ERROR),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(Request $request): array
    {
        $content = (string) $request->getContent();
        if ($content === '') {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
