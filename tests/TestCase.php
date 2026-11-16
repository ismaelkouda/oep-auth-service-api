<?php

namespace Tests;

use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakeUncompromisedVerifier;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Aucun appel réseau vers HIBP pendant les tests.
        $this->app->instance(UncompromisedVerifier::class, new FakeUncompromisedVerifier);
    }
}
