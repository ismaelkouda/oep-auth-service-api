<?php

return [
    'invitation' => [
        'driver' => env('INVITATION_DRIVER', 'none'),
        'lifetime_hours' => 72,
        'retention_days' => 30,
        // Page du backoffice où l'agent définit son mot de passe (le jeton est ajouté en paramètre).
        'url' => env('STAFF_PASSWORD_URL', ''),
    ],
];
