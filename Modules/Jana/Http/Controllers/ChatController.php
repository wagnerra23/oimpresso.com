<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\Jana\Contracts\AiAdapter;
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
        $conversas = Conversa::where('user_id', $userId)
            ->where('business_id', $businessId)
            ->orderByDesc('iniciada_em')
            ->get(['id', 'titulo', 'status', 'iniciada_em']);

        $mensagens = $conversa->mensagens()->orderBy('created_at')->get();

        $sugestoesPendentes = Sugestao::where('conversa_id', $conversa->id)
            ->whereNull('escolhida_em')
            ->whereNull('rejeitada_em')
            ->get();

        // Sprint 1 (2026-04-27): Chat.tsx agora usa AppShellV2 (Cockpit) como
        // layout-mae, então precisa dos shell props (business, user, conversas
        // formatadas pra fixadas/rotinas/recentes).
        return Inertia::render('Jana/Chat', array_merge(
            $this->shellPropsFor($businessId, $conversas, $conversa),
            [
                'conversa'           => $conversa,
                'mensagens'          => $mensagens,
                'sugestoesPendentes' => $sugestoesPendentes,
            ]
        ));
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
    public function send(Request $request, $id)
    {
        $request->validate(['content' => 'required|string|max:5000']);

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

        $msgAssistant = Mensagem::create([
            'conversa_id' => $conversa->id,
            'role'        => 'assistant',
            'content'     => $resposta,
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
    public function sendStream(Request $request, $id): StreamedResponse
    {
        $request->validate(['content' => 'required|string|max:5000']);

        $conversa = Conversa::findOrFail($id);
        abort_unless($conversa->user_id === auth()->id(), 403);

        // Persiste mensagem do user IMEDIATAMENTE (antes do stream).
        $msgUser = Mensagem::create([
            'conversa_id' => $conversa->id,
            'role'        => 'user',
            'content'     => $request->input('content'),
        ]);

        $userInput = $request->input('content');

        $response = new StreamedResponse(function () use ($conversa, $userInput, $msgUser) {
            // Disable output buffering em todos os layers PHP/nginx pra SSE real-time
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            @ini_set('implicit_flush', '1');
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            ob_implicit_flush(true);

            $write = function (array $payload) {
                echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            };

            $write(['type' => 'start', 'user_message_id' => $msgUser->id]);

            $textoCompleto = '';

            // US-COPI-203: brief shortcut pre-empta stream normal. Como agent
            // responde tudo de uma vez (não streaming nativo), enviamos como
            // 1 único chunk grande + end (UX: spinner curto → texto inteiro).
            if ($this->briefTrigger->matches($userInput)) {
                $textoCompleto = $this->briefTrigger->gerar($conversa);
                $write(['type' => 'chunk', 'content' => $textoCompleto]);
            } else {
                try {
                    foreach ($this->ai->responderChatStream($conversa, $userInput) as $chunk) {
                        if ($chunk === '') {
                            continue;
                        }
                        $textoCompleto .= $chunk;
                        $write(['type' => 'chunk', 'content' => $chunk]);
                    }
                } catch (\Throwable $e) {
                    $write([
                        'type'    => 'error',
                        'message' => 'Erro ao gerar resposta: ' . substr($e->getMessage(), 0, 200),
                    ]);
                    $textoCompleto = $textoCompleto !== '' ? $textoCompleto : '_(erro)_';
                }
            }

            // Persiste mensagem assistant ao fim do stream.
            // OpenAiDirectDriver atualiza tokens_in/tokens_out via segunda query
            // ao final do stream — depende de UPDATE na latest assistant.
            $msgAssistant = Mensagem::create([
                'conversa_id' => $conversa->id,
                'role'        => 'assistant',
                'content'     => $textoCompleto,
            ]);

            $write([
                'type'                  => 'end',
                'assistant_message_id'  => $msgAssistant->id,
                'chars'                 => mb_strlen($textoCompleto),
            ]);
        });

        $response->headers->set('Content-Type', 'text/event-stream; charset=utf-8');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('Connection', 'keep-alive');
        // Força nginx/Apache a NÃO buffer o stream (ambiente Hostinger)
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
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

    /**
     * MVP do padrão "Chat Cockpit" (ADR 0039) — rota paralela ao /copiloto.
     *
     * Não substitui o ChatController@index. Coexiste em /copiloto/cockpit pra
     * Wagner comparar a UX nova com a atual, lado-a-lado, sem risco. Mock data
     * por enquanto — o backend real só será plugado depois da validação visual.
     */
    public function cockpit(Request $request)
    {
        $businessId = $request->session()->get('user.business_id');
        $user       = auth()->user();
        $isSuper    = $user && ($user->user_type === 'superadmin' || $user->user_type === 'user_oimpresso');

        // Lista de businesses disponíveis pro CompanyPicker:
        // - Superadmin/admin oimpresso: TODAS as businesses ativas
        // - Outros: apenas a business atual do user
        // Tier 0 ADR 0093: filtro explícito por business_id pra não-super
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

        // Payload `jana` = mock estruturado seguindo charter Cockpit.charter.md
        // Visual source: prototipo-ui/_cowork-export-2026-05-15/chat-jana.jsx
        // F2 (próxima): plugar JanaCockpitDataService (brief diário real
        // + KPIs via Service consultando Sells/Receivables/Frota com business_id scope).
        // PII redaction client-side é UX warning · server-side PiiRedactor faz redação real no audit log.
        $jana = $this->mockJanaPayload();

        return Inertia::render('Jana/Cockpit', [
            'businessNome'      => session('business.name', 'Oimpresso Matriz'),
            'businesses'        => $businesses,
            'usuarioNome'       => $userNome,
            'usuarioNomeCurto'  => $user->first_name ?? 'Usuário',
            'usuarioEmail'      => $user->email ?? '',
            'usuarioCargo'      => $isSuper ? 'Administrador' : 'Usuário',
            'usuarioIniciais'   => $this->iniciais($userNome),
            'jana'              => $jana,
        ]);
    }

    /**
     * Mock payload do Cockpit Analista IA — espelha o protótipo Cowork
     * `chat-jana.jsx` (export 2026-05-15). Estrutura Martinho Caçambas
     * (biz=164 legacy migrado · cliente OfficeImpresso).
     *
     * F2 substitui por `JanaCockpitDataService::buildPayload($businessId)`
     * que consulta Sells/Receivables/Frota com business_id scope.
     */
    protected function mockJanaPayload(): array
    {
        return [
            'person'    => ['name' => 'Jana', 'role' => 'Analista IA'],
            'biz'       => ['code' => 'biz=164', 'version' => 'v1404 legacy migrado'],
            'updatedAt' => now()->format('H:i'),
            'today'     => now()->locale('pt_BR')->isoFormat('D [de] MMMM [de] YYYY'),
            'brief'     => [
                'greeting'   => $this->saudacaoPorHora() . ', ' . (auth()->user()->first_name ?? 'Wagner') . '.',
                'paragraphs' => [
                    ['kind' => 'text', 'body' => [
                        ['normal', 'Maio até hoje somou '],
                        ['strong', 'R$ [redacted Tier 0]'],
                        ['normal', ' (vs R$ [redacted Tier 0]k em maio/25 — '],
                        ['danger', '-68%'],
                        ['normal', ' · investigar sazonalidade ou causa estrutural).'],
                    ]],
                    ['kind' => 'text', 'body' => [
                        ['danger', 'R$ [redacted Tier 0]'],
                        ['normal', ' em 4.255 títulos vencidos (excluído lixo de saldos virtuais e parcelas agrupadas). '],
                        ['strong', 'Top 20 clientes concentram R$ [redacted Tier 0]k (~47%)'],
                        ['normal', ' da inadimplência.'],
                    ]],
                    ['kind' => 'action', 'icon' => '🎯', 'body' => [
                        ['strong', 'Ação sugerida HOJE: '],
                        ['normal', '8 clientes "ouro" (LTV >R$ [redacted Tier 0]k) estão >90d sem comprar — score reativação alto. Posso disparar régua WhatsApp HITL?'],
                    ]],
                    ['kind' => 'anomaly', 'body' => [
                        ['normal', 'Anomalia detectada: ticket médio caiu de R$ [redacted Tier 0] para R$ [redacted Tier 0] (-22%) — 4 meses consecutivos. Margem mantida (preço por m³ estável) → indica mix de produto mudando (mais caçambas pequenas/curtas).'],
                    ]],
                ],
                'chips' => [
                    ['tone' => 'primary', 'icon' => '📨', 'label' => 'Disparar régua 8 clientes'],
                    ['tone' => 'ghost',   'icon' => '📋', 'label' => 'Ver top 20 devedores'],
                    ['tone' => 'ghost',   'icon' => '🔍', 'label' => 'Investigar queda ticket médio'],
                    ['tone' => 'ghost',   'icon' => '💡', 'label' => 'Por que -68% MoM?'],
                ],
            ],
            'kpis' => [
                ['label' => 'Receita mês',       'value' => 'R$ [redacted Tier 0]k',   'delta' => '↓ -68% vs mai/25', 'deltaCls' => 'down', 'icon' => '💰'],
                ['label' => 'A receber vencido', 'value' => 'R$ [redacted Tier 0]M',  'deltaCls' => 'red big', 'icon' => '🚨', 'sub' => '4.255 títulos · 76% inadimplência', 'emphasize' => true],
                ['label' => 'Ticket médio',      'value' => 'R$ [redacted Tier 0]', 'delta' => '↓ -22% 4m', 'deltaCls' => 'down', 'icon' => '📈'],
                ['label' => 'Frota utilização',  'value' => '33%',      'deltaCls' => 'info', 'icon' => '🚚', 'sub' => '30/91 · 8 paradas >7d'],
            ],
            'analises' => [
                ['id' => 'inad', 'title' => 'Inadimplência', 'sub' => 'Top 20 devedores', 'pill' => ['tone' => 'crit', 'label' => 'CRÍTICO'], 'icon' => '🚨', 'kind' => 'buckets',
                 'big' => ['value' => 'R$ [redacted Tier 0]', 'color' => 'danger'],
                 'buckets' => [
                     ['label' => '0–30d',   'bar' => 18, 'val' => 'R$ [redacted Tier 0]M', 'color' => '#d4910f'],
                     ['label' => '30–90d',  'bar' => 14, 'val' => 'R$ [redacted Tier 0]k', 'color' => '#e0791a'],
                     ['label' => '90–365d', 'bar' => 31, 'val' => 'R$ [redacted Tier 0]M', 'color' => '#d65a3a'],
                     ['label' => '>365d',   'bar' => 13, 'val' => 'R$ [redacted Tier 0]k', 'color' => '#2a2a2a'],
                 ],
                 'footer' => 'Top 1: VARGAS LEANDRO R$ [redacted Tier 0]k (246 parcelas)'],
                ['id' => 'fat', 'title' => 'Faturamento', 'sub' => 'Curva 24 meses', 'pill' => ['tone' => 'warn', 'label' => 'QUEDA'], 'icon' => '📈', 'kind' => 'sparkline',
                 'big' => ['value' => 'R$ [redacted Tier 0]M', 'color' => 'ok'],
                 'spark' => [1.0, 0.95, 1.05, 1.10, 1.15, 1.18, 1.16, 1.12, 1.08, 1.05, 1.10, 1.15, 1.18, 1.19, 1.14, 1.08, 1.02, 0.95, 0.92, 0.87, 0.82, 0.74, 0.62, 0.55],
                 'sparkRange' => ['mai/24', 'mai/26'],
                 'footer' => 'Melhor mês: nov/24 R$ [redacted Tier 0]M · Pico sazonal: out-fev'],
                ['id' => 'conc', 'title' => 'Concentração', 'sub' => 'Top clientes Pareto', 'pill' => ['tone' => 'ok', 'label' => 'OK'], 'icon' => '🎯', 'kind' => 'bars',
                 'big' => ['value' => '8.856 clientes'],
                 'bars' => [
                     ['label' => 'Top 10',  'bar' => 24, 'pct' => '24%'],
                     ['label' => 'Top 50',  'bar' => 55, 'pct' => '55%'],
                     ['label' => 'Top 100', 'bar' => 73, 'pct' => '73%'],
                 ],
                 'footer' => '4.500 one-shot (~51%) · saudável caçamba avulsa'],
                ['id' => 'churn', 'title' => 'Churn ouro', 'sub' => 'LTV alto inativos', 'pill' => ['tone' => 'react', 'label' => 'REATIVAR'], 'icon' => '⏰', 'kind' => 'list',
                 'big' => ['value' => '8 clientes'],
                 'list' => [
                     ['left' => 'CONSTRUFERRO IND.', 'right' => 'LTV R$ [redacted Tier 0]k · 124d'],
                     ['left' => 'EXTREMA SOLDAS',    'right' => 'LTV R$ [redacted Tier 0]k · 98d'],
                     ['left' => 'CAPITAL CARGAS',    'right' => 'LTV R$ [redacted Tier 0]k · 112d'],
                 ],
                 'footer' => 'Cohort 2024: retenção 35% (target 60%) · drift alto'],
                ['id' => 'frota', 'title' => 'Frota', 'sub' => '91 caçambas avulsas', 'pill' => ['tone' => 'warn', 'label' => 'PARADAS'], 'icon' => '🚛', 'kind' => 'donut',
                 'donut' => ['pct' => 33, 'segs' => [
                     ['color' => '#2563eb', 'pct' => 33],
                     ['color' => '#22c55e', 'pct' => 58],
                     ['color' => '#e0791a', 'pct' => 9],
                 ]],
                 'legend' => [
                     ['color' => '#2563eb', 'label' => 'Locadas',     'val' => '30'],
                     ['color' => '#22c55e', 'label' => 'Disponíveis', 'val' => '61'],
                     ['color' => '#e0791a', 'label' => 'Paradas >7d', 'val' => '8', 'danger' => true],
                 ],
                 'footer' => '3 overdue HOJE · target util 70%'],
                ['id' => 'cheq', 'title' => 'Cheques previsão', 'sub' => 'Na mão / a depositar', 'icon' => '🧾', 'kind' => 'text',
                 'big' => ['value' => '4.421 cheques'],
                 'text' => [
                     'Total circulou histórico: R$ [redacted Tier 0]',
                     'Quitados: 4.420 (99,9%)',
                     'Ativos hoje: 1 (R$ [redacted Tier 0] — teste)',
                 ],
                 'footnote' => 'Atalho HITL: Jana lembra Larissa qual dia depositar cada cheque'],
            ],
            'acoes' => [
                ['id' => 'a1', 'icon' => '📨', 'tone' => 'rose',   'title' => 'Régua WhatsApp · 8 clientes >90d sem contato',  'sub' => 'Potencial recuperação: R$ [redacted Tier 0]k · HITL aprovação cada msg', 'cta' => ['label' => 'Disparar', 'tone' => 'danger']],
                ['id' => 'a2', 'icon' => '❤️', 'tone' => 'violet', 'title' => 'Reativação · 8 clientes "ouro" inativos',         'sub' => 'LTV combinado R$ [redacted Tier 0]k · oferta retorno personalizada',     'cta' => ['label' => 'Preparar', 'tone' => 'violet']],
                ['id' => 'a3', 'icon' => '🚛', 'tone' => 'peach',  'title' => 'Outbound · 8 caçambas paradas há >7d',           'sub' => 'Top 3 últimos clientes mesma região · ligar HOJE',          'cta' => ['label' => 'Listar', 'tone' => 'orange']],
                ['id' => 'a4', 'icon' => '🗑️', 'tone' => 'grey',   'title' => 'Cleanup · 2.470 títulos write-off candidatos',   'sub' => 'R$ [redacted Tier 0]k incobráveis >365d · liberar dashboard',             'cta' => ['label' => 'Revisar', 'tone' => 'dark']],
            ],
            'chat' => [
                'messages' => [
                    ['from' => 'user', 'kind' => 'text', 'text' => 'Quais os top 5 devedores agora?'],
                    ['from' => 'jana', 'kind' => 'tool_use', 'tool' => 'financeiro.devedores.top', 'status' => 'done'],
                    ['from' => 'jana', 'kind' => 'data_table',
                     'caption' => '5 devedores ativos (sem agrupados duplicados)',
                     'columns' => ['Cliente', 'Saldo', 'Parcelas'],
                     'rows' => [
                         ['VARGAS LEANDRO COM. VAREJISTA', 'R$ [redacted Tier 0]', '229'],
                         ['TORK COMERCIO DE PECAS AUTO',   'R$ [redacted Tier 0]', '167'],
                         ['AMS SOLDAS E MAQUINAS',         'R$ [redacted Tier 0]', '71'],
                         ['BUSSOLO E PRUDENCIO',           'R$ [redacted Tier 0]', '43'],
                         ['FAN COM. DE PECAS E IMPLEMENTOS', 'R$ [redacted Tier 0]', '166'],
                     ]],
                    ['from' => 'jana', 'kind' => 'markdown',
                     'text' => "Top 5 concentra **R$ [redacted Tier 0]k** (~20% inadimplência). VARGAS sozinho concentra **8,5%** [1] — risco alto, mas é cliente recorrente (229 parcelas) [2] então tem relacionamento.",
                     'sources' => [
                         ['n' => 1, 'label' => 'Inadimplência por cliente', 'href' => '/financeiro/inadimplencia?cliente=vargas'],
                         ['n' => 2, 'label' => 'Histórico VARGAS',          'href' => '/clientes/vargas'],
                     ]],
                    ['from' => 'jana', 'kind' => 'action_card',
                     'summary' => 'Disparar régua WhatsApp pra VARGAS LEANDRO (último contato 47d)',
                     'confirm_required' => true],
                ],
                'suggestions' => [
                    ['icon' => '🤔', 'label' => 'Quem deve mais?'],
                    ['icon' => '💸', 'label' => 'Vendi ontem?'],
                    ['icon' => '🧭', 'label' => 'Onde estou perdendo?'],
                    ['icon' => '🎯', 'label' => 'Quais ações HOJE?'],
                    ['icon' => '🚛', 'label' => 'Caçambas paradas'],
                ],
            ],
        ];
    }

    /**
     * Saudação por hora do dia (BRT — sem TZ awareness por enquanto).
     */
    protected function saudacaoPorHora(): string
    {
        $h = (int) now()->format('H');
        if ($h < 12) return 'Bom dia';
        if ($h < 18) return 'Boa tarde';
        return 'Boa noite';
    }

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
