<?php

namespace App\Services\Kinetik;

use Carbon\CarbonImmutable;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Logs in to kipApp through BPS SSO (Keycloak) with plain HTTP, no browser.
 * Flow verified on 2026-10-07: see docs/kinetik/spec-planning.md section 6.
 */
class KipLoginClient
{
    /**
     * @throws KipLoginException
     */
    public function login(string $username, string $password): KipLoginResult
    {
        $jar = new CookieJar;
        $base = rtrim((string) config('kinetik.kip.base_url'), '/');
        $timeout = (int) config('kinetik.kip.timeout', 15);

        try {
            $page = Http::withOptions(['cookies' => $jar])->timeout($timeout)->get($base.'/login');

            if (! preg_match('/<form[^>]*\baction="([^"]+)"/i', $page->body(), $form)) {
                throw KipLoginException::unavailable('SSO login form not found');
            }

            $submit = Http::withOptions(['cookies' => $jar])->withoutRedirecting()->timeout($timeout)
                ->asForm()->post(html_entity_decode($form[1]), [
                    'username' => $username,
                    'password' => $password,
                    'credentialId' => '',
                ]);

            // Keycloak shows the form again (200) when the credentials are wrong.
            if ($submit->status() === 200) {
                throw KipLoginException::rejected();
            }
            if (! $submit->redirect() || $submit->header('Location') === '') {
                throw KipLoginException::unavailable('SSO answered '.$submit->status());
            }

            $callback = Http::withOptions(['cookies' => $jar])->withoutRedirecting()->timeout($timeout)
                ->get($submit->header('Location'));
        } catch (KipLoginException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw KipLoginException::unavailable($e::class);
        }

        if (! preg_match('/[#?&]t=([\w-]+\.[\w-]+\.[\w-]+)/', $callback->header('Location'), $m)) {
            throw KipLoginException::unavailable('kipApp callback gave no token');
        }

        $claims = json_decode((string) base64_decode(strtr(explode('.', $m[1])[1], '-_', '+/')), true) ?: [];

        return new KipLoginResult(
            $m[1],
            isset($claims['exp']) ? CarbonImmutable::createFromTimestamp((int) $claims['exp']) : now()->addHours(24)->toImmutable(),
        );
    }
}
