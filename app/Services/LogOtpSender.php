<?php

namespace App\Services;

use App\Contracts\OtpSender;
use Illuminate\Support\Facades\Log;

final class LogOtpSender implements OtpSender
{
    public function send(string $phone, string $code): void
    {
        Log::info("OTP pour {$phone} : {$code}");
    }
}
