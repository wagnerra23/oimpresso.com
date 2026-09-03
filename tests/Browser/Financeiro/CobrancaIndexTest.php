<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E DE RENDER da tela `Financeiro/Cobranca/Index`.
 *
 * ── O BURACO QUE ISTO FECHA (medido, não suposto) ─────────────────────────────
 * `node scripts/qa/screen-coverage-map.mjs --screen Financeiro/Cobranca/Index`
 * (2026-09-03, main c23bcb8d7f) devolve trio ✓ · scorecard ✓ · visual-comparison ✓ ·
 * charter LIGADA (sinal prod-flags:biz=4) · 7/7 UC citados por teste — e
 * **`e2e (Browser) ✗ nenhum teste Browser cita o path`**. Este arquivo cobre só esse eixo:
 * não duplica assert de contrato nenhum.
 *
 * ⚠️ E o "7/7 UC citados por teste" do mapa é STRING-MATCH, não execução. Medido em
 * 2026-09-03 por três fontes independentes: (a) `git grep UC-COB-` diz quem cita; (b) o
 * run-set da lane `financeiro-pest` reproduzido com o mesmo `comm -23` do workflow (árvore
 * 83 · quarentena 23 · RODANDO 60) diz quem roda; (c) `scripts/casos-test-results.json` diz
 * o que o manifesto G-7 carimbou. Resultado: `CobrancaControllerTest` (UC-COB-01/02/03/07)
 * e `BoletoMockEmissaoTest` (UC-COB-05) estão em `.github/financeiro-pest-quarantine.list`
 * — o manifesto tem SÓ `UC-COB-04` e `UC-COB-06`. **2 de 7 execução-backed.** Ou seja:
 * enquanto a quarentena não cair, ESTE arquivo é a única cobertura desta tela de DINHEIRO
 * que de fato executa numa lane de PR. Sair da quarentena exige consertar os 4 vermelhos
 * anotados na lista — trabalho de outro PR, e é decisão [W].
 *
 * ── O QUE PROVA (e que Pest de contrato não alcança) ─────────────────────────
 *   1. monta autenticada em 1280 e 1440 sem log de console (UX Target do charter);
 *   2. a LINHA chega com o contrato de colunas do charter — vencimento formatado, chip
 *      COMPOSTO gateway+tipo, nosso nº, valor e status. `UC-COB-01` no eixo RENDER: o
 *      contrato prova `props.cobrancas`, não o que a tabela desenha;
 *   3. o doc do pagador sai MASCARADO e o cru NÃO chega à célula. A máscara é 100%
 *      client-side (`_lib/cobranca-shared.ts::piiMask`) e o payload Inertia carrega o doc
 *      inteiro — servidor nenhum prova isto. Goal do charter "Pagador + doc mascarado";
 *   4. o contador da aba "Todos" concorda com as linhas desenhadas (`statusCounts` e
 *      `filtered` são dois `useMemo` independentes — Index.tsx:128 e :109);
 *   5. o FUNIL de 5 etapas chega à tela e sua etapa "Em aberto" concorda com o KPI homônimo
 *      — `UC-COB-02` no eixo RENDER. As duas derivações são independentes no servidor
 *      (`CobrancaQuery::funil` e `::kpis`, cada uma com seu COUNT) e caem em dois
 *      `<Deferred>` distintos: divergir é a UI dando dois números pra mesma palavra;
 *   6. o mapeamento status → badge (emitida/paga/vencida) COM marcador visual, contra o UX
 *      Anti-pattern "Status badge sem ícone";
 *   7. o atalho `/` foca a busca (Goal KB-9.75, hoje `[BACKLOG]` sem id) e o filtro roda
 *      CLIENT-SIDE, sem trocar a URL;
 *   8. zero violação axe CRITICAL com a lista populada (ratchet level 0 do
 *      `A11yAxeBrowserTest`) — `assertNoAccessibilityIssues` de verdade, não menção em prosa.
 *
 * ── O QUE **NÃO** PROVA (resíduo declarado, não maquiado) ────────────────────
 *   - Tier 0 cross-tenant: o assert existe e é bom (`CobrancaControllerTest`, biz 1 × biz 99).
 *     Repetir aqui custaria 1 cobrança + 1 credencial no biz 99 e não traria informação
 *     nova — só superfície de flake. ⚠️ Mas note o que a quarentena implica: hoje esse
 *     assert NÃO é exercitado pela lane required `PHP / Pest (Financeiro · MySQL)`. Fechar
 *     esse buraco é tirar o arquivo da quarentena, não escrever um 2º teste da mesma coisa.
 *   - "cabe em 1280 sem scroll horizontal" (UX Target): probe de `scrollWidth` não foi
 *     escrita porque não dá pra calibrá-la sem executar; falso-vermelho advisory vira ruído.
 *   - `UC-COB-03` (deep-link): NÃO citado de propósito — ver o achado registrado abaixo.
 *   - ESCRITA: nada aqui clica em emitir/cancelar. A tela mexe em DINHEIRO (REGRA MESTRE,
 *     `memory/proibicoes.md`) — este arquivo é READ-ONLY sobre o render. Os valores são
 *     CONSTANTES DE FIXTURE: nenhum cálculo é tocado. O assert de `1.234,56` prova só que
 *     `valor_centavos` (123456) atravessa `CobrancaQuery::centavos` → `brlNoSign` sem
 *     perder a escala — é alarme da classe do incidente `num_uf` (2026-06-05), não cálculo.
 *   - RELÓGIO: nenhuma asserção depende de `today`. A coluna de vencimento é lida só no
 *     1º `<div>` (a data absoluta, derivada da fixture); o 2º (relativo/atraso) é ignorado
 *     de propósito, porque não pude verificar sem executar se o `setTestNow` do
 *     `VISREG_FREEZE_CLOCK` (AppServiceProvider:66) alcança o `CarbonImmutable::today()`
 *     do `CobrancaController::index`. O arquivo é imune aos dois desfechos.
 *   - Só os casos 1 e 2 citam UC. Os demais guardam Goals/UX Targets do charter que não têm
 *     UC — pôr id onde o caso não exerce o critério seria citação por presença (LC-11).
 *
 * ── ACHADO (reportado, NÃO consertado — `.tsx` é intocável neste PR) ─────────
 * `UC-COB-03` promete *"trocar tab/chip/dropdown reflete na URL e a volta pela URL reproduz
 * a visão"*. O código lê `useState(() => lsGet('tab', filtros.status || 'all'))` — os cinco
 * `useState(() => lsGet<string>(…))` do Index.tsx:75-79: o localStorage VENCE a querystring.
 * Varrido: 5 de 6 filtros usam esse padrão (tab · tipo · gateway · account · origem); só
 * `busca` (:80, `useState(filtros.busca || '')`) não persiste. Logo um
 * deep-link `?status=vencida` filtra no SERVIDOR mas a aba restaurada do localStorage pode
 * refiltrar no cliente e esvaziar a tabela. HIPÓTESE derivada de leitura + varredura contada
 * (§5 2026-07-15): não executei. Decisão de qual lado corrigir — casos.md ou `.tsx` — é [W].
 * É por isso que este arquivo NUNCA clica em tab/chip/dropdown: escrever numa dessas 5
 * chaves de localStorage contaminaria as execuções seguintes do mesmo contexto de browser.
 *
 * ── TENANT e FIXTURE (convenção DESTE gate — detalhada no ConciliacaoIndexTest) ──
 * `Business::orderBy('id')->first()` = biz 1 do gate visual: o tenant FICTÍCIO do
 * `VisregTenantSeeder` no MySQL `oimpresso_test` (service do runner), NÃO produção — a
 * convenção *"biz=1 self canônico · 99 adversário · 98 vazio · NUNCA biz=4"* está escrita no
 * `VisregEmptyTenantSeeder` (98 aqui daria empty-state por construção). A fixture nasce no
 * processo do TESTE e COMMITADA porque o browser roda em SUBPROCESSO e `RefreshDatabase` não
 * existe nesta suíte (tests/Pest.php:12). O `afterEach` APAGA porque nenhum seeder cria
 * cobrança: o que este arquivo deixar vira o universo de qualquer outro leitor de
 * `cobrancas`. Por isso o step roda por ÚLTIMO no workflow — nem uma limpeza que falhe
 * alcança baseline de pixel já capturada.
 *
 * ── EXECUÇÃO (CT 100 / CI — nunca local: memory/proibicoes.md + ADR 0062) ────
 *   tailscale ssh root@ct100-mcp "docker exec oimpresso-staging ./vendor/bin/pest tests/Browser/Financeiro/CobrancaIndexTest.php"
 *
 * ⚠️ HONESTIDADE (ADR 0108): NÃO rodado — `php` e `vendor/` ausentes nesta worktree e Pest
 * Browser é CI/CT-100 only; nem `php -l` foi possível. Só usa API já verde no repo: `visit` ·
 * `resize` · `assertSee` · `assertNoConsoleLogs` · `script` · `wait` ·
 * `assertNoAccessibilityIssues`. Nasce ADVISORY no workflow (ADR 0261/0275). Se a lane ficar
 * vermelha, o suspeito é este arquivo — e o 1º assert a checar é o `assertNoConsoleLogs()`,
 * o único cujo veredito depende de ruído da tela (warning de React, `toast`, `<Select>`) e
 * não da fixture. Vermelho ali é ACHADO contra o UX Target "0 erros JS console" do charter;
 * decidir entre consertar a tela ou afrouxar o assert é [W], nunca conserto silencioso.
 *
 * @see resources/js/Pages/Financeiro/Cobranca/Index.tsx (tela sob teste)
 * @see resources/js/Pages/Financeiro/Cobranca/Index.casos.md (UC-COB-01..07)
 * @see tests/Browser/Financeiro/ConciliacaoIndexTest.php (harness espelhado)
 * @see .github/workflows/visual-regression.yml (step que invoca)
 */

