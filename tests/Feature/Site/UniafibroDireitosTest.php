<?php

declare(strict_types=1);

use App\Support\Uniafibro\Conteudo;
use App\Support\Uniafibro\Layout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * /direitos da Uniafibro (https://uniafibro.com.br/direitos): páginas geradas de Markdown.
 * Ver memory/requisitos/Site/RUNBOOK-uniafibro.md §6.
 *
 * Sem tenant e sem banco (sqlite-safe). Os testes apontam uma pasta de exemplo, para não
 * depender do texto real das páginas, que muda a cada revisão.
 */
beforeEach(function () {
    config(['uniafibro.pasta_conteudo' => base_path('tests/fixtures/uniafibro-direitos')]);
    config(['uniafibro.contatos' => [
        'wagner' => ['whatsapp' => '', 'email' => ''],
        'giselli' => ['whatsapp' => '', 'email' => ''],
    ]]);
});

function uniafibroDireitosRota(string $url): ?string
{
    try {
        return Route::getRoutes()->match(Request::create($url))->getName();
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
        return null;
    }
}

it('o índice /direitos abre sem login, com as duas seções, a busca e o aviso', function () {
    $this->assertGuest();

    $html = $this->get('http://uniafibro.com.br/direitos')->assertOk()->getContent();

    expect($html)->toStartWith('<!DOCTYPE html>');
    expect($html)->toContain('id="direitos"');
    expect($html)->toContain('id="guias"');
    expect($html)->toContain('href="/direitos/exemplo-direito"');
    expect($html)->toContain('href="/direitos/exemplo-guia"');
    expect($html)->toContain('role="search"');
    expect($html)->toContain('não é consultoria jurídica');
});

it('a página traz título, data de revisão, PDF, índice lateral, aviso e fontes', function () {
    $html = $this->get('http://uniafibro.com.br/direitos/exemplo-direito')->assertOk()->getContent();

    expect($html)->toContain('<h1>Exemplo de direito</h1>');
    expect($html)->toContain('Revisado em 15/11/2026');
    expect($html)->toContain('href="/direitos/exemplo-direito.pdf"');
    expect($html)->toContain('<h2 id="atendimento-no-sus">');
    expect($html)->toContain('<a href="#atendimento-no-sus">Atendimento no SUS</a>');
    expect($html)->toContain('não é consultoria jurídica');
    expect($html)->toContain('Fontes oficiais');
    expect($html)->toContain('https://www.planalto.gov.br/exemplo');
});

it('não carrega script nem rastreador, nem o que vier escrito como HTML no .md', function () {
    foreach (['/direitos', '/direitos/exemplo-direito', '/direitos?q=sus'] as $caminho) {
        $html = $this->get('http://uniafibro.com.br'.$caminho)->getContent();

        expect($html)->not->toContain('<script');
        expect($html)->not->toMatch('/googletagmanager|google-analytics|gtag\(|fbq\(/i');
    }
});

it('slug sem arquivo, ou com caractere fora do padrão, volta para /direitos', function () {
    $this->get('http://uniafibro.com.br/direitos/nao-existe')->assertRedirect('https://uniafibro.com.br/direitos');
    $this->get('http://uniafibro.com.br/direitos/nao-existe.pdf')->assertRedirect('https://uniafibro.com.br/direitos');
    // "_LEIA-ME" não casa o slug: cai no redirect geral do domínio.
    $this->get('http://uniafibro.com.br/direitos/_LEIA-ME')->assertRedirect('https://uniafibro.com.br');
});

it('o PDF da página é gerado no servidor', function () {
    $r = $this->get('http://uniafibro.com.br/direitos/exemplo-direito.pdf');

    $r->assertOk();
    expect((string) $r->headers->get('Content-Type'))->toContain('application/pdf');
    expect((string) $r->headers->get('Content-Disposition'))->toContain('uniafibro-exemplo-direito.pdf');
});

it('a busca acha palavra sem acento nem maiúscula e mostra o trecho', function () {
    $html = $this->get('http://uniafibro.com.br/direitos?q=CARTEIRINHA%20catarinense')->assertOk()->getContent();

    expect($html)->toContain('Resultado para');
    expect($html)->toContain('href="/direitos/exemplo-direito"');
    expect(Conteudo::buscar('limitacoes'))->toHaveCount(1);
    expect(Conteudo::buscar('palavra-que-nao-existe'))->toBe([]);
});

it('contatos vazios: nem botão do WhatsApp nem bloco de contato no rodapé', function () {
    $html = $this->get('http://uniafibro.com.br/direitos')->getContent();

    expect($html)->not->toContain('wa.me');
    expect($html)->not->toContain('mailto:');
    expect($html)->not->toContain('id="t-contato"');
});

it('contatos preenchidos aparecem sem o nome da pessoa e e-mail inválido é ignorado', function () {
    config(['uniafibro.contatos' => [
        'wagner' => ['whatsapp' => '', 'email' => 'contato@exemplo.org'],
        'giselli' => ['whatsapp' => '+55 (48) 99999-0000', 'email' => 'isto nao e email'],
    ]]);

    $html = $this->get('http://uniafibro.com.br/direitos')->getContent();

    expect(Layout::whatsapp())->toContain('href="https://wa.me/5548999990000"');
    expect($html)->toContain('id="t-contato"');
    expect($html)->toContain('href="mailto:contato@exemplo.org"');
    expect($html)->not->toContain('isto nao e email');
    // A chave do slot é só interna: o site não mostra nome de dirigente.
    expect($html)->not->toMatch('/wagner|giselli/i');
});

it('as rotas /direitos não capturam oimpresso.com', function () {
    expect(uniafibroDireitosRota('http://uniafibro.com.br/direitos'))->toBe('uniafibro.direitos');
    expect(uniafibroDireitosRota('http://uniafibro.com.br/direitos/exemplo-direito'))->toBe('uniafibro.direitos.pagina');
    expect(uniafibroDireitosRota('http://uniafibro.com.br/direitos/exemplo-direito.pdf'))->toBe('uniafibro.direitos.pdf');
    expect((string) uniafibroDireitosRota('https://oimpresso.com/direitos'))->not->toStartWith('uniafibro.');
    expect(uniafibroDireitosRota('https://oimpresso.com/privacidade'))->toBe('publico.privacidade');
});

it('a home mantém os 9 itens do menu, destaca Direitos e tem o botão grande no topo', function () {
    $home = (string) file_get_contents(resource_path('uniafibro/home.html'));
    $ini = strpos($home, '<nav class="nav"');
    $menu = substr($home, $ini, strpos($home, '</nav>', $ini) - $ini);

    preg_match_all('/<li>/', $menu, $m);
    expect(count($m[0]))->toBe(9);
    expect($menu)->toMatch('/<a class="destaque" href="\/direitos">.*Direitos<\/a>/');
    expect($home)->toMatch('/class="btn btn-claro btn-grande" href="\/direitos">.*Seus Direitos<\/a>/');
    // O botão vem antes do lema, no topo. Busca pelos ELEMENTOS do corpo: a classe também está no
    // CSS do <head> e o lema também está na meta description.
    $botao = strpos($home, '<p class="topo-direitos">');
    $lema = strpos($home, '<p class="lema">');
    expect($botao)->toBeInt();
    expect($lema)->toBeInt();
    expect($botao)->toBeLessThan($lema);
    // Portal da Associada só entra quando existir (onda 6 da ADR 0451). Olha só a lista de links:
    // o comentário HTML acima do menu cita o Portal de propósito.
    $links = substr($menu, strpos($menu, '<ul>'), strpos($menu, '</ul>') - strpos($menu, '<ul>'));
    expect($links)->not->toMatch('/portal/i');
});

it('a pasta real de conteúdo só tem arquivos com frontmatter completo', function () {
    config(['uniafibro.pasta_conteudo' => resource_path('uniafibro/direitos')]);

    foreach (Conteudo::todas() as $secao => $paginas) {
        foreach ($paginas as $p) {
            foreach (['title', 'resumo', 'secao', 'ordem', 'updated_at', 'disclaimer_version', 'fontes'] as $campo) {
                expect($p['meta'])->toHaveKey($campo);
            }
            expect($p['meta']['fontes'])->not->toBeEmpty();
            expect((int) $p['meta']['disclaimer_version'])->toBe(Layout::AVISO_VERSAO);
        }
    }
    expect(Conteudo::existe('_LEIA-ME'))->toBeFalse();
});
