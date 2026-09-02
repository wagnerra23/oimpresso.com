<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Jana\Console\Commands\HealthCheckCommand;
use Modules\Jana\Console\Commands\JanaDriftSentinelCommand;
use Modules\Jana\Console\Commands\SystemAuditCommand;

uses(Tests\TestCase::class);

/**
 * Bite-tests dos sentinelas de governança (auditoria de sentinelas 2026-06-20).
 *
 * Problema "a suite mente": jana:health-check e jana:system-audit MORDEM no código
 * (return FAILURE quando um check duro falha), mas nenhum teste PROVAVA a mordida —
 * o smoke (JanaHealthCheckTest) só assere exit ∈ {0,1} e o system-audit tinha ZERO
 * testes. Um refactor poderia silenciar o exit code sem nenhum teste ficar vermelho.
 *
 * Estratégia DB-agnóstica (o schema completo só roda em MySQL no CI, não SQLite):
 *   1. UNIT do veredito (allChecksOk) — prova a REGRA check→ok, incluindo a sutileza
 *      advisory, sem tocar DB.
 *   2. INTEGRAÇÃO — roda o comando REAL e exige exit == veredito(checks do JSON).
 *      Pega "sempre retorna 0" (ou "sempre 1") em qualquer estado de DB.
 */

// ── 1. UNIT do veredito (determinístico, sem DB) ─────────────────────────────

test('health-check veredito: check duro ok=false DERRUBA o gate', function () {
    expect(HealthCheckCommand::allChecksOk([
        ['name' => 'a', 'ok' => true],
        ['name' => 'b', 'ok' => false], // duro falho
    ]))->toBeFalse();
});

test('health-check veredito: tudo ok = passa', function () {
    expect(HealthCheckCommand::allChecksOk([
        ['name' => 'a', 'ok' => true],
        ['name' => 'b', 'ok' => true],
    ]))->toBeTrue();
});

test('health-check veredito: advisory ok=false NÃO derruba o gate', function () {
    expect(HealthCheckCommand::allChecksOk([
        ['name' => 'a', 'ok' => true],
        ['name' => 'charter_missing', 'ok' => false, 'advisory' => true],
    ]))->toBeTrue();
});

test('system-audit veredito: qualquer check ok=false derruba (sem advisory)', function () {
    expect(SystemAuditCommand::allChecksOk([
        ['name' => 'a', 'ok' => true],
        ['name' => 'b', 'ok' => false],
    ]))->toBeFalse();

    expect(SystemAuditCommand::allChecksOk([
        ['name' => 'a', 'ok' => true],
    ]))->toBeTrue();
});

// ── 1b. write-canary: predicado de PRIVILÉGIO DE ESCRITA (incidente 2026-06-21) ──
//
// O GRANT INSERT revogado no Hostinger deixou os 17 checks verdes com prod sem
// conseguir escrever. isWriteDenied distingue "negado por privilégio" (MySQL 1142)
// de erro benigno — é o que faz o check db_write_canary MORDER no caso certo.

test('isWriteDenied: 1142 / command denied = negação de escrita', function () {
    expect(HealthCheckCommand::isWriteDenied(
        'SQLSTATE[42000]: Syntax error or access violation: 1142 INSERT command denied to user'
    ))->toBeTrue();
    expect(HealthCheckCommand::isWriteDenied('INSERT command denied to user foo'))->toBeTrue();
});

test('isWriteDenied: erro benigno / sentinela de rollback NÃO é negação', function () {
    expect(HealthCheckCommand::isWriteDenied('SQLSTATE[HY000]: server has gone away'))->toBeFalse();
    expect(HealthCheckCommand::isWriteDenied('__jana_write_canary_rollback__'))->toBeFalse();
    expect(HealthCheckCommand::isWriteDenied(''))->toBeFalse();
});

// db_storage_quota: a CAUSA do incidente (Hostinger revoga escrita ao bater a cota).
test('dbQuotaExceeded: >= warnPct acende; abaixo não', function () {
    expect(HealthCheckCommand::dbQuotaExceeded(5530.0, 6144, 90))->toBeTrue();   // ~90%
    expect(HealthCheckCommand::dbQuotaExceeded(6180.0, 6144, 90))->toBeTrue();   // estourado (incidente)
    expect(HealthCheckCommand::dbQuotaExceeded(816.0, 6144, 90))->toBeFalse();   // ~13% (pós-fix)
    expect(HealthCheckCommand::dbQuotaExceeded(100.0, 0, 90))->toBeFalse();      // cota desconhecida = não alarma
});