use App\Business;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

const COBIDX_ROTA = '/financeiro/cobranca';
const COBIDX_EMITIDA = 'VISREG COB EMITIDA LTDA';
const COBIDX_PAGA = 'VISREG COB PAGA ME';
const COBIDX_VENCIDA = 'VISREG COB VENCIDA SA';
/** Doc CRU da fixture — nunca pode chegar à célula; só a máscara pode. */
const COBIDX_DOC_CRU = '12345678000199';
const COBIDX_DOC_MASCARA = '***.***.***-99';
const COBIDX_NOSSO_NUMERO = 'VISREG-COB-NN-001';
const COBIDX_CRED_INTER = 'Inter (fixture visreg)';
const COBIDX_CRED_ASAAS = 'Asaas (fixture visreg)';
/** Fonte ÚNICA das chaves da fixture: quem semeia E quem limpa lê daqui. Duas listas
 *  paralelas drifariam no dia em que alguém acrescentasse a 4ª cobrança — e o resíduo
 *  vazaria pro próximo leitor de `cobrancas`, que é o vetor que o `afterEach` fecha. */
const COBIDX_IDEMPOTENCIAS = ['visreg-cob-emitida', 'visreg-cob-paga', 'visreg-cob-vencida'];

/** Linhas de DADO da fixture — a linha de empty-state tem um <td> só (colSpan=8), e o
 *  filtro por marca sobrevive ao tenant ganhar cobranças de outra origem. */
