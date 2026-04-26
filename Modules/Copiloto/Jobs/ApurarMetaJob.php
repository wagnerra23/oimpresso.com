<?php

namespace Modules\Copiloto\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Copiloto\Entities\Meta;
use Modules\Copiloto\Services\ApuracaoService;

/**
 * ApurarMetaJob — apura o realizado de uma meta na data de referência.
 *
 * Resolvido via container: busca drivers tagados com 'copiloto.drivers' em ApuracaoService.
 * Ver adr/tech/0001-drivers-apuracao-plugaveis.md.
 */
class ApurarMetaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly Meta $meta,
        public readonly Carbon $dataRef,
    ) {
    }

    public function handle(ApuracaoService $service): void
    {
        $service->apurar($this->meta, $this->dataRef);
    }

    public function tags(): array
    {
        return ['copiloto', 'apuracao', "meta:{$this->meta->id}"];
    }
}
