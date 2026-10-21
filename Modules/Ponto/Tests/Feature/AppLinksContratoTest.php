<?php

declare(strict_types=1);

use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato dos arquivos de associação do app de ponto (Capacitor):
 * /.well-known/assetlinks.json (Android) e /.well-known/apple-app-site-association (iOS).
 *
 * Âncora: formato exigido pelo Digital Asset Links (Google) e pelo Universal Links (Apple).
 * Regra própria: sem valor válido configurado → 404, nunca um arquivo vazio ou inválido.
 */

const ALK_FP_A = 'AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99';
const ALK_FP_B = '11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00';

beforeEach(fn () => config()->set('pontowr2.app_links.outros_android', []));

it('sem configuração, os dois arquivos respondem 404', function () {
    config()->set('pontowr2.app_links.android_package', '');
    config()->set('pontowr2.app_links.android_sha256', '');
    config()->set('pontowr2.app_links.ios_app_id', '');

    $this->get('/.well-known/assetlinks.json')->assertNotFound();
    $this->get('/.well-known/apple-app-site-association')->assertNotFound();
});

it('assetlinks lista o pacote e TODOS os fingerprints válidos (upload + app signing)', function () {
    config()->set('pontowr2.app_links.android_package', 'com.oimpresso.ponto');
    config()->set('pontowr2.app_links.android_sha256', strtolower(ALK_FP_A) . ', ' . ALK_FP_B . ',lixo');

    $this->get('/.well-known/assetlinks.json')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target'   => [
                'namespace'                => 'android_app',
                'package_name'             => 'com.oimpresso.ponto',
                'sha256_cert_fingerprints' => [ALK_FP_A, ALK_FP_B],
            ],
        ]]);
});

it('fingerprint só inválido não publica assetlinks', function () {
    config()->set('pontowr2.app_links.android_package', 'com.oimpresso.ponto');
    config()->set('pontowr2.app_links.android_sha256', 'AA:BB');

    $this->get('/.well-known/assetlinks.json')->assertNotFound();
});

it('outros apps Android (ex. Fisio) entram no mesmo assetlinks, e app inválido fica de fora', function () {
    config()->set('pontowr2.app_links.android_package', '');
    config()->set('pontowr2.app_links.outros_android', [
        ['package' => 'com.oimpresso.fisio', 'sha256' => ALK_FP_A],
        ['package' => 'com.sem.fingerprint', 'sha256' => 'AA:BB'],
    ]);

    $this->get('/.well-known/assetlinks.json')
        ->assertOk()
        ->assertExactJson([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target'   => [
                'namespace'                => 'android_app',
                'package_name'             => 'com.oimpresso.fisio',
                'sha256_cert_fingerprints' => [ALK_FP_A],
            ],
        ]]);
});

it('o Fisio vem configurado por padrão com um fingerprint válido', function () {
    $fisio = collect((array) require base_path('Modules/Ponto/Config/config.php'))['app_links']['outros_android'][0];

    expect($fisio['package'])->toBe('com.oimpresso.fisio');
    $this->assertMatchesRegularExpression('/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/', $fisio['sha256']);
});

it('apple-app-site-association associa o app às rotas do ponto mobile, sem redirect', function () {
    config()->set('pontowr2.app_links.ios_app_id', 'ABCDE12345.com.oimpresso.ponto');

    $this->get('/.well-known/apple-app-site-association')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['applinks' => ['details' => [[
            'appIDs'     => ['ABCDE12345.com.oimpresso.ponto'],
            'components' => [['/' => '/ponto/mobile*']],
        ]]]]);
});
