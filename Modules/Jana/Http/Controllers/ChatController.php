<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\Jana\Contracts\AiAdapter;
use Modules\Jana\Http\Requests\SendChatMessageRequest;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\Mensagem;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaFonte;
use Modules\Jana\Entities\MetaPeriodo;
use Modules\Jana\Entities\Sugestao;
use Modules\Jana\Jobs\ApurarMetaJob;
use Modules\Jana\Services\BriefDiarioChatTrigger;
use Modules\Jana\Services\ContextSnapshotService;
use Modules\Jana\Services\SuggestionEngine;
use Modules\Jana\Services\Telemetry\LangfuseClient;
use Modules\Jana\Services\Telemetry\TraceContext;

/**
 * Chat é o entry-point do módulo (ver adr/arq/0002).
 */
class ChatController extends Controller
{
    public function __construct(
        protected ContextSnapshotService $context,
        protected SuggestionEngine $suggestions,
        protected AiAdapter $ai,
        protected BriefDiarioChatTrigger $briefTrigger,
        protected LangfuseClient $langfuse,
    ) {
    }

    /**
     * Home do módulo — cria (ou retoma) conversa e renderiza chat.
     */
    public function index(Request $request)
    {
        $businessId = $request->session()->get('user.business_id');
        $userId     = auth()->id();

        $conversa = Conversa::where('user_id', $userId)
            ->where('business_id', $businessId)
            ->where('status', 'ativa')
            ->latest('iniciada_em')
            ->first();

        if (! $conversa) {
            $conversa = Conversa::create([
                'business_id' => $businessId,
                'user_id'     => $userId,
                'titulo'      => 'Nova conversa',
                'status'      => 'ativa',
                'iniciada_em' => now(),
            ]);

            // Gera briefing e insere como mensagem 0
            try {
                $ctx      = $this->context->paraBusiness($businessId);
                $briefing = $this->ai->gerarBriefing($ctx);
            } catch (\Throwable $e) {
                $briefing = 'Olá! Sou seu Copiloto. Como posso ajudar hoje?';
            }

            Mensagem::create([
                'conversa_id' => $conversa->id,
                'role'        => 'assistant',
                'content'     => $briefing,
            ]);
        }

        return $this->renderChat($conversa, $businessId, $userId);
    }

    public function show($id)
    {
        $conversa = Conversa::findOrFail($id);
        abort_unless($conversa->user_id === auth()->id(), 403);

        $businessId = session('user.business_id');

        return $this->renderChat($conversa, $businessId, auth()->id());
    }

    protected function renderChat(Conversa $conversa, $businessId, $userId)
    {
        // ROLLBACK Wave L/W7 PR #963: Inertia::defer quebrava Pages (initial render undefined).
        $shellProps = $this->shellPropsForDeferred($businessId, $conversa, $userId);

        return Inertia::render('Jana/Chat', array_merge(
            $shellProps,
            [
                'conversa'           => $conversa,
                'mensagens'          => $this->buildMensagensPayload($conversa),
                'sugestoesPendentes' => $this->buildSugestoesPendentesPayload($conversa),
            ]
        ));
    }

