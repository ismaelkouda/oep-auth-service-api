<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Log;

/**
 * Garde-fou de coût : préfixes autorisés et plafond global d'envois sur 24 h.
 */
final class OtpGate
{
    public function allows(string $phone): bool
    {
        return $this->hasAllowedPrefix($phone) && ! $this->dailyCapReached();
    }

    private function hasAllowedPrefix(string $phone): bool
    {
        $prefixes = config('otp.allowed_prefixes');

        if (! is_array($prefixes)) {
            return false;
        }

        foreach ($prefixes as $prefix) {
            if (is_string($prefix) && $prefix !== '' && str_starts_with($phone, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function dailyCapReached(): bool
    {
        $cap = (int) config('otp.daily_cap');

        $sent = OtpCode::where('created_at', '>', now()->subDay())->count();

        if ($sent < $cap) {
            return false;
        }

        Log::error('Plafond d\'envois OTP sur 24 h atteint : plus aucun SMS n\'est envoyé.');

        return true;
    }
}
