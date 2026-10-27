<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Api;

use App\Scopes\ScopeByBusiness;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Services\Canais\NormalizadorMetaGraph;
use Modules\Whatsapp\Services\Canais\RecebimentoNoCanal;
use Symfony\Component\HttpFoundation\Response;

/**
 * Webhook do Meta Graph para Instagram Direct e Messenger, endereçado por canal
 * (US-WA-329 fatia 2 · ADR 0135 Fase 1).
 *
 * Cada canal tem a sua URL (`/api/atendimento/channels/meta-graph/{channel_uuid}`),
 * então o tenant sai do Channel e nunca do payload. No `config_json` do canal:
 *  - `verify_token` — o "Verify Token" cadastrado no painel da Meta (GET de inscrição);
 *  - `app_secret` (opcional) — senão vale `META_APP_SECRET`, o mesmo app do WhatsApp Cloud;
 *  - `account_id` (opcional) — Page ID ou IG ID; se presente, entrada de outra conta é ignorada.
 *
 * Desligado por `whatsapp.omnicanal.meta_graph_enabled`: com a flag desligada responde
 * 404, igual a rota inexistente, e não grava nada.
 */
class MetaGraphWebhookController extends Controller
{
    private const OBJETO_DO_TIPO = [
        Channel::TYPE_INSTAGRAM => 'instagram',
        Channel::TYPE_MESSENGER => 'page',
    ];

    public function __construct(private readonly RecebimentoNoCanal $recebimento)
    {
    }

    public function verify(Request $request, string $channel_uuid): Response
    {
        $canal = $this->canal($channel_uuid);
        if ($canal === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $esperado = (string) ($canal->config_json['verify_token'] ?? '');
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $modo = (string) $request->query('hub_mode', $request->query('hub.mode', ''));

        if ($modo === 'subscribe' && $esperado !== '' && hash_equals($esperado, $token)) {
            return response((string) $request->query('hub_challenge', $request->query('hub.challenge', '')), 200)
                ->header('Content-Type', 'text/plain');
        }

        return response()->json(['error' => 'verify_token_mismatch'], 403);
    }

    public function handle(Request $request, string $channel_uuid): Response
    {
        $canal = $this->canal($channel_uuid);
        if ($canal === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $segredo = (string) ($canal->config_json['app_secret'] ?? config('whatsapp.meta.app_secret', ''));
        $assinatura = (string) $request->header('X-Hub-Signature-256', '');
        $calculada = 'sha256='.hash_hmac('sha256', $request->getContent(), $segredo);
        if ($segredo === '' || ! hash_equals($calculada, $assinatura)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $conta = (string) ($canal->config_json['account_id'] ?? '');
        $contagem = [];
        foreach (NormalizadorMetaGraph::normalizar((array) $request->json()->all()) as $item) {
            if ($item['objeto'] !== self::OBJETO_DO_TIPO[$canal->type]) {
                continue;
            }
            if ($conta !== '' && $item['conta'] !== $conta) {
                continue;
            }
            $r = $this->recebimento->receber($canal, $item['mensagem']);
            $contagem[$r] = ($contagem[$r] ?? 0) + 1;
        }

        Log::info('omnicanal.meta_graph.webhook', [
            'business_id' => $canal->business_id, 'channel_id' => $canal->id, 'resultado' => $contagem,
        ]);

        // A Meta reenvia em qualquer resposta que não seja 200; o que não entrou já foi contado acima.
        return response()->json(['ok' => true, 'resultado' => $contagem]);
    }

    private function canal(string $uuid): ?Channel
    {
        if (! config('whatsapp.omnicanal.meta_graph_enabled', false)) {
            return null;
        }

        // SUPERADMIN: webhook público pré-auth — o tenant é o do canal endereçado na URL.
        return Channel::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('channel_uuid', $uuid)
            ->whereIn('type', array_keys(self::OBJETO_DO_TIPO))
            ->where('status', 'active')
            ->first();
    }
}
