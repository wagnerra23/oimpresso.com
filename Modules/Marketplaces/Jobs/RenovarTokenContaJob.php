<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Services\ContasVendedor;
use Throwable;

/**
 * Renova o token de UMA conta de vendedor (US-MKT-003). `business_id` no construtor e na consulta
 * (fila não tem sessão). Recusa do refresh marca a conta `token_expired` (feito pelo serviço) e
 * não tenta de novo: só a empresa reconectando resolve.
 */
class RenovarTokenContaJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $uniqueFor = 900;

    public function __construct(public int $businessId, public int $contaId, public int $margemMinutos = 60)
    {
    }

    public function uniqueId(): string
    {
        return $this->businessId.':'.$this->contaId;
    }

    public function handle(ContasVendedor $contas): void
    {
        // SUPERADMIN: job em fila sem sessão; a empresa vem do construtor e entra explícita na consulta.
        $conta = ContaVendedor::withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('id', $this->contaId)
            ->where('status', ContaVendedor::STATUS_ATIVA)
            ->first();
        if ($conta === null) {
            return;
        }

        try {
            $contas->tokenValido($conta, $this->margemMinutos);
        } catch (Throwable $e) {
            Log::warning('marketplaces: conta precisa ser conectada de novo', [
                'business_id' => $this->businessId,
                'conta_id' => $this->contaId,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