const COBIDX_JS_LINHAS = <<<'JS'
(() => {
  const linhas = [...document.querySelectorAll('tbody tr')].filter((r) => r.querySelectorAll('td').length > 1);
  return String(linhas.filter((r) => (r.textContent || '').includes('VISREG COB')).length);
})()
JS;

/**
 * DIAGNOSTICO da espera que estoura — nao asserta nada, so descreve o que o browser via.
 *
 * Existe porque o vermelho anterior era MUDO: `esperado '3', ultimo '0'` nao distingue
 * "o servidor mandou 0 cobrancas" de "mandou 3 e o cliente filtrou tudo" de "a 2a request
 * do Inertia::defer nunca chegou". Sao tres consertos diferentes, e escolher entre eles
 * por leitura de codigo e a classe de erro que o §5 de 2026-07-15 proibe.
 *
 * O campo que decide e `deferido`: as linhas nascem de `props.cobrancas`, que so chega na
 * 2a request do defer. `pendente` -> a 2a request nao voltou (rede/erro JS); `0` -> o
 * SERVIDOR devolveu lista vazia (business_id da sessao, escopo, fixture); `3` com
 * `linhasComTd: 0` -> o CLIENTE filtrou (o `filtered` do Index.tsx).
 */
const COBIDX_JS_DIAG = <<<'JS'
(() => {
  const txt = (el) => ((el && el.textContent) || '').replace(/[ \t\n\r]+/g, ' ').trim();
  const trs = [...document.querySelectorAll('tbody tr')];
  let deferido = 'indisponivel';
  try {
    const raw = document.getElementById('app');
    const page = raw ? JSON.parse(raw.getAttribute('data-page') || '{}') : {};
    const props = page.props || {};
    deferido = Object.prototype.hasOwnProperty.call(props, 'cobrancas')
      ? (Array.isArray(props.cobrancas) ? String(props.cobrancas.length) : 'naoArray')
      : 'pendente';
  } catch (e) {
    deferido = 'erro:' + (e && e.message ? e.message.slice(0, 60) : '?');
  }
  return JSON.stringify({
    deferido,
    trs: trs.length,
    linhasComTd: trs.filter((r) => r.querySelectorAll('td').length > 1).length,
    comMarca: trs.filter((r) => txt(r).includes('VISREG COB')).length,
    primeiraTr: txt(trs[0]).slice(0, 160),
    corpo: txt(document.body).slice(0, 400),
  });
})()
JS;