    /**
     * D-14 perf — mensagens da conversa em closure defer.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function buildMensagensPayload(Conversa $conversa)
    {
        return $conversa->mensagens()->orderBy('created_at')->get();
    }

    /**
     * D-14 perf — sugestões pendentes (não escolhidas/rejeitadas) em closure defer.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function buildSugestoesPendentesPayload(Conversa $conversa)
    {
        return Sugestao::where('conversa_id', $conversa->id)
            ->whereNull('escolhida_em')
            ->whereNull('rejeitada_em')
            ->get();
    }

    /**
     * D-14 perf — shell props com lista de conversas deferida.
     * Conversa atual + business + user permanecem eager (necessários no render inicial).
     * Lista `conversas[recentes]` deferida (pode ter dezenas/centenas de rows).
     */
    protected function shellPropsForDeferred($businessId, ?Conversa $conversaFoco, $userId): array
    {
        $user    = auth()->user();
        $isSuper = $user && ($user->user_type === 'superadmin' || $user->user_type === 'user_oimpresso');

        // closure D-14 (2026-07-06, ref PR #3889): por business/usuário, não muda
        // ao trocar de conversa — pula no partial reload do selectConv (only:
        // conversa/mensagens/sugestoesPendentes). Diferente do defer (rollback
        // PR #963), closure roda no load cheio — 1º render nunca vê undefined.
        $businesses = function () use ($isSuper, $businessId) {
            $businessesDisponiveis = $isSuper
                ? \App\Business::orderBy('name')->limit(50)->get(['id', 'name'])
                : \App\Business::where('id', $businessId)->get(['id', 'name']);

            return $businessesDisponiveis->map(fn ($b) => [
                'id'       => $b->id,
                'nome'     => $b->name,
                'iniciais' => $this->iniciais($b->name),
                'ativa'    => $b->id === (int) $businessId,
            ])->values();
        };

        $userNome = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->username ?? 'Usuário');

        $roleName = null;
        try {
            $firstRole = method_exists($user, 'roles') ? $user->roles()->first() : null;
            $roleName = $firstRole?->name;
            if ($roleName) {
                $roleName = preg_replace('/#\d+$/', '', $roleName);
            }
        } catch (\Throwable $e) {
            $roleName = null;
        }
        $cargo = $isSuper ? 'Superadmin' : ($roleName ?: 'Usuário');

