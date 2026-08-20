<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Modules\Officeimpresso\Entities\LicencaLog;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * BASELINE do comportamento ATUAL das telas `licenca_log` (index + timeline).
 *
 * Por que existe: é a F2 do MWART (ADR 0104) pra Onda 1 da migração React do
 * módulo — o plano está em memory/requisitos/Officeimpresso/RUNBOOK-logs.md.
 * A regra é travar o que a tela faz HOJE, servindo Blade, ANTES de encostar
 * nela. Quando a F3 trocar o `view()` por `Inertia::render`, estes testes
 * continuam válidos: eles asseguram o CONTRATO DE DADOS (quais linhas o filtro
 * devolve, quais KPIs existem, quem toma 403/404) — não o HTML.
 *
 * Isso é o que impede a migração de perder regra em silêncio, que é a pior
 * falha da classe (Onda 0d — paridade).
 *
 * ⚠️ Asserções são sobre AS LINHAS QUE ESTE TESTE CRIA, nunca sobre totais.
 * No CI o banco nasce fresco, mas no CT 100 ele PERSISTE entre runs
 * (proibicoes.md §Ambiente) — teste que conta o mundo inteiro passa num lugar
 * e falha no outro. Por isso cada caso carimba um marcador único e procura só
 * por ele.
 *
 * NÃO usa RefreshDatabase — UltimatePOS legacy (100+ migrations/triggers não
 * rodam em sqlite). Tenant canônico de teste = 98 (ADR 0358), NUNCA biz=4.
 *
 * @covers-us US-OI-001
 * @covers-us US-OI-002
 * @covers-us US-OI-003
 * @covers-us US-OI-006
 * @see Modules\Officeimpresso\Http\Controllers\LicencaLogController
 * @see memory/requisitos/Officeimpresso/RUNBOOK-logs.md
 * @see memory/requisitos/Officeimpresso/SPEC.md (US-OI-001)
 */

defined('PERM_OI_ACCESS_BASE') || define('PERM_OI_ACCESS_BASE', 'officeimpresso.access');
defined('LICENCA_INEXISTENTE_BASE') || define('LICENCA_INEXISTENTE_BASE', 999999999);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS necessário (ADR 0358).');
    }

    // ENUMERADOS, não descobertos um por rodada de CI: destes dois o caminho de
    // render depende, e nenhum tem default no código.
    //   REMOTE_ADDR      layouts/app.blade.php:56  ·  Util.php:1267 (último else)
    //   HTTP_USER_AGENT  helpers.php:72 `isMobile()`, chamada na app.blade.php:72
    // Os vizinhos HTTP_X_FORWARDED_FOR e HTTP_CLIENT_IP (Util.php:1260-65) já são
    // guardados por `! empty()`; HTTP_HOST só aparece no middleware Csv, fora daqui.
    //
    // `withServerVariables()` NÃO serve: ele alimenta o server bag do Request do
    // Symfony, e este código lê o SUPERGLOBAL direto.
    //
    // Toda requisição HTTP real traz os dois — o teste só fornece o que o ambiente
    // sempre forneceria. O `??=` preserva valor existente.
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] ??= 'Pest/CI (X11; Linux x86_64) HeadlessChrome';

    $this->oiMarcador = 'BASE' . strtoupper(substr(uniqid(), -8));
    $this->oiLicencaIds = [];
});

