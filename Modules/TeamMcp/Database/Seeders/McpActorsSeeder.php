<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Entities\McpActor;

/**
 * McpActorsSeeder — popular 5 manifests canônicos do time interno oimpresso
 * em `mcp_actors` (Identity Mesh Tier 0, Constituição Art. 6).
 *
 * Por que existe (gap fechado 2026-05-15):
 *   - ADR 0081 criou schema + migration legacy 2026_05_05_240002_seed_initial_actors
 *     com manifests "v0" antes do time entrar no MCP server.
 *   - ADR 0086 criou ActorResolver + ActionGate em warn-only — depende de manifests
 *     atualizados pra emitir warnings precisos.
 *   - Time entra no MCP nas próximas semanas (Felipe/Maiara/Luiz/Eliana). SEM
 *     manifests atualizados ao papel real, ActionGate fica ineficaz, default-deny
 *     vira default-warn-only-genérico, e drift escala N× pessoas.
 *
 * O que faz:
 *   - updateOrCreate (idempotente) por slug pros 5 humanos do time:
 *     wagner (L0), felipe (L2), maira (L2), luiz (L3), eliana (L3)
 *   - Manifestos refletem PAPEL REAL declarado por Wagner em 2026-05-15:
 *     * Wagner    = root, [*] write/read, nada blocked
 *     * Felipe    = vai migrar WR Comercial Delphi → oimpresso + suporte técnico;
 *                   modules_write inclui Officeimpresso, OficinaAuto, ComunicacaoVisual
 *                   e o pseudo-módulo "legacy-delphi/*" (refere-se ao código fonte
 *                   Delphi sob `legacy-delphi/` no repo, fora de Modules/)
 *     * Maiara    = suporte técnico + dev all-around; bloqueada de fiscal (NfeBrasil,
 *                   RecurringBilling) sem L3 Eliana
 *     * Luiz      = iniciante, vai criar Modules/Mobile (ainda não existe — schema
 *                   prevê quando criar, atualizar manifest aqui)
 *     * Eliana[E] = advogada + financeiro; OWN de Financeiro/NfeBrasil/Accounting/
 *                   RecurringBilling; bloqueada de Copiloto/Jana (sprint LGPD)
 *
 * Slug `maira` (não `maiara`):
 *   - Slug é UNIQUE legacy seedado em 2026_05_05_240002 com typo "maira".
 *   - 2026_05_07_140000 migration ajustou display_name pra "Maiara"; slug ficou.
 *   - Mantemos slug pra preservar FK em mcp_tokens, mcp_audit_log etc.
 *   - display_name é o que aparece em UI.
 *
 * Idempotência:
 *   - updateOrCreate por slug — rodar 2× não duplica.
 *   - parent_actor_id resolvido por slug→id (Wagner já tem id da migration legacy).
 *   - Roda safe em prod e em testes via SQLite (Schema::hasTable guard).
 *
 * PII Tier 0 (proibições.md):
 *   - ZERO email real, ZERO credencial.
 *   - user_id é FK numérica pra UltimatePOS legacy (1=Wagner, 3=Eliana, 74=Maiara),
 *     não é PII por si só.
 *   - parent_actor é só pra IAs (não usado aqui — todos 5 são humanos diretos).
 *
 * Multi-tenant:
 *   - mcp_actors é tabela cross-tenant (actors operam multi-business).
 *   - business_id NULL pra todos. ADR 0093 documenta como tabela repo-wide.
 *
 * @see memory/decisions/0081-identity-mesh-mcp-actors.md
 * @see memory/decisions/0080-trust-tiers-operacional-audit-findings.md
 * @see memory/decisions/0086-fase-5-mvp-governance-actiongate-warn.md
 * @see memory/governance/TRUST-TIERS.md
 * @see memory/governance/IDENTITY-MESH-MANIFESTS.md (doc canônica deste seed)
 */
