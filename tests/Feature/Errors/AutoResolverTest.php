<?php

declare(strict_types=1);

/**
 * Pest — AutoResolver + ReprocessJob: retry · fallback · reprocesso (Fase 2 · E-3).
 *
 * Cobre "PRONTO QUANDO" do handoff erros-autoresolucao:
 *  - erro recuperável → resolvido por retry SEM disparar alerta; % auto-resolvido registrado
 *  - retry é idempotente (não duplica efeito — dedup por id externo)
 *  - após N falhas → dead-letter promove pra S1 e PARA (sem retry infinito)
 *  - S0 NUNCA auto-resolve (dinheiro/dado/segurança = humano · ADR 0284 §4)
 *
 * Sem MySQL: as tabelas de plataforma (error_groups, mcp_audit_log) são criadas sob
 * demanda no beforeEach (mesmo pattern do ErrorGrouperTest).
 *
 * @see prototipo-ui/handoffs/erros-autoresolucao.md
 */

use App\Jobs\ReprocessJob;
use App\Models\ErrorGroup;
use App\Support\Errors\Audience;
use App\Support\Errors\AutoResolver;
use App\Support\Errors\Classification;
use App\Support\Errors\Severity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/** Ação de reprocesso de teste — conta quantas vezes o efeito foi aplicado. */
class FakeReprocessAction
{
    public static int $count = 0;

    public function __invoke(array $params): void
    {
        self::$count++;
    }
}

/** Ação que sempre falha — alimenta o caminho de dead-letter. */
class FakeFailingAction
{
    public function __invoke(array $params): void
    {
        throw new RuntimeException('domínio ainda fora');
    }
}

function mkAR(string $key, Severity $sev = Severity::S1, string $owner = 'cobranca'): Classification
{
    return new Classification($sev, Audience::CONSTRUTOR, $owner, $key, 'Tente novamente em instantes.');
}

beforeEach(function () {
    FakeReprocessAction::$count = 0;

    config([
        'errors.auto_resolve.enabled' => true,
        'errors.auto_resolve.max_attempts' => 5,
        'errors.auto_resolve.whitelist_owners' => ['cobranca', 'fiscal', 'whatsapp'],
        'errors.auto_resolve.connection' => null,
        'errors.auto_resolve.queue' => null,
    ]);

    if (! Schema::hasTable('error_groups')) {
        Schema::create('error_groups', function ($t) {
            $t->bigIncrements('id');
            $t->string('dedup_key', 64)->unique();
            $t->string('severity', 4)->index();
            $t->string('audience', 16);
            $t->string('owner', 60)->nullable();
            $t->unsignedBigInteger('count')->default(1);
            $t->string('status', 16)->default('open')->index();
            $t->timestamp('first_seen')->nullable();
            $t->timestamp('last_seen')->nullable();
            $t->json('sample_payload')->nullable();
            $t->timestamps();
        });
    }

    if (! Schema::hasTable('mcp_audit_log')) {
        Schema::create('mcp_audit_log', function ($t) {
            $t->bigIncrements('id');
            $t->string('request_id', 64)->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('business_id')->nullable();
            $t->timestamp('ts')->nullable();
            $t->string('endpoint')->nullable();
            $t->string('tool_or_resource')->nullable();
            $t->string('status', 32)->nullable();
            $t->text('payload_summary')->nullable();
            $t->timestamp('created_at')->nullable();
        });
    }
});

it('canRetry: S1/S2 com dono na whitelist sim; S0/S3/dono-fora não', function () {
    $r = new AutoResolver;

    expect($r->canRetry(mkAR('a', Severity::S1, 'cobranca')))->toBeTrue()
        ->and($r->canRetry(mkAR('b', Severity::S2, 'fiscal')))->toBeTrue()
        // S0 NUNCA — inegociável (Tier 0).
        ->and($r->canRetry(mkAR('c', Severity::S0, 'cobranca')))->toBeFalse()
        // S3 é ruído — não entra no loop.
        ->and($r->canRetry(mkAR('d', Severity::S3, 'cobranca')))->toBeFalse()
        // dono fora da whitelist → humano.
        ->and($r->canRetry(mkAR('e', Severity::S1, 'plataforma')))->toBeFalse();
});

