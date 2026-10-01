<?php

declare(strict_types=1);

namespace App\Console\Commands\Unidades;

use App\Services\Unidades\AuditoriaMultiplicadorUnidade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * SÓ LEITURA. Lista unidades cuja conversão de estoque (`base_unit_multiplier` / `base_unit_id`)
 * foi corrompida pelo modal "Editar unidade" antes do PR #8394, com o valor anterior recuperado do
 * `activity_log` quando existe. Não grava nada — a correção é `units:corrigir-multiplicador`,
 * separada e com dry-run obrigatório, e aplicá-la em produção é decisão [W] (REGRA MESTRE de
 * estoque, memory/proibicoes.md).
 *
 *   php artisan units:auditar-multiplicador --business=1
 *   php artisan units:auditar-multiplicador            (todos os businesses, um bloco por business)
 *   php artisan units:auditar-multiplicador --json
 *
 * Saída sem PII: business_id, id e símbolo da unidade, números e contagens.
 */
class AuditarMultiplicadorCommand extends Command
{
    protected $signature = 'units:auditar-multiplicador
        {--business= : Restringe a um business_id (sem ele, varre todos, um bloco por business)}
        {--json : Saída JSON em vez de tabela}';

    protected $description = 'Mede (sem gravar) unidades com conversão de estoque corrompida pelo modal Editar unidade.';

    public function handle(AuditoriaMultiplicadorUnidade $auditoria): int
    {
        $opt = $this->option('business');
        if ($opt !== null && (int) $opt <= 0) {
            $this->error('--business inválido.');

            return self::FAILURE;
        }

        $businesses = $opt !== null
            ? [(int) $opt]
            : DB::table('units')->whereNotNull('base_unit_id')->distinct()->orderBy('business_id')->pluck('business_id')->map(fn ($b) => (int) $b)->all();

        $relatorio = [];
        foreach ($businesses as $biz) {
            $linhas = $auditoria->auditar($biz);
            if ($linhas !== [] || $opt !== null) {
                $relatorio[$biz] = $linhas;
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($relatorio, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Auditoria de base_unit_multiplier — SÓ LEITURA. Log de Unit existe desde '.AuditoriaMultiplicadorUnidade::INICIO_DO_LOG.'.');
        foreach ($relatorio as $biz => $linhas) {
            $this->newLine();
            $this->line("business_id={$biz} · ".count($linhas).' unidade(s) com achado');
            if ($linhas === []) {
                continue;
            }
            $this->table(
                ['unit', 'unidade', 'classe', 'antes (atual)', 'depois (proposto)', 'base', 'passos', 'desde', 'prova log', 'prova mecanismo', 'na criação', 'vendas', 'compras', 'produtos', 'sinais'],
                array_map(fn (array $l) => [
                    $l['unit_id'],
                    $l['unidade'],
                    $l['classe'],
                    $this->fmt($l['mult_atual'] ?? null).' '.($l['base_atual'] ?? '—'),
                    array_key_exists('mult_proposto', $l) && $l['mult_proposto'] !== null ? $this->fmt($l['mult_proposto']).' '.($l['base_proposta'] ?? '—') : '— (decisão [W])',
                    $l['base_atual'] ?? '—',
                    $l['passos_corrompidos'] ?? '—',
                    $l['desde'] ?? '—',
                    $this->sn($l['prova_valor_log'] ?? null),
                    $this->sn($l['prova_mecanismo'] ?? null),
                    $this->fmt($l['valor_na_criacao'] ?? null),
                    $l['linhas_venda'],
                    $l['linhas_compra'],
                    $l['produtos_com_subunidade'],
                    implode(',', $l['sinais'] ?? []),
                ], $linhas),
            );
        }

        $this->newLine();
        $this->line('Classes: RECUPERAVEL (assinatura exata no log) · BASE_REMOVIDA (pode ter sido desmarcada de propósito) · CORROMPIDA_DEPOIS_EDITADA · SUSPEITA_SEM_PROVA (sem valor recuperável).');
        $this->line('vendas/compras = linhas lançadas nesta sub-unidade desde a 1ª corrupção (ou no total, sem data). A correção NÃO recalcula essas linhas.');

        return self::SUCCESS;
    }

    private function fmt(?float $v): string
    {
        return $v === null ? '—' : rtrim(rtrim(number_format($v, 4, ',', '.'), '0'), ',');
    }

    private function sn(?bool $v): string
    {
        return $v === null ? 'n/a' : ($v ? 'sim' : 'NÃO');
    }
}
