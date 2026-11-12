<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — comportamento + acessibilidade das 3 telas do ComunicacaoVisual:
 * hub + calculadora (`/comunicacao-visual`), Agenda de instalação e a página pública do aceite.
 *
 * O servidor destas telas já está coberto em Modules/ComunicacaoVisual/Tests/Feature. O que
 * nenhuma suíte Feature alcança é o render real: até aqui ninguém abria as telas num browser
 * nem rodava axe nelas (auditoria do módulo 2026-11-05, eixo 2, achado A7 · lote 4).
 *
 * Sem comparação de pixel (ADR 0445): zero `screenshot()`, e as telas ficam fora de
 * `tests/Browser/visreg-screens.json`. O critério é comportamento + axe.
 *
 * Tenant 98 (ADR 0358): business e admin vêm do VisregEmptyTenantSeeder. O tenant não tem
 * assinatura nenhuma, e toda rota de /comunicacao-visual exige o pacote
 * `comunicacao_visual_module` (ComunicacaoVisualLiberado, UC-CVPAC-01): a assinatura nasce aqui.
 * O orçamento do aceite também, marcado em `observacoes`. Tudo sai no afterEach de arquivo.
 *
 * Lane: step "E2E de comportamento · ComunicacaoVisual" do visual-regression.yml. Pest Browser
 * é CI-only (o container do CT 100 não tem chromium).
 */

use Illuminate\Support\Facades\DB;
use Modules\ComunicacaoVisual\Services\AceiteDoOrcamento;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;

const CVB_BIZ = 98;
const CVB_ADMIN = 98;
const CVB_ASSINATURA = 900097;
const CVB_MARCA = 'E2E-CV-BROWSER-98';
const CVB_ITEM = 'Banner fachada E2E';

beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    DB::purge('mysql');

    // Falha alto: tenant ausente não pode virar verde por pulo.
    if (! DB::table('users')->where('id', CVB_ADMIN)->where('business_id', CVB_BIZ)->exists()) {
        throw new RuntimeException('Admin do tenant 98 ausente: o VisregEmptyTenantSeeder não rodou.');
    }

    cvbLimpar();
    DB::table('subscriptions')->insert([
        'id' => CVB_ASSINATURA,
        'business_id' => CVB_BIZ,
        'package_id' => 900001, // pacote do VisregTenantSeeder; o que vale aqui é o package_details
        'start_date' => '2026-01-01',
        'end_date' => '2099-12-31',
        'package_price' => 0,
        'package_details' => json_encode(['name' => 'Fixture Comunicação Visual', 'comunicacao_visual_module' => '1']),
        'created_id' => CVB_ADMIN,
        'status' => 'approved',
    ]);
});

afterEach(function () {
    cvbLimpar();
});

/** Apaga a assinatura e tudo que nasceu do orçamento marcado, inclusive a OS do aceite. */
function cvbLimpar(): void
{
    DB::table('subscriptions')->where('id', CVB_ASSINATURA)->delete();

    $orcs = DB::table('comvis_orcamentos')->where('business_id', CVB_BIZ)->where('observacoes', CVB_MARCA)->pluck('id')->all();
    if ($orcs === []) {
        return;
    }
    $os = DB::table('comvis_os')->where('business_id', CVB_BIZ)->whereIn('orcamento_id', $orcs)->pluck('id')->all();
    DB::table('comvis_os_itens')->where('business_id', CVB_BIZ)->whereIn('os_id', $os)->delete();
    DB::table('comvis_os')->where('business_id', CVB_BIZ)->whereIn('id', $os)->delete();
    DB::table('comvis_orcamento_aceites')->where('business_id', CVB_BIZ)->whereIn('orcamento_id', $orcs)->delete();
    DB::table('comvis_orcamento_itens')->whereIn('orcamento_id', $orcs)->delete();
    DB::table('comvis_orcamentos')->where('business_id', CVB_BIZ)->whereIn('id', $orcs)->delete();
}