// ── 2. INTEGRAÇÃO: exit code == veredito (prova que NÃO é constante) ──────────

/** Extrai o bloco JSON do output do comando (pode haver linhas de debug antes). */
function biteJson(string $output): array
{
    $start = strpos($output, '{');
    expect($start)->not->toBeFalse('Output não contém JSON');

    return json_decode(substr($output, (int) $start), true);
}

test('jana:health-check: exit code BATE com o veredito dos checks', function () {
    $exit = Artisan::call('jana:health-check', ['--json' => true]);
    $json = biteJson(Artisan::output());

    $esperaFalha = collect($json['checks'])
        ->contains(fn ($c) => ! ($c['ok'] ?? false) && ! ($c['advisory'] ?? false));

    expect($exit)->toBe($esperaFalha ? 1 : 0);
});

test('db_write_canary: escrita OK no DB de teste e NÃO persiste linha', function () {
    Artisan::call('jana:health-check', ['--json' => true]);
    $json = biteJson(Artisan::output());

    $canary = collect($json['checks'])->firstWhere('name', 'db_write_canary');
    expect($canary)->not->toBeNull();
    // Tabela existe no CI (migration roda) → o INSERT de prova passa e é revertido.
    expect($canary['ok'])->toBeTrue();
    expect($canary['value'])->toBe('writable');

    // Rollback de verdade: a prova nunca deixa linha pra trás.
    expect(\Illuminate\Support\Facades\DB::table(HealthCheckCommand::WRITE_CANARY_TABLE)->count())->toBe(0);
});

test('jana:system-audit: exit code BATE com o veredito dos checks', function () {
    // Evita HTTP real no check de observability (rápido + sem flakiness de rede).
    putenv('LANGFUSE_HOST');
    unset($_ENV['LANGFUSE_HOST'], $_SERVER['LANGFUSE_HOST']);

    $exit = Artisan::call('jana:system-audit', ['--json' => true]);
    $json = biteJson(Artisan::output());

    $esperaFalha = collect($json['checks'])->contains(fn ($c) => ! ($c['ok'] ?? false));

    expect($exit)->toBe($esperaFalha ? 1 : 0);
});

test('jana:system-audit registrado no artisan list', function () {
    Artisan::call('list');
    expect(Artisan::output())->toContain('jana:system-audit');
});

// ── 3. drift-sentinel: skip-guard HONESTO (sem OPENAI_API_KEY = dormant, não ruído) ──
//
// O canary semanal precisa de OPENAI_API_KEY pra rodar real. SEM a chave ele NÃO pode
// medir — antes do skip-guard (2026-06-20) isso virava falso "DRIFT 100%" + onFailure
// do cron toda semana. Agora sai DORMANT (exit 0, status=dormant) → ⊘ no agregador.
// Estes testes provam que (a) a regra do veredito é a esperada e (b) o exit NÃO morde
// o cron sem chave — pegando "o guard sumiu" OU "o guard pula sempre" num refactor.

test('drift-sentinel veredito: sem mock E sem chave = DORMANT', function () {
    expect(JanaDriftSentinelCommand::isDormant(false, null))->toBeTrue();
    expect(JanaDriftSentinelCommand::isDormant(false, ''))->toBeTrue();
});

test('drift-sentinel veredito: mock OU chave presente = NÃO dormant', function () {
    expect(JanaDriftSentinelCommand::isDormant(true, null))->toBeFalse();    // mock dispensa chave
    expect(JanaDriftSentinelCommand::isDormant(false, 'sk-xxx'))->toBeFalse(); // chave presente
});

test('drift-sentinel: SEM OPENAI_API_KEY sai DORMANT (exit 0 — não morde o cron)', function () {
    // config() vence env() e config:cache — fonte cache-safe que o guard usa.
    config(['openai.api_key' => null, 'services.openai.api_key' => null]);

    $exit = Artisan::call('jana:drift-sentinel', ['--json' => true]);
    $json = biteJson(Artisan::output());

    expect($exit)->toBe(0);                  // não estoura o onFailure do schedule
    expect($json['status'])->toBe('dormant');
    expect($json['ok'])->toBeTrue();
});

