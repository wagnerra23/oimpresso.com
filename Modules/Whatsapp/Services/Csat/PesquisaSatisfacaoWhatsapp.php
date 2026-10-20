<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Csat;

use App\Contracts\Atendimento\EnviaPesquisaSatisfacao;
use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\CsatResponse;

/**
 * Implementação do contrato do núcleo `EnviaPesquisaSatisfacao` (US-SELL-071 · UC-FLX-25):
 * reaproveita o `CsatDispatcher` na conversa mais recente do cliente nesta empresa. A nota volta
 * pelo `CsatResponseParser` (webhook) e é lida aqui por `notaRespondida`.
 *
 * Multi-tenant Tier 0 (ADR 0093): roda em fila sem session; business_id explícito em toda query.
 */
class PesquisaSatisfacaoWhatsapp implements EnviaPesquisaSatisfacao
{
    public function __construct(private readonly CsatDispatcher $dispatcher) {}

    public function enviar(int $businessId, int $contactId, int $transactionId, int $userId): array
    {
        $conversa = Conversation::query()->withoutGlobalScope(ScopeByBusiness::class) // job sem session
            ->where('business_id', $businessId)->where('contact_id', $contactId)
            ->orderByDesc('last_message_at')->first();
        if (! $conversa) {
            return ['enviado' => false, 'motivo' => 'sem_conversa', 'referencia' => null];
        }

        try {
            $csat = $this->dispatcher->dispatchOnResolve($conversa, $userId);
        } catch (\Throwable $e) {
            Log::warning('[whatsapp.pesquisa_entrega] falha no envio', [
                'business_id' => $businessId, 'transaction_id' => $transactionId, 'error' => class_basename($e),
            ]);

            return ['enviado' => false, 'motivo' => 'falha_envio', 'referencia' => null];
        }

        return $csat
            ? ['enviado' => true, 'motivo' => null, 'referencia' => (int) $csat->id]
            : ['enviado' => false, 'motivo' => 'canal_nao_suportado', 'referencia' => null];
    }

    public function notaRespondida(int $businessId, int $referencia): ?int
    {
        $score = CsatResponse::query()->withoutGlobalScope(ScopeByBusiness::class) // job sem session
            ->where('business_id', $businessId)->where('id', $referencia)->value('score');

        return $score === null ? null : (int) $score;
    }
}
