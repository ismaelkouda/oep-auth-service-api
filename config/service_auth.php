<?php

return [
    // Secrets acceptés pour l'introspection (séparés par des virgules, 32 caractères minimum).
    // Deux valeurs actives permettent une rotation sans coupure.
    'introspection_secrets' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('INTROSPECTION_SECRETS', ''))),
        fn (string $secret): bool => strlen($secret) >= 32,
    )),
];
