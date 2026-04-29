<?php

namespace Modules\Copiloto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\Copiloto\Contracts\AiAdapter;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Entities\Mensagem;
use Modules\Copiloto\Entities\Meta;
use Modules\Copiloto\Entities\MetaFonte;
use Modules\Copiloto\Entities\MetaPeriodo;
use Modules\Copiloto\Entities\Sugestao;
use Modules\Copiloto\Jobs\ApurarMetaJob;
use Modules\Copiloto\Services\ContextSnapshotService;
use Modules\Copiloto\Services\SuggestionEngine;

/**
 * Chat é o entry-point do módulo (ver adr/arq/0002).
 */
class ChatController extends Controller
{
    public function __construct(
        protected ContextSnapshotService $context,
        protected SuggestionEngine $suggestions,
        protected AiAdapter $ai,
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
        return Inertia::render('Copiloto/Chat', array_merge(
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
            'usuarioCargo'     => $isSuper ? 'Administrador' : 'Usuário',
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

        return redirect()->route('copiloto.conversas.show', $conversa->id);
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

        // Persiste mensagem do usuário
        Mensagem::create([
            'conversa_id' => $conversa->id,
            'role'        => 'user',
            'content'     => $request->input('content'),
        ]);

        // Obtém resposta da IA
        try {
            $resposta = $this->ai->responderChat($conversa, $request->input('content'));
        } catch (\Throwable $e) {
            $resposta = 'Estou com dificuldades técnicas no momento. Tente novamente em instantes.';
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

        return redirect()->route('copiloto.metas.show', $meta->id);
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
        $userId     = auth()->id();
        $user       = auth()->user();
        $isSuper    = $user && ($user->user_type === 'superadmin' || $user->user_type === 'user_oimpresso');

        // Lista de businesses disponiveis pro CompanyPicker:
        // - Superadmin/admin oimpresso: TODAS as businesses ativas
        // - Outros: apenas a business atual do user
        $businessesDisponiveis = $isSuper
            ? \App\Business::orderBy('name')->limit(50)->get(['id', 'name'])
            : \App\Business::where('id', $businessId)->get(['id', 'name']);

        $businesses = $businessesDisponiveis->map(fn ($b) => [
            'id'       => $b->id,
            'nome'     => $b->name,
            'iniciais' => $this->iniciais($b->name),
            'ativa'    => $b->id === (int) $businessId,
        ])->values();

        // Tenta puxar a conversa real ativa do usuário (se houver) — só pra
        // ter um ID válido pro composer de teste. Se não tiver, usa null.
        $conversaAtiva = Conversa::where('user_id', $userId)
            ->where('business_id', $businessId)
            ->where('status', 'ativa')
            ->latest('iniciada_em')
            ->first(['id', 'titulo']);

        // Mock de conversas espelhando a vibe do protótipo (Cowork "Oimpresso ERP
        // Comunicação Visual"). Categorias: fixadas, rotinas, recentes.
        // Gradualmente vai virar dado real conforme TaskProvider/CRM forem
        // entregando contexto (ver ADR 0039 plano de migração).
        $mockConversas = [
            'fixadas' => [
                ['id' => 'p1', 'titulo' => 'Banner Loja Acme 3×2m', 'unread' => 0, 'origem' => 'OS'],
                ['id' => 'p2', 'titulo' => 'Produção — Turno A',     'unread' => 2, 'origem' => 'MFG'],
            ],
            'rotinas' => [
                ['id' => 'r1', 'titulo' => 'Banner Acme — aprovação',  'frequencia' => 'Diário'],
                ['id' => 'r2', 'titulo' => 'Cobrança Padaria Estrela', 'frequencia' => 'Uma vez'],
                ['id' => 'r3', 'titulo' => 'Reunião PCP — 8h30',       'frequencia' => 'Diário'],
                ['id' => 'r4', 'titulo' => 'Fechamento Caixa',         'frequencia' => 'Diário'],
            ],
            'recentes' => [
                ['id' => 'c1', 'titulo' => 'Padaria Estrela — Renato',     'unread' => 1, 'origem' => 'CRM'],
                ['id' => 'c2', 'titulo' => 'Adesivos Recortados — TechPro', 'unread' => 0, 'origem' => 'OS'],
                ['id' => 'c3', 'titulo' => 'Comercial',                     'unread' => 0, 'origem' => null],
                ['id' => 'c4', 'titulo' => 'Clínica Vida — Marcos',         'unread' => 0, 'origem' => 'CRM', 'ativa' => true],
            ],
        ];

        // Conversa em foco (mock rico — protótipo de referência Cowork)
        $conversaFoco = [
            'id'         => 'c2',
            'titulo'     => 'Adesivos Recortados — TechPro',
            'tipo'       => 'os',
            'online'     => true,
            'avatar'     => ['iniciais' => 'TP', 'gradId' => 7],
            'cliente'    => [
                'nome'          => 'TechPro Soluções',
                'telefone'      => '+55 11 98712-3344',
                'ultimoContato' => 'hoje 11:48 — perguntou se pode retirar 9h amanhã',
            ],
            'os'         => [
                'numero'   => '#OS-2814',
                'cliente'  => 'TechPro Soluções',
                'estagio'  => 'Em produção',
                'prazo'    => '30/04 às 16h',
            ],
            'financeiro' => [
                'saldo'    => 'R$ 4.820,00 a receber',
                'boletos'  => '2 boletos · R$ 4.820,00',
            ],
            'historico' => [
                ['quando' => '14:32',       'quem' => 'Mateus PCP',  'oque' => 'liberou para impressão'],
                ['quando' => '13:55',       'quem' => 'Joana Lima',  'oque' => 'subiu versão v3'],
                ['quando' => '10:02',       'quem' => 'Mateus PCP',  'oque' => 'alocou na Roland 540'],
                ['quando' => 'ontem 17:30', 'quem' => 'Camila (cli)','oque' => 'pediu logo +6%'],
            ],
            'anexos' => [
                ['nome' => 'arte-final-v3.pdf', 'tamanho' => '2.4 MB'],
                ['nome' => 'briefing.pdf',      'tamanho' => '180 KB'],
            ],
            'mensagens' => [
                ['id' => 1, 'autor' => 'them', 'whoAvatar' => ['iniciais' => 'CT', 'gradId' => 12], 'whoNome' => 'Camila — TechPro', 'dia' => 'Hoje', 'texto' => 'Bom dia! Conseguem me passar a previsão de entrega?', 'hora' => '09:42'],
                ['id' => 2, 'autor' => 'me',   'dia' => 'Hoje', 'texto' => 'Bom dia, Camila! Estamos imprimindo agora — entrega 30/04 às 16h conforme combinado.', 'hora' => '09:48', 'lida' => true],
                ['id' => 3, 'autor' => 'them', 'whoAvatar' => ['iniciais' => 'CT', 'gradId' => 12], 'whoNome' => 'Camila — TechPro', 'dia' => 'Hoje', 'texto' => 'Perfeito! Posso retirar 9h amanhã em vez de 16h hoje?', 'hora' => '11:48'],
                ['id' => 4, 'autor' => 'me',   'dia' => 'Hoje', 'texto' => 'Vou confirmar com produção e te aviso em 5 min.', 'hora' => '11:50', 'lida' => true],
            ],
        ];

        $userNome = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->username ?? 'Usuário');

        return Inertia::render('Copiloto/Cockpit', [
            'businessNome'  => session('business.name', 'Oimpresso Matriz'),
            'businesses'    => $businesses,
            'usuarioNome'        => $userNome,
            'usuarioNomeCurto'   => $user->first_name ?? 'Usuário',
            'usuarioEmail'  => $user->email ?? '',
            'usuarioCargo'  => $isSuper ? 'Administrador' : 'Usuário',
            'usuarioIniciais'    => $this->iniciais($userNome),
            'conversas'     => $mockConversas,
            'conversaFoco'  => $conversaFoco,
            'conversaAtivaRealId' => $conversaAtiva?->id,
        ]);
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
