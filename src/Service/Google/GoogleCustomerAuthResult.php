<?php

namespace App\Service\Google;

use App\Entity\Customer;

/**
 * Outcome of Google customer auth (shared by web OAuth callback and mobile API).
 */
final class GoogleCustomerAuthResult
{
    public const STATUS_JWT_READY = 'jwt_ready';
    public const STATUS_SIGNUP_PENDING = 'signup_pending';
    public const STATUS_UNVERIFIED_LOGIN = 'unverified_login';
    public const STATUS_UNVERIFIED_SIGNUP_RESENT = 'unverified_signup_resent';
    public const STATUS_NOT_REGISTERED = 'not_registered';

    private function __construct(
        public readonly string $status,
        public readonly ?Customer $customer = null,
        public readonly string $message = '',
    ) {
    }

    public static function jwtReady(Customer $customer): self
    {
        return new self(self::STATUS_JWT_READY, $customer);
    }

    public static function signupPending(string $message): self
    {
        return new self(self::STATUS_SIGNUP_PENDING, null, $message);
    }

    public static function unverifiedLogin(string $message): self
    {
        return new self(self::STATUS_UNVERIFIED_LOGIN, null, $message);
    }

    public static function unverifiedSignupResent(string $message): self
    {
        return new self(self::STATUS_UNVERIFIED_SIGNUP_RESENT, null, $message);
    }

    public static function notRegistered(string $message): self
    {
        return new self(self::STATUS_NOT_REGISTERED, null, $message);
    }
}