class McpActorsSeeder extends Seeder
{
    /**
     * 5 manifests canônicos. Override em testes: nunca duplica
     * actor existente, apenas atualiza campos por slug.
     *
     * Convenção:
     *   - 'parent_actor_slug' é resolvido em run() pra parent_actor_id.
     *   - Strings de skill, módulo e action seguem nomenclatura do repo
     *     (folder `Modules/<Name>/` ou skill `.claude/skills/<name>/`).
     *   - "legacy-delphi/*" representa o repo Delphi sob `legacy-delphi/`
     *     (fora de Modules/ — Felipe vai migrar pra oimpresso).
     */
    private function manifests(): array
    {
        return [
            // ----------------------------------------------------------------
            // 1. WAGNER — L0 KERNEL root sovereign
            // ----------------------------------------------------------------
            [
                'slug'              => 'wagner',
                'type'              => 'human',
                'trust_level'       => 'L0',
                'parent_actor_slug' => null,
                'modules_write'     => ['*'],
                'modules_read'      => ['*'],
                'modules_blocked'   => [],
                'skills_required'   => [
                    'brief-first',
                    'mcp-first',
                    'multi-tenant-patterns',
                    'commit-discipline',
                ],
                'actions_blocked'   => [],
                'audit_required'    => false, // Único actor false (root — Constituição Art. 1)
                'user_id'           => 1,
                'display_name'      => 'Wagner Rocha',
                'notes'             => 'Root sovereign L0 — Constituição Art. 1 + 5. Único actor com audit_required=false (auditoria do root vira meta-audit infinito; ações L0 vão em mcp_audit_log.kernel_action separado).',
            ],

            // ----------------------------------------------------------------
            // 2. FELIPE — L2 OPERATOR, dev Delphi migrando WR Comercial → oimpresso
            // ----------------------------------------------------------------
            [
                'slug'              => 'felipe',
                'type'              => 'human',
                'trust_level'       => 'L2',
                'parent_actor_slug' => 'wagner',
                'modules_write'     => [
                    'Officeimpresso',     // núcleo legacy UltimatePOS + módulos verticais
                    'OficinaAuto',        // vertical mecânico — onde Felipe vai trabalhar
                    'ComunicacaoVisual',  // vertical gráfica — onde Felipe vai trabalhar
                    'legacy-delphi/*',    // repo Delphi sob legacy-delphi/ — migração WR Comercial
                ],
                'modules_read'      => ['*'],
                'modules_blocked'   => [
                    'Connector',     // L0 only
                    'Superadmin',    // L0 only
                    'Governance',    // L1 only (Wagner)
                    'ADS',           // L1 only (skills review)
                    'TeamMcp',       // L1 only (tokens/quotas/roles)
                ],
                'skills_required'   => [
                    'brief-first',
                    'mcp-first',
                    'multi-tenant-patterns',
                    'preflight-modulo',
                    'officeimpresso-source-analysis',  // contexto migração Delphi
                    'officeimpresso-financial-snapshot',
                ],
                'actions_blocked'   => [
                    'drop_table',
                    'schema_destructive',
                    'push_main_no_pr',
                    'merge_pr_solo',     // Wagner aprova
                    'deploy_prod_solo',  // checkpoint Wagner
                ],
                'audit_required'    => true,
                'user_id'           => null,
                'display_name'      => 'Felipe (dev+suporte, migração WR Comercial)',
                'notes'             => 'TEAM.md: dev+suporte Delphi. Vai migrar WR Comercial → oimpresso. Cuida de OficinaAuto + ComunicacaoVisual (verticais novos). Bloqueado de Governance/ADS/TeamMcp (L1) e Connector/Superadmin (L0).',
            ],

            // ----------------------------------------------------------------
            // 3. MAIRA (slug legacy) = Maiara — L2 OPERATOR, suporte + dev all-around
            // ----------------------------------------------------------------
            [
                'slug'              => 'maira', // Slug legacy preservado (ver classe docblock)
                'type'              => 'human',
                'trust_level'       => 'L2',
                'parent_actor_slug' => 'wagner',
                'modules_write'     => [
                    'Crm',
                    'Sells',
                    'Repair',
                    'Inventory',
                    'Purchase',
                ],
                'modules_read'      => ['*'],
                'modules_blocked'   => [
                    'Connector',         // L0 only
                    'Superadmin',        // L0 only
                    'Governance',        // L1 only
                    'ADS',               // L1 only
                    'TeamMcp',           // L1 only
                    'NfeBrasil',         // fiscal — só L3 Eliana ou Wagner
                    'RecurringBilling',  // fiscal/billing — só L3 Eliana ou Wagner
                ],
                'skills_required'   => [
                    'brief-first',
                    'mcp-first',
                    'multi-tenant-patterns',
                    'preflight-modulo',
                    'ticket-triage',
                ],
                'actions_blocked'   => [
                    'drop_table',
                    'schema_destructive',
                    'push_main_no_pr',
                    'deploy_prod_solo',  // TEAM.md regra dura
                ],
                'audit_required'    => true,
                'user_id'           => 74,
                'display_name'      => 'Maiara (suporte+dev)',
                'notes'             => 'TEAM.md: não faz deploy produção sozinha. Cobre Crm/Sells/Repair/Inventory/Purchase. Bloqueada de fiscal (NfeBrasil/RecurringBilling) sem L3 Eliana. Slug "maira" é legado da migration 240002 (typo preservado pra FK); display_name corrigido em 2026_05_07.',
            ],

            // ----------------------------------------------------------------
            // 4. LUIZ — L3 VERTICAL SPECIALIST, iniciante, vai criar Modules/Mobile
            // ----------------------------------------------------------------
            [
                'slug'              => 'luiz',
                'type'              => 'human',
                'trust_level'       => 'L3',
                'parent_actor_slug' => 'wagner',
                'modules_write'     => [
                    'Mobile',           // Modules/Mobile a criar — Luiz é o owner
                    'Pages/Mobile/*',   // resources/js/Pages/Mobile/*.tsx
                ],
                'modules_read'      => ['*'],
                'modules_blocked'   => [
                    'Connector',     // L0 only
                    'Superadmin',    // L0 only
                    'Governance',    // L1 only
                    'ADS',           // L1 only
                    'TeamMcp',       // L1 only
                ],
                'skills_required'   => [
                    'brief-first',
                    'mcp-first',
                    'multi-tenant-patterns',
                    'criar-modulo',     // Luiz vai criar Modules/Mobile do zero
                    'charter-first',    // S4+ pra .tsx com charter
                ],
                'actions_blocked'   => [
                    'merge_pr_solo',         // TEAM.md regra dura — Felipe ou Wagner aprova
                    'push_main',
                    'drop_table',
                    'schema_destructive',
                    'prod_migration_solo',   // sempre L2+ Felipe/Wagner
                    'deploy_prod_solo',
                ],
                'audit_required'    => true,
                'user_id'           => null,
                'display_name'      => 'Luiz (iniciante + IA-pair, owner Modules/Mobile)',
                'notes'             => 'TEAM.md: não mergeia PR sozinho (Felipe ou Wagner aprova). Owner do Modules/Mobile futuro (ainda não criado). Atualizar manifest quando criar (adicionar modules_write conforme Modules/Mobile crescer com submódulos).',
            ],

            // ----------------------------------------------------------------
            // 5. ELIANA[E] — L3 VERTICAL SPECIALIST, advogada + financeiro
            // ----------------------------------------------------------------
            [
                'slug'              => 'eliana',
                'type'              => 'human',
                'trust_level'       => 'L3',
                'parent_actor_slug' => 'wagner',
                'modules_write'     => [
                    'Financeiro',
                    'FinanceiroAvancado',
                    'NfeBrasil',           // OWN — fiscal
                    'NFSe',                // OWN — fiscal
                    'Accounting',
                    'RecurringBilling',    // OWN — billing recorrente
                ],
                'modules_read'      => ['*'],
                'modules_blocked'   => [
                    'Connector',     // L0 only
                    'Superadmin',    // L0 only
                    'Governance',    // L1 only
                    'ADS',           // L1 only
                    'TeamMcp',       // L1 only
                    'Mobile',        // Luiz é owner
                    'Copiloto',      // L2 — Jana sprints LGPD restritos (TEAM.md)
                    'Jana',          // alias futuro pós-rename Copiloto→Jana
                ],
                'skills_required'   => [
                    'brief-first',
                    'mcp-first',
                    'multi-tenant-patterns',
                    'preflight-modulo',
                ],
                'actions_blocked'   => [
                    'drop_table',
                    'schema_destructive',
                    'push_main_no_pr',
                    'deploy_prod_solo',
                    'edit_non_financial_code',  // declarativo — gate enforce em ActionGate Fase 5
                ],
                'audit_required'    => true,
                'user_id'           => 3,
                'display_name'      => 'Eliana (advogada + financeiro, esposa Wagner)',
                'notes'             => 'TEAM.md: esposa Wagner, advogada+financeiro+dev IA-pair. NÃO mexe em Copiloto/Jana (sprints LGPD restritos). OWN de Financeiro/NfeBrasil/NFSe/Accounting/RecurringBilling. Não DPO formal ainda (decisão Wagner 2026-05-09: estudar LGPD primeiro). Distinguir de Eliana(WR2) cliente externa.',
            ],
        ];
    }

