<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-UI-SIDEBAR-001 — Coluna `business.sidebar_hidden_groups` (JSON nullable).
 *
 * Permite esconder grupos OU itens específicos do sidebar AppShellV2 por
 * business, sem mexer no código (config-driven). Default safe: NULL =
 * mostra tudo (back-compat — todos businesses pré-existentes ficam
 * intactos).
 *
 * Schema:
 *   - JSON nullable. Array de strings.
 *   - Cada string pode ser:
 *       (a) chave de grupo SIDEBAR_GROUPS (ex: 'rh', 'fiscal', 'estoque',
 *           'plataforma', 'governanca', 'oficina') — esconde grupo inteiro
 *       (b) label EXATO de item top-level (ex: 'HRM', 'Reparar', 'CRM',
 *           'Ponto', 'Officeimpresso') — esconde só esse item
 *   - Match case-insensitive em ambos os casos.
 *   - Lookup table dos grupos canon: resources/js/Components/cockpit/Sidebar.tsx
 *     constante SIDEBAR_GROUPS (~linhas 97-159).
 *
 * Caso piloto (biz=164 Martinho Caçambas — oficina/aluguel caçambas):
 *   - Persona não-técnica (filha + Dani financeiro)
 *   - 80% dos ~25 módulos do sidebar não são usados
 *   - Clique errado → cai em Blade legacy → impressão queima onboarding
 *   - Solução: esconder grupos/itens não relevantes via seed
 *     (BusinessSidebarConfigSeeder)
 *
 * Multi-tenant Tier 0 (ADR 0093): coluna fica em `business`, filtragem
 * em LegacyMenuAdapter scopa pelo business_id do user logado — zero risco
 * cross-tenant.
 *
 * Default safe: se a coluna não existir (migration pendente) ou JSON
 * inválido, LegacyMenuAdapter retorna menu completo (back-compat).
 *
 * Migration idempotente (Schema::hasColumn guard) — ADR 0061 estilo.
 *
 * Refs:
 *   - ADR 0093 (Multi-tenant Tier 0 IRREVOGÁVEL)
 *   - ADR 0105 (Cliente como sinal qualificado — Martinho é sinal P0)
 *   - ADR 0121 (Modular especializado por vertical — OficinaAuto V0)
 *   - Skill sidebar-menu-arch (.claude/skills/sidebar-menu-arch/SKILL.md)
 *   - memory/requisitos/_DesignSystem/RUNBOOK-sidebar-per-business.md
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('business', 'sidebar_hidden_groups')) {
            Schema::table('business', function (Blueprint $table) {
                $table->json('sidebar_hidden_groups')
                    ->nullable()
                    ->after('legacy_origin')
                    ->comment("Array JSON de keys de grupo SIDEBAR_GROUPS (ex 'rh') OU labels de item top-level (ex 'HRM') a esconder do sidebar AppShellV2 pra este business. NULL=mostra tudo (default safe). Match case-insensitive.");
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('business', 'sidebar_hidden_groups')) {
            Schema::table('business', function (Blueprint $table) {
                $table->dropColumn('sidebar_hidden_groups');
            });
        }
    }
};