beforeEach(function () {
    // CROSS-PROCESS DB (idêntico A11yAxe/Conciliacao): o browser usa MySQL (.env), o test
    // process usa sqlite :memory: (phpunit.xml) — realinha pro MESMO MySQL do gate.
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');
    // Mesmo instante do VISREG_FREEZE_CLOCK do workflow: só pra `created_at` da fixture ser
    // determinístico. NENHUMA asserção deste arquivo depende disso.
    Carbon::setTestNow('2026-06-11 12:00:00');
});

afterEach(function () {
    cobIdxLimparFixture();
    Carbon::setTestNow();
});

/** Admin do tenant fictício do gate. Falha ALTO: fixture ausente não pode virar verde
 *  silencioso — smoke sem render seria falso positivo (idem ConciliacaoIndexTest). */
function cobIdxAdmin(): User
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

/**
 * Duas credenciais + três cobranças (emitida · paga · vencida).
 *
 * As credenciais são OBRIGATÓRIAS: `CobrancaQuery::shape()` resolve `gateway` por
 * `$c->credential?->gateway_key`, e sem ela o `GatewayTipoChip` devolve `null` — a coluna do
 * chip composto sairia vazia e o caso 1 mediria a ausência da fixture, não a tela.
 * `conta_bancaria_id => null` de propósito: a FK ligaria a linha ao cockpit de
 * `Financeiro/ContasBancarias`, que TEM baseline de pixel.
 *
 * Valores são CONSTANTES DE FIXTURE — nenhum cálculo é tocado por este arquivo.
 */
