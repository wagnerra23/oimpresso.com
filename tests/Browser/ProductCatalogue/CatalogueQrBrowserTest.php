<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — comportamento + acessibilidade da tela
 * `resources/js/Pages/ProductCatalogue/CatalogueQr.tsx` (ProductCatalogue/CatalogueQr).
 *
 * O contrato do servidor está em tests/Feature/Sells/CatalogueQrContratoTest.php. O que
 * nenhuma suíte Feature alcança é o QR de verdade: ele nasce no navegador, pelo
 * easy.qrcode do módulo. Auditoria do módulo, 2026-11-09: E2E ✗ e axe 0.
 *
 * Tenant 98 (ADR 0358): business e admin vêm do VisregEmptyTenantSeeder. O local 98 existe
 * e a assinatura é criada aqui, porque o tenant não tem nenhuma e a tela exige
 * `productcatalogue_module`. Tudo sai no afterEach de arquivo.
 *
 * Sem comparação de pixel (ADR 0445). Lane: step "E2E de comportamento · Catálogo QR" do
 * visual-regression.yml. Pest Browser é CI-only (o container do CT 100 não tem chromium).
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

const CQR_BIZ = 98;
const CQR_ADMIN = 98;
const CQR_LOCAL = 'Matriz-Vazia';
const CQR_ASSINATURA = 900098;
const CQR_ROTA = '/product-catalogue/catalogue-qr';

const CQR_JS_QRS = "document.querySelectorAll('[data-contract=\"saida\"] canvas').length";
const CQR_JS_LINK = "(document.querySelector('[data-contract=\"saida\"] code')?.textContent || '').trim()";
const CQR_JS_GERAR_DESLIGADO = "Array.from(document.querySelectorAll('button')).find((b) => b.textContent.includes('Gerar QR code')).disabled";

beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    DB::purge('mysql');

    // Falha alto: tenant ausente não pode virar verde por pulo.
    if (! DB::table('users')->where('id', CQR_ADMIN)->where('business_id', CQR_BIZ)->exists()) {
        throw new RuntimeException('Admin do tenant 98 ausente: o VisregEmptyTenantSeeder não rodou.');
    }

    // public/modules é gerado por module:publish e não está no git; sem o arquivo o gerador não carrega.
    File::ensureDirectoryExists(public_path('modules/productcatalogue/plugins'));
    File::copy(
        base_path('Modules/ProductCatalogue/Resources/assets/plugins/easy.qrcode.min.js'),
        public_path('modules/productcatalogue/plugins/easy.qrcode.min.js'),
    );

    DB::table('subscriptions')->where('id', CQR_ASSINATURA)->delete();
    DB::table('subscriptions')->insert([
        'id' => CQR_ASSINATURA,
        'business_id' => CQR_BIZ,
        'package_id' => 900001, // pacote do VisregTenantSeeder; o que vale aqui é o package_details
        'start_date' => '2026-01-01',
        'end_date' => '2099-12-31',
        'package_price' => 0,
        'package_details' => json_encode(['name' => 'Fixture Catálogo QR', 'productcatalogue_module' => '1']),
        'created_id' => CQR_ADMIN,
        'status' => 'approved',
    ]);
});

afterEach(function () {
    DB::table('subscriptions')->where('id', CQR_ASSINATURA)->delete();
    DB::table('business_locations')->where('business_id', CQR_BIZ)->update(['is_active' => 1]);
    DB::table('business')->where('id', CQR_BIZ)->update(['logo' => null]);
});

/** Abre a tela autenticado e espera o React montar a seção esperada. */
function cqrAbrirTela(string $secao = 'formulario')
{
    $page = visit('/_visreg-login/'.CQR_ADMIN.'?to='.urlencode(CQR_ROTA))->resize(1280, 800);

    $sinal = "!!document.querySelector('[data-contract=\"{$secao}\"]')";
    for ($i = 0; $i < 40; $i++) {
        if ($page->script($sinal) === true) {
            return $page;
        }
        $page->wait(0.25);
    }

    throw new RuntimeException("Seção {$secao} não apareceu em ".CQR_ROTA.'.');
}

function cqrEscolherLocal($page): void
{
    $page->click('#cqr-local');
    $page->click('[role="option"]');
}

/** Clica em gerar e espera o canvas: é assim que se sabe que o QR nasceu. */
function cqrGerar($page): void
{
    $page->click('Gerar QR code');
    for ($i = 0; $i < 40; $i++) {
        if ((int) $page->script(CQR_JS_QRS) > 0) {
            return;
        }
        $page->wait(0.25);
    }

    throw new RuntimeException('O QR não foi desenhado depois do clique em gerar.');
}

function cqrEsperarSemQr($page): void
{
    for ($i = 0; $i < 20; $i++) {
        if ((int) $page->script(CQR_JS_QRS) === 0) {
            return;
        }
        $page->wait(0.1);
    }

    throw new RuntimeException('O QR antigo continuou na tela depois da mudança.');
}

