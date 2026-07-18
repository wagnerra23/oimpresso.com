<?php

declare(strict_types=1);

namespace Modules\KB\Console\Commands;

use Illuminate\Console\Command;
use Modules\KB\Services\KbAutoClassifierService;

/**
 * kb:classify — preenche `category_id`/`subcategory_id` dos nós sem categoria, aplicando as
 * regras `auto_match` seedadas nas subcategorias.
 *
 * SEGURANÇA (regra-mestre de dados · Tier 0):
 *   - DEFAULT é DRY-RUN: conta o que classificaria + o que fica sem casa, NÃO grava nada.
 *   - `--apply` é o único jeito de mutar o banco — e imprime o impacto ANTES.
 *   - `--business=N` OBRIGATÓRIO (session não vale em CLI → business explícito, ADR 0093).
 *   - Nunca cruza tenant: regra de biz A não casa nó de biz B (o serviço filtra pelo mesmo id).
 *
 * Uso:
 *   php artisan kb:classify --business=1                 # DRY-RUN (o que aconteceria)
 *   php artisan kb:classify --business=1 --detail        # + quebra por subcategoria/type
 *   php artisan kb:classify --business=1 --apply         # GRAVA (após [W] ver o dry-run)
 *
 * ⚠️ Rodar SEMPRE no CT 100 (oimpresso-staging/oimpresso-mcp), nunca local (ADR 0062).
 *
 * @see Modules/KB/Services/KbAutoClassifierService.php — a lógica de match (Tier 0 explícito)
 */
class KbClassifyCommand extends Command
{
    protected $signature = 'kb:classify
        {--business= : business_id alvo (obrigatório — session não resolve em CLI)}
        {--apply : GRAVA no banco (default é dry-run: só conta)}
        {--detail : quebra por subcategoria classificada e por type sem-casa}';

    protected $description = 'Classifica kb_nodes sem categoria via auto_match das subcategorias (dry-run por default).';

    public function handle(KbAutoClassifierService $classifier): int
    {
        $businessOpt = $this->option('business');
        $apply       = (bool) $this->option('apply');
        $detail      = (bool) $this->option('detail');

        if (! $businessOpt) {
            $this->error('Passe --business=N explícito (Tier 0: session não resolve tenant em CLI).');

            return self::FAILURE;
        }

        $businessId = (int) $businessOpt;
        if ($businessId <= 0) {
            $this->error('--business deve ser inteiro positivo.');

            return self::FAILURE;
        }

        $modo = $apply ? '<fg=red>APPLY (grava)</>' : '<fg=cyan>DRY-RUN (não grava)</>';
        $this->info("kb:classify · business_id={$businessId} · modo={$modo}");

        $r = $classifier->classify($businessId, $apply);

        $this->newLine();
        $this->line("  Classificáveis:   <fg=green>{$r['classified']}</>");
        $this->line("  Sem casa (NULL):  <fg=yellow>{$r['homeless']}</>  <fg=gray>(nenhuma regra auto_match casa — dívida de taxonomia, não erro)</>");

        if ($detail) {
            if ($r['by_subcategory'] !== []) {
                $this->newLine();
                $this->line('  <fg=green>Por subcategoria (classificados):</>');
                foreach ($r['by_subcategory'] as $slug => $n) {
                    $this->line(sprintf('    %-22s %d', $slug, $n));
                }
            }
            if ($r['homeless_by_type'] !== []) {
                $this->newLine();
                $this->line('  <fg=yellow>Sem casa, por type (decisão de taxonomia — charter §7):</>');
                foreach ($r['homeless_by_type'] as $type => $n) {
                    $this->line(sprintf('    %-22s %d', $type, $n));
                }
            }
        }

        $this->newLine();
        if ($apply) {
            $this->info("✓ Gravado: {$r['classified']} nós receberam category_id/subcategory_id.");
            if ($r['homeless'] > 0) {
                $this->warn("{$r['homeless']} nós seguem SEM categoria — precisam de subcategoria nova (ou bucket 'Diversos'). Decisão [W].");
            }
        } else {
            $this->comment("DRY-RUN — nada gravado. Revise o impacto acima; rode com --apply pra gravar (após aval [W]).");
        }

        return self::SUCCESS;
    }
}