function cobIdxSemearFixture(int $businessId): void
{
    $credBase = [
        'ativo' => 1,
        'config_json' => '{}',
        'conta_bancaria_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    foreach ([COBIDX_CRED_INTER => 'inter', COBIDX_CRED_ASAAS => 'asaas'] as $nome => $key) {
        DB::table('payment_gateway_credentials')->updateOrInsert(
            ['business_id' => $businessId, 'gateway_key' => $key, 'ambiente' => 'production'],
            $credBase + ['nome_display' => $nome],
        );
    }

    $credId = fn (string $key) => (int) DB::table('payment_gateway_credentials')
        ->where('business_id', $businessId)
        ->where('gateway_key', $key)
        ->where('ambiente', 'production')
        ->value('id');

    $base = ['business_id' => $businessId, 'created_at' => now(), 'updated_at' => now()];

    $cobrancas = [
        'visreg-cob-emitida' => [
            'payment_gateway_credential_id' => $credId('inter'),
            'gateway_external_id' => 'visreg-ext-emitida',
            'tipo' => 'boleto', 'status' => 'emitida',
            'valor_centavos' => 123456,          // vira "1.234,56" na coluna Valor
            'vencimento' => '2026-07-15',        // vira "15/07/2026" no 1º div da coluna
            'payer_name' => COBIDX_EMITIDA,
            'payer_cpf_cnpj' => COBIDX_DOC_CRU,  // tem que sair MASCARADO na tela
            'descricao' => 'Fixture visreg - cobranca emitida',
            'nosso_numero' => COBIDX_NOSSO_NUMERO,
            'origem_type' => 'sale', 'origem_id' => 4242,
        ],
        'visreg-cob-paga' => [
            'payment_gateway_credential_id' => $credId('asaas'),
            'gateway_external_id' => 'visreg-ext-paga',
            'tipo' => 'pix_cob', 'status' => 'paga',
            'valor_centavos' => 50000, 'valor_pago_centavos' => 50000,
            'vencimento' => '2026-06-05', 'paga_em' => '2026-06-06 10:00:00',
            'payer_name' => COBIDX_PAGA,
            'descricao' => 'Fixture visreg - cobranca paga',
        ],
        'visreg-cob-vencida' => [
            'payment_gateway_credential_id' => $credId('inter'),
            'gateway_external_id' => 'visreg-ext-vencida',
            'tipo' => 'boleto', 'status' => 'vencida',
            'valor_centavos' => 7890, 'vencimento' => '2026-05-01',
            'payer_name' => COBIDX_VENCIDA,
            'descricao' => 'Fixture visreg - cobranca vencida',
        ],
    ];

    // Controle-negativo barato do drift entre semear e limpar: se alguém acrescentar uma
    // cobrança sem registrá-la em COBIDX_IDEMPOTENCIAS, o resíduo vazaria calado. Falha alto.
    $orfas = array_diff(array_keys($cobrancas), COBIDX_IDEMPOTENCIAS);
    if ($orfas !== []) {
        throw new RuntimeException('Fixture sem limpeza declarada: '.implode(', ', $orfas));
    }

    foreach ($cobrancas as $idempotencyKey => $dados) {
        DB::table('cobrancas')->updateOrInsert(
            ['business_id' => $businessId, 'idempotency_key' => $idempotencyKey],
            $base + $dados,
        );
    }
}

/** Cobranças antes das credenciais (higiene da FK lógica). Nenhum seeder cria estas linhas:
 *  o que ficar aqui vira o universo de qualquer outro leitor de `cobrancas`. */
function cobIdxLimparFixture(): void
{
    DB::table('cobrancas')
        ->whereIn('idempotency_key', COBIDX_IDEMPOTENCIAS)
        ->delete();
    DB::table('payment_gateway_credentials')
        ->whereIn('nome_display', [COBIDX_CRED_INTER, COBIDX_CRED_ASAAS])
        ->delete();
}

/** Abre a tela autenticada (auth bridge cross-process) já com a fixture commitada. */
function cobIdxAbrirTela(int $largura = 1280, int $altura = 800)
{
    $admin = cobIdxAdmin();
    cobIdxSemearFixture((int) $admin->business_id);

    return visit('/_visreg-login/'.$admin->id.'?to='.urlencode(COBIDX_ROTA))
        ->resize($largura, $altura)
        ->assertSee('Cobrança');
}

