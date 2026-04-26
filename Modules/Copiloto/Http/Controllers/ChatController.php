<?php

namespace Modules\Copiloto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
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

        return Inertia::render('Copiloto/Chat', [
            'conversa'           => $conversa,
            'conversas'          => $conversas,
            'mensagens'          => $mensagens,
            'sugestoesPendentes' => $sugestoesPendentes,
        ]);
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
}
