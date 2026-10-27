<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Backup\ExportacaoEmpresa\ExportadorDaEmpresa;
use App\Models\ExportacaoEmpresa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Gera o zip de um pedido de exportação (US-BKP-001). Leva `businessId` no construtor:
 * fila não tem sessão (ADR 0093). Vai pra fila `backups`, a mesma do RunBackupJob, que
 * tem worker agendado (BackupJobTest UC-BKP-04) — em `default` ficaria parado.
 */
class ExportarDadosDaEmpresaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $businessId, public int $exportacaoId)
    {
        // Mesmo motivo do RunBackupJob: o trait declara `public $queue;` sem default.
        $this->onQueue('backups');
    }

    public function handle(ExportadorDaEmpresa $exportador): void
    {
        $pedido = ExportacaoEmpresa::daEmpresa($this->businessId)->findOrFail($this->exportacaoId);
        $disco = Storage::disk(config('backup.exportacao_empresa.disco'));
        $relativo = "exportacoes-empresa/{$this->businessId}/" . now()->format('Y-m-d-His') . "-{$pedido->id}.zip";
        $disco->makeDirectory(dirname($relativo));

        try {
            $manifesto = $exportador->exportar($this->businessId, $disco->path($relativo));
            $pedido->update([
                'status' => 'pronto',
                'arquivo' => $relativo,
                'bytes' => $disco->size($relativo),
                'manifesto' => $manifesto['tabelas'],
                'expira_em' => now()->addHours((int) config('backup.exportacao_empresa.horas_validade')),
            ]);
        } catch (\Throwable $e) {
            Log::error('backup.exportacao_empresa.falhou', ['business_id' => $this->businessId, 'exportacao_id' => $pedido->id, 'erro' => $e->getMessage()]);
            $pedido->update(['status' => 'falhou', 'erro' => mb_substr($e->getMessage(), 0, 191)]);
        }
    }
}
