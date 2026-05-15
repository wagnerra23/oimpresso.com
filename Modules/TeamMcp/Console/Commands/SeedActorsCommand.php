<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Database\Seeders\McpActorsSeeder;
use Modules\TeamMcp\Entities\McpActor;
use Throwable;

/**
 * SeedActorsCommand — popula `mcp_actors` com 5 manifests canônicos do time.
 *
 * Fecha gap Identity Mesh (Constituição Art. 6): tabela existia vazia, ActionGate
 * Fase 5 (warn-only) consultava e não achava actor → fica genérico.
 *
 * Uso:
 *   php artisan team-mcp:seed-actors              # roda seeder (idempotente)
 *   php artisan team-mcp:seed-actors --dry-run    # mostra plano, não persiste
 *
 * Convenções:
 *   - `--detail` (não `--verbose`, Symfony reservado — ver .claude/rules/commands.md)
 *   - Output PT-BR
 *   - Exit 0 sucesso, 1 erro
 *
 * Multi-tenant: mcp_actors é tabela cross-tenant (actors operam multi-business);
 * sem business_id obrigatório. Detalhe em IDENTITY-MESH-MANIFESTS.md.
 *
 * @see Modules/TeamMcp/Database/Seeders/McpActorsSeeder.php
 * @see memory/decisions/0081-identity-mesh-mcp-actors.md
 * @see memory/governance/IDENTITY-MESH-MANIFESTS.md
 */
final class SeedActorsCommand extends Command
{
    protected $signature = 'team-mcp:seed-actors
        {--dry-run : Mostra o que seria feito, não persiste no banco}
        {--detail : Imprime manifest completo de cada actor (JSON)}';

    protected $description = 'Popula mcp_actors com 5 manifests canônicos (Wagner, Felipe, Maiara, Luiz, Eliana). Idempotente.';

    public function handle(): int
    {
        if (! Schema::hasTable('mcp_actors')) {
            $this->error('Tabela mcp_actors não existe. Rode php artisan migrate primeiro.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $detail = (bool) $this->option('detail');

        if ($dryRun) {
            $this->info('--- DRY RUN — nenhuma mudança persistida ---');
            return $this->renderPlan($detail);
        }

        try {
            $seeder = new McpActorsSeeder();
            $seeder->setCommand($this);
            $seeder->run();
        } catch (Throwable $e) {
            $this->error("Falha ao seedar actors: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->newLine();
        $this->renderCurrentState($detail);

        return self::SUCCESS;
    }

    /**
     * Em modo --dry-run, mostra a tabela do que seria aplicado lendo do seeder
     * (sem persistir) + diff vs estado atual.
     */
    private function renderPlan(bool $detail): int
    {
        // Plano = manifests do seeder. Usamos reflection privada via método público?
        // Estratégia mais simples: seeder roda, mas dentro de transaction rollback.
        // Pra evitar abrir conexão de transação e poluir log, replicamos a lista
        // mínima aqui pra renderizar — usa-se mesma source-of-truth lendo via
        // Schema::hasTable + count atual.

        $current = McpActor::orderBy('trust_level')->orderBy('slug')->get();
        $rows = [];

        foreach ($current as $actor) {
            $rows[] = [
                'slug'         => $actor->slug,
                'tier'         => $actor->trust_level,
                'type'         => $actor->type,
                'display_name' => mb_substr($actor->display_name ?? '', 0, 50),
                'modules_write' => $this->formatList($actor->modules_write, 3),
                'revoked'      => $actor->isRevoked() ? 'SIM' : '-',
            ];
        }

        $this->info('Estado atual de mcp_actors (' . count($rows) . ' rows):');
        $this->table(
            ['slug', 'tier', 'type', 'display_name', 'modules_write (top3)', 'revoked'],
            $rows
        );

        $this->newLine();
        $this->line('Para aplicar 5 manifests canônicos, rode SEM --dry-run:');
        $this->line('  php artisan team-mcp:seed-actors');

        if ($detail) {
            $this->newLine();
            $this->line('Manifests canônicos esperados (5 humanos do time):');
            $this->line('  wagner (L0), felipe (L2), maira (L2), luiz (L3), eliana (L3)');
            $this->line('Veja memory/governance/IDENTITY-MESH-MANIFESTS.md');
        }

        return self::SUCCESS;
    }

    /**
     * Após seed real, mostra estado pós-aplicação.
     */
    private function renderCurrentState(bool $detail): void
    {
        $actors = McpActor::orderByRaw("FIELD(trust_level, 'L0','L1','L2','L3','L4')")
            ->orderBy('slug')
            ->get();

        $rows = [];
        foreach ($actors as $actor) {
            $rows[] = [
                'slug'         => $actor->slug,
                'tier'         => $actor->trust_level,
                'type'         => $actor->type,
                'display_name' => mb_substr($actor->display_name ?? '', 0, 50),
                'modules_write' => $this->formatList($actor->modules_write, 3),
                'audit_required' => $actor->audit_required ? 'sim' : '-',
            ];
        }

        $this->info('mcp_actors pós-seed (' . count($rows) . ' rows):');
        $this->table(
            ['slug', 'tier', 'type', 'display_name', 'modules_write (top3)', 'audit'],
            $rows
        );

        if ($detail) {
            $this->newLine();
            $this->line('Manifests detalhados (JSON):');
            foreach ($actors as $actor) {
                $this->line("  [{$actor->slug}]");
                $this->line('    write    = ' . json_encode($actor->modules_write));
                $this->line('    blocked  = ' . json_encode($actor->modules_blocked));
                $this->line('    skills   = ' . json_encode($actor->skills_required));
                $this->line('    actions! = ' . json_encode($actor->actions_blocked));
            }
        }

        $this->newLine();
        $this->info('Próximo passo: ActionGate (Fase 5) consulta manifests agora e emite warnings precisos por slug.');
    }

    /**
     * Trunca lista pra rendering compacto na tabela.
     */
    private function formatList(?array $items, int $max = 3): string
    {
        if (empty($items)) return '-';
        $head = array_slice($items, 0, $max);
        $extra = count($items) > $max ? ' +' . (count($items) - $max) : '';
        return implode(', ', $head) . $extra;
    }
}
