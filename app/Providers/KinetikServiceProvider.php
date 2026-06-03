<?php

namespace App\Providers;

use App\Kinetik\Auth\ConfigBearerAuthenticator;
use App\Kinetik\Contracts\KipActivitySource;
use App\Kinetik\Contracts\KipAuthenticator;
use App\Kinetik\Sources\ApiKipActivitySource;
use App\Kinetik\Sources\MockKipActivitySource;
use Illuminate\Support\ServiceProvider;

class KinetikServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(KipAuthenticator::class, ConfigBearerAuthenticator::class);

        $this->app->bind(KipActivitySource::class, function () {
            if (config('kinetik.kip.source') === 'mock') {
                return $this->app->make(MockKipActivitySource::class);
            }

            return $this->app->make(ApiKipActivitySource::class);
        });
    }
}
