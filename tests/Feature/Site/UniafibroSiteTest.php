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
    expect($html)->toContain('União das Associações de Fibromiálgicos de Santa Catarina');
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
    $this->get('http://www.uniafibro.com.br/')->assertRedirect('https://uniafibro.com.br')->assertStatus(301);
    $this->get('http://www.uniafibro.com.br/qualquer')->assertRedirect('https://uniafibro.com.br');
});

it('outro caminho no domínio da associação volta ao início em vez de abrir o ERP', function () {
    $this->get('http://uniafibro.com.br/login')->assertRedirect('https://uniafibro.com.br');
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

it('quem somos destaca a fundadora Giselli Cunha e a história desde 2019', function () {
    $html = uniafibroHtml();

    expect($html)->toContain('Quem começou tudo');
    expect($html)->toContain('Giselli Cunha');
    expect($html)->toContain('Nossa história');
    expect($html)->toContain('AMASF');
});

it('vozes: só falas publicadas, cada uma com o link da matéria', function () {
    $html = uniafibroHtml();

    expect($html)->toContain('Vozes da fibromialgia');
    // Cada depoimento é uma <figure class="voz"> com link para a fonte pública.
    preg_match_all('/<figure class="voz[^"]*">(.*?)<\/figure>/s', $html, $m);
    expect(count($m[1]))->toBeGreaterThanOrEqual(3);
    foreach ($m[1] as $fig) {
        expect($fig)->toMatch('/href="https:\/\/[^"]+"/');
    }
});

it('usa a logomarca oficial no topo e o símbolo no ícone da aba', function () {
    $html = uniafibroHtml();

    expect($html)->toContain('src="/uniafibro/logomarca.png"');
    expect($html)->toContain('href="/uniafibro/favicon.png"');
    expect(is_file(public_path('uniafibro/logomarca.png')))->toBeTrue();
    expect(is_file(public_path('uniafibro/favicon.png')))->toBeTrue();
});

it('conquistas: cada marco da luta pelas leis aponta o documento oficial', function () {
    $html = uniafibroHtml();

    expect($html)->toContain('Conquistas: a luta pelas leis');
    expect($html)->toContain('Lei 8.394/2023');
    $ini = strpos($html, 'id="conquistas"');
    $fim = strpos($html, '</section>', $ini);
    preg_match_all('/<li[^>]*><span class="ano">.*?<\/li>/s', substr($html, $ini, $fim - $ini), $m);
    expect(count($m[0]))->toBeGreaterThanOrEqual(5);
    foreach ($m[0] as $marco) {
        expect($marco)->toContain('class="fonte"');
    }
});

it('cuidado em equipe: lista as seis áreas e diz que tem tratamento mas não tem cura', function () {
    $html = uniafibroHtml();

    expect($html)->toContain('id="cuidado"');
    foreach (['Médico', 'Fisioterapia', 'Educação física', 'Psicologia', 'Nutrição', 'Assistência social'] as $area) {
        expect($html)->toContain('<h3>'.$area.'</h3>');
    }
    expect($html)->toContain('tem tratamento, mas não tem cura');
});

it('a rede: associação mantém nome, diretoria e CNPJ e sabe como entrar', function () {
    $html = uniafibroHtml();

    expect($html)->toContain('id="rede"');
    expect($html)->toContain('continua com seu nome, sua diretoria e seu CNPJ');
    expect($html)->toContain('Lei estadual 18.162/2021');
    expect($html)->toContain('Sua associação quer entrar na rede?');
});

it('app: descreve o portal (check-in, FIQR, link + PIN) sem prometer teleconsulta', function () {
    $html = uniafibroHtml();
    $ini = strpos($html, 'id="app"');
    $secao = substr($html, $ini, strpos($html, '</section>', $ini) - $ini);

    expect($secao)->toContain('check-in');
    expect($secao)->toContain('FIQR');
    expect($secao)->toContain('PIN');
    expect($secao)->not->toMatch('/teleconsulta|videochamada|consulta por vídeo/i');
});

it('perguntas frequentes e o aviso de que o site não substitui orientação médica', function () {
    $html = uniafibroHtml();

    expect($html)->toContain('id="duvidas"');
    preg_match_all('/<details>/', $html, $m);
    expect(count($m[0]))->toBeGreaterThanOrEqual(5);
    expect($html)->toContain('Este site não substitui orientação médica.');
});

it('SEO: description e Open Graph próprios', function () {
    $html = uniafibroHtml();

    expect($html)->toMatch('/<meta name="description" content="[^"]{80,}">/');
    expect($html)->toContain('<meta property="og:title"');
    expect($html)->toContain('<meta property="og:description"');
    expect($html)->toContain('<link rel="canonical" href="https://uniafibro.com.br/">');
});

it('atendimento: começa pelo posto de saúde e lista os serviços de referência com fonte', function () {
    $html = uniafibroHtml();

    expect($html)->toContain('Onde buscar atendimento em SC');
    expect($html)->toContain('Unidade Básica de Saúde');
    expect($html)->toContain('<table class="servicos">');
    expect($html)->toContain('saude.sc.gov.br');
});
