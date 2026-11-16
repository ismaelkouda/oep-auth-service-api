<?php

namespace App\Services;

use App\Models\AuthToken;

final readonly class IssuedAuthToken
{
    public function __construct(
        public string $plainText,
        public AuthToken $token,
    ) {}
}