afterEach(function () {
    // Limpa só o que este teste criou — o marcador é a garantia de não encostar
    // em dado de ninguém. `licenca_log` é append-only por regra de negócio, mas
    // a regra protege o log de PRODUÇÃO; fixture de teste que criamos, apagamos.
    if (! empty($this->oiLicencaIds)) {
        LicencaLog::whereIn('licenca_id', $this->oiLicencaIds)->delete();
        DB::table('licenca_computador')->whereIn('id', $this->oiLicencaIds)->delete();
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// Guarda de acesso
// ─────────────────────────────────────────────────────────────────────────────

it('UC-TL-05 · nega a timeline pra autenticado sem permissão do módulo', function () {
    $business = $this->seededTenant();

    // O irmão LicencasAcessoPermissionTest cobre /licenca_log (a lista). A
    // TIMELINE não estava coberta por ninguém — e é a segunda tela desta onda.
    $user = makeOiLogsTestUser($business->id);
    $this->actingAs($user);

    $this->get('/officeimpresso/licenca_log/timeline/' . LICENCA_INEXISTENTE_BASE)
        ->assertForbidden();

    $user->forceDelete();
});

it('UC-TL-06 · devolve 404 na timeline de máquina inexistente pra quem TEM permissão', function () {
    $business = $this->seededTenant();
    $user = actingAsOiLeitor($this, $business->id);

    // A ordem importa: a guarda roda ANTES do lookup. Quem tem permissão
    // atravessa e morre no abort(404) — é assim que se distingue "não pode ver"
    // de "não existe". A F3 tem que preservar os dois códigos.
    $this->get('/officeimpresso/licenca_log/timeline/' . LICENCA_INEXISTENTE_BASE)
        ->assertNotFound();

    $user->forceDelete();
});

// ─────────────────────────────────────────────────────────────────────────────
// KPIs
// ─────────────────────────────────────────────────────────────────────────────

it('UC-LOGS-02 · publica os 4 KPIs da tela como inteiros', function () {
    $business = $this->seededTenant();
    $user = actingAsOiLeitor($this, $business->id);

    $kpis = $this->get('/officeimpresso/licenca_log')->viewData('kpis');

    // Valor exato é do mundo (muda a cada run); o CONTRATO é quais chaves
    // existem e que são numéricas — é isso que a Page React vai consumir.
    expect($kpis)->toHaveKeys([
        'total_maquinas', 'maquinas_bloqueadas', 'empresas_bloqueadas', 'chamadas_24h',
    ]);
    foreach ($kpis as $nome => $valor) {
        expect($valor)->toBeInt("KPI {$nome} deveria ser inteiro");
    }

    $user->forceDelete();
});

// ─────────────────────────────────────────────────────────────────────────────
// Filtros — o coração do que a migração pode perder em silêncio
// ─────────────────────────────────────────────────────────────────────────────

it('UC-LOGS-03 · filtra por hd exato e ignora as outras máquinas', function () {
    $business = $this->seededTenant();
    $user = actingAsOiLeitor($this, $business->id);

    $alvo  = criaMaquinaOi($this, $business->id, ['hd' => $this->oiMarcador . '-HD-ALVO']);
    $outra = criaMaquinaOi($this, $business->id, ['hd' => $this->oiMarcador . '-HD-OUTRA']);

    $ids = idsDasMaquinas($this->get('/officeimpresso/licenca_log?hd=' . $this->oiMarcador . '-HD-ALVO'));

    expect($ids)->toContain($alvo)->and($ids)->not->toContain($outra);

    $user->forceDelete();
});

it('UC-LOGS-04 · filtra por licenca_id e devolve só aquele equipamento', function () {
    $business = $this->seededTenant();
    $user = actingAsOiLeitor($this, $business->id);

    $alvo  = criaMaquinaOi($this, $business->id, ['user_win' => $this->oiMarcador . '-A']);
    $outra = criaMaquinaOi($this, $business->id, ['user_win' => $this->oiMarcador . '-B']);

    $ids = idsDasMaquinas($this->get('/officeimpresso/licenca_log?licenca_id=' . $alvo));

    expect($ids)->toContain($alvo)->and($ids)->not->toContain($outra);

    $user->forceDelete();
});

it('UC-LOGS-05 · busca livre q acha por hostname e não traz quem não casa', function () {
    $business = $this->seededTenant();
    $user = actingAsOiLeitor($this, $business->id);

    $alvo  = criaMaquinaOi($this, $business->id, ['hostname' => $this->oiMarcador . 'ACHAVEL']);
    $outra = criaMaquinaOi($this, $business->id, ['hostname' => $this->oiMarcador . 'ESCONDIDA']);

    // A busca do controller varre nome/CNPJ/razão da empresa E hd/user_win/
    // hostname/ip_interno da máquina. Este caso trava o ramo da máquina.
    $ids = idsDasMaquinas($this->get('/officeimpresso/licenca_log?q=' . $this->oiMarcador . 'ACHAVEL'));

    expect($ids)->toContain($alvo)->and($ids)->not->toContain($outra);

    $user->forceDelete();
});

it('UC-LOGS-06 · estado_atual separa máquina bloqueada de ativa', function () {
    $business = $this->seededTenant();
    $user = actingAsOiLeitor($this, $business->id);

    $bloqueada = criaMaquinaOi($this, $business->id, ['bloqueado' => 1, 'user_win' => $this->oiMarcador . '-BLOQ']);
    $ativa     = criaMaquinaOi($this, $business->id, ['bloqueado' => 0, 'user_win' => $this->oiMarcador . '-ATIVA']);

    $bloqueadas = idsDasMaquinas($this->get('/officeimpresso/licenca_log?estado_atual=bloqueada'));
    expect($bloqueadas)->toContain($bloqueada)->and($bloqueadas)->not->toContain($ativa);

    // Os dois lados: um filtro que só acerta metade é filtro quebrado, e sem o
    // caso oposto o teste passaria com um WHERE que devolve tudo.
    $ativas = idsDasMaquinas($this->get('/officeimpresso/licenca_log?estado_atual=ativa'));
    expect($ativas)->toContain($ativa)->and($ativas)->not->toContain($bloqueada);

    $user->forceDelete();
});

it('UC-LOGS-07 · não quebra com business_id não-numérico na query string', function () {
    $business = $this->seededTenant();
    $user = actingAsOiLeitor($this, $business->id);

    // Regressão da extração de `buildMaquinasPayload()` (F2): o valor vem de
    // `$request->query('business_id')`, que é STRING. Tipar o parâmetro como
    // `?int` transformaria isto num TypeError 500. O comportamento correto —
    // e o de antes da extração — é devolver lista vazia.
    $this->get('/officeimpresso/licenca_log?business_id=abc')->assertOk();

    $user->forceDelete();
});

// ─────────────────────────────────────────────────────────────────────────────
// Timeline — payload
// ─────────────────────────────────────────────────────────────────────────────

it('UC-TL-08 · timeline devolve a máquina e só os acessos dela', function () {
    $business = $this->seededTenant();
    $user = actingAsOiLeitor($this, $business->id);

    $alvo  = criaMaquinaOi($this, $business->id, ['user_win' => $this->oiMarcador . '-TL']);
    $outra = criaMaquinaOi($this, $business->id, ['user_win' => $this->oiMarcador . '-TL-OUTRA']);

    criaLogOi($alvo, $business->id, ['http_status' => 200, 'metadata' => ['was_blocked' => false]]);
    criaLogOi($alvo, $business->id, ['http_status' => 403, 'metadata' => ['was_blocked' => true]]);
    criaLogOi($outra, $business->id, ['http_status' => 200]);

    $resposta = $this->get('/officeimpresso/licenca_log/timeline/' . $alvo);

    expect($resposta->viewData('maquina')->id)->toBe($alvo);

    $logs = $resposta->viewData('logs');
    expect($logs)->toHaveCount(2)
        ->and($logs->pluck('licenca_id')->unique()->all())->toBe([$alvo]);

    $user->forceDelete();
});

it('UC-TL-09 · timeline preserva o was_blocked do metadata (tri-estado da coluna Estado no Login)', function () {
    $business = $this->seededTenant();
    $user = actingAsOiLeitor($this, $business->id);

    $alvo = criaMaquinaOi($this, $business->id, ['user_win' => $this->oiMarcador . '-META']);
    criaLogOi($alvo, $business->id, ['http_status' => 403, 'metadata' => ['was_blocked' => true]]);

    // Pegadinha 5 e 7 do RUNBOOK: `was_blocked` é tri-estado e `metadata` chega
    // como string OU array conforme o caminho de leitura. Se a F3 ler errado, a
    // coluna mente sobre o estado da licença no momento do acesso.
    $log  = $this->get('/officeimpresso/licenca_log/timeline/' . $alvo)->viewData('logs')->first();
    $meta = is_string($log->metadata) ? json_decode($log->metadata, true) : $log->metadata;

    expect($meta['was_blocked'])->toBeTrue();

    $user->forceDelete();
});

// ─────────────────────────────────────────────────────────────────────────────
// Caminho dual da LISTA (F3) — @covers-us US-OI-002
//
// Os casos da TIMELINE moram no PR dela: teste de render só entra junto da page
// que ele renderiza, mesma razão pela qual o `Inertia::render` saiu da F2
// (OrphanRenderGateTest — render órfão é 500 esperando a flag ligar).
// ─────────────────────────────────────────────────────────────────────────────

it('UC-LOGS-08 · com a flag OFF a lista continua servindo Blade', function () {
    $business = $this->seededTenant();
    $user = actingAsOiLeitor($this, $business->id);

    // Default é OFF: o GrowthBook não conhece a chave e o `fallbackDefaults` do
    // FeatureFlagService não a lista. É o estado em produção até [W] ligar.
    forcaFlagV2($this, false);

    // `viewData` só existe em resposta de view: se um dia isto virar Inertia sem
    // alguém ligar a flag, o teste quebra aqui.
    expect($this->get('/officeimpresso/licenca_log')->viewData('kpis'))->toBeArray();

    $user->forceDelete();
});

it('UC-LOGS-09 · com a flag ON a lista responde Inertia com filters e permissions', function () {
    $business = $this->seededTenant();
    $user = actingAsOiLeitor($this, $business->id);

    forcaFlagV2($this, true);

    $this->get('/officeimpresso/licenca_log?q=' . $this->oiMarcador)
        ->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Officeimpresso/Logs/Index')
            // `maquinas` e `kpis` NÃO entram aqui de propósito — são
            // `Inertia::defer`, então não vêm no payload inicial.
            ->has('filters')
            ->where('filters.q', $this->oiMarcador)
            ->has('permissions.pode_ver_todas_empresas')
        );

    $user->forceDelete();
});

it('UC-LOGS-10 · a flag ON NÃO afrouxa a guarda de acesso', function () {
    $business = $this->seededTenant();

    // O caminho novo não pode virar porta dos fundos: quem não podia ver a tela
    // no Blade continua tomando 403 no Inertia. A guarda roda antes do render.
    $user = makeOiLogsTestUser($business->id);
    $this->actingAs($user);
    forcaFlagV2($this, true);

    $this->get('/officeimpresso/licenca_log')->assertForbidden();

    $user->forceDelete();
});

it('UC-TL-07 · com a flag ON a timeline responde Inertia com a máquina', function () {
    $business = $this->seededTenant();
    $user = actingAsOiLeitor($this, $business->id);
    $alvo = criaMaquinaOi($this, $business->id, ['user_win' => $this->oiMarcador . '-ON']);

    forcaFlagV2($this, true);

    $this->get('/officeimpresso/licenca_log/timeline/' . $alvo)
        ->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Officeimpresso/Logs/Timeline')
            ->where('maquina.id', $alvo)
        );

    $user->forceDelete();
});

it('UC-TL-10 · a flag ON NÃO afrouxa a guarda da timeline', function () {
    $business = $this->seededTenant();

    // Contraparte do UC-LOGS-10 pro caminho da timeline: o render novo não pode
    // virar porta dos fundos. A guarda roda antes.
    $user = makeOiLogsTestUser($business->id);
    $this->actingAs($user);
    forcaFlagV2($this, true);

    $this->get('/officeimpresso/licenca_log/timeline/' . LICENCA_INEXISTENTE_BASE)->assertForbidden();

    $user->forceDelete();
});

// ─────────────────────────────────────────────────────────────────────────────
// F4 — os itens `alta` do logs-parity.md que ainda não tinham teste de comportamento.
// Cada um cita o id do UC no título (G-2) e QUEBRA se o campo/regra sumir.
// ─────────────────────────────────────────────────────────────────────────────

it('UC-LOGS-11 · quem tem officeimpresso.access enxerga máquina de OUTRA empresa (cross-empresa é POR DESIGN)', function () {
    $casa = $this->seededTenant();
    $cliente = $this->seededSupportClientTenant();
    $user = actingAsOiLeitor($this, $casa->id);

    $minha = criaMaquinaOi($this, $casa->id, ['user_win' => $this->oiMarcador . '-CASA']);
    $doCliente = criaMaquinaOi($this, $cliente->id, ['user_win' => $this->oiMarcador . '-CLIENTE']);

    // Tier 0, e o sentido aqui é o INVERSO do usual: a WR2 é a FORNECEDORA do desktop
    // e os licenciados são outros businesses, então o suporte PRECISA ver a máquina do
    // cliente (docblock de `podeVerTodasEmpresas()`, relato do Luiz em 29/07). Este teste
    // existe pra que estreitar isso seja uma decisão DELIBERADA, não um efeito colateral:
    // a consulta é `DB::table` cru (sem global scope), logo nada além deste teste avisa.
    $ids = idsDasMaquinas($this->get('/officeimpresso/licenca_log'));
    expect($ids)->toContain($minha)->and($ids)->toContain($doCliente);

    $user->forceDelete();
});

it('UC-LOGS-12 · filtro business_id devolve só as máquinas daquela empresa', function () {
    $casa = $this->seededTenant();
    $cliente = $this->seededSupportClientTenant();
    $user = actingAsOiLeitor($this, $casa->id);

    $minha = criaMaquinaOi($this, $casa->id, ['user_win' => $this->oiMarcador . '-F-CASA']);
    $doCliente = criaMaquinaOi($this, $cliente->id, ['user_win' => $this->oiMarcador . '-F-CLI']);

    // O contraponto do UC-LOGS-11: a visão é ampla por default, e o filtro é o que
    // estreita. Os dois sentidos, senão um WHERE que devolve tudo passaria.
    $ids = idsDasMaquinas($this->get('/officeimpresso/licenca_log?business_id=' . $cliente->id));
    expect($ids)->toContain($doCliente)->and($ids)->not->toContain($minha);

    $user->forceDelete();
});

it('UC-LOGS-13 · os filtros compõem: business_id + hd juntos estreitam, e sozinho o hd não', function () {
    $casa = $this->seededTenant();
    $cliente = $this->seededSupportClientTenant();
    $user = actingAsOiLeitor($this, $casa->id);

    $hd = $this->oiMarcador . '-HD-COMPOE';
    $mesmaHdCasa    = criaMaquinaOi($this, $casa->id,    ['hd' => $hd]);
    $mesmaHdCliente = criaMaquinaOi($this, $cliente->id, ['hd' => $hd]);
    $outraHdCasa    = criaMaquinaOi($this, $casa->id,    ['hd' => $hd . '-X']);

    // Composição de verdade: os dois filtros ao mesmo tempo têm que ser MAIS estreitos
    // que cada um sozinho. Sem o caso "hd sozinho traz os dois", um AND quebrado que
    // ignorasse o business_id passaria despercebido.
    $juntos = idsDasMaquinas($this->get('/officeimpresso/licenca_log?business_id=' . $casa->id . '&hd=' . $hd));
    expect($juntos)->toContain($mesmaHdCasa)
        ->and($juntos)->not->toContain($mesmaHdCliente)
        ->and($juntos)->not->toContain($outraHdCasa);

    $soHd = idsDasMaquinas($this->get('/officeimpresso/licenca_log?hd=' . $hd));
    expect($soHd)->toContain($mesmaHdCasa)->and($soHd)->toContain($mesmaHdCliente);

    $user->forceDelete();
});

it('UC-TL-02 · a timeline entrega business_blocked e machine_blocked separados, nos três estados', function () {
    $casa = $this->seededTenant();
    $user = actingAsOiLeitor($this, $casa->id);
    forcaFlagV2($this, true);

    // A PRECEDÊNCIA (empresa > máquina > ativa) é expressão do Timeline.tsx:81-85. O que o
    // backend controla — e o que o Blade computava no servidor antes da migração — são as
    // DUAS flags chegarem SEPARADAS. Se alguém tirar `business_blocked` do select, a tela
    // passa a chamar de "máquina bloqueada" um cliente inteiro bloqueado. É esse o dano que
    // este teste pega; a expressão em si só um teste de browser alcança (não existe hoje).
    $ativa = criaMaquinaOi($this, $casa->id, ['bloqueado' => 0]);
    $this->get('/officeimpresso/licenca_log/timeline/' . $ativa)
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('maquina.business_blocked', false)
            ->where('maquina.machine_blocked', false));

    $bloqueada = criaMaquinaOi($this, $casa->id, ['bloqueado' => 1]);
    $this->get('/officeimpresso/licenca_log/timeline/' . $bloqueada)
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('maquina.business_blocked', false)
            ->where('maquina.machine_blocked', true));

    \DB::table('business')->where('id', $casa->id)->update(['officeimpresso_bloqueado' => 1]);
    try {
        $this->get('/officeimpresso/licenca_log/timeline/' . $bloqueada)
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('maquina.business_blocked', true)
                ->where('maquina.machine_blocked', true));
    } finally {
        \DB::table('business')->where('id', $casa->id)->update(['officeimpresso_bloqueado' => 0]);
    }

    $user->forceDelete();
});

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeOiLogsTestUser(int $businessId): User
{
    // Sem role nenhuma — com role o Gate::before faz bypass e o teste de guarda
    // passaria por motivo errado.
    return User::create([
        'business_id' => $businessId,
        'first_name'  => 'OI',
        'surname'     => 'Logs',
        'username'    => 'oi_logs_' . $businessId . '_' . uniqid(),
        'email'       => 'oi_logs_' . $businessId . '_' . uniqid() . '@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
}

/** Usuário com `officeimpresso.access` já logado e com sessão montada. */
function actingAsOiLeitor($test, int $businessId): User
{
    Permission::firstOrCreate(['name' => PERM_OI_ACCESS_BASE, 'guard_name' => 'web']);

    $user = makeOiLogsTestUser($businessId);
    $user->givePermissionTo(PERM_OI_ACCESS_BASE);
    $test->actingAs($user);

    // NÃO semear `session(['user.business_id' => ...])` aqui.
    // O `SetSessionData` só monta a sessão quando ela ainda NÃO tem `user`
    // (SetSessionData.php:29). Semear na mão faz ele RETORNAR CEDO — e aí
    // `currency`, `business` e `financial_year` nunca entram na sessão, e a
    // `layouts/app.blade.php:61` (`session('currency')['code']`) estoura
    // "Trying to access array offset on null". Deixar o middleware montar é o
    // que a requisição real faz; ele preenche `user.business_id` a partir do
    // `Auth::user()`, com o MESMO valor que eu estava semeando.

    return $user;
}

/** Cria uma máquina de fixture e registra o id pra limpeza no afterEach. */
function criaMaquinaOi($test, int $businessId, array $attrs = []): int
{
    $id = DB::table('licenca_computador')->insertGetId(array_merge([
        'business_id' => $businessId,
        'hd'          => $test->oiMarcador . '-' . uniqid(),
        'user_win'    => $test->oiMarcador,
        'hostname'    => $test->oiMarcador,
        'ip_interno'  => '10.0.0.1',
        'bloqueado'   => 0,
    ], $attrs));

    $test->oiLicencaIds[] = $id;

    return $id;
}

function criaLogOi(int $licencaId, int $businessId, array $attrs = []): void
{
    LicencaLog::create(array_merge([
        'licenca_id'  => $licencaId,
        'business_id' => $businessId,
        'event'       => 'login_success',
        // O controller filtra a timeline por ESTES dois campos — fixture que
        // erra qualquer um deles some da tela e o teste vira falso-negativo.
        'source'      => 'delphi_middleware',
        'endpoint'    => '/connector/api/processa-dados-cliente',
        'http_status' => 200,
        'created_at'  => now(),
    ], $attrs));
}

/** Extrai os ids das máquinas que a tela devolveu. */
function idsDasMaquinas($resposta): array
{
    return collect($resposta->viewData('maquinas'))->pluck('licenca_id')->all();
}

/**
 * Força o veredito da feature flag sem depender do GrowthBook do CT 100 —
 * teste que sai na rede não é teste, é sorte.
 *
 * Fica no FIM do arquivo DE PROPÓSITO. Quando ele morava no começo da seção de
 * helpers, uma remoção por intervalo "deste docblock até o idsDasMaquinas"
 * engoliu os quatro helpers que estavam no meio (§5 2026-08-02). No fim, um
 * intervalo assim não tem vizinho pra comer.
 */
function forcaFlagV2($test, bool $ligada): void
{
    // `app()->instance()`, NÃO `$test->instance()`: o `instance()` do
    // Illuminate\Foundation\Testing\TestCase é PROTECTED, e daqui — função no
    // escopo global — a chamada estoura
    // "Call to protected method ... ::instance() from global scope".
    // Medido no CT 100 em 2026-08-19: 5 dos 5 casos de flag morriam nisso.
    // O container é o mesmo, então o efeito é idêntico.
    unset($test);

    app()->instance(\App\Services\FeatureFlagService::class, new class($ligada) extends \App\Services\FeatureFlagService
    {
        public function __construct(private bool $ligada) {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return $this->ligada;
        }
    });
}