/** Alvo injetado por SUBSTITUIÇÃO, nunca interpolação — o probe não pode depender da
 *  sintaxe do heredoc. Todo probe devolve STRING: comparação `===` fica estável. */
function cobIdxProbe(string $js, array $subs): string
{
    return str_replace(array_keys($subs), array_values($subs), $js);
}

/** Espera o React estabilizar: `cobrancas`/`kpis`/`funil` são `Inertia::defer` e chegam numa
 *  2ª request — ver o cabeçalho significa skeleton, não tabela. */
function cobIdxEsperar($page, string $js, string $esperado, string $oque): void
{
    $ultimo = null;
    for ($i = 0; $i < 24; $i++) {
        $ultimo = (string) $page->script($js);
        if ($ultimo === $esperado) {
            return;
        }
        $page->wait(0.25);
    }

    // O diagnostico e colhido DEPOIS do loop, so no caminho que ja vai falhar: custo zero
    // no verde. `try` porque um probe que estoura nao pode substituir a falha real pela dele.
    $diag = 'indisponivel';
    try {
        $diag = (string) $page->script(COBIDX_JS_DIAG);
    } catch (Throwable $e) {
        $diag = 'probe falhou: '.$e->getMessage();
    }

    throw new RuntimeException(
        "Nao estabilizou: {$oque} — esperado '{$esperado}', ultimo '{$ultimo}'. DIAG: {$diag}"
    );
}

it('UC-COB-01 · render — a linha chega à tabela com o contrato de colunas do charter', function (int $w, int $h) {
    $page = cobIdxAbrirTela($w, $h);
    cobIdxEsperar($page, COBIDX_JS_LINHAS, '3', 'as 3 linhas de fixture montarem');

    // Os 4 KPIs do charter (3 fixos + o contextual, que sem filtro cai no fallback
    // "Próx. janela remessa"). Ancorado no TEXTO, nunca em classe CSS (L-24).
    $page->assertSee('Pago no mês')->assertSee('Vencido')->assertSee('Próx. janela remessa');

    // O contrato de COLUNAS da linha, numa asserção só. `td[2]` é o chip COMPOSTO: a sigla do
    // driver e o rótulo curto do tipo são dois <span> vizinhos, então o textContent sai colado
    // ("IN" + "boleto"). `td[0]` lê só o 1º div — a data absoluta, derivada da fixture; o 2º
    // (relativo/atraso) depende do relógio do servidor e fica de fora de propósito.
    expect($page->script(cobIdxProbe(<<<'JS'
(() => {
  const tr = [...document.querySelectorAll('tbody tr')].find((r) => (r.textContent || '').includes('__ALVO__'));
  if (!tr) return 'LINHA-AUSENTE';
  const td = tr.querySelectorAll('td');
  const t = (el) => (el ? (el.textContent || '') : '').replace(/\u00A0/g, ' ').trim().replace(/\s+/g, ' ');
  return [t(td[0].querySelector('div')), t(td[2]), t(td[4]), t(td[5]), t(td[6])].join(' | ');
})()
JS, ['__ALVO__' => COBIDX_EMITIDA])))
        ->toBe('15/07/2026 | INboleto | '.COBIDX_NOSSO_NUMERO.' | 1.234,56 | Emitida');

    // LGPD/Goal do charter "Pagador + doc mascarado": a máscara é client-side (`piiMask`), o
    // payload Inertia carrega o doc inteiro — servidor nenhum prova isto. Leio o texto da
    // CÉLULA (não da página): o doc cru existe legitimamente no `data-page` do Inertia.
    expect($page->script(cobIdxProbe(<<<'JS'
(() => {
  const tr = [...document.querySelectorAll('tbody tr')].find((r) => (r.textContent || '').includes('__ALVO__'));
  if (!tr) return 'LINHA-AUSENTE';
  const cel = (tr.querySelectorAll('td')[1].textContent || '');
  return (cel.includes('__MASCARA__') ? 'MASCARADO' : 'SEM-MASCARA')
       + (cel.includes('__CRU__') ? '+VAZOU-DOC-CRU' : '');
})()
JS, ['__ALVO__' => COBIDX_EMITIDA, '__MASCARA__' => COBIDX_DOC_MASCARA, '__CRU__' => COBIDX_DOC_CRU])))
        ->toBe('MASCARADO');

    // O contador da aba não pode mentir sobre a lista: `statusCounts` e `filtered` são dois
    // useMemo independentes. Invariante self-consistente — sobrevive ao tenant ganhar outras
    // cobranças; o que NÃO sobrevive é o contador divergir do que foi desenhado.
    expect($page->script(<<<'JS'
(() => {
  const linhas = [...document.querySelectorAll('tbody tr')].filter((r) => r.querySelectorAll('td').length > 1);
  const aba = [...document.querySelectorAll('button')]
    .find((b) => b.children.length === 2 && (b.children[0].textContent || '').trim() === 'Todos');
  if (!aba) return 'ABA-AUSENTE';
  const n = (aba.children[1].textContent || '').trim();
  return String(linhas.length) === n ? 'CONCORDAM:' + n : 'DIVERGEM: linhas=' + linhas.length + ' aba=' + n;
})()
JS))->toStartWith('CONCORDAM');

    $page->assertNoConsoleLogs();
})->with([[1280, 800], [1440, 900]]);