/** Orçamento do tenant 98 com um item, e o link de aceite dele. Valores fictícios. */
function cvbLinkDeAceite(): array
{
    $orc = app(OrcamentoGravador::class)->gravar(CVB_BIZ, [
        'data_emissao' => now()->toDateString(), 'data_validade' => now()->addDays(10)->toDateString(),
        'contato_id' => null, 'vendedor_id' => null, 'observacoes' => CVB_MARCA, 'subtotal' => 270.0,
        'desconto' => 0.0, 'extras' => 30.0, 'custo_instalacao' => 0.0, 'custo_entrega' => 0.0,
        'total' => 300.0, 'itens' => [[
            'material_id' => null, 'descricao' => CVB_ITEM, 'largura_m' => 3.0, 'altura_m' => 1.5,
            'quantidade' => 1, 'area_m2' => 4.5, 'preco_unitario_m2' => 60.0, 'subtotal' => 270.0, 'observacoes' => null,
        ]],
    ]);

    return ['orcamento' => (int) $orc->id, 'token' => app(AceiteDoOrcamento::class)->gerar(CVB_BIZ, (int) $orc->id)['token']];
}

/** Abre a tela autenticado e espera o React montar a seção esperada. */
function cvbAbrir(string $rota, string $secao)
{
    $page = visit('/_visreg-login/'.CVB_ADMIN.'?to='.urlencode($rota))->resize(1280, 800);

    $sinal = "!!document.querySelector('[data-contract=\"{$secao}\"]')";
    for ($i = 0; $i < 40; $i++) {
        if ($page->script($sinal) === true) {
            return $page;
        }
        $page->wait(0.25);
    }

    throw new RuntimeException("Seção {$secao} não apareceu em {$rota}.");
}

// ── Hub + calculadora ────────────────────────────────────────────────────────

it('UC-CV-10 · o hub abre para o admin com o pacote e renderiza a calculadora, sem erro e no axe', function () {
    cvbAbrir('/comunicacao-visual', 'calculadora')
        ->assertSee('Comunicação Visual')
        ->assertVisible('[data-contract="cabecalho"]')
        ->assertVisible('[data-contract="total"]')
        ->assertNoConsoleLogs()
        ->assertNoAccessibilityIssues(level: 1);
});

it('UC-CV-10 · o hub não tem rolagem horizontal da página a 1280px', function () {
    $largura = (string) cvbAbrir('/comunicacao-visual', 'calculadora')
        ->script('document.documentElement.scrollWidth <= document.documentElement.clientWidth ? "cabe" : "vaza"');

    expect($largura)->toBe('cabe');
});

// ── Agenda de instalação ─────────────────────────────────────────────────────

it('UC-CVAG-03 · o admin abre a agenda da semana com o formulário de agendar, e a tela passa no axe', function () {
    cvbAbrir('/comunicacao-visual/agenda', 'agenda')
        ->assertSee('Agenda de instalação')
        ->assertVisible('[data-contract="semana"]')
        ->assertVisible('[data-contract="agendar"]')
        ->assertNoConsoleLogs()
        ->assertNoAccessibilityIssues(level: 1);
});

// ── Aceite público (sem login) ───────────────────────────────────────────────

it('UC-CVACEP-01 · link válido abre a página com o item, o total e os botões, e passa no axe', function () {
    $link = cvbLinkDeAceite();

    visit('/orcamento-cv/'.$link['token'])
        ->resize(390, 780)
        ->assertVisible('[data-contract="itens"]')
        ->assertSee(CVB_ITEM)
        ->assertSee('300,00')
        ->assertSee('Aceitar orçamento')
        ->assertSee('Pedir ajuste')
        ->assertNoConsoleLogs()
        ->assertNoAccessibilityIssues(level: 1);
});

it('UC-CVACEP-02 · token que não existe não mostra dado de orçamento nenhum', function () {
    cvbLinkDeAceite();

    visit('/orcamento-cv/'.str_repeat('a', 48))
        ->resize(390, 780)
        ->assertDontSee(CVB_ITEM)
        ->assertDontSee('Aceitar orçamento');
});

it('UC-CVACEP-03 · aceitar pela página mostra a confirmação e vira OS com o total do orçamento', function () {
    $link = cvbLinkDeAceite();

    visit('/orcamento-cv/'.$link['token'])
        ->resize(390, 780)
        ->assertSee(CVB_ITEM)
        ->press('Aceitar orçamento')
        ->assertSee('Resposta enviada')
        ->assertDontSee('Pedir ajuste')
        ->assertNoAccessibilityIssues(level: 1);

    $os = DB::table('comvis_os')->where('business_id', CVB_BIZ)->where('orcamento_id', $link['orcamento'])->first();
    expect($os)->not->toBeNull()
        ->and(number_format((float) $os->valor_total, 2, '.', ''))->toBe('300.00');
});
