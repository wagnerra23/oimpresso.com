<?php

declare(strict_types=1);

use App\Providers\OtelServiceProvider;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Contracts\AiAdapter;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\Mensagem;
use Modules\Jana\Http\Controllers\ChatController;
use Modules\Jana\Jobs\Telemetry\LangfuseTraceJob;
use Modules\Jana\Support\ContextoNegocio;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;

uses(Tests\TestCase::class);

/**
 * Dublê estreito do contrato: não emite telemetria própria. Assim os testes
 * provam que raiz/correlação pertencem ao controller e não a um segundo driver.
 */
final class ObservableStreamAdapterFake implements AiAdapter
{
    /** @var list<bool> */
    public array $spanAtivoNosChunks = [];

    /**
     * @param list<string> $chunks
     * @param array{path:string,status:string,cache_hit:bool,recall_count:int,jobs_dispatched:int,error_class:?string} $outcome
     */
    public function __construct(
        private readonly array $chunks,
        private array $outcome,
        private readonly ?int $throwAfterChunk = null,
    ) {
    }

    public function gerarBriefing(ContextoNegocio $ctx): string
    {
        return '';
    }

    public function sugerirMetas(ContextoNegocio $ctx, string $prompt): array
    {
        return [];
    }

    public function responderChat(Conversa $conv, string $mensagem): string
    {
        return implode('', $this->chunks);
    }

    public function responderChatStream(Conversa $conv, string $mensagem): Generator
    {
        foreach ($this->chunks as $index => $chunk) {
            $this->spanAtivoNosChunks[] = Span::getCurrent()->isRecording();
            yield $chunk;

            if ($this->throwAfterChunk === $index + 1) {
                $this->outcome['status'] = 'partial_error';
                $this->outcome['error_class'] = RuntimeException::class;
                throw new RuntimeException('segredo-do-provider-que-nao-pode-vazar');
            }
        }
    }

    public function ultimoResultadoStream(): array
    {
        return $this->outcome;
    }

    public function ultimoUsoTokens(): array
    {
        return ['tokens_in' => 12, 'tokens_out' => 7];
    }
}

/** Controller de teste: simula socket abandonado após o primeiro chunk. */
final class DisconnectingChatController extends ChatController
{
    protected function connectionAborted(): bool
    {
        return true;
    }
}

function streamOutcome(
    string $path = 'llm',
    string $status = 'ok',
    bool $cacheHit = false,
    int $recallCount = 0,
): array {
    return [
        'path' => $path,
        'status' => $status,
        'cache_hit' => $cacheHit,
        'recall_count' => $recallCount,
        'jobs_dispatched' => 0,
        'error_class' => null,
    ];
}

/**
 * Executa a callback real do StreamedResponse sem depender do buffer do helper
 * Laravel, que o SSE deliberadamente desmonta para liberar chunks em tempo real.
 */
function consumeObservedStream(object $test, User $user, Conversa $conversa, string $content): string
{
    $response = $test->actingAs($user)
        ->withoutMiddleware()
        ->post(route('jana.conversas.mensagens.stream', $conversa->id), compact('content'));

    $response->assertOk();
    $level = ob_get_level();
    ob_start();
    try {
        $response->baseResponse->sendContent();
    } finally {
        while (ob_get_level() > $level) {
            $captured = (string) ob_get_clean();
        }
        while (ob_get_level() < $level) {
            ob_start();
        }
    }

    return $captured ?? '';
}

