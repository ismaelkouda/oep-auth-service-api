<?php

namespace App\Services;

use App\Contracts\OtpSender;
use App\Enums\OtpRequestResult;
use App\Models\OtpCode;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class OtpService
{
    public function __construct(private readonly OtpSender $sender) {}

    public function request(string $phone): OtpRequestResult
    {
        if (! $this->isValidPhone($phone)) {
            throw new InvalidArgumentException('Le numéro doit être au format international (E.164).');
        }

        $latest = OtpCode::where('phone', $phone)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($latest !== null && $latest->created_at->gt(now()->subSeconds($this->setting('resend_seconds')))) {
            return OtpRequestResult::TooSoon;
        }

        $sentLastHour = OtpCode::where('phone', $phone)
            ->where('created_at', '>', now()->subHour())
            ->count();

        if ($sentLastHour >= $this->setting('max_per_hour')) {
            return OtpRequestResult::LimitReached;
        }

        OtpCode::where('phone', $phone)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = $this->generateCode();

        $otp = new OtpCode;
        $otp->forceFill([
            'phone' => $phone,
            'code_hash' => $this->hash($phone, $code),
            'expires_at' => now()->addMinutes($this->setting('ttl_minutes')),
        ])->save();

        try {
            $this->sender->send($phone, $code);
        } catch (Throwable $e) {
            $otp->delete();

            throw $e;
        }

        return OtpRequestResult::Sent;
    }

    public function verify(string $phone, string $code): bool
    {
        if (! $this->isValidPhone($phone) || ! preg_match('/^\d{'.$this->setting('length').'}$/', $code)) {
            return false;
        }

        $otp = OtpCode::where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($otp === null) {
            return false;
        }

        $counted = OtpCode::whereKey($otp->id)
            ->where('attempts', '<', $this->setting('max_attempts'))
            ->increment('attempts');

        if ($counted === 0) {
            return false;
        }

        if (! hash_equals($otp->code_hash, $this->hash($phone, $code))) {
            return false;
        }

        return OtpCode::whereKey($otp->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]) === 1;
    }

    private function generateCode(): string
    {
        $length = $this->setting('length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    private function hash(string $phone, string $code): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('APP_KEY absente : impossible de hacher un code OTP.');
        }

        return hash_hmac('sha256', $phone.'|'.$code, $key);
    }

    private function isValidPhone(string $phone): bool
    {
        return preg_match('/^\+[1-9]\d{6,14}$/', $phone) === 1;
    }

    private function setting(string $key): int
    {
        return (int) config('otp.'.$key);
    }
}
