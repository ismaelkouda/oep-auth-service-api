<?php

return [
    // Adresses (ou plages CIDR) des proxies à approuver, séparées par des virgules :
    // la passerelle seulement. Vide = aucun proxy approuvé (les en-têtes X-Forwarded-* sont ignorés).
    // Ne jamais mettre « * » en production : n'importe quel client pourrait falsifier son adresse.
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
