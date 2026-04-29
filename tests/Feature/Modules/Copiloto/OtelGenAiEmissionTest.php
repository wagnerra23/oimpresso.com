<?php

use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Services\Ai\LaravelAiSdkDriver;

/**
 * MEM-OTEL-1 (ADR 0051) — emissão OpenTelemetry GenAI semantic conventions
 * no log channel `otel-gen-ai`.
 *
 * Verifica que `emitirOtelGenAi()` produz atributos `gen_ai.*` no formato
 * que Datadog/Langfuse/Arize reconhecem nativamente.
 *
 * Estratégia: usa Log::spy() pra capturar a chamada do channel sem realmente
 * gravar arquivo. Não tocamos OpenAI nem ChatCopilotoAgent::prompt().
 */

beforeEach(function () {
    config(['ai.default' => 'openai']);
    config(['ai.providers.openai.models.text.default' => 'gpt-4o-mini']);
});

function fakeResponseObject(int $promptTokens = 100, int $completionTokens = 50, string $finishReason = 'stop'): object
{
    return new class($promptTokens, $completionTokens, $finishReason) {
        public object $usage;
        public string $finishReason;
        public function __construct(int $promptTokens, int $completionTokens, string $finishReason)
        {
            $this->usage = (object) [
                'promptTokens' => $promptTokens,
                'completionTokens' => $completionTokens,
            ];
            $this->finishReason = $finishReason;
        }
    };
}

function fakeConv(): Conversa
{
    $c = new Conversa();
    $c->id = 42;
    $c->business_id = 4;
    $c->user_id = 9;
    return $c;
}

it('emitirOtelGenAi chama Log::channel(otel-gen-ai)->info(gen_ai.span, ...)', function () {
    \Log::shouldReceive('channel')->with('otel-gen-ai')->once()->andReturnSelf();
    \Log::shouldReceive('info')->with('gen_ai.span', \Mockery::type('array'))->once();
    \Log::shouldReceive('channel')->with('copiloto-ai')->andReturnSelf();
    \Log::shouldReceive('debug');

    $driver = new LaravelAiSdkDriver();
    $reflection = new \ReflectionClass($driver);
    $method = $reflection->getMethod('emitirOtelGenAi');
    $method->setAccessible(true);

    $method->invoke(
        $driver,
        fakeConv(),
        'qual meu faturamento de março',
        fakeResponseObject(150, 80, 'stop'),
        1450,
        true,
    );
});

it('atributos OTel obrigatórios estão presentes em sucesso', function () {
    $driver = new LaravelAiSdkDriver();

    $reflection = new \ReflectionClass($driver);
    $method = $reflection->getMethod('emitirOtelGenAi');
    $method->setAccessible(true);

    // Capturar via fake driver de log
    $captured = null;
    \Illuminate\Support\Facades\Log::partialMock();

    \Log::shouldReceive('channel')
        ->with('otel-gen-ai')
        ->andReturnSelf();

    \Log::shouldReceive('info')
        ->with('gen_ai.span', \Mockery::on(function ($attrs) use (&$captured) {
            $captured = $attrs;
            return true;
        }));

    \Log::shouldReceive('channel')->with('copiloto-ai')->andReturnSelf();
    \Log::shouldReceive('debug');

    $method->invoke(
        $driver,
        fakeConv(),
        'qual meu faturamento de março',
        fakeResponseObject(150, 80, 'stop'),
        1450,
        true,
    );

    expect($captured)->not->toBeNull();
    // Padrão OTel GenAI
    expect($captured['gen_ai.system'])->toBe('openai');
    expect($captured['gen_ai.request.model'])->toBe('gpt-4o-mini');
    expect($captured['gen_ai.operation.name'])->toBe('chat');
    expect($captured['gen_ai.response.duration_ms'])->toBe(1450);
    expect($captured['gen_ai.usage.input_tokens'])->toBe(150);
    expect($captured['gen_ai.usage.output_tokens'])->toBe(80);
    expect($captured['gen_ai.response.finish_reason'])->toBe('stop');
    // Multi-tenant + LGPD audit (custom)
    expect($captured['gen_ai.business_id'])->toBe(4);
    expect($captured['gen_ai.user.id'])->toBe(9);
    expect($captured['gen_ai.conversation.id'])->toBe(42);
});

it('em erro, emite finish_reason=error + gen_ai.error.type', function () {
    $driver = new LaravelAiSdkDriver();

    $reflection = new \ReflectionClass($driver);
    $method = $reflection->getMethod('emitirOtelGenAi');
    $method->setAccessible(true);

    $captured = null;

    \Log::shouldReceive('channel')->with('otel-gen-ai')->andReturnSelf();
    \Log::shouldReceive('info')
        ->with('gen_ai.span', \Mockery::on(function ($attrs) use (&$captured) {
            $captured = $attrs;
            return true;
        }));
    \Log::shouldReceive('channel')->with('copiloto-ai')->andReturnSelf();
    \Log::shouldReceive('debug');

    $erro = new \RuntimeException('OpenAI 500');

    $method->invoke(
        $driver,
        fakeConv(),
        'qual meu faturamento',
        null,
        2300,
        false,
        $erro,
    );

    expect($captured['gen_ai.response.finish_reason'])->toBe('error');
    expect($captured['gen_ai.error.type'])->toBe(\RuntimeException::class);
    expect($captured['gen_ai.error.message'])->toBe('OpenAI 500');
    expect($captured['gen_ai.response.duration_ms'])->toBe(2300);
    // Atributos sem usage em erro
    expect($captured)->not->toHaveKey('gen_ai.usage.input_tokens');
});

it('business_id null (plataforma) vira null no atributo, não quebra', function () {
    $conv = new Conversa();
    $conv->id = 1;
    $conv->business_id = null;
    $conv->user_id = 9;

    $driver = new LaravelAiSdkDriver();
    $reflection = new \ReflectionClass($driver);
    $method = $reflection->getMethod('emitirOtelGenAi');
    $method->setAccessible(true);

    $captured = null;

    \Log::shouldReceive('channel')->with('otel-gen-ai')->andReturnSelf();
    \Log::shouldReceive('info')
        ->with('gen_ai.span', \Mockery::on(function ($attrs) use (&$captured) {
            $captured = $attrs;
            return true;
        }));
    \Log::shouldReceive('channel')->with('copiloto-ai')->andReturnSelf();
    \Log::shouldReceive('debug');

    $method->invoke($driver, $conv, 'oi', fakeResponseObject(), 100, true);

    expect($captured['gen_ai.business_id'])->toBeNull();
    expect($captured['gen_ai.user.id'])->toBe(9);
});

it('emissão não quebra responderChat se Log falhar (degradação silenciosa)', function () {
    \Log::shouldReceive('channel')->with('otel-gen-ai')->andThrow(new \RuntimeException('log channel error'));
    \Log::shouldReceive('channel')->with('copiloto-ai')->andReturnSelf();
    \Log::shouldReceive('debug')->once(); // engole a falha

    $driver = new LaravelAiSdkDriver();
    $reflection = new \ReflectionClass($driver);
    $method = $reflection->getMethod('emitirOtelGenAi');
    $method->setAccessible(true);

    expect(fn () => $method->invoke(
        $driver,
        fakeConv(),
        'oi',
        fakeResponseObject(),
        100,
        true,
    ))->not->toThrow(\Throwable::class);
});
