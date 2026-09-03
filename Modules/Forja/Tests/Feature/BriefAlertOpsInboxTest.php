<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpInboxNotification;

uses(Tests\TestCase::class);

/**
 * GUARD do `alertOps()` do `brief:generate` (Modules\Forja\Console\Commands\GenerateBriefCommand).
 *
 * **O defeito que isto trava:** o alerta era gravado em `mcp_inbox`, tabela que
 * NUNCA existiu — sem migration e sem schema em prod. Toda chamada caía no catch
 * e o alerta virava linha de log que ninguém lê. Medido em prod 2026-09-02
 * (Hostinger, `storage/logs/laravel.log`):
 *   `[brief:generate] alertOps falhou: SQLSTATE[42S02] ... 'mcp_inbox' doesn't exist`
 * A tabela real do Forja é `mcp_inbox_notifications` (ADR 0070).
 *
 * **As 2 provas:**
 *   (a) o alerta CHEGA em `mcp_inbox_notifications`, com o mapeamento ops que o
 *       `handoff:stale-alert` já fixou (`type='due_soon'` + user de ops, ADR 0283);
 *   (b) insert falhando NÃO derruba o comando — vira `Log::warning` e o exit segue
 *       sendo o que a GERAÇÃO decidiu, nunca uma exceção escapada do alerta.
 *
 * **Tenant:** `mcp_inbox_notifications` é per-user e cross-tenant POR DESIGN
 * (ADR 0070 — isolamento por `user_id`, não por `business_id`), então não há
 * `business_id` a escopar aqui. O `user_id` sintético abaixo existe pra não
 * encostar em inbox de gente real: no CT 100 a base é clone de prod e NÃO se
 * limpa entre runs (ADR 0358 / proibicoes.md §Ambiente), por isso o afterEach
 * apaga o que o teste criou.
 *
 * @see Modules\Forja\Console\Commands\GenerateBriefCommand::alertOps()
 * @see Modules\Forja\Console\Commands\HandoffStaleAlertCommand
 */

/** user_id sintético de ops — fora de qualquer faixa real (prod tem 82 businesses). */
const BRIEF_ALERTOPS_USER = 989801;

beforeEach(function () {
    if (! Schema::hasTable('mcp_inbox_notifications')) {
        // Sem a tabela real não há o que provar — e montar tabela sintética aqui
        // esconderia justamente o bug (schema divergente) que este teste trava.
        $this->fail('mcp_inbox_notifications ausente — rode as migrations do Forja antes.');
    }

    config(['admin.wagner_user_id' => BRIEF_ALERTOPS_USER]);

    // Faz generateNow() falhar SEM rede e SEM depender de qual etapa quebra
    // primeiro: sem OPENAI_API_KEY o service lança RuntimeException antes do HTTP,
    // e Http::fake() garante que nenhuma chamada real escapa se a ordem mudar.
    config(['services.openai.api_key' => null]);
    Http::fake();

    McpInboxNotification::where('user_id', BRIEF_ALERTOPS_USER)->delete();
});

afterEach(function () {
    McpInboxNotification::where('user_id', BRIEF_ALERTOPS_USER)->delete();
    McpInboxNotification::flushEventListeners();
});

it('(a) alerta de falha do brief CHEGA em mcp_inbox_notifications como due_soon do sistema', function () {
    $this->artisan('brief:generate')
        ->expectsOutputToContain('Falha ao gerar brief')
        ->assertExitCode(1);

    $notes = McpInboxNotification::where('user_id', BRIEF_ALERTOPS_USER)->get();

    expect($notes)->toHaveCount(1);

    $nota = $notes->first();
    expect($nota->type)->toBe('due_soon');
    expect($nota->actor_id)->toBeNull();                      // NULL = system (migration)
    expect($nota->read_at)->toBeNull();                       // nasce não-lida
    expect($nota->body)->toContain('[brief-generate]');        // marker machine-detectável
    expect($nota->body)->toContain('Brief gerador falhou');
    expect($nota->payload['kind'])->toBe('brief_generate_failure');
    expect($nota->payload['origem'])->toBe('brief:generate');
});

it('(b) insert falhando NAO derruba o comando — vira Log::warning e nenhuma notificacao', function () {
    Log::spy();

    // Faz o insert do inbox explodir DENTRO do alertOps. Se ele propagasse, este
    // artisan lançaria a exceção e o teste ERRARIA (não falharia num assert) —
    // então o assertExitCode abaixo é a prova de que o fallback existe de verdade.
    McpInboxNotification::creating(function () {
        throw new RuntimeException('boom: insert do inbox falhou');
    });

    $this->artisan('brief:generate')->assertExitCode(1);

    expect(McpInboxNotification::where('user_id', BRIEF_ALERTOPS_USER)->count())->toBe(0);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $msg, array $ctx = []) => str_contains($msg, '[brief:generate] alertOps falhou'))
        ->once();
});

it('(b2) o exit 1 vem da GERACAO, nao do alerta — alertOps nunca muda o exit code', function () {
    // Controle: com o alerta gravando OK (caso a) e com o alerta explodindo (caso b),
    // o exit é o MESMO. Isso separa as duas causas que o log de prod misturava:
    // "alertOps falhou" e "failed with exit code [1]" são eventos independentes.
    $comAlerta = $this->artisan('brief:generate')->run();

    McpInboxNotification::creating(function () {
        throw new RuntimeException('boom: insert do inbox falhou');
    });

    $semAlerta = $this->artisan('brief:generate')->run();

    expect($comAlerta)->toBe(1);
    expect($semAlerta)->toBe($comAlerta);
});
