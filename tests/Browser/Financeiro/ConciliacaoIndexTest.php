<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E DE RENDER da tela `Financeiro/Conciliacao/Index`.
 *
 * ── O BURACO QUE ISTO FECHA (medido, não suposto) ─────────────────────────────
 * `node scripts/qa/screen-coverage-map.mjs --screen Financeiro/Conciliacao/Index`
 * (2026-08-31) devolve trio ✓ · scorecard ✓ · RUNBOOK ✓ · visual-comparison ✓ ·
 * proto-baseline ✓ · 13/13 UC citados por teste — e **`e2e (Browser) ✗`**. Ou seja: o
 * CONTRATO já está defendido no servidor (4 arquivos Pest em `Modules/Financeiro/Tests/
 * Feature`, lane required) e NADA prova o que o usuário vê. Este arquivo cobre só esse
 * eixo; não duplica assert de contrato nenhum.
 *
 * ── O QUE PROVA (e que Pest de contrato não alcança) ─────────────────────────
 *   1. a tela monta autenticada em 1280 e 1440 sem erro de console (UX Target do charter);
 *   2. a linha chega ao DOM com o chip de ORIGEM e o `title` do charter — `UC-FCC-10` no
 *      eixo RENDER (o contrato prova `props.linhas`, não o que a tabela desenha);
 *   3. o KPI "PENDENTES" concorda com a tabela (o KPI não mente sobre a lista);
 *   4. valor negativo e positivo saem com COR COMPUTADA diferente (UX Target "valor negativo
 *      em vermelho") — jsdom não tem layout engine; só o Chromium responde isso;
 *   5. o `match_score` chega à UI como "match NN%" — metade de EXIBIÇÃO do `UC-FCC-04`
 *      (o cálculo em si é do `ConciliacaoMatchScoreTest`; aqui provo que o número atravessa
 *      até a tela, que é o ponto do charter *"a UI mente ao mostrar match 85%"*);
 *   6. a AFFORDÂNCIA por status: "Confirmar" só em linha `sugerido` COM `titulo_id`;
 *      "Ignorar" em pendente e sugerido; "Reabrir" em nenhuma. Lógica 100% client-side
 *      (`Index.tsx:284-316`), hoje sem cobertura nenhuma;
 *   7. a busca filtra sem ida ao servidor (Goal do charter "busca client-side");
 *   8. zero violação axe CRITICAL (mesmo ratchet level 0 do `A11yAxeBrowserTest`).
 *
 * ── O QUE **NÃO** PROVA (resíduo declarado, não maquiado) ────────────────────
 *   - a metade "Banco" do `UC-FCC-10`: a origem API (`fin_extrato_lancamentos`) exige
 *     `fin_contas_bancarias` + `accounts` (FK NOT NULL) — 3 tabelas de fixture num teste que
 *     não pode ser rodado local. Fora DE PROPÓSITO: E2E frágil ensina o time a ignorar
 *     vermelho. O chip "Banco" segue provado só no servidor (`ConciliacaoLeExtratoApiTest`).
 *   - "cabe em 1280 sem scroll horizontal" (UX Target): probe de `scrollWidth` não foi escrita
 *     porque não dá pra calibrá-la sem executar — falso-vermelho advisory vira ruído.
 *   - ESCRITA: nada aqui clica em Confirmar/Ignorar/Reabrir. Esta tela mexe em DINHEIRO
 *     (REGRA MESTRE, `memory/proibicoes.md`) — o arquivo é READ-ONLY sobre o render.
 *   - Só o caso 2 cita UC. Os demais guardam Goals/UX Targets do charter que não têm UC —
 *     pôr id onde o caso não exerce o critério seria citação por presença (LC-11).
 *
 * ── TENANT (convenção DESTE gate) ────────────────────────────────────────────
 * `Business::orderBy('id')->first()` = biz 1 do gate visual: o tenant FICTÍCIO do
 * `VisregTenantSeeder` no MySQL `oimpresso_test` (service do runner), NÃO produção. A
 * convenção está escrita no seeder do tenant-vazio (`database/seeders/VisregEmptyTenantSeeder.php`):
 * *"biz=1 = self canônico, biz=99 = adversário sentinela (leak), biz=98 = tenant-vazio.
 * NUNCA biz=4"*. Usar 98 aqui daria empty-state por construção — é o tenant que existe pra
 * fotografar lista vazia. Mesmo idioma de `AuthBridgeSmokeTest` e `A11yAxeBrowserTest`.
 *
 * ── FIXTURE: por que no processo do TESTE, e por que limpa ───────────────────
 * O browser roda em SUBPROCESSO: `RefreshDatabase` não existe aqui (tests/Pest.php) e o dado
 * precisa estar COMMITADO pro server enxergar — idioma do `semearTituloVisualFinanceiro`
 * (`FinanceiroFlowBaselineTest`, verde no CI). O `afterEach` APAGA porque `fin_titulos`
 * alimenta a baseline de pixel de `Financeiro/Unificado`: deixar rastro mudaria o snapshot de
 * OUTRA tela. Por isso este step roda por ÚLTIMO no workflow — mesmo uma limpeza que falhe
 * não alcança baseline já capturada.
 *
 * ── EXECUÇÃO (CT 100 / CI — nunca local: memory/proibicoes.md + ADR 0062) ────
 *   tailscale ssh root@ct100-mcp "docker exec oimpresso-staging ./vendor/bin/pest tests/Browser/Financeiro/ConciliacaoIndexTest.php"
 *
 * ⚠️ HONESTIDADE (ADR 0108): NÃO rodado — `php` e `vendor/` ausentes nesta worktree e Pest
 * Browser é CI/CT-100 only; nem `php -l` foi possível. Só usa API já provada verde: `visit` ·
 * `resize` · `assertSee` · `assertDontSee` · `assertNoConsoleLogs` · `script` · `wait` ·
 * `assertNoAccessibilityIssues`. Nasce ADVISORY no workflow (ADR 0261/0275: gate novo nasce
 * advisory → 2 verdes → enforcing). Se a lane ficar vermelha, o suspeito é este arquivo.
 *
 * @see resources/js/Pages/Financeiro/Conciliacao/Index.tsx (tela sob teste)
 * @see resources/js/Pages/Financeiro/Conciliacao/Index.casos.md (UC-FCC-01..13)
 * @see tests/Browser/CoreScreens/A11yAxeBrowserTest.php (harness espelhado)
 * @see .github/workflows/visual-regression.yml (step que invoca)
 */

use App\Business;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

const CONC_ROTA = '/financeiro/conciliacao';
const CONC_FITID_PENDENTE = 'VISREG-CONC-OFX-001';
const CONC_FITID_SUGERIDO = 'VISREG-CONC-OFX-002';
const CONC_TITULO_ORIGEM_ID = 987655; // ≠ 987654 do seedFinanceiroVisregFlow (routes/web.php)
const CONC_DESC_PENDENTE = 'VISREG CONC OFX DEBITO PENDENTE';
const CONC_DESC_SUGERIDO = 'VISREG CONC OFX CREDITO SUGERIDO';

/** Linhas de DADO da tabela — a linha de empty-state tem um <td> só (colSpan=7). */
const CONC_JS_LINHAS = <<<'JS'
(() => {
  const linhas = [...document.querySelectorAll('tbody tr')].filter((r) => r.querySelectorAll('td').length > 1);
  return String(linhas.length);
})()
JS;

/** Botões oferecidos na linha cujo texto contém `__ALVO__` (ordem preservada). */
const CONC_JS_BOTOES = <<<'JS'
(() => {
  const tr = [...document.querySelectorAll('tbody tr')].find((r) => (r.textContent || '').includes('__ALVO__'));
  if (!tr) return 'LINHA-AUSENTE';
  const nomes = [...tr.querySelectorAll('button')].map((b) => (b.textContent || '').trim());
  return nomes.length ? nomes.join('|') : 'SEM-BOTAO';
})()
JS;

beforeEach(function () {
    // CROSS-PROCESS DB (idêntico A11yAxe/AuthBridge): o browser usa MySQL (.env), o test
    // process usa sqlite :memory: (phpunit.xml) — realinha pro MESMO MySQL do gate.
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');
    Carbon::setTestNow('2026-06-11 12:00:00');
});

afterEach(function () {
    concLimparFixture();
    Carbon::setTestNow();
});

/** Admin do tenant fictício do gate. Falha ALTO: fixture ausente não pode virar verde
 *  silencioso — smoke sem render seria falso positivo (idem AuthBridgeSmokeTest). */
function concAdmin(): User
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        throw new RuntimeException('Sem business seedado: o VisregTenantSeeder não rodou.');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        throw new RuntimeException('Sem user no business seedado: não dá pra autenticar.');
    }

    return $admin;
}