it('UC-COB-02 · render — o funil de 5 etapas chega à tela e concorda com o KPI "Em aberto"', function () {
    $page = cobIdxAbrirTela();
    cobIdxEsperar($page, COBIDX_JS_LINHAS, '3', 'as 3 linhas de fixture montarem');

    // 4 das 5 etapas têm rótulo único (a seta faz parte do texto); "Em aberto" colide com a
    // aba e com o KPI, então vai pelo probe estrutural abaixo.
    $page->assertSee('Funil de cobrança · mês corrente')
        ->assertSee('→ Lembrete')->assertSee('→ Cobrança ativa')
        ->assertSee('→ Vencidos +5d')->assertSee('→ Protesto');

    // Etapa do funil × KpiCard: os dois desenham `COUNT(status='emitida')`, derivado por dois
    // métodos distintos do `CobrancaQuery` e entregue em dois `<Deferred>` distintos.
    // Discriminados por ESTRUTURA, não por classe: os dois têm 3 filhos <div>, mas o 1º filho
    // da etapa é texto puro e o do KpiCard carrega ícone + rótulo.
    expect($page->script(<<<'JS'
(() => {
  const t = (el) => (el ? (el.textContent || '') : '').replace(/\u00A0/g, ' ').trim().replace(/\s+/g, ' ');
  const divs = [...document.querySelectorAll('div')];
  const etapa = divs.find((d) => d.children.length === 3
    && [...d.children].every((c) => c.tagName === 'DIV')
    && d.children[0].children.length === 0
    && t(d.children[0]) === 'Em aberto');
  if (!etapa) return 'ETAPA-AUSENTE';
  const card = divs.find((d) => d.children.length === 3
    && d.children[0].children.length > 0
    && t(d.children[0]) === 'Em aberto');
  if (!card) return 'KPI-AUSENTE';
  const kpi = t(card.children[2]).match(/^(\d+)\s+cobran/);
  if (!kpi) return 'KPI-SUB-ILEGIVEL:' + t(card.children[2]);
  return t(etapa.children[1]) === kpi[1]
    ? 'CONCORDAM:' + kpi[1]
    : 'DIVERGEM: funil=' + t(etapa.children[1]) + ' kpi=' + kpi[1];
})()
JS))->toStartWith('CONCORDAM');
});

