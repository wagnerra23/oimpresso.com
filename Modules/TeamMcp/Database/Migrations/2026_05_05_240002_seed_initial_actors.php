<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed inicial dos 6 actors canônicos do oimpresso (ADR 0081 + IDENTITY-MESH.md):
 * 1. wagner (L0 root)
 * 2. felipe (L2)
 * 3. maira (L2)
 * 4. luiz (L3)
 * 5. eliana (L3)
 * 6. claude-code-wagner-laptop (ai_agent L2, parent=wagner)
 *
 * Backfill: tokens existentes ganham actor_id correspondente.
 * Tabela users ganha mcp_actor_id pros 4 humanos com user_id mapeado.
 *
 * Idempotente: skip insert se slug já existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $actors = [
            [
                'slug'              => 'wagner',
                'type'              => 'human',
                'trust_level'       => 'L0',
                'parent_actor_id'   => null,
                'modules_write'     => json_encode(['*']),
                'modules_read'      => json_encode(['*']),
                'modules_blocked'   => json_encode([]),
                'skills_required'   => json_encode([]),
                'actions_blocked'   => json_encode([]),
                'audit_required'    => 0,  // Único actor com audit_required=false (root)
                'user_id'           => 1,
                'display_name'      => 'Wagner Rocha',
                'notes'             => 'Root sovereign — Constituição Art. 1',
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'slug'              => 'felipe',
                'type'              => 'human',
                'trust_level'       => 'L2',
                'parent_actor_id'   => null,
                'modules_write'     => json_encode(['Jana', 'Notas', 'KB', 'Project', 'Financeiro', 'ConsultaOs']),
                'modules_read'      => json_encode(['*']),
                'modules_blocked'   => json_encode(['Connector', 'Superadmin', 'TeamMcp']),
                'skills_required'   => json_encode([]),
                'actions_blocked'   => json_encode(['drop_table', 'schema_destructive', 'push_main_no_pr']),
                'audit_required'    => 1,
                'user_id'           => null,
                'display_name'      => 'Felipe (dev+suporte)',
                'notes'             => null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'slug'              => 'maira',
                'type'              => 'human',
                'trust_level'       => 'L2',
                'parent_actor_id'   => null,
                'modules_write'     => json_encode(['Jana', 'Notas', 'KB', 'Project']),
                'modules_read'      => json_encode(['*']),
                'modules_blocked'   => json_encode(['Connector', 'Superadmin', 'TeamMcp']),
                'skills_required'   => json_encode([]),
                'actions_blocked'   => json_encode(['drop_table', 'schema_destructive', 'push_main_no_pr', 'deploy_prod_solo']),
                'audit_required'    => 1,
                'user_id'           => 74,
                'display_name'      => 'Maíra (suporte+dev)',
                'notes'             => 'TEAM.md: não faz deploy produção sozinha',
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'slug'              => 'luiz',
                'type'              => 'human',
                'trust_level'       => 'L3',
                'parent_actor_id'   => null,
                'modules_write'     => json_encode(['Jana', 'Notas']),
                'modules_read'      => json_encode(['*']),
                'modules_blocked'   => json_encode(['Connector', 'Superadmin', 'TeamMcp', 'ADS']),
                'skills_required'   => json_encode([]),
                'actions_blocked'   => json_encode(['merge_pr', 'push_main', 'drop_table']),
                'audit_required'    => 1,
                'user_id'           => null,
                'display_name'      => 'Luiz (iniciante + IA-pair)',
                'notes'             => 'TEAM.md: não mergeia PR sozinho (Felipe ou Wagner aprova)',
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'slug'              => 'eliana',
                'type'              => 'human',
                'trust_level'       => 'L3',
                'parent_actor_id'   => null,
                'modules_write'     => json_encode(['Financeiro', 'Notas']),
                'modules_read'      => json_encode(['*']),
                'modules_blocked'   => json_encode(['Connector', 'Superadmin', 'TeamMcp', 'Copiloto', 'ADS']),
                'skills_required'   => json_encode([]),
                'actions_blocked'   => json_encode(['deploy_prod_solo', 'drop_table']),
                'audit_required'    => 1,
                'user_id'           => 3,
                'display_name'      => 'Eliana (financeiro + IA-pair, esposa Wagner)',
                'notes'             => 'TEAM.md: não mexe em Copiloto sprints LGPD. Distinguir de Eliana(WR2) cliente externa',
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
        ];

        // Insere humanos primeiro (precisamos do id do wagner pra parent do claude-code)
        foreach ($actors as $actor) {
            $exists = DB::table('mcp_actors')->where('slug', $actor['slug'])->exists();
            if (!$exists) {
                DB::table('mcp_actors')->insert($actor);
            }
        }

        // Resolve wagner.id pra usar como parent do claude-code
        $wagnerId = DB::table('mcp_actors')->where('slug', 'wagner')->value('id');

        $aiActor = [
            'slug'              => 'claude-code-wagner-laptop',
            'type'              => 'ai_agent',
            'trust_level'       => 'L2',
            'parent_actor_id'   => $wagnerId,
            'modules_write'     => json_encode(['Jana', 'Notas', 'KB', 'Project']),
            'modules_read'      => json_encode(['*']),
            'modules_blocked'   => json_encode(['Connector', 'Superadmin', 'MemCofre']),
            'skills_required'   => json_encode(['oimpresso-stack', 'multi-tenant-patterns', 'publication-policy']),
            'actions_blocked'   => json_encode(['drop_table', 'schema_destructive', 'push_main_no_pr', 'delete_prod_data']),
            'audit_required'    => 1,
            'user_id'           => null,
            'display_name'      => 'Claude Code @ Wagner Laptop',
            'notes'             => 'Token DXT — Wagner gerou em 2026-04-30. Sessões em mcp_cc_sessions.',
            'created_by_actor_id' => $wagnerId,
            'created_at'        => $now,
            'updated_at'        => $now,
        ];

        if (!DB::table('mcp_actors')->where('slug', 'claude-code-wagner-laptop')->exists()) {
            DB::table('mcp_actors')->insert($aiActor);
        }

        // Backfill: bind tokens existentes ao actor correto
        // Tokens com user_id=1 (todos do Wagner) → actor=wagner
        $wagnerActorId = DB::table('mcp_actors')->where('slug', 'wagner')->value('id');
        $felipeActorId = DB::table('mcp_actors')->where('slug', 'felipe')->value('id');
        $mairaActorId = DB::table('mcp_actors')->where('slug', 'maira')->value('id');
        $elianaActorId = DB::table('mcp_actors')->where('slug', 'eliana')->value('id');
        $claudeCodeActorId = DB::table('mcp_actors')->where('slug', 'claude-code-wagner-laptop')->value('id');

        // Tokens com user_id=1 ou user_id=2 → wagner (Wagner tem 2 user rows duplicadas)
        DB::table('mcp_tokens')
            ->whereIn('user_id', [1, 2])
            ->whereNull('actor_id')
            ->update(['actor_id' => $wagnerActorId]);

        // Token DXT específico do Claude Code (token id=10) → claude-code-wagner-laptop
        // Identificado pelo nome "DXT — Wagner (gerado 30/04/2026 07:44)"
        DB::table('mcp_tokens')
            ->where('id', 10)
            ->update(['actor_id' => $claudeCodeActorId]);

        // Token user_id=74 (Maiara) → maira
        DB::table('mcp_tokens')
            ->where('user_id', 74)
            ->whereNull('actor_id')
            ->update(['actor_id' => $mairaActorId]);

        // Token user_id=3 (Eliana) → eliana
        DB::table('mcp_tokens')
            ->where('user_id', 3)
            ->whereNull('actor_id')
            ->update(['actor_id' => $elianaActorId]);

        // Backfill users.mcp_actor_id pros humanos com user_id linkado
        DB::table('users')->where('id', 1)->update(['mcp_actor_id' => $wagnerActorId]);
        DB::table('users')->where('id', 3)->update(['mcp_actor_id' => $elianaActorId]);
        DB::table('users')->where('id', 74)->update(['mcp_actor_id' => $mairaActorId]);
    }

    public function down(): void
    {
        // Limpa backfills antes de drop (mas a tabela em si é dropada na migration anterior)
        DB::table('users')->whereNotNull('mcp_actor_id')->update(['mcp_actor_id' => null]);
        DB::table('mcp_tokens')->whereNotNull('actor_id')->update(['actor_id' => null]);
        DB::table('mcp_actors')->truncate();
    }
};