test('drift-sentinel --status: armed quando a chave existe, dormant quando não', function () {
    config(['openai.api_key' => 'sk-test-armed', 'services.openai.api_key' => null]);
    $exitArmed = Artisan::call('jana:drift-sentinel', ['--status' => true, '--json' => true]);
    $armed = biteJson(Artisan::output());
    expect($exitArmed)->toBe(0);
    expect($armed['status'])->toBe('armed');
    expect($armed['armed'])->toBeTrue();

    config(['openai.api_key' => null, 'services.openai.api_key' => null]);
    $exitDormant = Artisan::call('jana:drift-sentinel', ['--status' => true, '--json' => true]);
    $dormant = biteJson(Artisan::output());
    expect($exitDormant)->toBe(0);
    expect($dormant['status'])->toBe('dormant');
    expect($dormant['armed'])->toBeFalse();
});

test('drift-sentinel: ARMADO roda o veredito (mock 0.85 vs baseline 0.85 = ok)', function () {
    // Prova que o guard NÃO pula quando dá pra rodar (mock dispensa rede/chave).
    $exit = Artisan::call('jana:drift-sentinel', ['--mock' => true, '--json' => true]);
    $json = biteJson(Artisan::output());

    expect($exit)->toBe(0);
    expect($json['status'])->toBe('ok');
    expect($json['ok'])->toBeTrue();
});

// ── 3. llm_provider_quota: bite-test do alarme de provedor sem credito ────
//
// Incidente 2026-08-31 -> 09-02 (L-OP-005): sem credito a Jana emudeceu, e o
// `custo_brain_b_24h` pintou VERDE porque zero passa no teto "custo <= X". Abaixo:
// o predicado morde no caso certo E distingue os dois 429, que chegam com o MESMO
// status e pedem acoes OPOSTAS.

test('quota: enum de falta de credito = sem-credito e DERRUBA o gate', function () {
    foreach (HealthCheckCommand::QUOTA_CODIGOS_SEM_CREDITO as $codigo) {
        $r = HealthCheckCommand::evaluateLlmProviderQuota(true, true, 429, $codigo);

        expect($r['state'])->toBe('sem-credito', "codigo {$codigo}");
        expect($r['ok'])->toBeFalse();
        expect($r['advisory'] ?? false)->toBeFalse('sem credito tem que derrubar o exit code');

        // O elo que fecha: check duro falho derruba o veredito do comando.
        expect(HealthCheckCommand::allChecksOk([
            ['name' => 'llm_provider_quota', 'ok' => $r['ok'], 'advisory' => $r['advisory'] ?? false],
        ]))->toBeFalse();
    }
});

// CONTROLE NEGATIVO -- o 429 que NAO e falta de credito. Se este virar `sem-credito`,
// o alarme vira ruido e some a distincao que o PR #6540 ensinou.
test('quota: 429 rate_limit_exceeded NAO e sem-credito (advisory, nao pagina)', function () {
    $r = HealthCheckCommand::evaluateLlmProviderQuota(true, true, 429, 'rate_limit_exceeded');

    expect($r['state'])->toBe('rate-limit');
    expect($r['advisory'] ?? false)->toBeTrue();
    expect(HealthCheckCommand::allChecksOk([
        ['name' => 'llm_provider_quota', 'ok' => $r['ok'], 'advisory' => true],
    ]))->toBeTrue('rate-limit e transitorio: nao derruba o cron');
});

test('quota: demais estados do contrato', function () {
    // sem 429 = verde
    expect(HealthCheckCommand::evaluateLlmProviderQuota(true, true, 200, null)['state'])->toBe('ok');
    expect(HealthCheckCommand::evaluateLlmProviderQuota(true, true, 200, null)['ok'])->toBeTrue();

    // sem chave / fora de prod = skip limpo
    $skip = HealthCheckCommand::evaluateLlmProviderQuota(false, false, null, null);
    expect($skip['state'])->toBe('nao-configurado');
    expect($skip['ok'])->toBeTrue();

    // 401 = credencial recusada, DURO (a Jana responde erro em toda chamada)
    $auth = HealthCheckCommand::evaluateLlmProviderQuota(true, true, 401, 'invalid_api_key');
    expect($auth['state'])->toBe('credencial-recusada');
    expect($auth['advisory'] ?? false)->toBeFalse();
});

