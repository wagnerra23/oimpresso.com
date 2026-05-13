<?php

declare(strict_types=1);

namespace Modules\Jana\Jobs\Telemetry;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Jana\Services\Telemetry\LangfuseClient;

/**
 * LangfuseTraceJob — POST batch async pra Langfuse v3 self-host.
 *
 * Disparado por LangfuseClient::dispatch() em mode 'queue' (default).
 * Roda em queue 'default' por padrão; conexão configurável via config('langfuse.queue_connection').
 *
 * Fail-open: erros 5xx ou timeout reportados pelo client são apenas warnings
 * em log channel 'langfuse' (não relança exception — telemetria não pode quebrar).
 * Por isso `$tries=1` — re-tentar batch parcialmente entregue causa duplicação.
 */
class LangfuseTraceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var int max tries — 1, telemetria não retry (evita duplicação)
     */
    public $tries = 1;

    /**
     * @var int timeout segundos — folga sobre HTTP timeout (config('langfuse.timeout'))
     */
    public $timeout = 15;

    /**
     * @param array<int,array<string,mixed>> $events batch events ingestion
     */
    public function __construct(
        public array $events,
    ) {
        //
    }

    public function handle(LangfuseClient $client): void
    {
        $client->flushBatch($this->events);
    }
}
