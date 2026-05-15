<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * US-UI-SIDEBAR-001 — Seeder idempotente que configura `sidebar_hidden_groups`
 * por business pra esconder módulos não usados.
 *
 * Caso piloto (biz Martinho Caçambas — oficina/aluguel caçambas/PERSONA
 * não-técnica): esconder ~80% dos ~25 módulos do sidebar pra evitar
 * cliques em Blade legacy. Champions: filha do Martinho + Dani financeiro.
 *
 * Identificação por nome substring (LIKE) — NÃO crava IDs (variam entre
 * dev/staging/prod). Match case-insensitive.
 *
 * Idempotente:
 *   - WHERE sidebar_hidden_groups IS NULL — não sobrescreve marcação manual.
 *   - Skipa silenciosamente se coluna não existe (defesa pré-migration).
 *
 * Multi-tenant Tier 0 (ADR 0093): UPDATE filtra `business.id` específico —
 * zero risco cross-tenant.
 *
 * Configurações canon (cada chave é um business "alvo", valor é o array
 * JSON de grupos/items a esconder):
 *
 *   Martinho Caçambas (oficina mecânica + aluguel caçambas):
 *     - MANTER: ACESSOS RÁPIDOS (Contatos, Produtos, Vendas), OFICINA AUTO,
 *       FINANCEIRO, FISCAL (NFe), IA & PRODUTIVIDADE (Jana, Copiloto),
 *       Configurações
 *     - ESCONDER (grupos): rh, estoque, conhecimento, governanca, plataforma
 *     - ESCONDER (items específicos): Reparar (substituído por Oficina Auto),
 *       Officeimpresso (legacy WR Comercial Delphi — Martinho nunca usou),
 *       Projeto, Project Mgmt, ADS
 *
 * Refs:
 *   - ADR 0093 (Multi-tenant Tier 0)
 *   - ADR 0105 (Cliente como sinal qualificado)
 *   - ADR 0121 (Modular especializado por vertical)
 *   - memory/requisitos/_DesignSystem/RUNBOOK-sidebar-per-business.md
 */
class BusinessSidebarConfigSeeder extends Seeder
{
    /**
     * Mapa name LIKE → array de grupos/items a esconder.
     *
     * Chave: substring case-insensitive de business.name
     * Valor: array JSON salvo em business.sidebar_hidden_groups
     *
     * Schema do array (ver migration 2026_05_14_120000):
     *   - Strings podem ser chaves de grupo (lowercase: 'rh', 'fiscal', etc)
     *     OU labels exatos de item top-level (preservar casing original)
     */
    private const SIDEBAR_CONFIGS = [
        // Martinho Caçambas — oficina mecânica + aluguel caçambas
        // (CNAE 4520-0/01 — vertical OficinaAuto)
        'Martinho' => [
            // Grupos inteiros escondidos
            'rh',            // HRM, Essenciais, Ponto — Martinho sem folha CLT
            'conhecimento',  // Cofre, KB, Planilha — pra dev/admin, não cliente
            'governanca',    // Governança, ADS, Team MCP — interno oimpresso
            'plataforma',    // CMS, Conector, Backup, Módulos — superadmin
            // NOTA 14/maio noite — Lara (filha) é responsável estoque (peças/lonas
            // mecânica). Grupo 'estoque' PRECISA estar visível pra ela. Hide items
            // específicos que ela não usa (Transferências entre locations, Ativos).
            // Items específicos escondidos (cross-grupo)
            'Reparar',         // substituído por Oficina Auto (vertical V0)
            'Officeimpresso',  // legacy WR Comercial Delphi — Martinho nunca usou
            'Office Impresso', // variação do label
            'Projeto',         // Modules/Project — não relevante pra oficina
            'Project Mgmt',    // idem
            'ADS',             // adaptive decision system — interno oimpresso
            'Transferências',  // entre locations — Martinho tem 1 location só
            'Transferencias',  // variação acento
            'Ativos',          // gestão ativos — não relevante caçambeiro
        ],
    ];

    public function run(): void
    {
        // Defesa: se coluna ainda não existe (migration pendente), skipa.
        if (! Schema::hasColumn('business', 'sidebar_hidden_groups')) {
            $this->command?->warn(
                'BusinessSidebarConfigSeeder: coluna business.sidebar_hidden_groups não existe '
                . '(rode migrations primeiro). Skip.'
            );
            return;
        }

        $marked = 0;
        $skipped = 0;

        foreach (self::SIDEBAR_CONFIGS as $needle => $hiddenList) {
            // Match case-insensitive em qualquer parte do name.
            // Idempotente: only WHERE sidebar_hidden_groups IS NULL.
            $affected = DB::table('business')
                ->where('name', 'like', "%{$needle}%")
                ->whereNull('sidebar_hidden_groups')
                ->update([
                    'sidebar_hidden_groups' => json_encode($hiddenList),
                    'updated_at' => now(),
                ]);

            if ($affected > 0) {
                $marked += $affected;
                $count = count($hiddenList);
                $this->command?->info("  ✓ '{$needle}' → marcado {$affected} business(es) com {$count} grupo(s)/item(s) escondido(s)");
            } else {
                $skipped++;
            }
        }

        $this->command?->info(
            "BusinessSidebarConfigSeeder: {$marked} business(es) configurados, "
            . "{$skipped} candidato(s) sem match (já marcado, ausente, ou nome divergente)."
        );
    }
}
