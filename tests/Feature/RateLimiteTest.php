<?php

declare(strict_types=1);

it('bloque avec un 429 au-delà de 30 requêtes par minute sur /v1/ping', function () {
    foreach (range(1, 30) as $tentative) {
        $this->getJson('/v1/ping')->assertOk();
    }

    $this->getJson('/v1/ping')->assertTooManyRequests();
});
