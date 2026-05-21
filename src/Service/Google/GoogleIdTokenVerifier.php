<?php

namespace App\Service\Google;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Verifies Google ID tokens via Google's tokeninfo endpoint (signature validated by Google).
 */
final class GoogleIdTokenVerifier
{
    private const TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo';
    private const VALID_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    /**
     * @param list<string> $allowedAudiences OAuth client IDs (web, Android, iOS)
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly array $allowedAudiences,
    ) {
    }

    /**
     * @throws \InvalidArgumentException when the token is invalid or untrusted
     */
    public function verify(string $idToken): GoogleProfile
    {
        $idToken = trim($idToken);
        if ($idToken === '') {
            throw new \InvalidArgumentException('Google idToken is required.');
        }

        try {
            $response = $this->httpClient->request('GET', self::TOKENINFO_URL, [
                'query' => ['id_token' => $idToken],
                'timeout' => 15,
                'max_duration' => 20,
            ]);
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Google tokeninfo request failed.', [
                'exception_message' => $e->getMessage(),
            ]);
            throw new \InvalidArgumentException('Could not verify Google sign-in. Check your network and try again.');
        } catch (\Throwable $e) {
            $this->logger->error('Google tokeninfo response parse failed.', [
                'exception_message' => $e->getMessage(),
            ]);
            throw new \InvalidArgumentException('Google sign-in verification failed.');
        }

        if ($response->getStatusCode() >= 400) {
            throw new \InvalidArgumentException('Invalid or expired Google sign-in. Please try again.');
        }

        $aud = (string) ($payload['aud'] ?? '');
        $allowed = [];
        foreach ($this->allowedAudiences as $clientId) {
            if (!\is_string($clientId)) {
                continue;
            }
            $clientId = trim($clientId);
            if ($clientId !== '') {
                $allowed[] = $clientId;
            }
        }
        if ($allowed === [] || !\in_array($aud, $allowed, true)) {
            throw new \InvalidArgumentException('Google sign-in is not configured for this application.');
        }

        $iss = (string) ($payload['iss'] ?? '');
        if (!\in_array($iss, self::VALID_ISSUERS, true)) {
            throw new \InvalidArgumentException('Untrusted Google token issuer.');
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp > 0 && $exp < time()) {
            throw new \InvalidArgumentException('Google sign-in expired. Please try again.');
        }

        $email = (string) ($payload['email'] ?? '');
        if ($email === '') {
            throw new \InvalidArgumentException('Google account email is not available.');
        }

        $emailVerified = $payload['email_verified'] ?? null;
        if ($emailVerified === 'false' || $emailVerified === false) {
            throw new \InvalidArgumentException('Please verify your Google account email before continuing.');
        }

        $givenName = (string) ($payload['given_name'] ?? '');
        $familyName = (string) ($payload['family_name'] ?? '');
        $fullName = trim(($givenName . ' ' . $familyName) ?: $email);

        return new GoogleProfile($email, $fullName, $givenName, $familyName);
    }
}
