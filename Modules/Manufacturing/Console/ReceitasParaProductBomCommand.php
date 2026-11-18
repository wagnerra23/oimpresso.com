<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Services\ReceitasParaProductBom;

/**
 * ADR 0448, etapa 2: copia as receitas da Fabricação de UMA empresa para o `product_bom`.
 *
 * Sem `--aplicar`, só simula: mostra o antes e o depois de cada linha, o que fica de fora e a
 * conferência do consumo, e não grava nada. É essa saída que o [W] vê antes de rodar em produção.
 * Com `--aplicar`, grava numa transação, e só se a conferência bater em todas as receitas.
 * Rodar de novo não duplica: variação que já tem composição no `product_bom` fica de fora.
 */
final class ReceitasParaProductBomCommand extends Command
{
    protected $signature = 'manufacturing:receitas-para-product-bom
        {business_id : Empresa cujas receitas serão copiadas}
        {--aplicar : Grava a cópia. Sem esta opção, só simula}';

    protected $description = 'Copia as receitas da Fabricação para o product_bom (ADR 0448, etapa 2). Simula por padrão.';

    public function handle(ReceitasParaProductBom $copia): int
    {
        $businessId = (int) $this->argument('business_id');
        if ($businessId <= 0 || ! DB::table('business')->where('id', $businessId)->exists()) {
            $this->error("Empresa {$businessId} não existe.");

            return self::FAILURE;
        }

        $plano = $copia->planejar($businessId);
        $this->info("Empresa {$businessId} · ".($this->option('aplicar') ? 'APLICAR' : 'SIMULAÇÃO, nada é gravado'));

        $this->line('');
        $this->line('Receitas (uma por variação, a que o sistema usa hoje):');
        $this->table(['receita', 'variação', 'produto', 'por_quantidade', 'situação'], array_map(fn (array $r) => [
            $r['receita_id'], $r['variation_id'], $r['produto'], $r['por_quantidade'],
            $r['ja_copiada'] ? 'já tem composição no product_bom: fica de fora' : 'será copiada',
        ], $plano['receitas']));

        $this->line('Linhas que entram no product_bom (antes: linha da receita → depois: linha nova):');
        $this->table(['receita', 'insumo', 'variação do insumo', 'qty_required', 'por_quantidade', 'sub-unidade', 'grupo'], array_map(fn (array $l) => [
            $l['receita_id'], $l['insumo'], $l['component_variation_id'], $l['qty_required'], $l['por_quantidade'],
            $l['sub_unit_id'] ?? '—', $l['ingredient_group_id'] ?? '—',
        ], $plano['linhas']));

        if ($plano['ignoradas'] !== []) {
            $this->line('Fica de fora:');
            $this->table(['receita', 'variação', 'motivo'], array_map(fn (array $i) => [
                $i['receita_id'], $i['insumo_variation_id'] ?? $i['variation_id'], $i['motivo'],
            ], $plano['ignoradas']));
        }

        $copiadas = count(array_filter($plano['receitas'], fn (array $r) => ! $r['ja_copiada']));
        if ($plano['divergencias'] !== []) {
            $this->error('Conferência: '.count($plano['divergencias'])." de {$copiadas} receita(s) dão consumo diferente do de hoje.");
            foreach ($plano['divergencias'] as $d) {
                $this->line("  receita {$d['receita_id']}: hoje ".json_encode($d['hoje']).' · cópia '.json_encode($d['copia']));
            }

            return self::FAILURE;
        }
        $this->info("Conferência: as {$copiadas} receita(s) copiadas dão o mesmo consumo por unidade que o sistema calcula hoje.");

        if (! $this->option('aplicar')) {
            $this->line(count($plano['linhas']).' linha(s) seriam gravadas. Para gravar, rode de novo com --aplicar.');

            return self::SUCCESS;
        }

        $gravadas = $copia->aplicar($businessId);
        $this->info("{$gravadas} linha(s) gravadas no product_bom.");

        return self::SUCCESS;
    }
}
