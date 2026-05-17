<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Rotas dummy pra confirmar que /admin / / não disparam 301 quando
    // alcançados pelo path canônico (auth pode redirect 302, mas nunca 301).
    Route::get('/admin', fn () => 'admin')->name('test.admin');
    Route::get('/admin/sub', fn () => 'admin sub')->name('test.admin.sub');
    Route::get('/', fn () => 'root')->name('test.root');
    Route::get('/publicly-traded-stuff', fn () => 'unrelated')->name('test.publicly');
});

it('redireciona /public/admin pra /admin com 301', function () {
    $response = $this->get('/public/admin');

    expect($response->status())->toBe(301);
    expect($response->headers->get('Location'))->toEndWith('/admin');
});

it('redireciona /public/admin/sub pra /admin/sub com 301', function () {
    $response = $this->get('/public/admin/sub');

    expect($response->status())->toBe(301);
    expect($response->headers->get('Location'))->toEndWith('/admin/sub');
});

it('redireciona /public bare pra / com 301', function () {
    $response = $this->get('/public');

    expect($response->status())->toBe(301);
    // Laravel pode emitir URL absoluta com ou sem trailing slash — aceita ambos
    expect($response->headers->get('Location'))->toMatch('#^https?://[^/]+/?$#');
});

it('preserva query string no redirect (Symfony pode normalizar ordem)', function () {
    $response = $this->get('/public/admin?foo=bar&baz=qux');

    expect($response->status())->toBe(301);
    $location = $response->headers->get('Location');
    expect($location)->toContain('/admin?');
    expect($location)->toContain('foo=bar');
    expect($location)->toContain('baz=qux');
});

it('não dispara em paths que começam com "publicly" (palavra diferente)', function () {
    $response = $this->get('/publicly-traded-stuff');

    expect($response->status())->not->toBe(301);
    expect($response->status())->toBe(200);
});

it('não dispara em path canônico /admin', function () {
    $response = $this->get('/admin');

    expect($response->status())->not->toBe(301);
    expect($response->status())->toBe(200);
});

it('não dispara em path canônico /', function () {
    $response = $this->get('/');

    expect($response->status())->not->toBe(301);
    expect($response->status())->toBe(200);
});
