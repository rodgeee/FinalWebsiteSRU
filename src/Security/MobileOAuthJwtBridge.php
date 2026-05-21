<?php

namespace App\Security;

use App\Entity\Customer;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issues Lexik JWTs and redirects to the mobile app after web-based Google OAuth.
 */
final class MobileOAuthJwtBridge
{
    public const PLATFORM_COOKIE = 'oauth_google_platform';
    public const SESSION_KEY = 'google_oauth_platform';

    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly string $appDeepLinkScheme = 'vallejera',
    ) {
    }

    public function isAppPlatform(Request $request): bool
    {
        if ((string) $request->getSession()->get(self::SESSION_KEY, '') === 'app') {
            return true;
        }

        return (string) $request->cookies->get(self::PLATFORM_COOKIE, '') === 'app';
    }

    public function markAppPlatform(Request $request, Response $response): void
    {
        $request->getSession()->set(self::SESSION_KEY, 'app');
        $response->headers->setCookie(
            Cookie::create(self::PLATFORM_COOKIE, 'app', time() + 600)
                ->withPath('/')
                ->withHttpOnly(true)
                ->withSecure($request->isSecure())
                ->withSameSite(Cookie::SAMESITE_LAX)
        );
    }

    public function clearPlatformCookie(Request $request, Response $response): void
    {
        $request->getSession()->remove(self::SESSION_KEY);
        $response->headers->clearCookie(
            self::PLATFORM_COOKIE,
            '/',
            null,
            $request->isSecure(),
            true,
            Cookie::SAMESITE_LAX
        );
    }

    public function successRedirect(Customer $customer): RedirectResponse
    {
        $token = $this->jwtManager->create($customer);

        return new RedirectResponse($this->buildCallbackUrl(['token' => $token]));
    }

    public function pendingRedirect(string $message): RedirectResponse
    {
        return new RedirectResponse($this->buildCallbackUrl([
            'status' => 'pending',
            'message' => $message,
        ]));
    }

    public function errorRedirect(string $message): RedirectResponse
    {
        return new RedirectResponse($this->buildCallbackUrl([
            'error' => '1',
            'message' => $message,
        ]));
    }

    /**
     * @param array<string, string> $params
     */
    private function buildCallbackUrl(array $params): string
    {
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return sprintf('%s://auth/callback?%s', $this->appDeepLinkScheme, $query);
    }
}
