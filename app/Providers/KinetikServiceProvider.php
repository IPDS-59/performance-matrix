<?php

namespace App\Providers;

use App\Kinetik\Auth\ConfigBearerAuthenticator;
use App\Kinetik\Contracts\KipActivitySource;
use App\Kinetik\Contracts\KipAuthenticator;
use App\Kinetik\Contracts\KipStructureSource;
use App\Kinetik\Contracts\SkpApproval;
use App\Kinetik\Contracts\SkpFlow;
use App\Kinetik\Sources\ApiKipActivitySource;
use App\Kinetik\Sources\ApiKipStructureSource;
use App\Kinetik\Sources\MockKipActivitySource;
use App\Kinetik\Sources\MockKipStructureSource;
use App\Services\Kinetik\ApiSkpApproval;
use App\Services\Kinetik\ApiSkpFlow;
use App\Services\Kinetik\MockSkpApproval;
use App\Services\Kinetik\MockSkpFlow;
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

        $this->app->bind(SkpFlow::class, fn () => config('kinetik.kip.source') === 'mock'
            ? $this->app->make(MockSkpFlow::class)
            : $this->app->make(ApiSkpFlow::class));

        $this->app->bind(SkpApproval::class, fn () => config('kinetik.kip.source') === 'mock'
            ? $this->app->make(MockSkpApproval::class)
            : $this->app->make(ApiSkpApproval::class));

        $this->app->bind(KipStructureSource::class, function () {
            if (config('kinetik.kip.source') === 'mock') {
                return $this->app->make(MockKipStructureSource::class);
            }

            return $this->app->make(ApiKipStructureSource::class);
        });
    }
}
