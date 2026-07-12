<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Telemetry;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Jana\Jobs\Telemetry\LangfuseTraceJob;

/**
 * LangfuseClient — emite traces/generations/spans/scores pra Langfuse v3 self-host.
 *
 * Stack-alvo (ADR 0132 + ADR 0037 §GAP-1):
 *  - Cliente HTTP simples (NÃO depende de OTel SDK PHP — Langfuse REST batch ingestion é canônico).
 *  - Endpoint: POST {$host}/api/public/ingestion com Basic Auth (public_key:secret_key).
 *  - Dispatch async via queue por default — não bloqueia request principal.
 *  - Fail-open: erros HTTP/timeout viram warning em log channel 'langfuse', NUNCA crash.
 *  - Multi-tenant Tier 0: business_id sempre presente em metadata do trace.
 *  - Sampling: respeita config('langfuse.sample_rate') — 1.0 = sempre, 0 = nunca.
 *  - PII: redacta input/output via PiiRedactor (LGPD) quando config('langfuse.redact_pii').
 *
 * Eventos canônicos (formato Langfuse ingestion v3):
 *  - trace-create        — operação raiz (chat/brief/kb-answer/handoff-summary/digest)
 *  - generation-create   — chamada LLM (model/tokens/cost) DENTRO de trace
 *  - span-create         — sub-operação (retrieval/rerank/judge) DENTRO de trace
 *  - score-create        — métrica numérica (RAGAS faithfulness, relevancy, etc)
 *
 * Uso típico — pattern "trace + generation" no driver IA:
 *   $traceId = $client->startTrace([
 *       'name' => 'jana-chat',
 *       'business_id' => $conv->business_id,
 *       'user_id' => $conv->user_id,
 *       'input' => $mensagem,
 *   ]);
 *   $client->recordGeneration($traceId, [
 *       'name' => 'chat-response',
 *       'model' => 'gpt-4o-mini',
 *       'input' => $prompt,
 *       'output' => $resposta,
 *       'usage' => ['input' => $tokensIn, 'output' => $tokensOut],
 *       'duration_ms' => $durationMs,
 *   ]);
 *
 * Pattern RAGAS — wire score automático:
 *   $client->recordScore($traceId, name: 'ragas_faithfulness', value: 0.87);
 *
 * @see config/langfuse.php
 * @see memory/decisions/0132-langfuse-self-host-ct100.md
 * @see https://langfuse.com/docs/api-and-data-platform/features/public-api
 */
class LangfuseClient
{
    /**
     * Inicia trace raiz e retorna trace_id pra correlacionar generations/spans/scores.
     *
     * @param array<string,mixed> $attrs {
     *   name: string,
     *   business_id: ?int,
     *   user_id?: ?int,
     *   conversation_id?: ?int,
     *   tool?: string,            // ex 'kb-answer', 'handoff-diff' (vide config)
     *   input?: string|array,
     *   metadata?: array<string,mixed>,
     *   tags?: array<string>,
     *   release?: string,
     * }
     */
    public function startTrace(array $attrs): string
    {
        $traceId = (string) Str::uuid();

        if (! $this->shouldEmit()) {
            return $traceId;
        }

        $this->dispatch([$this->traceEvent($traceId, $attrs)]);

        return $traceId;
    }

    /**
     * Emite trace + generation num ÚNICO batch HTTP (metade do overhead de
     * startTrace + recordGeneration separados — importa em dispatch=sync).
     *
     * Usado pelo LangfuseAgentTelemetryListener (1 chamada LLM = 1 trace
     * com 1 generation filha). Retorna trace_id pra correlação posterior
     * (ex: recordScore RAGAS).
     *
     * @param array<string,mixed> $trace mesmo shape de startTrace()
     * @param array<string,mixed> $generation mesmo shape de recordGeneration()
     */
    public function traceComGeneration(array $trace, array $generation): string
    {
        $traceId = (string) Str::uuid();

        if (! $this->shouldEmit()) {
            return $traceId;
        }

        $this->dispatch([
            $this->traceEvent($traceId, $trace),
            $this->generationEvent($traceId, $generation),
        ]);

        return $traceId;
    }

