<?php

declare(strict_types=1);

namespace Modules\Connector\Services\WebhookSaida;

use Illuminate\Support\Str;
use Modules\Connector\Entities\WebhookDestino;
use Modules\Connector\Entities\WebhookEntrega;
use Modules\Connector\Jobs\EnviarWebhookSaidaJob;

/**
 * Cria uma entrega por destino da empresa que assina o evento e põe o envio na fila
 * (US-CONN-021). Sem destino cadastrado, não faz nada.
 *
 * O payload é ENXUTO de propósito: ids e campos de controle, sem dado pessoal. Quem recebe
 * busca o detalhe pela API do Connector com o próprio token — assim nada de cliente viaja num
 * webhook que pode ter sido apontado errado.
 */
class DisparadorWebhookSaida
{
    public const VENDA_FINALIZADA = 'venda.finalizada';

    /** Eventos que esta versão emite. Destino só pode assinar estes. */
    public const EVENTOS = [self::VENDA_FINALIZADA];

    /**
     * @param  array<string, mixed>  $dados
     * @param  bool  $umaVezPorRecurso  se true, não repete o evento para o mesmo recurso no mesmo destino
     * @return int quantas entregas foram criadas
     */
    public function disparar(int $businessId, string $evento, ?int $recursoId, array $dados, bool $umaVezPorRecurso = false): int
    {
        // Contexto pode ser fila/CLI (sem sessão): o escopo global não filtra, então a empresa
        // vai explícita em toda consulta.
        $destinos = WebhookDestino::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('ativo', true)
            ->get()
            ->filter(fn (WebhookDestino $d) => $d->assina($evento));

        $criadas = 0;
        foreach ($destinos as $destino) {
            if ($umaVezPorRecurso && $recursoId !== null && WebhookEntrega::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('destino_id', $destino->id)
                ->where('evento', $evento)
                ->where('recurso_id', $recursoId)
                ->exists()) {
                continue;
            }

            $uuid = (string) Str::uuid();
            $entrega = WebhookEntrega::withoutGlobalScopes()->create([
                'business_id' => $businessId,
                'destino_id' => $destino->id,
                'entrega_uuid' => $uuid,
                'evento' => $evento,
                'recurso_id' => $recursoId,
                'payload' => [
                    'id' => $uuid,
                    'evento' => $evento,
                    'ocorrido_em' => now()->toIso8601String(),
                    'dados' => $dados,
                ],
                'status' => WebhookEntrega::PENDENTE,
            ]);

            EnviarWebhookSaidaJob::dispatch($businessId, (int) $entrega->id)->afterCommit();
            $criadas++;
        }

        return $criadas;
    }
}
