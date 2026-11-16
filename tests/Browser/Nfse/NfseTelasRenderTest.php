<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E de render + axe das 3 telas do NFSe (`resources/js/Pages/Nfse/`).
 *
 * ── O BURACO QUE ISTO FECHA (auditoria NFSe 2026-11-05, lote 1 PR 1.3) ───────
 * `git grep -l "Pages/Nfse\|/nfse" tests/Browser/` não achava nada: Index, Emitir e Show só
 * tinham o contrato DB-less (`NfseTelasContratoTest`), que lê o controller e o FormRequest,
 * nunca o DOM. Nenhuma das 3 tinha axe.
 *
 * Telas: `resources/js/Pages/Nfse/Index.tsx` · `resources/js/Pages/Nfse/Show.tsx` ·
 * `resources/js/Pages/Nfse/Emitir.tsx` (UCs nos `.casos.md` ao lado de cada uma).
 *
 * ── O QUE PROVA, POR TELA (viewport 1280) ─────────────────────────────────────
 *   - Nfse/Index · UC-NFSL-01: a nota da empresa do harness aparece na lista e a de outra
 *     empresa não aparece — no DOM renderizado, não só na consulta.
 *   - Nfse/Show  · UC-NFSD-01: o detalhe da nota da própria empresa abre; o id de nota de
 *     outra empresa não mostra a nota. UC-NFSD-04: a cancelada exibe o rótulo "Cancelada".
 *   - Nfse/Emitir · UC-NFSEM-01: os campos fiscais mínimos do formulário chegam ao DOM.
 *   - As 3: 0 violações axe-core CRITICAL (level 0, o piso do A11yAxeBrowserTest).
 *
 * ── SEM BASELINE DE PIXEL ──────────────────────────────────────────────────────
 * A comparação de pixel saiu do projeto (ADR 0445), e as telas ficam fora do
 * `visreg-screens.json` pelo mesmo motivo do `tests/Browser/NfeBrasil/NfeBrasilTelasRenderTest.php`
 * (entrar no manifesto as põe na catraca do tema escuro). Não captura imagem.
 *
 * ── DADO ──────────────────────────────────────────────────────────────────────
 * O harness do visreg não semeia NFS-e. Cada caso grava as próprias notas com `DB::table`
 * (a transação do teste não atravessa o processo do servidor do browser) e apaga **pelo id**
 * no `->after()`. A nota "de outra empresa" usa um business_id que não existe no banco de
 * CI (nfse_emissoes não tem FK para business). Nunca biz=4. Tomador e números fictícios.
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI / CT 100 apenas (memory/proibicoes.md §Ambiente). Step "E2E de render + axe · NFSe
 * (ADVISORY)" do `visual-regression.yml`. Não foi executado antes do PR.
 */

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    DB::purge('mysql');
    nfseBrowserIds(reset: true);
});

/** Registro dos ids gravados pelo caso corrente (estático: zera no beforeEach). */
function nfseBrowserIds(?int $novo = null, bool $reset = false): array
{
    static $ids = [];
    if ($reset) {
        $ids = [];
    }
    if ($novo !== null) {
        $ids[] = $novo;
    }

    return $ids;
}

/** Admin do tenant fictício do harness (role `Admin#N`, liberado pelo Gate::before). */
function nfseBrowserAdmin(): User
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        test()->markTestSkipped('Sem user no business seedado.');
    }

    return $admin;
}

function nfseBrowserAbrir(User $admin, string $rota): object
{
    return visit('/_visreg-login/' . $admin->id . '?to=' . urlencode($rota))->resize(1280, 800);
}

