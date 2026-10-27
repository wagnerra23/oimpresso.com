<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Services\Manifestacao\DistribuicaoDfeService;

/**
 * US-NFE-051 · Job que dispara DistribuicaoDfeService::puxarLote por business.
 *
 * Multi-tenant: $businessId no constructor — `session()` não funciona em fila
 * (skill multi-tenant-patterns).
 *
 * Retry exponencial: 3 tentativas com backoff 30s, 60s, 120s.
 */
class BuscarDfesRecebidosJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly int $businessId,
    ) {}

    public function handle(DistribuicaoDfeService $service): void
    {
        $resultado = $service->puxarLote($this->businessId);

        Log::info('BuscarDfesRecebidosJob: completou', [
            'business_id' => $this->businessId,
            'resultado'   => $resultado,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('BuscarDfesRecebidosJob: falhou definitivamente', [
            'business_id' => $this->businessId,
            'erro'        => $exception->getMessage(),
        ]);

        // R-NFE-042: a falha vira alerta da empresa (mcp_alertas_eventos), um por dia, que o
        // cockpit Fiscal mostra em vermelho. Antes só o log e o failed_jobs sabiam — 69 falhas
        // seguidas sem ninguém ver (#9833). Falha ao gravar o alerta não derruba o failed().
        try {
            $agora = now();
            $chave = sprintf('%s:%d:%s', self::TIPO_ALERTA, $this->businessId, $agora->format('Y-m-d'));
            $dados = [
                'severidade' => 'high',
                'titulo'     => 'A busca das notas recebidas na SEFAZ falhou',
                'descricao'  => mb_substr($exception->getMessage(), 0, 1000),
                'metadata'   => json_encode(['origem' => self::class, 'tentativas' => $this->tries], JSON_UNESCAPED_UNICODE),
                'updated_at' => $agora,
            ];
            $existe = DB::table('mcp_alertas_eventos')->where('chave_idempotencia', $chave)->exists();
            $existe
                ? DB::table('mcp_alertas_eventos')->where('chave_idempotencia', $chave)->update($dados)
                : DB::table('mcp_alertas_eventos')->insert($dados + [
                    'business_id' => $this->businessId, 'tipo' => self::TIPO_ALERTA,
                    'chave_idempotencia' => $chave, 'status' => 'aberto',
                    'criado_em' => $agora, 'created_at' => $agora,
                ]);
        } catch (\Throwable $t) {
            Log::error('BuscarDfesRecebidosJob: alerta não gravado', ['business_id' => $this->businessId, 'erro' => $t->getMessage()]);
        }
    }

    /** Tipo do alerta da falha da busca de DF-e (lido pelo cockpit Fiscal). */
    public const TIPO_ALERTA = 'dfe_busca_falhou';

    public function tags(): array
    {
        return ['nfebrasil', 'dist-dfe', "biz:{$this->businessId}"];
    }
}
