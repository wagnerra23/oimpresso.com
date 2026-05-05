<?php

namespace Modules\Copiloto\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Drivers\Channels\EvolutionApiChannel;
use Modules\Copiloto\Services\Channels\ChannelIdentityResolver;

/**
 * Webhook receiver da Evolution API (Fase 0 — ADR 0075).
 *
 * Roda em CT 100 (FrankenPHP, mesmo runtime do mcp.oimpresso.com — ADR 0058
 * + ADR 0062). NÃO em Hostinger.
 *
 * Fluxo:
 *  1. Valida assinatura (header `X-Evolution-Secret` shared).
 *  2. Parse payload em IncomingMessage (driver).
 *  3. Resolve identity (multi-tenant scope).
 *  4. Sem identity → log e ignora.
 *  5. Sem opt-in → responde mensagem de consentimento.
 *  6. "SAIR" → marca revoked.
 *  7. "ACEITO"/"OK"/"SIM" e sem opt-in → marca opt-in + saudação.
 *  8. Caso contrário → enfileira pra ChatService (TODO US-COPI-089).
 */
class EvolutionWebhookController extends Controller
{
    public function __construct(
        private readonly ChannelIdentityResolver $resolver,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (! config('copiloto.channels.whatsapp.enabled', false)) {
            return response()->json(['status' => 'disabled'], 503);
        }

        $driver = $this->makeDriver();

        if (! $driver->verifySignature($request->headers->all(), $request->getContent())) {
            Log::warning('[copiloto.channel.evolution] signature invalid');
            return response()->json(['status' => 'unauthorized'], 401);
        }

        $payload = $request->all();
        $incoming = $driver->parseWebhook($payload);

        if ($incoming === null) {
            return response()->json(['status' => 'ignored']);
        }

        $identity = $this->resolver->resolve($incoming);

        if ($identity === null) {
            Log::info('[copiloto.channel.evolution] unknown wire_id', [
                'wire_id' => $incoming->wireId,
            ]);
            return response()->json(['status' => 'unknown_identity']);
        }

        // Allow-list de tenants no canário (Fase 0 = só ROTA LIVRE biz=4).
        $allowList = (array) config('copiloto.channels.whatsapp.tenant_allowlist', []);
        if ($allowList && ! in_array($identity['business_id'], $allowList, true)) {
            return response()->json(['status' => 'tenant_not_allowlisted']);
        }

        // TODO(US-COPI-089): enfileirar ProcessIncomingChannelMessageJob (Horizon CT 100)
        //  - normalize 'SAIR' → resolver->revoke + reply
        //  - normalize 'ACEITO|OK|SIM' (quando !opted_in) → resolver->markOptIn + saudação
        //  - se opted_in → ChatService::sendIncomingFromChannel($identity, $incoming)
        //
        // Por ora, scaffold só faz log + ack. Lógica completa entra quando US-COPI-088
        // (auto-capture) estiver pronta — turn-level captura é channel-agnostic.
        Log::info('[copiloto.channel.evolution] received (scaffold)', [
            'business_id' => $identity['business_id'],
            'user_id'     => $identity['user_id'],
            'opted_in'    => $identity['opted_in'],
            'wire_id'     => $incoming->wireId,
            'msg_id'      => $incoming->providerMessageId,
        ]);

        return response()->json(['status' => 'queued']);
    }

    private function makeDriver(): EvolutionApiChannel
    {
        $cfg = config('copiloto.channels.whatsapp.evolution', []);

        return new EvolutionApiChannel(
            baseUrl: $cfg['base_url'] ?? '',
            apiKey: $cfg['api_key'] ?? '',
            instance: $cfg['instance'] ?? '',
            webhookSecret: $cfg['webhook_secret'] ?? '',
        );
    }
}