it('UC-CQR-13 · sem local escolhido, gerar fica bloqueado e a tela diz por quê', function () {
    $page = cqrAbrirTela();

    $page->assertSee('Escolha o local comercial primeiro.');
    expect($page->script(CQR_JS_GERAR_DESLIGADO))->toBeTrue();
    expect((int) $page->script(CQR_JS_QRS))->toBe(0);

    cqrEscolherLocal($page);

    $page->assertDontSee('Escolha o local comercial primeiro.');
    expect($page->script(CQR_JS_GERAR_DESLIGADO))->toBeFalse();
});

it('UC-CQR-14 · o QR só nasce do clique, e o link leva o local escolhido', function () {
    $page = cqrAbrirTela();
    $page->assertSee('Nenhum QR gerado ainda.');
    cqrEscolherLocal($page);

    // Escolher o local não gera nada: é o anti-hook do charter.
    $page->wait(0.5);
    expect((int) $page->script(CQR_JS_QRS))->toBe(0);

    cqrGerar($page);

    expect((string) $page->script(CQR_JS_LINK))->toEndWith('/catalogue/'.CQR_BIZ.'/'.CQR_BIZ);
    $page->assertSee('O catálogo é público');
    $page->assertDontSee('Nenhum QR gerado ainda.');
});

it('UC-CQR-15 · trocar título, subtítulo ou cor limpa o QR até gerar de novo', function () {
    $page = cqrAbrirTela();
    cqrEscolherLocal($page);

    cqrGerar($page);
    $page->fill('#cqr-titulo', 'Outro título');
    cqrEsperarSemQr($page);
    $page->assertSee('Nenhum QR gerado ainda.');

    cqrGerar($page);
    $page->fill('#cqr-subtitulo', 'Outro subtítulo');
    cqrEsperarSemQr($page);

    cqrGerar($page);
    $page->click('[aria-label="Cor rgb(35, 94, 169)"]');
    cqrEsperarSemQr($page);
    $page->assertSee('Nenhum QR gerado ainda.');
});

it('UC-CQR-12 · copiar leva para a área de transferência o mesmo link do QR', function () {
    $page = cqrAbrirTela();
    cqrEscolherLocal($page);
    cqrGerar($page);

    // Área de transferência falsa: o Chromium headless pode recusar a real, e o que se prova
    // aqui é qual texto a tela entrega. A recusa está em tests/js/catalogue-qr-copiar.test.tsx.
    $page->script("Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: (t) => { window.__cqrCopiado = t; return Promise.resolve(); } } })");

    $page->click('Copiar link');
    $page->assertSee('Link copiado');

    expect((string) $page->script('window.__cqrCopiado'))->toBe((string) $page->script(CQR_JS_LINK));
});

it('UC-CQR-16 · sem local comercial, o vazio leva para Locais comerciais', function () {
    DB::table('business_locations')->where('business_id', CQR_BIZ)->update(['is_active' => 0]);

    $page = cqrAbrirTela('cabecalho');

    $page->assertSee('Nenhum local comercial cadastrado.');
    expect($page->script("!!document.querySelector('[data-contract=\"formulario\"]')"))->toBeFalse();
    expect($page->script("!!document.querySelector('a[href=\"/business-location\"]')"))->toBeTrue();
    $page->assertNoAccessibilityIssues(level: 1);
});

it('UC-CQR-17 · o logo só liga quando o negócio tem logo', function () {
    $page = cqrAbrirTela();
    $page->assertSee('Sem logo cadastrado no negócio: o QR sai limpo.');
    expect($page->script("document.querySelector('#cqr-logo').disabled"))->toBeTrue();

    DB::table('business')->where('id', CQR_BIZ)->update(['logo' => 'fixture-cqr.png']);

    $page = cqrAbrirTela();
    $page->assertSee('O logo entra no meio do QR.');
    expect($page->script("document.querySelector('#cqr-logo').disabled"))->toBeFalse();
    expect($page->script("document.querySelector('#cqr-logo').getAttribute('data-state')"))->toBe('checked');
});

it('Catálogo QR · cabe em 1280 px e passa no axe, antes e depois de gerar', function () {
    $page = cqrAbrirTela();

    expect((int) $page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(0);
    $page->assertNoAccessibilityIssues(level: 1);

    cqrEscolherLocal($page);
    cqrGerar($page);

    // O ponteiro ficou no "Gerar QR code". Sem tirar, o axe mede o `hover:bg-primary/90` do Button
    // do DS (contraste 4,02, medido no CI em 2026-11-10), que é de todas as telas e não desta.
    // O alvo é o título da seção: `hover('h1')` estourou 5 s no CI (run 42831537474).
    $page->hover('[data-contract="formulario"] h2');
    $page->wait(0.3);

    $page->assertNoAccessibilityIssues(level: 1);
});
