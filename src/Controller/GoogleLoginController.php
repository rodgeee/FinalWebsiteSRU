<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Security\MobileOAuthJwtBridge;
use App\Service\Google\GoogleCustomerAuthResult;
use App\Service\Google\GoogleCustomerAuthService;
use App\Service\Google\GoogleProfile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class GoogleLoginController extends AbstractController
{
    private const OAUTH_STATE_COOKIE = 'oauth_google_state';
    private const OAUTH_ACTION_COOKIE = 'oauth_google_action';

    public function __construct(
        private readonly string $googleClientId,
        private readonly string $googleClientSecret,
        private readonly string $googleCallbackUrl,
        private readonly MobileOAuthJwtBridge $mobileJwtBridge,
        private readonly GoogleCustomerAuthService $googleCustomerAuth,
    ) {
    }

    #[Route('/login/google', name: 'customer_login_google', methods: ['GET'])]
    public function start(Request $request): RedirectResponse
    {
        return $this->startOAuth($request, 'login');
    }

    #[Route('/signup/google', name: 'customer_signup_google', methods: ['GET'])]
    public function startSignup(Request $request): RedirectResponse
    {
        return $this->startOAuth($request, 'signup');
    }

    private function startOAuth(Request $request, string $action): RedirectResponse
    {
        $clientId = $this->googleClientId;
        $redirectUri = $this->googleCallbackUrl;

        $loginRoute = $action === 'signup' ? 'customer_signup' : 'customer_login';
        if ($clientId === '' || $redirectUri === '') {
            if ($this->wantsAppPlatform($request)) {
                return $this->finalizeOAuthRedirect(
                    $request,
                    $this->mobileJwtBridge->errorRedirect('Google OAuth is not configured on the server.')
                );
            }
            $this->addFlash('error', 'Google OAuth is not configured (missing GOOGLE_CLIENT_ID / GOOGLE_CALLBACK_URL).');

            return $this->redirectToRoute($loginRoute);
        }

        $state = bin2hex(random_bytes(16));
        $session = $request->getSession();
        $session->set('google_oauth2_state', $state);
        $session->set('google_oauth_action', $action);

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        $response = $this->redirect($url);

        $response->headers->setCookie(Cookie::create(self::OAUTH_STATE_COOKIE, $state, time() + 600)
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite(Cookie::SAMESITE_LAX));

        $response->headers->setCookie(Cookie::create(self::OAUTH_ACTION_COOKIE, $action, time() + 600)
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite(Cookie::SAMESITE_LAX));

        if ($this->wantsAppPlatform($request)) {
            $this->mobileJwtBridge->markAppPlatform($request, $response);
        }

        return $response;
    }

    #[Route('/connect/google/check', name: 'customer_google_check', methods: ['GET'])]
    public function callback(
        Request $request,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        RouterInterface $router,
        Security $security
    ): Response {
        $isApp = $this->mobileJwtBridge->isAppPlatform($request);

        $error = $request->query->get('error');
        if ($error) {
            $message = 'Google login failed: ' . (string) $error;
            if ($isApp) {
                return $this->finalizeOAuthRedirect($request, $this->mobileJwtBridge->errorRedirect($message));
            }
            $this->addFlash('error', $message);

            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('customer_login'));
        }

        $expectedState = (string) $request->getSession()->get('google_oauth2_state', '');
        if ($expectedState === '') {
            $expectedState = (string) $request->cookies->get(self::OAUTH_STATE_COOKIE, '');
        }
        $receivedState = (string) $request->query->get('state', '');
        if ($expectedState === '' || !hash_equals($expectedState, $receivedState)) {
            $message = 'Google login failed: invalid state. Use a normal link (not preview), avoid switching between localhost and 127.0.0.1, and ensure GOOGLE_CALLBACK_URL matches the address you use in the browser.';
            if ($isApp) {
                return $this->finalizeOAuthRedirect($request, $this->mobileJwtBridge->errorRedirect($message));
            }
            $this->addFlash('error', $message);

            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('customer_login'));
        }

        $request->getSession()->remove('google_oauth2_state');

        $oauthAction = (string) $request->getSession()->get('google_oauth_action', '');
        if ($oauthAction === '') {
            $oauthAction = (string) $request->cookies->get(self::OAUTH_ACTION_COOKIE, '');
        }
        if ($oauthAction !== 'login' && $oauthAction !== 'signup') {
            $oauthAction = 'login';
        }
        $request->getSession()->remove('google_oauth_action');

        $code = (string) $request->query->get('code', '');
        if ($code === '') {
            $message = 'Google login failed: missing authorization code.';
            if ($isApp) {
                return $this->finalizeOAuthRedirect($request, $this->mobileJwtBridge->errorRedirect($message));
            }
            $this->addFlash('error', $message);

            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('customer_login'));
        }

        $clientId = $this->googleClientId;
        $clientSecret = $this->googleClientSecret;
        $redirectUri = $this->googleCallbackUrl;

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            $message = 'Google OAuth is not configured.';
            if ($isApp) {
                return $this->finalizeOAuthRedirect($request, $this->mobileJwtBridge->errorRedirect($message));
            }
            $this->addFlash('error', $message);

            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('customer_login'));
        }

        try {
            $tokenResponse = $httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
                'body' => http_build_query([
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                ]),
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'timeout' => 20,
                'max_duration' => 25,
            ]);
        } catch (TransportExceptionInterface $e) {
            $logger->error('Google token request transport failed.', [
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            $message = 'Could not reach Google to finish sign-in (network timeout or blocked). Check your connection, firewall, or try again.';
            if ($isApp) {
                return $this->finalizeOAuthRedirect($request, $this->mobileJwtBridge->errorRedirect($message));
            }
            $this->addFlash('error', $message);

            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('customer_login'));
        }

        try {
            $tokenData = $tokenResponse->toArray(false);
        } catch (\Throwable $e) {
            $logger->error('Google token response parse failed.', [
                'status' => $tokenResponse->getStatusCode(),
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            $message = 'Google login failed: invalid response from Google. Check GOOGLE_CLIENT_ID / secret and callback URL.';
            if ($isApp) {
                return $this->finalizeOAuthRedirect($request, $this->mobileJwtBridge->errorRedirect($message));
            }
            $this->addFlash('error', $message);

            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('customer_login'));
        }

        if ($tokenResponse->getStatusCode() >= 400) {
            $logger->warning('Google token endpoint returned error.', [
                'status' => $tokenResponse->getStatusCode(),
                'body_preview' => substr((string) $tokenResponse->getContent(false), 0, 500),
            ]);
            $message = 'Google login failed: token exchange rejected. Confirm GOOGLE_CALLBACK_URL matches this app URL exactly.';
            if ($isApp) {
                return $this->finalizeOAuthRedirect($request, $this->mobileJwtBridge->errorRedirect($message));
            }
            $this->addFlash('error', $message);

            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('customer_login'));
        }

        $idToken = (string) ($tokenData['id_token'] ?? '');
        if ($idToken === '') {
            $message = 'Google login failed: missing id_token.';
            if ($isApp) {
                return $this->finalizeOAuthRedirect($request, $this->mobileJwtBridge->errorRedirect($message));
            }
            $this->addFlash('error', $message);

            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('customer_login'));
        }

        $payload = $this->decodeIdTokenPayload($idToken);
        $email = (string) ($payload['email'] ?? '');

        if ($email === '') {
            $accessToken = (string) ($tokenData['access_token'] ?? '');
            if ($accessToken === '') {
                $message = 'Google login failed: email not available.';
                if ($isApp) {
                    return $this->finalizeOAuthRedirect($request, $this->mobileJwtBridge->errorRedirect($message));
                }
                $this->addFlash('error', $message);

                return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('customer_login'));
            }

            try {
                $userinfoResponse = $httpClient->request('GET', 'https://openidconnect.googleapis.com/v1/userinfo', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                    ],
                    'timeout' => 15,
                    'max_duration' => 20,
                ]);
                $userinfo = $userinfoResponse->toArray(false);
            } catch (TransportExceptionInterface $e) {
                $logger->error('Google userinfo request failed.', [
                    'exception_class' => $e::class,
                    'exception_message' => $e->getMessage(),
                ]);
                $message = 'Could not load your Google profile (network issue). Try again.';
                if ($isApp) {
                    return $this->finalizeOAuthRedirect($request, $this->mobileJwtBridge->errorRedirect($message));
                }
                $this->addFlash('error', $message);

                return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('customer_login'));
            } catch (\Throwable $e) {
                $logger->error('Google userinfo parse failed.', ['exception_message' => $e->getMessage()]);
                $message = 'Google login failed: could not read profile.';
                if ($isApp) {
                    return $this->finalizeOAuthRedirect($request, $this->mobileJwtBridge->errorRedirect($message));
                }
                $this->addFlash('error', $message);

                return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('customer_login'));
            }
            $email = (string) ($userinfo['email'] ?? '');
            $payload = array_merge($payload, $userinfo);
        }

        if ($email === '') {
            $message = 'Google login failed: email not available.';
            if ($isApp) {
                return $this->finalizeOAuthRedirect($request, $this->mobileJwtBridge->errorRedirect($message));
            }
            $this->addFlash('error', $message);

            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('customer_login'));
        }

        $givenName = (string) ($payload['given_name'] ?? '');
        $familyName = (string) ($payload['family_name'] ?? '');
        $fullName = trim(($givenName . ' ' . $familyName) ?: $email);

        $router->getContext()->fromRequest($request);
        $profile = new GoogleProfile($email, $fullName, $givenName, $familyName);
        $authResult = $this->googleCustomerAuth->authenticate($profile, $oauthAction);

        if ($isApp) {
            return $this->finalizeOAuthRedirect($request, $this->mapAuthResultToAppRedirect($authResult));
        }

        return $this->finalizeOAuthRedirect(
            $request,
            $this->mapAuthResultToWebRedirect($request, $authResult, $security, $logger, $email)
        );
    }

    private function mapAuthResultToAppRedirect(GoogleCustomerAuthResult $result): RedirectResponse
    {
        return match ($result->status) {
            GoogleCustomerAuthResult::STATUS_JWT_READY => $this->mobileJwtBridge->successRedirect(
                $result->customer ?? throw new \LogicException('Customer missing for JWT redirect.')
            ),
            GoogleCustomerAuthResult::STATUS_SIGNUP_PENDING,
            GoogleCustomerAuthResult::STATUS_UNVERIFIED_SIGNUP_RESENT => $this->mobileJwtBridge->pendingRedirect($result->message),
            default => $this->mobileJwtBridge->errorRedirect(
                $result->message !== '' ? $result->message : 'Google sign-in failed.'
            ),
        };
    }

    private function mapAuthResultToWebRedirect(
        Request $request,
        GoogleCustomerAuthResult $result,
        Security $security,
        LoggerInterface $logger,
        string $email,
    ): RedirectResponse {
        return match ($result->status) {
            GoogleCustomerAuthResult::STATUS_JWT_READY => $this->loginCustomerOnWeb(
                $result->customer ?? throw new \LogicException('Customer missing for web login.'),
                $security,
                $logger,
                $email
            ),
            GoogleCustomerAuthResult::STATUS_SIGNUP_PENDING => $this->webFlashAndRedirect(
                'success',
                $result->message,
                'customer_login'
            ),
            GoogleCustomerAuthResult::STATUS_UNVERIFIED_SIGNUP_RESENT => $this->webFlashAndRedirect(
                str_contains($result->message, 'could not send') ? 'error' : 'success',
                $result->message,
                'customer_login'
            ),
            GoogleCustomerAuthResult::STATUS_UNVERIFIED_LOGIN => $this->webFlashAndRedirect(
                'error',
                $result->message,
                'customer_login'
            ),
            GoogleCustomerAuthResult::STATUS_NOT_REGISTERED => $this->webFlashAndRedirect(
                'error',
                $result->message,
                'customer_signup'
            ),
            default => $this->webFlashAndRedirect('error', 'Google sign-in failed.', 'customer_login'),
        };
    }

    private function loginCustomerOnWeb(
        Customer $userForLogin,
        Security $security,
        LoggerInterface $logger,
        string $email,
    ): RedirectResponse {
        try {
            $loginResponse = $security->login($userForLogin, null, 'customer');
        } catch (\Throwable $e) {
            $message = $e instanceof CustomUserMessageAccountStatusException
                ? $e->getMessage()
                : 'Google account connected, but automatic login failed. Please log in manually.';

            $this->addFlash('error', $message);
            $logger->error('Google automatic login failed.', [
                'email' => $email,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            return $this->redirectToRoute('customer_login');
        }

        return $loginResponse instanceof RedirectResponse
            ? $loginResponse
            : $this->redirectToRoute('app_landingpage_legacy');
    }

    private function webFlashAndRedirect(string $type, string $message, string $route): RedirectResponse
    {
        $this->addFlash($type, $message);

        return $this->redirectToRoute($route);
    }

    private function wantsAppPlatform(Request $request): bool
    {
        return $request->query->get('platform') === 'app';
    }

    private function finalizeOAuthRedirect(Request $request, RedirectResponse $response): RedirectResponse
    {
        $response->headers->clearCookie(self::OAUTH_STATE_COOKIE, '/', null, $request->isSecure(), true, Cookie::SAMESITE_LAX);
        $response->headers->clearCookie(self::OAUTH_ACTION_COOKIE, '/', null, $request->isSecure(), true, Cookie::SAMESITE_LAX);
        $this->mobileJwtBridge->clearPlatformCookie($request, $response);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeIdTokenPayload(string $idToken): array
    {
        $parts = explode('.', $idToken);
        if (\count($parts) < 2) {
            return [];
        }

        $payloadB64 = $parts[1];
        $padLength = (4 - (strlen($payloadB64) % 4)) % 4;
        if ($padLength > 0) {
            $payloadB64 .= str_repeat('=', $padLength);
        }
        $payloadB64 = strtr($payloadB64, '-_', '+/');

        $json = base64_decode($payloadB64, true);
        if (!\is_string($json)) {
            return [];
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return $data;
        } catch (\JsonException) {
            return [];
        }
    }
}
