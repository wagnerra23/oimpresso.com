<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Página inicial da Uniafibro (https://uniafibro.com.br), servida pelo app do oimpresso.
 * Ver memory/requisitos/Site/RUNBOOK-uniafibro.md.
 *
 * Sem tenant e sem banco: a página é só texto e imagem (sqlite-safe).
 */
function uniafibroRotaPara(string $url): ?string
{
    return Route::getRoutes()->match(Request::create($url))->getName();
}

it('abre a página da associação em uniafibro.com.br, sem login', function () {
    $this->assertGuest();

    $r = $this->get('http://uniafibro.com.br/');

    $r->assertOk();
    $r->assertSee('Uniafibro', false);
    $r->assertSee('União das Associações de Fibromialgia de SC', false);
    $r->assertSee('Juntas somos mais fortes.', false);
    $r->assertSee('Em breve na Google Play', false);
    // A imagem vem do próprio domínio (caminho relativo), não de oimpresso.com.
    $r->assertSee('src="/uniafibro/logo.png"', false);
});

it('não publica link da Play nem carrega script ou rastreador', function () {
    $html = (string) $this->get('http://uniafibro.com.br/')->getContent();

    expect($html)->not->toContain('play.google.com');
    expect($html)->not->toContain('<script');
    expect($html)->not->toMatch('/googletagmanager|google-analytics|gtag\(|fbq\(/i');
});

it('www.uniafibro.com.br redireciona (301) para o domínio principal', function () {
    $this->get('http://www.uniafibro.com.br/')->assertRedirect('https://uniafibro.com.br/')->assertStatus(301);
    $this->get('http://www.uniafibro.com.br/qualquer')->assertRedirect('https://uniafibro.com.br/');
});

it('outro caminho no domínio da associação volta ao início em vez de abrir o ERP', function () {
    $this->get('http://uniafibro.com.br/login')->assertRedirect('https://uniafibro.com.br/');
});

it('as rotas da Uniafibro não capturam oimpresso.com', function () {
    expect(uniafibroRotaPara('http://uniafibro.com.br/'))->toBe('uniafibro.home');
    expect((string) uniafibroRotaPara('https://oimpresso.com/'))->not->toStartWith('uniafibro.');
    expect((string) uniafibroRotaPara('https://oimpresso.com/login'))->not->toStartWith('uniafibro.');
    expect(uniafibroRotaPara('https://oimpresso.com/privacidade'))->toBe('publico.privacidade');
});

it('o logo existe no public/', function () {
    expect(is_file(public_path('uniafibro/logo.png')))->toBeTrue();
});
