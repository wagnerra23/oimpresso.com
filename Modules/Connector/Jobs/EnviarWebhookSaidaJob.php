<?php

declare(strict_types=1);

namespace Modules\Connector\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Modules\Connector\Entities\WebhookEntrega;
use RuntimeException;
use Throwable;

/**
 * Envia UMA entrega de webhook de saída (US-CONN-021). `business_id` no construtor (fila não tem
 * sessão) e em toda consulta.
 *
 * Assinatura: `X-Oimpresso-Signature: t=<unix>,v1=<hex>`, onde v1 = HMAC-SHA256(segredo,
 * "<t>.<corpo>"). O destino recalcula e compara; o `t` deixa recusar reenvio antigo.
 * Até 5 tentativas (1, 5, 15 e 60 min); depois disso a entrega fica `falhou`.
 */
class EnviarWebhookSaidaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public int $businessId, public int $entregaId)
    {
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public static function assinatura(string $segredo, int $timestamp, string $corpo): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$corpo, $segredo);
    }

    public function handle(): void
    {
        $entrega = $this->entrega();
        if ($entrega === null || $entrega->status === WebhookEntrega::ENTREGUE) {
            return;
        }
        // SUPERADMIN: fila/CLI sem sessão, o escopo global não filtra — business_id vai explícito no where.
        $destino = $entrega->destino()->withoutGlobalScopes()->where('business_id', $this->businessId)->first();
        if ($destino === null || ! $destino->ativo) {
            $entrega->update(['status' => WebhookEntrega::FALHOU, 'ultimo_erro' => 'destino removido ou desativado']);

            return;
        }

        $corpo = (string) json_encode($entrega->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $t = time();
        $inicio = microtime(true);
        $entrega->increment('tentativas');

        try {
            $resposta = Http::withHeaders([
                'X-Oimpresso-Event' => $entrega->evento,
                'X-Oimpresso-Delivery' => $entrega->entrega_uuid,
                'X-Oimpresso-Signature' => self::assinatura((string) $destino->segredo, $t, $corpo),
                'User-Agent' => 'oimpresso-webhooks/1',
            ])->withBody($corpo, 'application/json')->timeout(10)->post($destino->url);
        } catch (Throwable $e) {
            $this->registrarFalha($entrega, null, $inicio, 'erro de conexão: '.class_basename($e));
            throw $e;
        }

        if ($resposta->successful()) {
            $entrega->update([
                'status' => WebhookEntrega::ENTREGUE,
                'http_status' => $resposta->status(),
                'duracao_ms' => (int) round((microtime(true) - $inicio) * 1000),
                'entregue_em' => now(),
                'ultimo_erro' => null,
            ]);

            return;
        }

        // O corpo da resposta do destino não é guardado: pode trazer dado de terceiro.
        $this->registrarFalha($entrega, $resposta->status(), $inicio, 'HTTP '.$resposta->status());
        throw new RuntimeException('webhook de saída recusado pelo destino: HTTP '.$resposta->status());
    }

    public function failed(?Throwable $e): void
    {
        $this->entrega()?->update(['status' => WebhookEntrega::FALHOU]);
    }

    private function entrega(): ?WebhookEntrega
    {
        // SUPERADMIN: fila/CLI sem sessão, o escopo global não filtra — business_id vai explícito no where.
        return WebhookEntrega::withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('id', $this->entregaId)
            ->first();
    }

    private function registrarFalha(WebhookEntrega $entrega, ?int $http, float $inicio, string $erro): void
    {
        $entrega->update([
            'http_status' => $http,
            'duracao_ms' => (int) round((microtime(true) - $inicio) * 1000),
            'ultimo_erro' => mb_substr($erro, 0, 500),
        ]);
    }
}