/** Grava uma nota crua e registra o id para o ->after() apagar só ela. */
function nfseBrowserNota(int $businessId, string $tomador, string $status, string $numero): int
{
    $id = (int) DB::table('nfse_emissoes')->insertGetId([
        'business_id'     => $businessId,
        'serie'           => 'RPS',
        'rps_numero'      => 'BRW-' . $numero,
        'numero'          => $numero,
        'competencia'     => now()->startOfMonth()->toDateString(),
        'tomador_nome'    => $tomador,
        'descricao'       => 'Servico ficticio de teste de tela',
        'valor_servicos'  => 100,
        'valor_iss'       => 2,
        'status'          => $status,
        'idempotency_key' => 'brw-nfse-' . uniqid('', true),
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
    nfseBrowserIds($id);

    return $id;
}

/** Limpeza: só as notas que o próprio teste gravou, pelo id. */
function nfseBrowserLimpar(): void
{
    $ids = nfseBrowserIds();
    if ($ids !== []) {
        DB::table('nfse_emissoes')->whereIn('id', $ids)->delete();
    }
}

/** business_id de uma empresa que não existe no banco do CI — a "outra empresa". */
function nfseBrowserOutraEmpresa(): int
{
    return (int) DB::table('business')->max('id') + 9000;
}

/**
 * Lê o texto da página até aparecer um dos marcadores (ou esgotar ~4s). Devolve o último lido,
 * EM MINÚSCULAS: `innerText` aplica `text-transform`, e um selo "Cancelada" pode chegar como
 * "CANCELADA" (lição medida no tests/Browser/Arquivos/ArquivosIndexRenderTest.php).
 */
function nfseBrowserTexto($page, array $marcadores): string
{
    $texto = '';
    for ($i = 0; $i < 40; $i++) {
        $texto = mb_strtolower((string) $page->script('document.body.innerText.replace(/\s+/g, " ")'));
        foreach ($marcadores as $m) {
            if (str_contains($texto, mb_strtolower($m))) {
                return $texto;
            }
        }
        $page->wait(0.1);
    }

    return $texto;
}

// ── Nfse/Index ───────────────────────────────────────────────────────────────

it('UC-NFSL-01 · Nfse/Index lista a nota da propria empresa e nao a de outra', function () {
    $admin = nfseBrowserAdmin();
    nfseBrowserNota($admin->business_id, 'TOMADOR BRW PROPRIO', 'emitida', '910001');
    nfseBrowserNota(nfseBrowserOutraEmpresa(), 'TOMADOR BRW ALHEIO', 'emitida', '910002');

    $page = nfseBrowserAbrir($admin, '/nfse');
    $texto = nfseBrowserTexto($page, ['TOMADOR BRW PROPRIO']);

    expect((string) $page->script('window.location.pathname'))->toBe('/nfse');
    expect($texto)->toContain('tomador brw proprio');
    expect($texto)->not->toContain('tomador brw alheio');
})->after(fn () => nfseBrowserLimpar());

it('A11Y · Nfse/Index: 0 violações axe CRITICAL', function () {
    $admin = nfseBrowserAdmin();
    nfseBrowserNota($admin->business_id, 'TOMADOR BRW A11Y', 'emitida', '910003');

    $page = nfseBrowserAbrir($admin, '/nfse');
    nfseBrowserTexto($page, ['TOMADOR BRW A11Y']);

    $page->assertNoAccessibilityIssues(level: 0);
})->after(fn () => nfseBrowserLimpar());

// ── Nfse/Show ────────────────────────────────────────────────────────────────

it('UC-NFSD-01 · UC-NFSD-04 · Nfse/Show abre a nota da propria empresa e mostra o rotulo Cancelada', function () {
    $admin = nfseBrowserAdmin();
    $id = nfseBrowserNota($admin->business_id, 'TOMADOR BRW SHOW', 'cancelada', '910004');

    $page = nfseBrowserAbrir($admin, "/nfse/{$id}");
    $texto = nfseBrowserTexto($page, ['TOMADOR BRW SHOW']);

    expect((string) $page->script('window.location.pathname'))->toBe("/nfse/{$id}");
    expect($texto)->toContain('tomador brw show');
    expect($texto)->toContain('cancelada');
})->after(fn () => nfseBrowserLimpar());

it('[T0] UC-NFSD-01 · Nfse/Show com o id de nota de outra empresa nao mostra a nota', function () {
    $admin = nfseBrowserAdmin();
    $alheia = nfseBrowserNota(nfseBrowserOutraEmpresa(), 'TOMADOR BRW SHOW ALHEIO', 'emitida', '910005');

    $page = nfseBrowserAbrir($admin, "/nfse/{$alheia}");
    $page->wait(1);
    $texto = mb_strtolower((string) $page->script('document.body.innerText.replace(/\s+/g, " ")'));

    expect($texto)->not->toContain('tomador brw show alheio');
    expect($texto)->not->toContain('910005');
})->after(fn () => nfseBrowserLimpar());

it('A11Y · Nfse/Show: 0 violações axe CRITICAL', function () {
    $admin = nfseBrowserAdmin();
    $id = nfseBrowserNota($admin->business_id, 'TOMADOR BRW SHOW A11Y', 'emitida', '910006');

    $page = nfseBrowserAbrir($admin, "/nfse/{$id}");
    nfseBrowserTexto($page, ['TOMADOR BRW SHOW A11Y']);

    $page->assertNoAccessibilityIssues(level: 0);
})->after(fn () => nfseBrowserLimpar());

// ── Nfse/Emitir ──────────────────────────────────────────────────────────────

it('UC-NFSEM-01 · Nfse/Emitir mostra os campos fiscais minimos do formulario', function () {
    $page = nfseBrowserAbrir(nfseBrowserAdmin(), '/nfse/emitir');
    $texto = nfseBrowserTexto($page, ['Descrição']);

    expect((string) $page->script('window.location.pathname'))->toBe('/nfse/emitir');
    foreach (['Nome / Razão Social', 'Descrição', 'Código LC 116/2003', 'Valor dos serviços', 'Alíquota ISS'] as $campo) {
        expect($texto)->toContain(mb_strtolower($campo));
    }
});

it('A11Y · Nfse/Emitir: 0 violações axe CRITICAL', function () {
    $page = nfseBrowserAbrir(nfseBrowserAdmin(), '/nfse/emitir');
    nfseBrowserTexto($page, ['Descrição']);

    $page->assertNoAccessibilityIssues(level: 0);
});
