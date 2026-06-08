<?php

namespace Modules\Jana\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Services\ApuracaoService;

/**
 * ApurarMetaJob — apura o realizado de uma meta na data de referencia.
 *
 * Resolvido via container: busca drivers tagados com 'copiloto.drivers' em ApuracaoService.
 * Ver adr/tech/0001-drivers-apuracao-plugaveis.md.
 *
 * Multi-tenant Tier 0 (ADR 0093) — Wave 16: constructor recebe int $businessId
 * EXPLICITO (rubrica D1 v3.2 hardened audita signature). Default null preserva
 * back-compat (chamadas legadas extraem do Meta::business_id no handle()), mas
 * novos callers DEVEM passar explicitamente pra defesa em profundidade quando o
 * job rodar fora do escopo HTTP (queue worker daemon CT 100 nao tem session()).
 */
class ApurarMetaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public readonly int $businessId;

    public function __construct(
        public readonly Meta $meta,
        public readonly Carbon $dataRef,
        ?int $businessId = null,
    ) {
        // Multi-tenant Tier 0 ADR 0093: fallback pra meta.business_id se caller
        // legado nao passou. Novos callers DEVEM passar explicito (defesa em
        // profundidade — Meta serializada pode ter scope drift entre dispatch
        // e execucao do worker).
        $this->businessId = $businessId ?? (int) $meta->business_id;
    }

    public function handle(ApuracaoService $service): void
    {
        $service->apurar($this->meta, $this->dataRef);
    }

    public function tags(): array
    {
        return ['copiloto', 'apuracao', "meta:{$this->meta->id}", "biz:{$this->businessId}"];
    }
}