beforeEach(function () {
    foreach (['jana_mensagens', 'jana_conversas', 'users'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('business_id');
        $table->string('surname')->default('');
        $table->string('first_name');
        $table->string('last_name')->nullable();
        $table->string('username');
        $table->string('email')->nullable();
        $table->string('password');
        $table->string('language')->default('pt');
        $table->rememberToken();
        $table->softDeletes();
        $table->timestamps();
    });
    Schema::create('jana_conversas', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('business_id');
        $table->unsignedInteger('user_id');
        $table->string('titulo')->nullable();
        $table->string('status')->default('ativa');
        $table->timestamp('iniciada_em')->nullable();
        $table->timestamps();
    });
    Schema::create('jana_mensagens', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('conversa_id');
        $table->string('role');
        $table->text('content');
        $table->unsignedInteger('tokens_in')->nullable();
        $table->unsignedInteger('tokens_out')->nullable();
        $table->timestamp('created_at')->nullable();
    });

    DB::table('users')->insert([
        'id' => 1,
        'business_id' => 23,
        'first_name' => 'Teste',
        'username' => 'observability',
        'password' => 'irrelevante',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('jana_conversas')->insert([
        'id' => 10,
        'business_id' => 23,
        'user_id' => 1,
        'titulo' => 'trace',
        'status' => 'ativa',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    config([
        'otel.enabled' => true,
        'otel.sdk_disabled' => false,
        'otel.sample_rate' => 1.0,
        'otel.service_name' => 'oimpresso-test',
        'langfuse.enabled' => true,
        'langfuse.sample_rate' => 1.0,
        'langfuse.dispatch' => 'queue',
        'langfuse.redact_pii' => true,
    ]);
    Bus::fake([LangfuseTraceJob::class]);

    Globals::reset();
    $this->otelExporter = new InMemoryExporter();
    $this->otelProvider = (new OtelServiceProvider(app()))->buildTracerProvider($this->otelExporter);
    Globals::registerInitializer(
        fn (Configurator $configurator) => $configurator->withTracerProvider($this->otelProvider)
    );

    $this->user = User::findOrFail(1);
    $this->conversa = Conversa::withoutGlobalScopes()->findOrFail(10);
});

afterEach(function () {
    $this->otelProvider->shutdown();
    Globals::reset();
});

it('mantém uma raiz ativa nos chunks e persiste resultado completo sem PII em atributos', function () {
    $fake = new ObservableStreamAdapterFake(
        ['primeiro ', 'segundo'],
        streamOutcome(recallCount: 3),
    );
    app()->instance(AiAdapter::class, $fake);

    $cpf = implode('.', ['123', '456', '789']).'-00';

    consumeObservedStream(
        $this,
        $this->user,
        $this->conversa,
        "meu CPF {$cpf} não pode virar atributo",
    );

    $this->otelProvider->forceFlush();
    $spans = $this->otelExporter->getSpans();
    $root = collect($spans)->first(fn ($span) => $span->getName() === 'jana.chat.stream');
    $attrs = $root->getAttributes()->toArray();

    expect($fake->spanAtivoNosChunks)->toBe([true, true])
        ->and($root)->not->toBeNull()
        ->and($root->getStatus()->getCode())->toBe(StatusCode::STATUS_OK)
        ->and($attrs['business_id'])->toBe(23)
        ->and($attrs['conversation_id'])->toBe(10)
        ->and($attrs['path'])->toBe('llm')
        ->and($attrs['recall_count'])->toBe(3)
        ->and($attrs['result'])->toBe('ok')
        ->and(json_encode($attrs))->not->toContain($cpf);

    $assistant = Mensagem::where('role', 'assistant')->firstOrFail();
    expect($assistant->content)->toBe('primeiro segundo')
        ->and((int) $assistant->tokens_in)->toBe(12)
        ->and((int) $assistant->tokens_out)->toBe(7);

    $events = [];
    Bus::assertDispatched(LangfuseTraceJob::class, function (LangfuseTraceJob $job) use (&$events) {
        array_push($events, ...$job->events);

        return true;
    });
    expect(json_encode($events))->not->toContain($cpf);
});

it('cache hit produz somente raiz Langfuse, sem generation inventada', function () {
    app()->instance(AiAdapter::class, new ObservableStreamAdapterFake(
        ['resposta do cache'],
        streamOutcome('semantic_cache', cacheHit: true),
    ));

    consumeObservedStream($this, $this->user, $this->conversa, 'consulta cacheada');

    $types = [];
    $events = [];
    Bus::assertDispatchedTimes(LangfuseTraceJob::class, 2);
    Bus::assertDispatched(LangfuseTraceJob::class, function (LangfuseTraceJob $job) use (&$types, &$events) {
        foreach ($job->events as $event) {
            $types[] = $event['type'];
            $events[] = $event;
        }

        return true;
    });

    expect($types)->toBe(['trace-create', 'trace-create'])
        ->and($types)->not->toContain('generation-create')
        ->and($events[1]['body']['metadata']['path'])->toBe('semantic_cache')
        ->and($events[1]['body']['metadata']['result'])->toBe('ok');
});

it('erro depois do primeiro chunk fecha as raízes como parcial sem vazar mensagem do provider', function () {
    app()->instance(AiAdapter::class, new ObservableStreamAdapterFake(
        ['parte visível'],
        streamOutcome(),
        throwAfterChunk: 1,
    ));

    consumeObservedStream($this, $this->user, $this->conversa, 'provocar erro');
    $this->otelProvider->forceFlush();
    $root = collect($this->otelExporter->getSpans())
        ->first(fn ($span) => $span->getName() === 'jana.chat.stream');
    $attrs = $root->getAttributes()->toArray();

    expect($root->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and($attrs['result'])->toBe('partial_error')
        ->and($attrs['response_partial'])->toBeTrue()
        ->and($attrs['error_class'])->toBe(RuntimeException::class)
        ->and(Mensagem::where('role', 'assistant')->value('content'))->toBe('parte visível');

    $events = [];
    Bus::assertDispatched(LangfuseTraceJob::class, function (LangfuseTraceJob $job) use (&$events) {
        array_push($events, ...$job->events);

        return true;
    });
    expect(json_encode($events))->not->toContain('segredo-do-provider');
});

it('abandono fecha exatamente uma raiz como cancelada e preserva o trecho já entregue', function () {
    app()->instance(AiAdapter::class, new ObservableStreamAdapterFake(
        ['primeiro', 'nunca consumido'],
        streamOutcome(),
    ));
    app()->instance(ChatController::class, app(DisconnectingChatController::class));

    consumeObservedStream($this, $this->user, $this->conversa, 'cancelar');
    $this->otelProvider->forceFlush();
    $roots = collect($this->otelExporter->getSpans())
        ->filter(fn ($span) => $span->getName() === 'jana.chat.stream')
        ->values();
    $attrs = $roots->first()->getAttributes()->toArray();

    expect($roots)->toHaveCount(1)
        ->and($attrs['result'])->toBe('cancelled')
        ->and($attrs['response_partial'])->toBeTrue()
        ->and(Mensagem::where('role', 'assistant')->value('content'))->toBe('primeiro');
});