// Lapide 2026-07-29: "nao consegui medir" NUNCA colapsa num estado do objeto medido.
test('quota: inacessivel = nao-medido advisory (nem verde, nem vermelho duro)', function () {
    $r = HealthCheckCommand::evaluateLlmProviderQuota(true, false, null, null);

    expect($r['state'])->toBe('nao-medido');
    expect($r['ok'])->toBeFalse();
    expect($r['advisory'] ?? false)->toBeTrue();
});

// ── 3b. INTEGRACAO: o check monta o veredito e ESCALA pro canal HITL ──────

/**
 * Roda o check com a sonda fakeada e devolve [resultado, escalador stub].
 *
 * Stub anonimo em vez de Mockery: o HitlEscalationService e `final` de proposito
 * (transporte, nao ponto de extensao) e Mockery recusa classe final. O stub tambem
 * assere MAIS que um spy -- guarda os argumentos, entao da pra provar a chave
 * deterministica `LLM-QUOTA`, que e o que impede a 39a task duplicada.
 */
function biteQuota(array $body, int $status): array
{
    Illuminate\Support\Facades\Http::fake([
        'api.openai.com/*' => Illuminate\Support\Facades\Http::response($body, $status),
    ]);
    config(['services.openai.api_key' => 'sk-fake-para-teste']);
    app()->instance('env', 'live'); // a sonda so roda em producao

    $escalador = new class
    {
        /** @var list<array<string, string>> */
        public array $chamadas = [];

        public function escalar(
            string $chave,
            string $titulo,
            string $descricao,
            string $modulo,
            string $prioridade = 'p2',
            string $origem = 'sentinela',
        ) {
            $this->chamadas[] = compact('chave', 'modulo', 'prioridade', 'origem');

            return null;
        }
    };
    app()->instance(Modules\Jana\Services\TaskRegistry\HitlEscalationService::class, $escalador);

    $m = (new ReflectionClass(HealthCheckCommand::class))->getMethod('checkLlmProviderQuota');
    $m->setAccessible(true);

    return [$m->invoke(app(HealthCheckCommand::class)), $escalador];
}

test('quota: fixture 429 LITERAL de prod -> check falha E escala HITL-LLM-QUOTA', function () {
    // Corpo medido na sonda ao vivo em 2026-09-02, nao inventado.
    [$r, $escalador] = biteQuota(['error' => [
        'message' => 'You have no credits remaining.',
        'type' => 'insufficient_quota',
        'param' => null,
        'code' => 'credit_balance_exhausted',
    ]], 429);

    expect($r['name'])->toBe('llm_provider_quota');
    expect($r['ok'])->toBeFalse();
    expect($r['value'])->toBe('sem-credito');
    expect($r['message'])->toContain('SEM CRÉDITO');

    // Escalar e o ponto: detectar sem escalar repete o nag perpetuo que o
    // HitlEscalationService nasceu pra matar. A chave TEM que ser deterministica --
    // com data/contagem dentro dela, cada run criaria uma task nova.
    expect($escalador->chamadas)->toHaveCount(1);
    expect($escalador->chamadas[0]['chave'])->toBe('LLM-QUOTA');
    expect($escalador->chamadas[0]['prioridade'])->toBe('p0');
    expect($escalador->chamadas[0]['origem'])->toBe('jana:health-check');
});

test('quota: fixture 200 -> check ok E NAO escala (sem alarme falso)', function () {
    [$r, $escalador] = biteQuota(['choices' => [['message' => ['content' => 'ok']]]], 200);

    expect($r['ok'])->toBeTrue();
    expect($r['value'])->toBe('ok');
    expect($escalador->chamadas)->toBeEmpty();
});

test('quota: fora de producao a sonda NAO sai pela rede', function () {
    Illuminate\Support\Facades\Http::preventStrayRequests();
    config(['services.openai.api_key' => 'sk-fake-para-teste']);

    $m = (new ReflectionClass(HealthCheckCommand::class))->getMethod('checkLlmProviderQuota');
    $m->setAccessible(true);
    $r = $m->invoke(app(HealthCheckCommand::class));

    // preventStrayRequests estouraria se a sonda tivesse saido sem fake.
    expect($r['value'])->toBe('nao-configurado');
    expect($r['ok'])->toBeTrue();
});
