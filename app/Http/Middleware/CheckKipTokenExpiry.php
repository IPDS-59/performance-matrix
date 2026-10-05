<?php

namespace App\Http\Middleware;

use App\Actions\Kinetik\AlertKipTokenExpiryAction;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The server runs no cron, so the token check rides on normal app use: at
 * most once every 10 minutes, after the response is sent.
 */
class CheckKipTokenExpiry
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->user() === null || ! Cache::add('kip-token-check', true, now()->addMinutes(10))) {
            return;
        }

        try {
            app(AlertKipTokenExpiryAction::class)->execute();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
