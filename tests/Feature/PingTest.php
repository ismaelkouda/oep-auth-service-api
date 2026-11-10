<?php

declare(strict_types=1);

it('répond ok sur /v1/ping', function () {
    $this->getJson('/v1/ping')
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);
});

it('renvoie une erreur JSON sur une route /v1 inconnue', function () {
    $this->get('/v1/inconnu')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