        return [
            'businessNome'     => session('business.name', 'Oimpresso Matriz'),
            'businesses'       => $businesses,
            'usuarioNome'      => $userNome,
            'usuarioNomeCurto' => $user->first_name ?? 'Usuário',
            'usuarioEmail'     => $user->email ?? '',
            'usuarioCargo'     => $cargo,
            'usuarioIniciais'  => $this->iniciais($userNome),
            // ROLLBACK Wave L/W7 PR #963: Inertia::defer quebrava Pages (initial render undefined).
            // closure D-14: lista de conversas não muda ao trocar de conversa (highlight
            // é client-side via activeConvId) — pula no partial reload do selectConv.
            'conversas'        => fn () => $this->buildConversasListPayload($businessId, $userId, $conversaFoco),
        ];
    }

    /**
     * D-14 perf — lista de conversas do usuário (sidebar Cockpit) em closure defer.
     * @return array{fixadas: array, rotinas: array, recentes: array}
     */
    protected function buildConversasListPayload($businessId, $userId, ?Conversa $conversaFoco): array
    {
        $conversasReais = Conversa::where('user_id', $userId)
            ->where('business_id', $businessId)
            ->orderByDesc('iniciada_em')
            ->get(['id', 'titulo', 'status', 'iniciada_em']);

        $ultimas = $this->ultimaMensagemPorConversa($conversasReais->pluck('id')->all());

        // `status` alimenta o filtro Todas|Arquivadas do ConvSidePanel (Chat.tsx).
        // A coluna já existia e já vinha no get() acima — só não trafegava pro
        // frontend, o que deixava a barra de filtro decorativa. Vocabulário:
        // 'ativa' (default de criarConversa) | 'arquivada' (via PATCH
        // jana.conversas.update, que já aceita o campo).
        $recentes = collect($conversasReais)->map(fn ($c) => [
            'id'     => (string) $c->id,
            'titulo' => $c->titulo,
            'unread' => 0,
            'origem' => 'COPI',
            'status' => $c->status,
            'ativa'  => $conversaFoco && (int) $c->id === (int) $conversaFoco->id,
            // `preview` e `ultima_em` alimentam os metadados do card (protótipo
            // `jana-merge.jsx` §JmConversa: resumo de uma linha + "última em X").
            // Nenhum dos dois é coluna: `jana_conversas` não guarda preview nem
            // última atividade, e `iniciada_em` NÃO serve — é quando a conversa
            // nasceu, não quando falaram nela pela última vez.
            'preview'   => $ultimas[$c->id]['preview'] ?? null,
            'ultima_em' => $ultimas[$c->id]['ultima_em'] ?? null,
        ])->values()->all();

        return [
            'fixadas'  => [],
            'rotinas'  => [],
            'recentes' => $recentes,
        ];
    }

    /**
     * Última mensagem de cada conversa — preview + instante, numa query só.
     *
     * UMA query pro conjunto inteiro (subquery de MAX(id) agrupada), não uma por
     * conversa: a lista é servida em `Inertia::defer` no render da tela, e um N+1
     * aqui apareceria direto na latência que o charter cobra (p95 < 1000ms).
     * O índice `(conversa_id, created_at)` da migration 000006 cobre o agrupamento.
     *
     * MAX(`id`) e não MAX(`created_at`): `jana_mensagens` é append-only com
     * auto-increment, então o maior id É o mais recente — e não empata quando duas
     * mensagens caem no mesmo segundo, que é o caso normal de user+assistant.
     *
     * Multi-tenant Tier 0 (ADR 0093): duas camadas. `$ids` já vem de uma query
     * escopada por `business_id` + `user_id`, e o `Mensagem::query()` ainda carrega
     * o global scope `ScopeByBusinessViaParent` (tenancy herdada da conversa).
     *
     * @param  array<int, int|string>  $ids
     * @return array<int|string, array{preview: string, ultima_em: string}>
     */
    protected function ultimaMensagemPorConversa(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Mensagem::query()
            ->whereIn('conversa_id', $ids)
            ->whereIn('id', function ($q) use ($ids) {
                $q->selectRaw('MAX(id)')
                    ->from('jana_mensagens')
                    ->whereIn('conversa_id', $ids)
                    ->groupBy('conversa_id');
            })
            ->get(['conversa_id', 'content', 'created_at'])
            ->mapWithKeys(fn ($m) => [$m->conversa_id => [
                'preview' => $this->resumirParaPreview((string) $m->content),
                // ISO-8601 CRU, formatado no cliente de propósito. Formatar aqui
                // herdaria o shift +3h que `format_date` aplica pra cliente legado
                // (ADR 0066) — o card mostraria hora errada pra quem cair naquele
                // caminho. O browser sabe o fuso e o locale de quem está olhando.
                'ultima_em' => optional($m->created_at)->toIso8601String(),
            ]])
            ->all();
    }

    /** Resumo de UMA linha: colapsa espaço/quebra e corta em 90 caracteres. */
    protected function resumirParaPreview(string $content): string
    {
        $limpo = trim((string) preg_replace('/\s+/u', ' ', $content));

        return mb_strlen($limpo) > 90 ? mb_substr($limpo, 0, 89) . '…' : $limpo;
    }

    /**
     * Shell props comuns pro AppShellV2 (Cockpit) — reusado por @index, @show,
     * @cockpit. Retorna business + user + conversas mapeadas pro formato esperado
     * pelo layout (fixadas/rotinas/recentes).
     */
    protected function shellPropsFor($businessId, $conversasReais, ?Conversa $conversaFoco = null): array
    {
        $user    = auth()->user();
        $isSuper = $user && ($user->user_type === 'superadmin' || $user->user_type === 'user_oimpresso');

        $businessesDisponiveis = $isSuper
            ? \App\Business::orderBy('name')->limit(50)->get(['id', 'name'])
            : \App\Business::where('id', $businessId)->get(['id', 'name']);

        $businesses = $businessesDisponiveis->map(fn ($b) => [
            'id'       => $b->id,
            'nome'     => $b->name,
            'iniciais' => $this->iniciais($b->name),
            'ativa'    => $b->id === (int) $businessId,
        ])->values();

        $userNome = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->username ?? 'Usuário');

        // Cargo real do Spatie role (formato `{Nome}#{biz_id}` — strip suffix).
        // Wagner pediu 2026-05-05: footer mostra role, não label genérico.
        $roleName = null;
        try {
            $firstRole = method_exists($user, 'roles') ? $user->roles()->first() : null;
            $roleName = $firstRole?->name;
            if ($roleName) {
                $roleName = preg_replace('/#\d+$/', '', $roleName);
            }
        } catch (\Throwable $e) {
            $roleName = null;
        }
        $cargo = $isSuper ? 'Superadmin' : ($roleName ?: 'Usuário');

        // Conversas reais → formato Cockpit. Pra Sprint 1, todas viram "recentes".
        // Fixadas e rotinas são mocks vazios — Fase 2 vai modelar isso de verdade.
        $recentes = collect($conversasReais)->map(fn ($c) => [
            'id'     => (string) $c->id,
            'titulo' => $c->titulo,
            'unread' => 0,
            'origem' => 'COPI', // tag interna; UI ainda usa só 5 origin badges canônicas
            'ativa'  => $conversaFoco && (int) $c->id === (int) $conversaFoco->id,
        ])->values()->all();

        return [
            'businessNome'     => session('business.name', 'Oimpresso Matriz'),
            'businesses'       => $businesses,
            'usuarioNome'      => $userNome,
            'usuarioNomeCurto' => $user->first_name ?? 'Usuário',
            'usuarioEmail'     => $user->email ?? '',
            'usuarioCargo'     => $cargo,
            'usuarioIniciais'  => $this->iniciais($userNome),
            'conversas'        => [
                'fixadas'  => [],
                'rotinas'  => [],
                'recentes' => $recentes,
            ],
        ];
    }

    public function criarConversa(Request $request)
    {
        $conversa = Conversa::create([
            'business_id' => $request->session()->get('user.business_id'),
            'user_id'     => auth()->id(),
            'titulo'      => $request->input('titulo', 'Nova conversa'),
            'status'      => 'ativa',
            'iniciada_em' => now(),
        ]);

        return redirect()->route('jana.conversas.show', $conversa->id);
    }

    /**
     * GET /copiloto/conversas/nova — atalho UX da sidebar.
     *
     * Wagner 2026-05-08: link "Nova conversa" na sidebar do chat era `<a href="/conversas/nova">`
     * (GET) mas só existia POST `criarConversa` — resultava em 404. Esta rota cria conversa
     * limpa e redireciona pra /conversas/{id}.
     */
    public function novaConversa(Request $request)
    {
        $conversa = Conversa::create([
            'business_id' => $request->session()->get('user.business_id'),
            'user_id'     => auth()->id(),
            'titulo'      => 'Nova conversa',
            'status'      => 'ativa',
            'iniciada_em' => now(),
        ]);

        return redirect()->route('jana.conversas.show', $conversa->id);
    }

    public function updateConversa(Request $request, $id)
    {
        $conversa = Conversa::findOrFail($id);
        abort_unless($conversa->user_id === auth()->id(), 403);

        $conversa->update($request->only(['titulo', 'status']));

        return response()->json(['ok' => true]);
    }

    /**
     * Usuário manda mensagem → IA responde + opcionalmente retorna propostas.
     */
    public function send(SendChatMessageRequest $request, $id)
    {
        $conversa = Conversa::findOrFail($id);
        abort_unless($conversa->user_id === auth()->id(), 403);

        $userInput = $request->input('content');

        // Persiste mensagem do usuário
        Mensagem::create([
            'conversa_id' => $conversa->id,
            'role'        => 'user',
            'content'     => $userInput,
        ]);

        // US-COPI-203: intent shortcut pro brief diário JANA Pro. Se user
        // pediu brief (regex match), invoca BriefDiarioAgent direto em vez
        // do ChatCopilotoAgent. Retorna markdown formatado Versão A.
        if ($this->briefTrigger->matches($userInput)) {
            $resposta = $this->briefTrigger->gerar($conversa);
        } else {
            // Caminho padrão — IA conversacional ChatCopilotoAgent
            try {
                $resposta = $this->ai->responderChat($conversa, $userInput);
            } catch (\Throwable $e) {
                $resposta = 'Estou com dificuldades técnicas no momento. Tente novamente em instantes.';
            }
        }

        // Tokens DESTE turno. O driver não grava mais sozinho — ele retornava
        // antes desta linha, então o UPDATE dele caía no turno ANTERIOR.
        // Ver AiAdapter::ultimoUsoTokens(). No atalho do brief o adapter nem é
        // chamado, e aí vem null/null (correto: não houve consumo por aqui).
        $uso = $this->ai->ultimoUsoTokens();

        $msgAssistant = Mensagem::create([
            'conversa_id' => $conversa->id,
            'role'        => 'assistant',
            'content'     => $resposta,
            'tokens_in'   => $uso['tokens_in'] ?? null,
            'tokens_out'  => $uso['tokens_out'] ?? null,
        ]);

        return back();
    }

    /**
     * Variante streaming SSE do `send()`. UX token-por-token (sem freeze).
     *
     * Protocolo SSE custom (linha-a-linha JSON):
     *   data: {"type":"start","user_message_id":42}\n\n
     *   data: {"type":"chunk","content":"Olá"}\n\n
     *   data: {"type":"chunk","content":", como"}\n\n
     *   ...
     *   data: {"type":"end","assistant_message_id":43,"chars":120}\n\n
     *
     * Em erro:
     *   data: {"type":"error","message":"..."}\n\n
     *
     * Frontend: fetch() + ReadableStream + TextDecoder pra parse linha-a-linha.
     * NÃO usa EventSource (que só faz GET; nosso endpoint é POST com body).
     */
    public function sendStream(SendChatMessageRequest $request, $id): StreamedResponse
    {
        $conversa = Conversa::findOrFail($id);
        abort_unless($conversa->user_id === auth()->id(), 403);

        $userInput = (string) $request->input('content');
        $businessId = (int) $conversa->business_id;
        $userId = (int) $conversa->user_id;

        $response = new StreamedResponse(function () use ($conversa, $userInput, $businessId, $userId) {
            $startedAt = microtime(true);
            $traceId = $this->langfuse->startTrace([
                'name' => 'jana-chat-stream',
                'business_id' => $businessId,
                'user_id' => $userId,
                'conversation_id' => (int) $conversa->id,
                'tool' => 'chat-sse',
                'input' => $userInput,
                'metadata' => ['stream' => true],
            ]);

            $previousTraceId = TraceContext::activate($traceId);

            $result = 'error';
            $path = 'llm';
            $errorClass = null;
            $textoCompleto = '';
            $msgAssistant = null;
            $previousIgnoreUserAbort = ignore_user_abort(true);

            try {
                OtelHelper::span('jana.chat.stream', [
                    'business_id' => $businessId,
                    'oimpresso.tenant_id' => (string) $businessId,
                    'user_id' => $userId,
                    'conversation_id' => (int) $conversa->id,
                    'jana.trace_id' => $traceId,
                    'stream' => true,
                ], function () use (
                    $conversa,
                    $userInput,
                    &$result,
                    &$path,
                    &$errorClass,
                    &$textoCompleto,
                    &$msgAssistant,
                    $startedAt
                ): void {
                    // A raiz já está ativa: a persistência do user e toda a
                    // iteração do Generator ficam no mesmo lifecycle.
                    $msgUser = Mensagem::create([
                        'conversa_id' => $conversa->id,
                        'role' => 'user',
                        'content' => $userInput,
                    ]);

                    // Disable output buffering em todos os layers PHP/nginx pra SSE real-time.
                    @ini_set('zlib.output_compression', '0');
                    @ini_set('output_buffering', 'off');
                    @ini_set('implicit_flush', '1');
                    while (ob_get_level() > 0) {
                        @ob_end_flush();
                    }
                    ob_implicit_flush(true);

                    $write = function (array $payload): void {
                        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                    };

                    $write(['type' => 'start', 'user_message_id' => $msgUser->id]);
                    $cancelled = false;
                    $controllerError = null;

                    try {
                        // US-COPI-203: brief shortcut pre-empta stream normal.
                        if ($this->briefTrigger->matches($userInput)) {
                            $path = 'brief';
                            $textoCompleto = $this->briefTrigger->gerar($conversa);
                            $write(['type' => 'chunk', 'content' => $textoCompleto]);
                            $cancelled = $this->connectionAborted();
                        } else {
                            foreach ($this->ai->responderChatStream($conversa, $userInput) as $chunk) {
                                if ($chunk === '') {
                                    continue;
                                }
                                $textoCompleto .= $chunk;
                                $write(['type' => 'chunk', 'content' => $chunk]);
                                if ($this->connectionAborted()) {
                                    $cancelled = true;
                                    break;
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        $controllerError = $e;
                        $errorClass = $e::class;
                        $write([
                            'type' => 'error',
                            'message' => 'Não foi possível concluir a resposta.',
                        ]);
                        $textoCompleto = $textoCompleto !== '' ? $textoCompleto : '_(erro)_';
                    }

                    $outcome = $path === 'brief'
                        ? [
                            'path' => 'brief',
                            'status' => 'ok',
                            'cache_hit' => false,
                            'recall_count' => 0,
                            'jobs_dispatched' => 0,
                            'error_class' => null,
                        ]
                        : $this->ai->ultimoResultadoStream();

                    $path = $outcome['path'];
                    $adapterStatus = $outcome['status'];
                    $errorClass ??= $outcome['error_class'];
                    $result = $cancelled
                        ? 'cancelled'
                        : ($controllerError !== null
                            ? ($textoCompleto !== '_(erro)_' ? 'partial_error' : 'error')
                            : $adapterStatus);

                    // Persiste a resposta parcial inclusive no abandono: o que
                    // já foi mostrado não desaparece e o ciclo pode aprender.
                    $uso = $path === 'brief'
                        ? ['tokens_in' => null, 'tokens_out' => null]
                        : $this->ai->ultimoUsoTokens();
                    $msgAssistant = Mensagem::create([
                        'conversa_id' => $conversa->id,
                        'role' => 'assistant',
                        'content' => $textoCompleto,
                        'tokens_in' => $uso['tokens_in'] ?? null,
                        'tokens_out' => $uso['tokens_out'] ?? null,
                    ]);

                    if (! $cancelled) {
                        $write([
                            'type' => 'end',
                            'assistant_message_id' => $msgAssistant->id,
                            'chars' => mb_strlen($textoCompleto),
                        ]);
                    }

                    OtelHelper::annotateCurrent([
                        'user_message_id' => (int) $msgUser->id,
                        'assistant_message_id' => (int) $msgAssistant->id,
                        'path' => $path,
                        'cache_hit' => $outcome['cache_hit'],
                        'recall_count' => $outcome['recall_count'],
                        'usage_input' => (int) ($uso['tokens_in'] ?? 0),
                        'usage_output' => (int) ($uso['tokens_out'] ?? 0),
                        'jobs_dispatched' => $outcome['jobs_dispatched'],
                        'chars_out' => mb_strlen($textoCompleto),
                        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                        'result' => $result,
                        'response_partial' => in_array($result, ['partial_error', 'cancelled'], true),
                        'error_class' => $errorClass,
                    ], in_array($result, ['error', 'partial_error'], true));
                });
            } catch (\Throwable $e) {
                $errorClass = $e::class;
                $result = $textoCompleto !== '' ? 'partial_error' : 'error';
                throw $e;
            } finally {
                $this->langfuse->endTrace($traceId, [
                    'output' => $textoCompleto,
                    'level' => in_array($result, ['error', 'partial_error'], true) ? 'ERROR' : 'DEFAULT',
                    'status_message' => $errorClass,
                    'metadata' => [
                        'path' => $path,
                        'result' => $result,
                        'assistant_message_id' => $msgAssistant?->id,
                        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    ],
                ]);

                TraceContext::restore($previousTraceId);
                ignore_user_abort($previousIgnoreUserAbort !== 0);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream; charset=utf-8');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('Connection', 'keep-alive');
        // Força nginx/Apache a NÃO buffer o stream (ambiente Hostinger)
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * Ponto estreito para detectar abandono real do socket e permitir teste
     * determinístico sem fabricar um segundo pipeline de streaming.
     */
    protected function connectionAborted(): bool
    {
        return connection_aborted() === 1;
    }

    /**
     * Gestor escolhe uma proposta → vira Meta + MetaPeriodo + MetaFonte ativos.
     */
    public function escolher(Request $request, $id)
    {
        $sugestao = Sugestao::findOrFail($id);
        $payload  = $sugestao->payload_json;

        $meta = Meta::create([
            'business_id'        => $sugestao->conversa->business_id,
            'slug'               => data_get($payload, 'slug', 'custom'),
            'nome'               => data_get($payload, 'nome', 'Meta'),
            'unidade'            => data_get($payload, 'unidade', 'R$'),
            'tipo_agregacao'     => data_get($payload, 'tipo_agregacao', 'soma'),
            'ativo'              => true,
            'criada_por_user_id' => auth()->id(),
            'origem'             => 'chat_ia',
        ]);

        MetaPeriodo::create([
            'meta_id'      => $meta->id,
            'tipo_periodo' => data_get($payload, 'periodo_tipo', 'ano'),
            'data_ini'     => data_get($payload, 'data_ini'),
            'data_fim'     => data_get($payload, 'data_fim'),
            'valor_alvo'   => data_get($payload, 'valor_alvo'),
            'trajetoria'   => 'linear',
        ]);

        MetaFonte::create([
            'meta_id'     => $meta->id,
            'driver'      => 'sql',
            'config_json' => data_get($payload, 'fonte', ['query' => null]),
            'cadencia'    => 'diaria',
        ]);

        $sugestao->update(['meta_id' => $meta->id, 'escolhida_em' => now()]);

        // Agenda apuração imediata
        ApurarMetaJob::dispatch($meta, now());

        return redirect()->route('jana.metas.show', $meta->id);
    }

    public function rejeitar(Request $request, $id)
    {
        Sugestao::findOrFail($id)->update(['rejeitada_em' => now()]);

        return response()->json(['ok' => true]);
    }

    // ── Cockpit REMOVIDO na onda 4 da fusão (US-COPI-148, 2026-08-07) ───────
    //
    // Saíram daqui juntos, por dependência mútua medida:
    //   cockpit()          renderizava `Jana/Cockpit`, a Page apagada nesta onda
    //   mockJanaPayload()  só era chamado por cockpit() — payload MOCK (Martinho
    //                      biz=164) sendo servido numa rota LIVE
    //   saudacaoPorHora()  só era chamado por mockJanaPayload()
    //
    // Isto FECHA a US-COPI-123 (p0). Ela pedia tirar o mock da rota live, e as
    // DUAS metades do mock eram este par: o `startMockStream` do Cockpit.tsx e o
    // `mockJanaPayload()` daqui. Não houve conserto — a capacidade tem receptor
    // vivo em `/ia` (IndexController), que entrega brief · KPIs · análises · ações
    // com dado REAL do SellsCockpitAggregator.
    //
    // `iniciais()` FICOU: index() e show() usam (linhas 148 · 174 · 225 · 262).
    // `/ia/cockpit` segue como 301 → /ia (routes.php, onda 3).

    /**
     * Iniciais (até 2 letras) pra usar em avatars: "Wagner Rocha" -> "WR".
     */
    protected function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [];
        $iniciais = '';
        foreach ($partes as $p) {
            if ($p === '') continue;
            $iniciais .= mb_strtoupper(mb_substr($p, 0, 1));
            if (mb_strlen($iniciais) >= 2) break;
        }
        return $iniciais ?: '?';
    }
}