it('render — cada status sai com o badge do seu estado e com o marcador visual', function () {
    $page = cobIdxAbrirTela();
    cobIdxEsperar($page, COBIDX_JS_LINHAS, '3', 'as 3 linhas de fixture montarem');

    // UX Anti-pattern do charter: "Status badge sem ícone". O badge renderiza um <span>
    // marcador + o rótulo; asserto os dois, sem cravar QUAL glifo (o charter diz "ícone
    // lucide", o código usa ponto colorido — divergência reportada, não resolvida aqui).
    expect($page->script(cobIdxProbe(<<<'JS'
(() => {
  const t = (el) => (el ? (el.textContent || '') : '').trim().replace(/\s+/g, ' ');
  return ['__EMITIDA__', '__PAGA__', '__VENCIDA__'].map((alvo) => {
    const tr = [...document.querySelectorAll('tbody tr')].find((r) => (r.textContent || '').includes(alvo));
    if (!tr) return 'LINHA-AUSENTE';
    const badge = tr.querySelectorAll('td')[6].querySelector('span');
    if (!badge) return 'BADGE-AUSENTE';
    return t(badge) + (badge.children.length > 0 ? '+marca' : '-marca');
  }).join('|');
})()
JS, ['__EMITIDA__' => COBIDX_EMITIDA, '__PAGA__' => COBIDX_PAGA, '__VENCIDA__' => COBIDX_VENCIDA])))
        ->toBe('Emitida+marca|Paga+marca|Vencida+marca');
});

it('render — o atalho / foca a busca e o filtro roda client-side, sem trocar a URL', function () {
    $page = cobIdxAbrirTela();
    cobIdxEsperar($page, COBIDX_JS_LINHAS, '3', 'as 3 linhas de fixture montarem');

    // Goal KB-9.75 do charter ("`/` foco busca"), hoje `[BACKLOG]` sem id no casos.md.
    // O listener vive em `document` (Index.tsx:161); disparo pelo body pra que ele suba.
    expect($page->script(<<<'JS'
(() => {
  document.body.dispatchEvent(new KeyboardEvent('keydown', { key: '/', bubbles: true }));
  const alvo = document.activeElement;
  return alvo && (alvo.getAttribute('placeholder') || '').startsWith('cliente, doc') ? 'FOCOU' : 'NAO-FOCOU';
})()
JS))->toBe('FOCOU');

    // Setter nativo + evento `input`: é o caminho que um input CONTROLADO do React aceita
    // (atribuir `.value` direto não dispara o onChange e o estado não muda).
    expect($page->script(cobIdxProbe(<<<'JS'
(() => {
  const input = document.activeElement;
  if (!input || input.tagName !== 'INPUT') return 'FOCO-PERDIDO';
  const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
  setter.call(input, '__TERMO__');
  input.dispatchEvent(new Event('input', { bubbles: true }));
  return 'OK';
})()
JS, ['__TERMO__' => 'COB VENCIDA'])))->toBe('OK');

    // Sobra 1 linha e a URL não muda: o filtro é client-side (Goal do charter + UX
    // Anti-pattern "sem window.location.reload()"). `busca` é o ÚNICO filtro desta tela que
    // não persiste em localStorage — por isso é o único que este arquivo pode exercitar.
    cobIdxEsperar($page, COBIDX_JS_LINHAS, '1', 'a busca reduzir a lista a 1 linha');
    expect($page->script('window.location.pathname'))->toBe(COBIDX_ROTA);
    $page->assertSee(COBIDX_VENCIDA)->assertNoConsoleLogs();
});

it('a11y — zero violação axe CRITICAL com a lista populada', function () {
    // Mesmo ratchet do A11yAxeBrowserTest: level 0 = CRITICAL only (piso honesto; `serious`
    // inclui contraste, fora do escopo deste PR). O ganho é auditar a tela COM DADO — a
    // tabela, os chips e o botão de copiar identificador só existem no DOM quando há linha.
    $page = cobIdxAbrirTela();
    cobIdxEsperar($page, COBIDX_JS_LINHAS, '3', 'as 3 linhas de fixture montarem');

    $page->assertNoAccessibilityIssues(level: 0);
});
