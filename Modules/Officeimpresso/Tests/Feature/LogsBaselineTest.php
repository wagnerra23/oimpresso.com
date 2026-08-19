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

    // `layouts/app.blade.php:56` lê `$_SERVER['REMOTE_ADDR']` DIRETO do
    // superglobal (não do Request), e em teste HTTP ele não existe: a view
    // estoura ErrorException → ViewException → a resposta vira pagina de erro,
    // e todo `viewData()` falha com "The response is not a view".
    //
    // `withServerVariables()` NÃO resolve: ele alimenta o server bag do Request
    // criado pelo Symfony, e não toca o superglobal que a Blade lê.
    //
    // Toda requisição HTTP real traz REMOTE_ADDR — o teste só está fornecendo o
    // que o ambiente sempre forneceria. Não é máscara de defeito de produto.
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';

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

it('nega a timeline pra autenticado sem permissão do módulo', function () {
    $business = $this->seededTenant();

    // O irmão LicencasAcessoPermissionTest cobre /licenca_log (a lista). A
    // TIMELINE não estava coberta por ninguém — e é a segunda tela desta onda.
    $user = makeOiLogsTestUser($business->id);
    $this->actingAs($user);

    $this->get('/officeimpresso/licenca_log/timeline/' . LICENCA_INEXISTENTE_BASE)
        ->assertForbidden();

    $user->forceDelete();
});

it('devolve 404 na timeline de máquina inexistente pra quem TEM permissão', function () {
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

it('publica os 4 KPIs da tela como inteiros', function () {
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

it('filtra por hd exato e ignora as outras máquinas', function () {
    $business = $this->seededTenant();
    $user = actingAsOiLeitor($this, $business->id);

    $alvo  = criaMaquinaOi($this, $business->id, ['hd' => $this->oiMarcador . '-HD-ALVO']);
    $outra = criaMaquinaOi($this, $business->id, ['hd' => $this->oiMarcador . '-HD-OUTRA']);

    $ids = idsDasMaquinas($this->get('/officeimpresso/licenca_log?hd=' . $this->oiMarcador . '-HD-ALVO'));

    expect($ids)->toContain($alvo)->and($ids)->not->toContain($outra);

    $user->forceDelete();
});

it('filtra por licenca_id e devolve só aquele equipamento', function () {
    $business = $this->seededTenant();
    $user = actingAsOiLeitor($this, $business->id);

    $alvo  = criaMaquinaOi($this, $business->id, ['user_win' => $this->oiMarcador . '-A']);
    $outra = criaMaquinaOi($this, $business->id, ['user_win' => $this->oiMarcador . '-B']);

    $ids = idsDasMaquinas($this->get('/officeimpresso/licenca_log?licenca_id=' . $alvo));

    expect($ids)->toContain($alvo)->and($ids)->not->toContain($outra);

    $user->forceDelete();
});

it('busca livre q acha por hostname e não traz quem não casa', function () {
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

it('estado_atual separa máquina bloqueada de ativa', function () {
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

it('não quebra com business_id não-numérico na query string', function () {
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

it('timeline devolve a máquina e só os acessos dela', function () {
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

it('timeline preserva o was_blocked do metadata (tri-estado da coluna Estado no Login)', function () {
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