    /**
     * Monta event `trace-create` (formato ingestion v3).
     *
     * @param array<string,mixed> $attrs
     * @return array<string,mixed>
     */
    protected function traceEvent(string $traceId, array $attrs): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'trace-create',
            'timestamp' => $this->now(),
            'body' => [
                'id' => $traceId,
                'name' => (string) ($attrs['name'] ?? 'unknown'),
                'userId' => isset($attrs['user_id']) && $attrs['user_id'] !== null ? (string) $attrs['user_id'] : null,
                'sessionId' => isset($attrs['conversation_id']) && $attrs['conversation_id'] !== null
                    ? (string) $attrs['conversation_id']
                    : null,
                'input' => $this->maybeRedact($attrs['input'] ?? null),
                'metadata' => array_merge(
                    [
                        'business_id' => $attrs['business_id'] ?? null,
                        'tool' => $attrs['tool'] ?? null,
                        'driver' => 'laravel_ai_sdk',
                    ],
                    (array) ($attrs['metadata'] ?? []),
                ),
                // US-COPI-132: business_id como TAG (não só metadata) — habilita filtro
                // "custo/latência/traces por business" no UI Langfuse (ADR 0093 Tier 0).
                'tags' => array_values(array_filter(array_merge(
                    [(string) config('langfuse.environment', 'production')],
                    isset($attrs['business_id']) && $attrs['business_id'] !== null
                        ? ['business_id:' . $attrs['business_id']]
                        : [],
                    (array) ($attrs['tags'] ?? []),
                ))),
                'release' => (string) ($attrs['release'] ?? config('langfuse.release', 'unknown')),
                'environment' => (string) config('langfuse.environment', 'production'),
            ],
        ];
    }

    /**
     * Atualiza trace existente com output (e/ou status erro).
     *
     * @param array<string,mixed> $attrs { output?: mixed, level?: 'DEFAULT'|'ERROR', status_message?: string }
     */
    public function endTrace(string $traceId, array $attrs = []): void
    {
        if (! $this->shouldEmit()) {
            return;
        }

        $event = [
            'id' => (string) Str::uuid(),
            'type' => 'trace-create', // update via re-emit (Langfuse merge by id)
            'timestamp' => $this->now(),
            'body' => array_filter([
                'id' => $traceId,
                'output' => $this->maybeRedact($attrs['output'] ?? null),
                'level' => $attrs['level'] ?? null,
                'statusMessage' => $attrs['status_message'] ?? null,
            ], fn ($v) => $v !== null),
        ];

        $this->dispatch([$event]);
    }

    /**
     * Registra chamada LLM (generation) dentro de um trace.
     *
     * @param array<string,mixed> $attrs {
     *   name: string,
     *   model: string,
     *   input?: string|array,
     *   output?: string|array,
     *   usage?: array{input?:int, output?:int, total?:int, unit?:string},
     *   duration_ms?: int,
     *   metadata?: array<string,mixed>,
     *   level?: 'DEFAULT'|'WARNING'|'ERROR',
     *   status_message?: string,
     * }
     */
    public function recordGeneration(string $traceId, array $attrs): void
    {
        if (! $this->shouldEmit()) {
            return;
        }

        $this->dispatch([$this->generationEvent($traceId, $attrs)]);
    }

    /**
     * Monta event `generation-create` (formato ingestion v3).
     *
     * @param array<string,mixed> $attrs
     * @return array<string,mixed>
     */
    protected function generationEvent(string $traceId, array $attrs): array
    {
        $startTime = $this->now();
        $endTime = isset($attrs['duration_ms'])
            ? gmdate('Y-m-d\TH:i:s.v\Z', (int) (time() + ((int) $attrs['duration_ms'] / 1000)))
            : null;

        return [
            'id' => (string) Str::uuid(),
            'type' => 'generation-create',
            'timestamp' => $startTime,
            'body' => array_filter([
                'id' => (string) Str::uuid(),
                'traceId' => $traceId,
                'name' => (string) ($attrs['name'] ?? 'llm-call'),
                'model' => (string) ($attrs['model'] ?? 'unknown'),
                'startTime' => $startTime,
                'endTime' => $endTime,
                'input' => $this->maybeRedact($attrs['input'] ?? null),
                'output' => $this->maybeRedact($attrs['output'] ?? null),
                'usage' => $this->normalizeUsage($attrs['usage'] ?? null),
                'metadata' => (array) ($attrs['metadata'] ?? []),
                'level' => $attrs['level'] ?? null,
                'statusMessage' => $attrs['status_message'] ?? null,
            ], fn ($v) => $v !== null && $v !== []),
        ];
    }

    /**
     * Registra sub-operação (span) — retrieval, rerank, RAGAS judge, etc.
     *
     * @param array<string,mixed> $attrs {
     *   name: string,
     *   input?: mixed,
     *   output?: mixed,
     *   duration_ms?: int,
     *   metadata?: array<string,mixed>,
     * }
     */
    public function recordSpan(string $traceId, array $attrs): void
    {
        if (! $this->shouldEmit()) {
            return;
        }

        $startTime = $this->now();
        $endTime = isset($attrs['duration_ms'])
            ? gmdate('Y-m-d\TH:i:s.v\Z', (int) (time() + ((int) $attrs['duration_ms'] / 1000)))
            : null;

        $event = [
            'id' => (string) Str::uuid(),
            'type' => 'span-create',
            'timestamp' => $startTime,
            'body' => array_filter([
                'id' => (string) Str::uuid(),
                'traceId' => $traceId,
                'name' => (string) ($attrs['name'] ?? 'span'),
                'startTime' => $startTime,
                'endTime' => $endTime,
                'input' => $this->maybeRedact($attrs['input'] ?? null),
                'output' => $this->maybeRedact($attrs['output'] ?? null),
                'metadata' => (array) ($attrs['metadata'] ?? []),
            ], fn ($v) => $v !== null && $v !== []),
        ];

        $this->dispatch([$event]);
    }

    /**
     * Registra score numérico (RAGAS / user feedback / custom).
     *
     * Wire pra pipeline H4 (RagasJudgeService): após avaliar resposta, chama
     * recordScore com name='ragas_faithfulness' (etc), value=0.87.
     *
     * @param string $name ex 'ragas_faithfulness', 'ragas_relevancy', 'user_thumbs'
     */
    public function recordScore(string $traceId, string $name, float $value, ?string $comment = null): void
    {
        if (! $this->shouldEmit()) {
            return;
        }

        $event = [
            'id' => (string) Str::uuid(),
            'type' => 'score-create',
            'timestamp' => $this->now(),
            'body' => array_filter([
                'id' => (string) Str::uuid(),
                'traceId' => $traceId,
                'name' => $name,
                'value' => $value,
                'comment' => $comment,
                'dataType' => 'NUMERIC',
            ], fn ($v) => $v !== null),
        ];

        $this->dispatch([$event]);
    }

    /**
     * Envia batch HTTP síncrono pra Langfuse — chamado pelo job assíncrono
     * OU diretamente em modo 'sync' (testes/CI).
     *
     * Fail-open: erros viram warning em log channel 'langfuse', NUNCA crash.
     *
     * @param array<int,array<string,mixed>> $events
     */
    public function flushBatch(array $events): bool
    {
        if (empty($events)) {
            return true;
        }

        $host = rtrim((string) config('langfuse.host', ''), '/');
        $publicKey = (string) config('langfuse.public_key', '');
        $secretKey = (string) config('langfuse.secret_key', '');
        $timeout = (int) config('langfuse.timeout', 5);

        if ($host === '' || $publicKey === '' || $secretKey === '') {
            Log::channel($this->logChannel())->warning('langfuse: keys/host ausentes, skip flush', [
                'events_count' => count($events),
            ]);

            return false;
        }

        try {
            $response = Http::withBasicAuth($publicKey, $secretKey)
                ->timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post("{$host}/api/public/ingestion", ['batch' => $events]);

            if ($response->successful()) {
                return true;
            }

            Log::channel($this->logChannel())->warning('langfuse: ingestion HTTP non-2xx', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
                'events_count' => count($events),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::channel($this->logChannel())->warning('langfuse: ingestion exception', [
                'error' => $e->getMessage(),
                'events_count' => count($events),
            ]);

            return false;
        }
    }

    /**
     * Aplica sampling + enabled flag — decide se evento deve ser emitido.
     */
    protected function shouldEmit(): bool
    {
        if (! (bool) config('langfuse.enabled', false)) {
            return false;
        }

        $rate = (float) config('langfuse.sample_rate', 1.0);
        if ($rate >= 1.0) {
            return true;
        }
        if ($rate <= 0.0) {
            return false;
        }

        return mt_rand() / mt_getrandmax() < $rate;
    }

    /**
     * Dispatch batch via fila ou síncrono conforme config('langfuse.dispatch').
     *
     * @param array<int,array<string,mixed>> $events
     */
    protected function dispatch(array $events): void
    {
        $mode = (string) config('langfuse.dispatch', 'queue');

        if ($mode === 'log') {
            Log::channel($this->logChannel())->info('langfuse: dispatch=log (dry-run)', ['events' => $events]);

            return;
        }

        if ($mode === 'sync') {
            $this->flushBatch($events);

            return;
        }

        // 'queue' default — assíncrono via Bus
        try {
            $job = new LangfuseTraceJob($events);
            $connection = config('langfuse.queue_connection');
            $queue = (string) config('langfuse.queue', 'default');

            if ($connection !== null) {
                $job->onConnection($connection);
            }
            $job->onQueue($queue);

            Bus::dispatch($job);
        } catch (\Throwable $e) {
            // Fail-open mesmo no dispatch: se queue stack quebrar, NÃO crash request.
            Log::channel($this->logChannel())->warning('langfuse: dispatch failed, fallback sync', [
                'error' => $e->getMessage(),
            ]);
            $this->flushBatch($events);
        }
    }

    /**
     * Normaliza usage tokens — Langfuse aceita {input, output, total, unit}.
     *
     * @param array<string,mixed>|null $usage
     * @return array<string,mixed>|null
     */
    protected function normalizeUsage(?array $usage): ?array
    {
        if ($usage === null) {
            return null;
        }

        $normalized = [
            'input' => (int) ($usage['input'] ?? $usage['prompt'] ?? 0),
            'output' => (int) ($usage['output'] ?? $usage['completion'] ?? 0),
            'unit' => (string) ($usage['unit'] ?? 'TOKENS'),
        ];

        if (isset($usage['total'])) {
            $normalized['total'] = (int) $usage['total'];
        } else {
            $normalized['total'] = $normalized['input'] + $normalized['output'];
        }

        return $normalized;
    }

    /**
     * Redacta PII em campos input/output (LGPD) quando flag ON.
     */
    protected function maybeRedact(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! (bool) config('langfuse.redact_pii', true)) {
            return $value;
        }

        $redactor = $this->piiRedactor();
        if ($redactor === null) {
            return $value;
        }

        if (is_string($value)) {
            return $redactor->redact($value);
        }

        if (is_array($value)) {
            return $this->walkRedact($value, $redactor);
        }

        return $value;
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    protected function walkRedact(array $value, object $redactor): array
    {
        foreach ($value as $k => $v) {
            if (is_string($v)) {
                $value[$k] = $redactor->redact($v);
            } elseif (is_array($v)) {
                $value[$k] = $this->walkRedact($v, $redactor);
            }
        }

        return $value;
    }

    protected function piiRedactor(): ?object
    {
        $class = '\\Modules\\Jana\\Services\\Privacy\\PiiRedactor';
        if (! class_exists($class)) {
            return null;
        }
        try {
            return app($class);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function now(): string
    {
        // ISO 8601 com millis UTC — formato esperado por Langfuse
        return gmdate('Y-m-d\TH:i:s.v\Z');
    }

    protected function logChannel(): string
    {
        // Reusa channel 'copiloto-ai' se 'langfuse' não estiver configurado.
        return config('logging.channels.langfuse') ? 'langfuse' : 'copiloto-ai';
    }
}
