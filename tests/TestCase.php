<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Inertia resolves page components through the Vite manifest. CI does not
        // build assets, so disable Vite in tests to keep Inertia GET assertions
        // independent of build/dev-server (public/hot) state.
        $this->withoutVite();

        // A test that forgets to fake an HTTP call must fail at once. On CI such a call waited for its timeout instead (5 minutes of a 7.5 minute run).
        Http::preventStrayRequests();
    }
}
