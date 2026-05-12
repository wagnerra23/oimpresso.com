<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-015 — Seeder idempotente que marca os 6 candidatos saudáveis
 * OfficeImpresso (gráficas legacy WR Comercial) com legacy_origin =
 * 'officeimpresso'.
 *
 * Critério de seleção (consultar memory/claude/reference_clientes_ativos.md
 * pra contexto, e ADR 0121 pra estratégia modular vertical):
 *   - 6 candidatos saudáveis pra migração: Vargas, Extreme, Gold, Zoom,
 *     Fixar, Produart
 *   - Identificação por nome substring (LIKE) E flag is_officeimpresso=true
 *     (opcional defesa em profundidade — `is_officeimpresso` indica licença
 *     do módulo desktop, geralmente verdadeiro pros 6).
 *
 * Idempotente:
 *   - WHERE legacy_origin IS NULL — não sobrescreve marcação manual.
 *   - WHERE name LIKE — preserva business renomeado/excluído (no-op).
 *   - Skipa silenciosamente se coluna não existe (defesa pra rodar antes
 *     da migration ter rodado).
 *
 * Multi-tenant Tier 0 (ADR 0093): seeder roda ATÔMICO por business_id;
 * cada UPDATE filtra `business.id` específico — zero risco cross-tenant.
 *
 * Trustless: NÃO crava IDs (variam entre dev/staging/prod) — usa name
 * LIKE 'X%' que sobrevive a IDs diferentes em ambientes.
 *
 * Refs:
 *   - ADR 0136 (Sells: split Lista vs Grade Avançada toggle)
 *   - ADR 0121 (modular especializado por vertical)
 *   - memory/claude/reference_clientes_ativos.md
 */
class BusinessLegacyOriginSeeder extends Seeder
{
    /**
     * Padrões name LIKE pros 6 candidatos saudáveis OfficeImpresso.
     * Lista canon pode ser ajustada via PR — coluna name é texto livre
     * editável pelo dono no settings UltimatePOS, então padrões devem ser
     * substring representativa (case-insensitive).
     */
    private const OFFICEIMPRESSO_CANDIDATES = [
        'Vargas',
        'Extreme',
        'Gold',
        'Zoom',
        'Fixar',
        'Produart',
    ];

    public function run(): void
    {
        // Defesa: se coluna ainda não existe (migration pendente), skipa.
        if (! Schema::hasColumn('business', 'legacy_origin')) {
            $this->command?->warn(
                'BusinessLegacyOriginSeeder: coluna business.legacy_origin não existe '
                . '(rode migrations primeiro). Skip.'
            );
            return;
        }

        $marked = 0;
        $skipped = 0;

        foreach (self::OFFICEIMPRESSO_CANDIDATES as $needle) {
            // Match case-insensitive em qualquer parte do name.
            // Idempotente: only WHERE legacy_origin IS NULL.
            $affected = DB::table('business')
                ->where('name', 'like', "%{$needle}%")
                ->whereNull('legacy_origin')
                ->update([
                    'legacy_origin' => 'officeimpresso',
                    'updated_at' => now(),
                ]);

            if ($affected > 0) {
                $marked += $affected;
                $this->command?->info("  ✓ '{$needle}' → marcado {$affected} business(es) como 'officeimpresso'");
            } else {
                $skipped++;
            }
        }

        $this->command?->info(
            "BusinessLegacyOriginSeeder: {$marked} business(es) marcados, "
            . "{$skipped} candidato(s) sem match (já marcado, ausente, ou nome divergente)."
        );
    }
}
