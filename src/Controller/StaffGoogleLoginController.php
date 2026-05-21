<?php

namespace App\Controller;

use App\Entity\Staff;
use App\Repository\StaffRepository;
use App\Service\StaffVerificationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class StaffGoogleLoginController extends AbstractController
{
    private const OAUTH_STATE_COOKIE = 'oauth_google_staff_state';
    private const OAUTH_ACTION_COOKIE = 'oauth_google_staff_action';

    public function __construct(
        private readonly string $googleClientId,
        private readonly string $googleClientSecret,
    ) {
    }

    #[Route('/adminls/login/google', name: 'staff_login_google', methods: ['GET'])]
    public function start(Request $request, UrlGeneratorInterface $urlGenerator): RedirectResponse
    {
        $clientId = $this->googleClientId;
        $redirectUri = $urlGenerator->generate(
            'staff_google_check',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        if ($clientId === '' || $redirectUri === '') {
            $this->addFlash('error', 'Google OAuth is not configured (missing GOOGLE_CLIENT_ID).');
            return $this->redirectToRoute('adminls_login');
        }

        $state = bin2hex(random_bytes(16));
        $session = $request->getSession();
        $session->set('google_oauth2_state', $state);
        $session->set('google_oauth_action', 'login');

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

        // Backup: session can be lost (Turbo/XHR, host mismatch). Cookie is sent on Google’s redirect back.
        $response->headers->setCookie(Cookie::create(self::OAUTH_STATE_COOKIE, $state, time() + 600)
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite(Cookie::SAMESITE_LAX));

        $response->headers->setCookie(Cookie::create(self::OAUTH_ACTION_COOKIE, 'login', time() + 600)
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite(Cookie::SAMESITE_LAX));

        return $response;
    }

    #[Route('/adminls/connect/google/check', name: 'staff_google_check', methods: ['GET'])]
    public function callback(
        Request $request,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        StaffRepository $staffRepository,
        StaffVerificationService $staffVerificationService,
        UrlGeneratorInterface $urlGenerator,
        Security $security
    ): Response {
        $error = $request->query->get('error');
        if ($error) {
            $this->addFlash('error', 'Google login failed: ' . (string) $error);
            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        // Prefer session; fall back to cookie (see start()) when session was not persisted.
        $expectedState = (string) $request->getSession()->get('google_oauth2_state', '');
        if ($expectedState === '') {
            $expectedState = (string) $request->cookies->get(self::OAUTH_STATE_COOKIE, '');
        }
        $receivedState = (string) $request->query->get('state', '');
        if ($expectedState === '' || !hash_equals($expectedState, $receivedState)) {
            $this->addFlash('error', 'Google login failed: invalid state. Use a normal link (not preview), avoid switching between localhost and 127.0.0.1.');
            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        $request->getSession()->remove('google_oauth2_state');

        $oauthAction = (string) $request->getSession()->get('google_oauth_action', '');
        if ($oauthAction === '') {
            $oauthAction = (string) $request->cookies->get(self::OAUTH_ACTION_COOKIE, '');
        }
        $request->getSession()->remove('google_oauth_action');

        if ($oauthAction !== 'login') {
            $oauthAction = 'login';
        }

        $code = (string) $request->query->get('code', '');
        if ($code === '') {
            $this->addFlash('error', 'Google login failed: missing authorization code.');
            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        $clientId = $this->googleClientId;
        $clientSecret = $this->googleClientSecret;
        $redirectUri = $urlGenerator->generate(
            'staff_google_check',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            $this->addFlash('error', 'Google OAuth is not configured.');
            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
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

            $this->addFlash('error', 'Could not reach Google to finish sign-in (network issue).');
            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        try {
            $tokenData = $tokenResponse->toArray(false);
        } catch (\Throwable $e) {
            $logger->error('Google token response parse failed.', [
                'exception_class' => $e::class,
                'status' => $tokenResponse->getStatusCode(),
                'exception_message' => $e->getMessage(),
            ]);

            $this->addFlash('error', 'Google login failed: invalid token response.');
            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        if ($tokenResponse->getStatusCode() >= 400) {
            $this->addFlash('error', 'Google login failed: token exchange rejected.');
            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        $idToken = (string) ($tokenData['id_token'] ?? '');
        if ($idToken === '') {
            $this->addFlash('error', 'Google login failed: missing id_token.');
            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        $payload = $this->decodeIdTokenPayload($idToken);
        $email = (string) ($payload['email'] ?? '');

        // Fallback: fetch user info from OpenID Connect UserInfo endpoint if email is missing.
        if ($email === '') {
            $accessToken = (string) ($tokenData['access_token'] ?? '');
            if ($accessToken === '') {
                $this->addFlash('error', 'Google login failed: email not available.');
                return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
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
            } catch (\Throwable $e) {
                $logger->error('Google userinfo request failed.', [
                    'exception_class' => $e::class,
                    'exception_message' => $e->getMessage(),
                ]);
                $this->addFlash('error', 'Could not load your Google profile. Try again.');
                return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
            }

            $email = (string) ($userinfo['email'] ?? '');
            $payload = array_merge($payload, $userinfo);
        }

        if ($email === '') {
            $this->addFlash('error', 'Google login failed: email not available.');
            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        $givenName = (string) ($payload['given_name'] ?? '');
        $familyName = (string) ($payload['family_name'] ?? '');
        $fullName = trim(($givenName . ' ' . $familyName) ?: $email);
        $emailCanonical = strtolower(trim($email));

        $staff = $staffRepository->findOneBy(['email' => $emailCanonical]);

        if (!$staff instanceof Staff) {
            $this->addFlash('error', 'No staff account is provisioned for this Google email. Please contact an admin.');

            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        if ($staff->getFullName() === null || $staff->getFullName() === '') {
            $staff->setFullName($fullName);
            // Token/email verification service will handle persistence where needed.
        }

        // Staff can only log in after admin has verified their email.
        if (!$staff->isVerified()) {
            // Only allow accounts that were provisioned by an admin (token exists).
            if (!$staff->getVerificationToken()) {
                $this->addFlash('error', 'This staff account is not provisioned by an admin yet.');

                return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
            }

            $verificationToken = $staff->getVerificationToken();
            $verificationUrl = $urlGenerator->generate(
                'staff_verify_email',
                ['token' => $verificationToken],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $staffVerificationService->sendVerificationEmail($staff, $verificationUrl);

            $this->addFlash('error', 'Please verify your email address before logging in. We sent you a verification link.');

            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        if (!$staff->isActive()) {
            $this->addFlash('error', 'This staff account is disabled.');

            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        if (!in_array('ROLE_STAFF', $staff->getRoles(), true)) {
            $this->addFlash('error', 'This account does not have staff access.');

            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        $userId = $staff->getId();
        if ($userId === null) {
            $this->addFlash('error', 'Google login failed: account state is invalid.');
            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        $staffForLogin = $staffRepository->find($userId);
        if (!$staffForLogin instanceof Staff) {
            $this->addFlash('error', 'Google login failed: could not load your account.');
            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        if (!$staffForLogin->isActive()) {
            $this->addFlash('error', 'This staff account is disabled.');
            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        try {
            // Third arg is firewall name.
            $loginResponse = $security->login($staffForLogin, null, 'main');
        } catch (\Throwable $e) {
            $message = $e instanceof CustomUserMessageAccountStatusException
                ? $e->getMessage()
                : 'Google account connected, but automatic login failed. Please log in manually.';

            $this->addFlash('error', $message);
            $logger->error('Google automatic staff login failed.', [
                'email' => $emailCanonical,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            return $this->finalizeOAuthRedirect($request, $this->redirectToRoute('adminls_login'));
        }

        $afterLogin = $loginResponse instanceof RedirectResponse
            ? $loginResponse
            : $this->redirectToRoute('post_login_redirect');

        return $this->finalizeOAuthRedirect($request, $afterLogin);
    }

    private function finalizeOAuthRedirect(Request $request, RedirectResponse $response): RedirectResponse
    {
        $response->headers->clearCookie(self::OAUTH_STATE_COOKIE, '/', null, $request->isSecure(), true, Cookie::SAMESITE_LAX);
        $response->headers->clearCookie(self::OAUTH_ACTION_COOKIE, '/', null, $request->isSecure(), true, Cookie::SAMESITE_LAX);

        return $response;
    }

    /**
     * Decode the payload part of a Google id_token (JWT) without verifying signature.
     * For production, verify signature against Google JWKS.
     *
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