    /**
     * Roda o seeder — idempotente por slug.
     *
     * Estratégia:
     *   1. Guard: skip se mcp_actors não existe (SQLite Pest in-memory).
     *   2. Itera manifests, resolve parent_actor_slug → parent_actor_id.
     *   3. updateOrCreate por slug — JSON casts do Model serializam arrays.
     *   4. Imprime sumário pra command CLI.
     */
    public function run(): void
    {
        if (! Schema::hasTable('mcp_actors')) {
            $this->command?->warn('mcp_actors table missing — rode php artisan migrate primeiro.');
            return;
        }

        $manifests = $this->manifests();
        $criados   = 0;
        $atualizados = 0;

        // Wagner sempre primeiro (todos os outros têm parent_actor_slug=wagner)
        usort($manifests, function ($a, $b) {
            if ($a['slug'] === 'wagner') return -1;
            if ($b['slug'] === 'wagner') return 1;
            return 0;
        });

        foreach ($manifests as $manifest) {
            $parentId = null;
            if (! empty($manifest['parent_actor_slug'])) {
                $parentId = DB::table('mcp_actors')
                    ->where('slug', $manifest['parent_actor_slug'])
                    ->value('id');
            }

            $attributes = [
                'type'              => $manifest['type'],
                'trust_level'       => $manifest['trust_level'],
                'parent_actor_id'   => $parentId,
                'modules_write'     => $manifest['modules_write'],
                'modules_read'      => $manifest['modules_read'],
                'modules_blocked'   => $manifest['modules_blocked'],
                'skills_required'   => $manifest['skills_required'],
                'actions_blocked'   => $manifest['actions_blocked'],
                'audit_required'    => $manifest['audit_required'],
                'user_id'           => $manifest['user_id'],
                'display_name'      => $manifest['display_name'],
                'notes'             => $manifest['notes'],
            ];

            $existing = McpActor::where('slug', $manifest['slug'])->first();

            if ($existing) {
                $existing->fill($attributes);
                if ($existing->isDirty()) {
                    $existing->save();
                    $atualizados++;
                }
            } else {
                McpActor::create(array_merge(
                    ['slug' => $manifest['slug']],
                    $attributes
                ));
                $criados++;
            }
        }

        $total = count($manifests);
        $this->command?->info(
            "McpActorsSeeder: {$criados} criados, {$atualizados} atualizados (de {$total} manifests canônicos)."
        );
    }
}
