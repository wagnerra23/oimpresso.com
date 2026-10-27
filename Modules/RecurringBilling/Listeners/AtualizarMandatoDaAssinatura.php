<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Listeners;

use Modules\PaymentGateway\Events\RecorrenciaPixAtualizada;
use Modules\RecurringBilling\Models\Subscription;

/**
 * O banco mudou o status da recorrência do Pix Automático → o mandato da assinatura acompanha
 * (`metadata.pix_automatico.status`). É esse status que o `CobradorPixAutomatico` exige
 * (`APROVADA`) para cobrar a fatura. US-RB-020/021 · decisão [W] 2026-10-26.
 *
 * Tier 0 (ADR 0093): só a assinatura do business do evento com o mesmo `id_rec`.
 */
final class AtualizarMandatoDaAssinatura
{
    public function handle(RecorrenciaPixAtualizada $event): void
    {
        // SUPERADMIN: roda no processamento do webhook, sem sessão; business vem do evento.
        $assinaturas = Subscription::withoutGlobalScopes()
            ->where('business_id', $event->businessId)
            ->where('metadata->pix_automatico->id_rec', $event->idRec)
            ->get();

        foreach ($assinaturas as $sub) {
            $metadata = (array) ($sub->metadata ?? []);
            $mandato = (array) ($metadata['pix_automatico'] ?? []);
            if (($mandato['status'] ?? null) === $event->status) {
                continue;
            }
            $mandato['status'] = $event->status;
            $mandato['atualizado_em'] = now()->toIso8601String();
            $metadata['pix_automatico'] = $mandato;
            $sub->forceFill(['metadata' => $metadata])->save();
        }
    }
}
