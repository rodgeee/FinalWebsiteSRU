<?php

namespace App\Service\Google;

final readonly class GoogleProfile
{
    public function __construct(
        public string $email,
        public string $fullName,
        public string $givenName,
        public string $familyName,
    ) {
    }
}
