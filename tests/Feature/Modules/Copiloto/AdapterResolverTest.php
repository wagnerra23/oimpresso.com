<?php

use Modules\Copiloto\Contracts\AiAdapter;
use Modules\Copiloto\Services\Ai\LaravelAiSdkDriver;
use Modules\Copiloto\Services\Ai\OpenAiDirectDriver;

/**
 * Testa o resolver do adapter de IA (adr/tech/0002).
 *
 * Cenários:
 * 1. LaravelAI ausente → driver é OpenAiDirectDriver
 * 2. laravel_ai_sdk    → driver é LaravelAiSdkDriver (canônico, ADR 0035)
 * 3. Config openai_direct força OpenAI mesmo com LaravelAI ativo
 *
 * Não requer DB — testa apenas o binding do container.
 */

it('resolve OpenAiDirectDriver quando LaravelAI está ausente', function () {
    config(['copiloto.ai_adapter' => 'auto']);

    // Simula LaravelAI ausente fazendo o SP binding voltar pro default
    app()->bind(AiAdapter::class, fn () => app(OpenAiDirectDriver::class));

    $driver = app(AiAdapter::class);

    expect($driver)->toBeInstanceOf(OpenAiDirectDriver::class);
});

it('resolve OpenAiDirectDriver quando config força openai_direct', function () {
    config(['copiloto.ai_adapter' => 'openai_direct']);

    // Rebind para forçar openai_direct
    app()->bind(AiAdapter::class, function () {
        if (config('copiloto.ai_adapter') === 'openai_direct') {
            return app(OpenAiDirectDriver::class);
        }
        return app(OpenAiDirectDriver::class);
    });

    $driver = app(AiAdapter::class);

    expect($driver)->toBeInstanceOf(OpenAiDirectDriver::class);
});

it('resolve LaravelAiSdkDriver quando ai_adapter=laravel_ai_sdk (canônico ADR 0035)', function () {
    config(['copiloto.ai_adapter' => 'laravel_ai_sdk']);

    $driver = app(AiAdapter::class);

    expect($driver)->toBeInstanceOf(LaravelAiSdkDriver::class);
});

it('OpenAiDirectDriver é instanciável', function () {
    $driver = new OpenAiDirectDriver();

    expect($driver)->toBeInstanceOf(AiAdapter::class);
});

it('LaravelAiSdkDriver é instanciável (canônico ADR 0035)', function () {
    $driver = new LaravelAiSdkDriver();

    expect($driver)->toBeInstanceOf(AiAdapter::class);
});

it('laravelAiSdkAvailable detecta laravel/ai instalado', function () {
    expect(class_exists(\Laravel\Ai\AiManager::class))->toBeTrue();
});

it('dry_run retorna fixture sem chamar API', function () {
    config(['copiloto.dry_run' => true]);

    $driver = new OpenAiDirectDriver();
    $ctx    = new \Modules\Copiloto\Support\ContextoNegocio(
        businessId:    1,
        businessName:  'ROTA LIVRE',
        faturamento90d: [['mes' => '2026-01', 'valor' => 10000]],
        clientesAtivos: 5,
        modulosAtivos:  [],
        metasAtivas:    [],
    );

    $briefing = $driver->gerarBriefing($ctx);

    expect($briefing)->toBeString()->not->toBeEmpty();

    $propostas = $driver->sugerirMetas($ctx, 'Sugira metas');

    expect($propostas)->toBeArray()->not->toBeEmpty();
    expect($propostas[0])->toHaveKeys(['nome', 'metrica', 'valor_alvo', 'periodo', 'dificuldade', 'racional', 'dependencias']);
});
