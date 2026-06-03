<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Inertia resolves page components through the Vite manifest. CI does not
        // build assets, so disable Vite in tests to keep Inertia GET assertions
        // independent of build/dev-server (public/hot) state.
        $this->withoutVite();
    }
}
