<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;

// The BPS webhosting answers HTTPS at its load balancer and passes plain HTTP to PHP, and the load balancer marks every
// cookie HttpOnly. Kinetik must still write https links and send its CSRF token without reading the XSRF-TOKEN cookie.

afterEach(fn () => URL::forceScheme(null));

it('writes https links when APP_URL is https, even when the request reached PHP as http', function () {
    config(['app.url' => 'https://kinetik.web.bps.go.id']);
    AppServiceProvider::forceHttpsFromAppUrl();

    expect(route('login'))->toStartWith('https://');
});

it('leaves the scheme alone when APP_URL is http', function () {
    config(['app.url' => 'http://localhost']);
    AppServiceProvider::forceHttpsFromAppUrl();

    expect(route('login'))->toStartWith('http://');
});

it('gives every page its CSRF token as a prop and a meta tag, so no script has to read the cookie', function () {
    $response = $this->actingAs(staffUser())->get(route('profile.edit'));

    $token = session()->token();
    $response->assertInertia(fn ($page) => $page->where('csrf_token', $token));
    expect($response->getContent())->toContain('<meta name="csrf-token" content="'.$token.'">');
});
