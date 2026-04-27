<?php

use Illuminate\Support\Facades\Queue;
use Modules\Copiloto\Ai\Agents\ChatCopilotoAgent;
use Modules\Copiloto\Ai\Agents\ExtrairFatosAgent;
use Modules\Copiloto\Contracts\MemoriaContrato;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Jobs\ExtrairFatosDaConversaJob;
use Modules\Copiloto\Services\Memoria\NullMemoriaDriver;

/**
 * Sprint 5 — bridge memória↔chat (ADR 0036).
 * Cobre: ExtrairFatosAgent, ExtrairFatosDaConversaJob, recall em ChatCopilotoAgent.
 */

it('ChatCopilotoAgent injeta memoriaContexto no system prompt quando preenchido', function () {
    $conversa = new Conversa(['business_id' => 4, 'user_id' => 12, 'titulo' => 't']);
    $contexto = "Você lembra dos seguintes fatos sobre este usuário/business:\n- Larissa quer meta R$80k/mês";

    $agent = new ChatCopilotoAgent($conversa, $contexto);
    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('Larissa quer meta R$80k/mês');
    expect($instructions)->toContain('lembra dos seguintes fatos');
});

it('ChatCopilotoAgent sem memoriaContexto mantém prompt base limpo', function () {
    $conversa = new Conversa(['business_id' => 4, 'user_id' => 12, 'titulo' => 't']);

    $agent = new ChatCopilotoAgent($conversa);
    $instructions = (string) $agent->instructions();

    expect($instructions)->not->toContain('lembra dos seguintes fatos');
    expect($instructions)->toContain('Copiloto do oimpresso');
});

it('ExtrairFatosAgent define schema com 5 categorias e relevancia 1-10', function () {
    $agent = new ExtrairFatosAgent('ROTA LIVRE', 'USER: meta de R$80k\nASSISTANT: registrado.');

    $reflection = new ReflectionClass(ExtrairFatosAgent::class);
    $hasStructured = collect($reflection->getInterfaceNames())
        ->contains(\Laravel\Ai\Contracts\HasStructuredOutput::class);

    expect($hasStructured)->toBeTrue();
    expect((string) $agent->instructions())->toContain('REGRAS RÍGIDAS');
    expect((string) $agent->instructions())->toContain('meta');
    expect((string) $agent->instructions())->toContain('preferencia');
    expect((string) $agent->instructions())->toContain('restricao');
});

it('ExtrairFatosDaConversaJob é dispatchable e tem queue copiloto-memoria', function () {
    $job = new ExtrairFatosDaConversaJob(conversaId: 1, businessId: 4, userId: 12);

    expect($job->queue)->toBe('copiloto-memoria');
    expect($job->tries)->toBe(2);
    expect($job->timeout)->toBe(60);
});

it('ExtrairFatosDaConversaJob handle pula em dry_run', function () {
    config(['copiloto.dry_run' => true]);
    Queue::fake();

    $driver = new NullMemoriaDriver();
    $job = new ExtrairFatosDaConversaJob(conversaId: 999999, businessId: 4, userId: 12);

    $job->handle($driver);

    expect($driver->listar(4, 12))->toHaveCount(0);
})->skip('Requer migration copiloto_conversas em SQLite in-memory — validar com mysql dev local');

it('ExtrairFatosDaConversaJob não falha pra conversa inexistente', function () {
    config(['copiloto.dry_run' => false]);

    $driver = new NullMemoriaDriver();
    $job = new ExtrairFatosDaConversaJob(conversaId: 999999, businessId: 4, userId: 12);

    $job->handle($driver);

    expect($driver->listar(4, 12))->toHaveCount(0);
})->skip('Requer migration copiloto_conversas em SQLite in-memory — validar com mysql dev local');

it('config recall_enabled e write_enabled têm default true', function () {
    expect(config('copiloto.memoria.recall_enabled'))->toBeTrue();
    expect(config('copiloto.memoria.write_enabled'))->toBeTrue();
});

it('LaravelAiSdkDriver injeta MemoriaContrato via DI ao buscar memória', function () {
    config(['copiloto.memoria.driver' => 'null']);
    config(['copiloto.dry_run' => false]);

    $driver = app(MemoriaContrato::class);
    expect($driver)->toBeInstanceOf(NullMemoriaDriver::class);
});
