<?php

declare(strict_types=1);

namespace Modules\Ponto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Ponto\Entities\PushDispositivo;
use Modules\Ponto\Services\Push\FcmClient;

/**
 * Envia o lembrete de bater ponto aos aparelhos ativos de UM usuário (ADR 0422 §5).
 *
 * Tier 0 ([ADR 0093]): `$businessId` vem no constructor e filtra explicitamente — fora de
 * sessão o global scope não protege. Com a fila `sync` do Hostinger roda no mesmo tick do
 * comando, sem worker.
 *
 * Conteúdo genérico, sem dado sensível (ADR 0422 §7). O push nunca bate ponto.
 */
class EnviarLembretePontoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ROTULOS = [
        'ENTRADA' => 'Entrada',
        'ALMOCO_INICIO' => 'Saída para o almoço',
        'ALMOCO_FIM' => 'Volta do almoço',
        'SAIDA' => 'Saída',
    ];

    public int $tries = 1;

    public function __construct(
        public int $businessId,
        public int $userId,
        public string $tipo,
        public string $hora,
    ) {
    }

    public function handle(FcmClient $fcm): void
    {
        // SUPERADMIN: Job sem sessão — o scope não filtraria nada; o where explícito é a defesa.
        $aparelhos = PushDispositivo::withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('user_id', $this->userId)
            ->where('ativo', true)
            ->get();

        $rotulo = self::ROTULOS[$this->tipo] ?? 'Marcação';

        foreach ($aparelhos as $aparelho) {
            $resultado = $fcm->enviar(
                $aparelho->token,
                'Lembrete de ponto',
                "{$rotulo} às {$this->hora}. Toque para bater o ponto.",
                ['url' => '/ponto/mobile'],
            );

            if ($resultado === FcmClient::TOKEN_INVALIDO) {
                $aparelho->update(['ativo' => false]);
            } elseif ($resultado === FcmClient::OK) {
                $aparelho->update(['ultimo_uso_at' => now()]);
            }

            Log::info('ponto.push.lembrete', [
                'business_id' => $this->businessId,
                'dispositivo_id' => $aparelho->id,
                'tipo' => $this->tipo,
                'resultado' => $resultado,
            ]);
        }
    }
}
