<?php

declare(strict_types=1);

namespace Modules\Arquivos\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use LogicException;
use Modules\Arquivos\Services\ArquivosRetentionService;

/**
 * SimularRetencaoJob — onda 3 · PR-8 (thread 04 do playbook Arquivos). D4: a UI nunca apaga.
 *
 * Roda `ArquivosRetentionService::run()` SEMPRE em dry-run e devolve, por arquivo, o que a
 * política removeria e POR QUÊ. Não escreve em `arquivos` nem em `arquivos_audit_log`: o
 * único efeito é o relatório no cache (lido pela tela) e a linha de log do `report()`.
 *
 * Defesa em profundidade: o controller força `dryRun = true` e recusa `purge`; este job
 * RECUSA de novo (LogicException) se chegar `dryRun = false` ou `purge = true` — um job de
 * simulação que apagasse seria o pior defeito possível, então a recusa não depende de quem
 * o despacha.
 *
 * Sem `ShouldQueue` de propósito: o controller o despacha com `dispatchAfterResponse()`, que
 * roda no mesmo processo DEPOIS da resposta. Fila `database` aqui ficaria parada — `default`
 * só é drenada pelo worker de backlog, atrás de flag (medido no `app/Console/Kernel.php`
 * em 2026-10-01). Fila dedicada exige worker novo no Kernel: fora do escopo desta thread.
 *
 * Multi-tenant Tier 0 (ADR 0093): `$businessId` vem no construtor (da sessão, no controller);
 * nada aqui lê sessão. O Service filtra por `business_id` em toda query.
 *
 * LGPD Art. 37: o relatório leva id, data e motivo — nunca nome, caminho de disco nem hash.
 */
class SimularRetencaoJob
{
    use Dispatchable, Queueable;

    /** Relatório disponível pra tela por 24h. */
    public const TTL = 86400;

    public function __construct(
        public readonly int $businessId,
        public readonly int $retentionDays,
        public readonly bool $dryRun,
        public readonly bool $purge,
        public readonly ?int $userId = null,
        public readonly ?string $batchTag = null,
    ) {
    }

    public static function chaveCache(int $businessId): string
    {
        return "arquivos:retencao:simulacao:{$businessId}";
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(ArquivosRetentionService $service): array
    {
        if (! $this->dryRun || $this->purge) {
            throw new LogicException('Simulação de retenção só roda em dry-run, sem purge (D4).');
        }

        $resultado = $service->run($this->businessId, $this->retentionDays, true, false);
        $limite = now()->subDays($this->retentionDays);

        $itens = $service->scanExpired($this->businessId, $this->retentionDays)
            ->map(fn ($a) => [
                'id'         => (int) $a->id,
                'criado_em'  => $a->created_at?->toDateString(),
                'motivo'     => 'criado em ' . $a->created_at?->format('d/m/Y')
                    . ', antes do limite de ' . $this->retentionDays . ' dias (' . $limite->format('d/m/Y') . ')',
            ])
            ->values()
            ->all();

        $relatorio = $service->report($this->businessId, $this->retentionDays, $resultado, [
            'batch_tag' => $this->batchTag,
            'motivo'    => 'simulação pela tela (dry-run)',
            'user_id'   => $this->userId,
        ]);
        $relatorio['itens'] = $itens;

        Cache::put(self::chaveCache($this->businessId), $relatorio, self::TTL);

        return $relatorio;
    }
}