/** Duas linhas de extrato OFX + o título que a linha `sugerido` referencia. O título é REAL
 *  porque `titulo_id` tem FK pra `fin_titulos` — a fixture hardcoded `titulo_id = 12345` do
 *  `ConciliacaoAuditReabrirTest` morreu nessa FK (achado no `UC-FCC-06` do casos.md).
 *  Valores são CONSTANTES DE FIXTURE: nenhum cálculo de valor é tocado por este arquivo. */
function concSemearFixture(int $businessId, int $userId): void
{
    DB::table('fin_titulos')->updateOrInsert(
        ['business_id' => $businessId, 'origem' => 'manual', 'origem_id' => CONC_TITULO_ORIGEM_ID, 'parcela_numero' => 1],
        [
            'numero' => 'VISREG-CONC-TIT-001',
            'tipo' => 'receber',
            'status' => 'aberto',
            'cliente_descricao' => 'Cliente de prova de conciliação',
            'valor_total' => 250.00,
            'valor_aberto' => 250.00,
            'moeda' => 'BRL',
            'emissao' => '2026-06-10',
            'vencimento' => '2026-06-10',
            'competencia_mes' => '2026-06',
            'parcela_total' => 1,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    $tituloId = (int) DB::table('fin_titulos')
        ->where('business_id', $businessId)
        ->where('origem', 'manual')
        ->where('origem_id', CONC_TITULO_ORIGEM_ID)
        ->value('id');

    // `deleted_at => null`: updateOrInsert sobre linha soft-deletada de um run anterior
    // ficaria invisível pro BusinessScope/SoftDeletes da BankStatementLine.
    $base = ['conta_bancaria_id' => null, 'source_file' => null, 'deleted_at' => null,
        'created_at' => now(), 'updated_at' => now()];

    DB::table('fin_bank_statement_lines')->updateOrInsert(
        ['business_id' => $businessId, 'fitid' => CONC_FITID_PENDENTE],
        $base + ['data_movimento' => '2026-06-11', 'descricao' => CONC_DESC_PENDENTE,
            'valor' => -123.45, 'tipo' => 'debit', 'status' => 'pendente',
            'titulo_id' => null, 'match_score' => null],
    );

    DB::table('fin_bank_statement_lines')->updateOrInsert(
        ['business_id' => $businessId, 'fitid' => CONC_FITID_SUGERIDO],
        $base + ['data_movimento' => '2026-06-10', 'descricao' => CONC_DESC_SUGERIDO,
            'valor' => 250.00, 'tipo' => 'credit', 'status' => 'sugerido',
            'titulo_id' => $tituloId,   // sem ele o botão "Confirmar" não renderiza
            'match_score' => 0.92],     // vira "match 92%" na UI
    );
}

/** DELETE real (query builder ignora SoftDeletes). Linhas antes do título por higiene de FK. */
function concLimparFixture(): void
{
    DB::table('fin_bank_statement_lines')
        ->whereIn('fitid', [CONC_FITID_PENDENTE, CONC_FITID_SUGERIDO])->delete();
    DB::table('fin_titulos')
        ->where('origem', 'manual')->where('origem_id', CONC_TITULO_ORIGEM_ID)->delete();
}

/** Abre a tela autenticada (auth bridge cross-process) já com a fixture commitada. */
function concAbrirTela(int $largura = 1280, int $altura = 800)
{
    $admin = concAdmin();
    concSemearFixture((int) $admin->business_id, (int) $admin->id);

    return visit('/_visreg-login/' . $admin->id . '?to=' . urlencode(CONC_ROTA))
        ->resize($largura, $altura)
        ->assertSee('Conciliação');
}

/** Alvo injetado por SUBSTITUIÇÃO, nunca interpolação — o probe não pode depender da
 *  sintaxe do heredoc. Todo probe devolve STRING: comparação `===` fica estável. */
function concProbe(string $js, array $subs): string
{
    return str_replace(array_keys($subs), array_values($subs), $js);
}

/** Espera o React estabilizar. Ver o cabeçalho não significa que a tabela montou — mesma
 *  razão do `aguardarAlvoVisual` do FinanceiroFlowBaselineTest. */
function concEsperar($page, string $js, string $esperado, string $oque): void
{
    $ultimo = null;
    for ($i = 0; $i < 24; $i++) {
        $ultimo = (string) $page->script($js);
        if ($ultimo === $esperado) {
            return;
        }
        $page->wait(0.25);
    }

    throw new RuntimeException("Não estabilizou: {$oque} — esperado '{$esperado}', último '{$ultimo}'.");
}

it('UC-FCC-10 · render — a lista mostra a linha com chip de origem e o KPI concorda', function (int $w, int $h) {
    $page = concAbrirTela($w, $h);
    concEsperar($page, CONC_JS_LINHAS, '2', 'as 2 linhas de fixture montarem');

    $page->assertSee(CONC_DESC_PENDENTE)->assertSee(CONC_DESC_SUGERIDO)
        ->assertSee('PENDENTES')->assertSee('SUGERIDOS')
        ->assertSee('CONCILIADOS')->assertSee('IGNORADOS');

    // Chip de origem + `title` (UX Target: "Chip de origem com title explicando Banco vs OFX").
    // Ancorado no TEXTO da linha, nunca em classe CSS (L-24).
    expect($page->script(concProbe(<<<'JS'
(() => {
  const tr = [...document.querySelectorAll('tbody tr')].find((r) => (r.textContent || '').includes('__ALVO__'));
  if (!tr) return 'LINHA-AUSENTE';
  const chip = tr.querySelectorAll('td')[1].querySelector('span');
  if (!chip) return 'CHIP-AUSENTE';
  return (chip.textContent || '').trim() + ' :: ' + (chip.getAttribute('title') || 'SEM-TITLE');
})()
JS, ['__ALVO__' => CONC_DESC_PENDENTE])))->toBe('OFX :: Importado de arquivo OFX');

    // O KPI concorda com o que a tabela mostra. Invariante self-consistente: sobrevive ao
    // tenant ganhar outras linhas; o que NÃO sobrevive é o KPI passar a mentir.
    expect($page->script(<<<'JS'
(() => {
  const linhas = [...document.querySelectorAll('tbody tr')].filter((r) => r.querySelectorAll('td').length > 1);
  const pendentes = linhas.filter((r) => (r.querySelectorAll('td')[5].textContent || '').includes('pendente')).length;
  const card = [...document.querySelectorAll('div')].find((d) => {
    const rotulo = d.querySelector(':scope > small');
    return rotulo && (rotulo.textContent || '').trim() === 'PENDENTES';
  });
  if (!card) return 'KPI-AUSENTE';
  const valor = card.querySelector(':scope > b');
  return String(pendentes) + '==' + (valor ? (valor.textContent || '').trim() : 'VALOR-AUSENTE');
})()
JS))->toBe('1==1');

    $page->assertNoConsoleLogs();
})->with([[1280, 800], [1440, 900]]);

it('render — valor negativo destoa do positivo e o match% chega à UI', function () {
    $page = concAbrirTela();
    concEsperar($page, CONC_JS_LINHAS, '2', 'as 2 linhas de fixture montarem');

    // "valor negativo em vermelho" (UX Target). Comparo as DUAS cores computadas em vez de
    // cravar um literal: continua válido se o token for retunado, e falha se o negativo
    // deixar de destoar. Só o Chromium responde `getComputedStyle` — jsdom não.
    $cores = (string) $page->script(concProbe(<<<'JS'
(() => {
  const cor = (alvo) => {
    const tr = [...document.querySelectorAll('tbody tr')].find((r) => (r.textContent || '').includes(alvo));
    if (!tr) return 'LINHA-AUSENTE';
    const span = tr.querySelectorAll('td')[3].querySelector('span');
    return span ? getComputedStyle(span).color : 'VALOR-AUSENTE';
  };
  return cor('__NEG__') + ' :: ' + cor('__POS__');
})()
JS, ['__NEG__' => CONC_DESC_PENDENTE, '__POS__' => CONC_DESC_SUGERIDO]));

    [$corNegativo, $corPositivo] = explode(' :: ', $cores);
    expect($corNegativo)->not->toContain('AUSENTE')
        ->and($corPositivo)->not->toContain('AUSENTE')
        ->and($corNegativo)->not->toBe($corPositivo);

    // Metade de EXIBIÇÃO do UC-FCC-04: o score guardado (0.92) tem que chegar como
    // "match 92%". O charter registra que o 0.85 constante (bug B1, ADR 0236) fazia a UI
    // mentir — este assert é o alarme disso. O CÁLCULO é do ConciliacaoMatchScoreTest.
    // Asserção de valor por FRAGMENTO: o BRL pt-BR usa NBSP entre "R$" e o número.
    $page->assertSee('match 92%')->assertSee('123,45')->assertSee('250,00');
});

it('render — a affordância de cada linha respeita o status', function () {
    $page = concAbrirTela();
    concEsperar($page, CONC_JS_LINHAS, '2', 'as 2 linhas de fixture montarem');

    // Linha `pendente`: só "Ignorar". Sem "Confirmar" (não há título a confirmar) e sem
    // "Reabrir" (a linha não está resolvida) — Index.tsx:284-316.
    expect($page->script(concProbe(CONC_JS_BOTOES, ['__ALVO__' => CONC_DESC_PENDENTE])))
        ->toBe('Ignorar');

    // Linha `sugerido` COM titulo_id: "Confirmar" e depois "Ignorar". É o CONTROLE POSITIVO —
    // sem ele, apagar o botão "Confirmar" passaria despercebido pelo assert de cima.
    expect($page->script(concProbe(CONC_JS_BOTOES, ['__ALVO__' => CONC_DESC_SUGERIDO])))
        ->toBe('Confirmar|Ignorar');

    // Non-Goal do charter ("❌ desfazer conciliação já confirmada" não é ação de linha
    // aberta): "Reabrir" só aparece sob o toggle "Ver conciliados/ignorados".
    $page->assertDontSee('Reabrir');
});

it('render — a busca por descrição filtra sem ida ao servidor', function () {
    $page = concAbrirTela();
    concEsperar($page, CONC_JS_LINHAS, '2', 'as 2 linhas de fixture montarem');

    // Setter nativo + evento `input`: é o caminho que um input CONTROLADO do React aceita
    // (atribuir `.value` direto não dispara o onChange e o estado não muda).
    expect($page->script(concProbe(<<<'JS'
(() => {
  const input = [...document.querySelectorAll('input')]
    .find((el) => (el.placeholder || '').startsWith('Buscar por descri'));
  if (!input) return 'BUSCA-AUSENTE';
  const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
  setter.call(input, '__TERMO__');
  input.dispatchEvent(new Event('input', { bubbles: true }));
  return 'OK';
})()
JS, ['__TERMO__' => 'CREDITO'])))->toBe('OK');

    // Sobra 1 linha e a URL não muda: o filtro é client-side (Goal do charter + UX
    // Anti-pattern "sem window.location.reload()").
    concEsperar($page, CONC_JS_LINHAS, '1', 'a busca reduzir a lista a 1 linha');
    expect($page->script('window.location.pathname'))->toBe(CONC_ROTA);
    $page->assertSee(CONC_DESC_SUGERIDO)->assertDontSee(CONC_DESC_PENDENTE)
        ->assertNoConsoleLogs();
});

it('a11y — zero violação axe CRITICAL com a lista populada', function () {
    // Mesmo ratchet do A11yAxeBrowserTest: level 0 = CRITICAL only (piso honesto; `serious`
    // inclui contraste, que não é escopo deste PR). O ganho é auditar a tela COM DADO — a
    // tabela, os chips e os botões de ação só existem no DOM quando há linha, e é neles que
    // mora o risco de nome acessível ausente.
    $page = concAbrirTela();
    concEsperar($page, CONC_JS_LINHAS, '2', 'as 2 linhas de fixture montarem');

    $page->assertNoAccessibilityIssues(level: 0);
});
