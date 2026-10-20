<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Modules\Financeiro\Services\RetencaoNfseTituloService;
use Modules\NFSe\Models\NfseEmissao;

/**
 * US-FIN-072 · UC-FLX-30 — PRÉVIA, para as NFS-e JÁ autorizadas de um negócio, da baixa de RETENÇÃO
 * que seria lançada em cada venda/título (o bruto não muda). NÃO GRAVA NADA.
 *
 * Só existe `--dry-run`: aplicar nos títulos existentes é decisão [W] (REGRA MESTRE — valor),
 * e entra num PR próprio depois que o antes→depois daqui for visto.
 */
class IssRetidoAjusteCommand extends Command
{
    protected $signature = 'financeiro:iss-retido-ajuste
        {--business= : business_id (obrigatório — Tier 0)}
        {--dry-run : obrigatório — só lista, nunca grava}';

    protected $description = 'Prévia (dry-run) da baixa de retenção (ISS/PIS/COFINS/CSLL) das NFS-e já autorizadas';

    public function handle(RetencaoNfseTituloService $servico): int
    {
        $businessId = (int) $this->option('business');
        if ($businessId <= 0) {
            $this->error('--business=N é obrigatório.');

            return self::FAILURE;
        }
        if (! $this->option('dry-run')) {
            $this->error('Só existe --dry-run nesta versão: aplicar em título existente é decisão [W].');

            return self::FAILURE;
        }

        $this->info(sprintf('business %d · chave do ajuste: %s · DRY-RUN, nada é gravado', $businessId,
            $servico->ativo($businessId) ? 'LIGADA' : 'desligada'));

        // SUPERADMIN: comando sem sessão — business_id explícito do parâmetro (ADR 0093).
        $notas = NfseEmissao::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', 'emitida')
            ->whereNotNull('transaction_id')
            ->orderBy('id')
            ->get();

        $linhas = [];
        $contagem = [];
        foreach ($notas as $nota) {
            $s = $servico->simular($nota);
            $contagem[$s['acao']] = ($contagem[$s['acao']] ?? 0) + 1;
            if ($s['acao'] === 'sem_retencao') {
                continue;
            }
            $f = fn (int $c) => number_format($c / 100, 2, ',', '.');
            $linhas[] = [
                $nota->id, $s['titulo']?->id ?? '-', $s['titulo']?->numero ?? '-',
                $f($s['bruto']), $f($s['aberto']), $f($s['retido']), $f($s['baixa']),
                $f($s['aberto'] - $s['baixa']), $f($s['pendente']), $s['acao'],
            ];
        }

        $this->table(['nfse_id', 'titulo_id', 'numero', 'bruto', 'aberto antes', 'retido', 'baixa retenção', 'aberto depois', 'pendência', 'ação'], $linhas);
        foreach ($contagem as $acao => $n) {
            $this->line(sprintf('  %s: %d', $acao, $n));
        }
        $this->line(sprintf('%d nota(s) autorizada(s) com venda vinculada. Nenhum título foi alterado.', $notas->count()));

        return self::SUCCESS;
    }
}
