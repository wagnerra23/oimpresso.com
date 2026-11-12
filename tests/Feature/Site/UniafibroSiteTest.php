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

function uniafibroHtml(): string
{
    return (string) file_get_contents(resource_path('uniafibro/home.html'));
}

it('abre a página da associação em uniafibro.com.br, sem login', function () {
    $this->assertGuest();

    $r = $this->get('http://uniafibro.com.br/');

    $r->assertOk();
    expect((string) $r->headers->get('Content-Type'))->toContain('text/html');
    // A resposta É o arquivo estático (BinaryFileResponse — getContent() é vazio por design).
    expect($r->baseResponse->getFile()->getRealPath())->toBe(realpath(resource_path('uniafibro/home.html')));
});

it('a página traz nome, lema, app "em breve" e logo do próprio domínio', function () {
    $html = uniafibroHtml();

    expect($html)->toStartWith('<!DOCTYPE html>');
    expect($html)->toContain('União das Associações de Fibromialgia de SC');
    expect($html)->toContain('Juntas somos mais fortes.');
    expect($html)->toContain('Em breve na Google Play');
    // A imagem vem do próprio domínio (caminho relativo), não de oimpresso.com.
    expect($html)->toContain('src="/uniafibro/logo.png"');
});

it('não publica link da Play nem carrega script ou rastreador', function () {
    $html = uniafibroHtml();

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

it('direitos: cita as leis com número e não promete equiparação automática', function () {
    $html = uniafibroHtml();

    // Fontes conferidas no texto oficial (Planalto · Alesc/TJSC) em 2026-11-12.
    expect($html)->toContain('Lei 15.176/2025');
    expect($html)->toContain('Lei 14.705/2023');
    expect($html)->toContain('Lei estadual 18.928/2024');
    expect($html)->toContain('avaliação');
    expect($html)->toContain('biopsicossocial');
    expect($html)->toContain('não é automática');
    // Conteúdo de saúde e de direitos é informativo — o aviso não pode sumir.
    expect($html)->toContain('não substitui a consulta');
    expect($html)->toContain('não substitui orientação jurídica');
});
