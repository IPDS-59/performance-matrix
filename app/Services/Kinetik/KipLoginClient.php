<?php

namespace App\Services\Kinetik;

use Carbon\CarbonImmutable;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
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

            // Keycloak shows a form again (200): the one-time code form for accounts
            // with OTP, the login form when the credentials are wrong.
            if ($submit->status() === 200) {
                throw $this->hasOtpForm($submit->body())
                    ? KipLoginException::otpRequired($this->pending($submit->body(), $jar))
                    : KipLoginException::rejected();
            }

            return $this->finish($jar, $submit, $timeout);
        } catch (KipLoginException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw KipLoginException::unavailable($e::class);
        }
    }

    /**
     * Second step for accounts with OTP: sends the code to the form that login() stopped at.
     *
     * @param  array{action: string, cookies: array<int, array<string, mixed>>}  $pending
     *
     * @throws KipLoginException
     */
    public function loginWithOtp(array $pending, string $code): KipLoginResult
    {
        $jar = new CookieJar(false, $pending['cookies']);
        $timeout = (int) config('kinetik.kip.timeout', 15);

        try {
            $submit = Http::withOptions(['cookies' => $jar])->withoutRedirecting()->timeout($timeout)
                ->asForm()->post($pending['action'], ['otp' => $code, 'login' => 'Log In']);

            if ($submit->status() === 200) {
                throw $this->hasOtpForm($submit->body())
                    ? KipLoginException::otpInvalid($this->pending($submit->body(), $jar, $pending['action']))
                    : KipLoginException::unavailable('SSO answered the one-time code with an unknown form');
            }

            return $this->finish($jar, $submit, $timeout);
        } catch (KipLoginException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw KipLoginException::unavailable($e::class);
        }
    }

    private function hasOtpForm(string $html): bool
    {
        return (bool) preg_match('/<input[^>]*\bname="otp"/i', $html);
    }

    /** @return array{action: string, cookies: array<int, array<string, mixed>>} */
    private function pending(string $html, CookieJar $jar, ?string $fallbackAction = null): array
    {
        $action = preg_match('/<form[^>]*\baction="([^"]+)"/i', $html, $form) ? html_entity_decode($form[1]) : $fallbackAction;

        if ($action === null) {
            throw KipLoginException::unavailable('SSO one-time code form has no action');
        }

        return ['action' => $action, 'cookies' => $jar->toArray()];
    }

    /** Follows the SSO redirect into kipApp and reads the token from the callback. */
    private function finish(CookieJar $jar, Response $submit, int $timeout): KipLoginResult
    {
        try {
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
