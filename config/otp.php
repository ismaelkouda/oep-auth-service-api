<?php

return [
    'driver' => env('OTP_DRIVER', 'none'),
    'length' => 6,
    'ttl_minutes' => 5,
    'max_attempts' => 5,
    'resend_seconds' => 60,
    'max_per_hour' => 3,
    'retention_hours' => 24,

];
