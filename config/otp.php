<?php

return [
    'driver' => env('OTP_DRIVER', 'none'),
    'length' => 6,
    'ttl_minutes' => 5,
    'max_attempts' => 5,
    'resend_seconds' => 60,
    'max_per_hour' => 3,

    // Heures de conservation après EXPIRATION d'un code. Le plafond global se calcule depuis la
    // création (24 h glissantes) : un code vit 5 min puis reste 24 h, la fenêtre est donc couverte.
    'retention_hours' => 24,

    // Anti « SMS pumping » : seuls ces préfixes reçoivent un SMS (liste vide = aucun envoi).
    'allowed_prefixes' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('OTP_ALLOWED_PREFIXES', '+225'))),
        fn (string $prefix): bool => $prefix !== '',
    )),

    // Nombre maximal de codes créés sur 24 h glissantes, tous numéros confondus.
    'daily_cap' => (int) env('OTP_DAILY_CAP', 2000),
];