it('canRetry: desligado por config → não auto-resolve nada', function () {
    config(['errors.auto_resolve.enabled' => false]);

    expect((new AutoResolver)->canRetry(mkAR('x', Severity::S1, 'cobranca')))->toBeFalse();
});

it('attempt: recuperável enfileira ReprocessJob; S0 não enfileira nada', function () {
    Queue::fake();
    $r = new AutoResolver;

    expect($r->attempt(mkAR('q1', Severity::S1, 'cobranca'), FakeReprocessAction::class, [], 'q1'))->toBeTrue();
    Queue::assertPushed(ReprocessJob::class, 1);

    // S0 nunca auto-resolve — nada é enfileirado.
    expect($r->attempt(mkAR('q2', Severity::S0, 'cobranca'), FakeReprocessAction::class, [], 'q2'))->toBeFalse();
    Queue::assertPushed(ReprocessJob::class, 1); // continua 1
});

it('erro recuperável resolve por retry SEM alerta e registra % auto-resolvido', function () {
    config(['errors.s0_channel' => 'https://hooks.slack.com/services/T/B/secret']);
    Http::fake();

    $c = mkAR('resolved-1', Severity::S1, 'cobranca');
    (new ReprocessJob($c, FakeReprocessAction::class, ['cobranca_id' => 7], 'ext-resolved-1'))
        ->handle(app(AutoResolver::class));

    // efeito aplicado…
    expect(FakeReprocessAction::$count)->toBe(1)
        // …registrado como auto-resolvido (alimenta o painel)…
        ->and(DB::table('mcp_audit_log')->where('status', 'auto_resolved')->where('tool_or_resource', 'cobranca')->exists())->toBeTrue();

    // …e NENHUM alerta disparado (o erro que se resolve sozinho não acorda ninguém).
    Http::assertNothingSent();
});

it('retry é idempotente — efeito aplicado UMA vez por id externo', function () {
    $c = mkAR('idem-1', Severity::S1, 'fiscal');
    $key = 'ext-idem-1';
    Cache::forget('reprocess_done:'.$key);

    // 2 execuções com o mesmo id externo (ex: entrega dupla / retry após sucesso).
    (new ReprocessJob($c, FakeReprocessAction::class, ['nfe_id' => 9], $key))->handle(app(AutoResolver::class));
    (new ReprocessJob($c, FakeReprocessAction::class, ['nfe_id' => 9], $key))->handle(app(AutoResolver::class));

    expect(FakeReprocessAction::$count)->toBe(1); // não duplicou NF-e
});

it('dead-letter: esgotou tentativas → promove pra S1 e PARA (sem retry infinito)', function () {
    $c = mkAR('dl-1', Severity::S1, 'whatsapp');
    $job = new ReprocessJob($c, FakeFailingAction::class, [], 'ext-dl-1');

    // Limites duros: $tries finito e backoff com tries-1 esperas (a fila para sozinha).
    expect($job->tries)->toBe(5)
        ->and($job->backoff())->toHaveCount(4);

    // Laravel chama failed() uma vez, depois da última tentativa.
    $job->failed(new RuntimeException('domínio ainda fora'));

    expect(ErrorGroup::where('severity', 'S1')->exists())->toBeTrue()
        ->and(DB::table('mcp_audit_log')->where('status', 'auto_dead_letter')->exists())->toBeTrue();
});

it('backoff é exponencial e saturado no teto', function () {
    config([
        'errors.auto_resolve.max_attempts' => 6,
        'errors.auto_resolve.backoff_base_seconds' => 30,
        'errors.auto_resolve.backoff_max_seconds' => 240,
    ]);

    // 30, 60, 120, 240 (cap), 240 (cap) — 5 esperas pra 6 tentativas.
    expect((new AutoResolver)->backoff())->toBe([30, 60, 120, 240, 240]);
});
