<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Meta-skills (Wagner's "Cognitive Control Panel") — regras SOFT de governança.
 *
 * Diferentes do PolicyEngine (HARD firewall imutável):
 *   - Policy = "NUNCA executar X em produção" (código no git)
 *   - Governance Rule = "Promove pra ALLOW_BRAIN_A se score > 0.8 E uso > 10"
 *     (configurável via UI, validado por Wagner, com versão e histórico)
 */
class CreateMcpGovernanceRulesTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_governance_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('rule_key', 80)->unique();      // 'promote_pattern', 'archive_unused', etc.
            $table->string('name', 150);
            $table->text('description');                    // PT-BR explicando regra
            $table->enum('category', [
                'promotion', 'archival', 'escalation', 'retry', 'budget', 'review',
            ]);

            // Condição como JSON DSL: {operator, fields, values}
            // Ex: {op: 'AND', conds: [{field: 'wilson_lb', op: '>=', value: 0.8}, ...]}
            $table->json('condition');
            $table->json('action');                         // {type, params}

            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('version')->default(1);
            $table->unsignedSmallInteger('triggered_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();

            $table->string('created_by', 50)->default('system');
            $table->timestamps();

            $table->index('enabled', 'idx_gov_enabled');
            $table->index('category', 'idx_gov_category');
        });

        // Seed das 4 regras core (estas são exatamente as do exemplo do Wagner)
        $now = now();
        DB::table('mcp_governance_rules')->insert([
            [
                'rule_key'    => 'promote_pattern_to_brain_a',
                'name'        => 'Promover padrão pra ALLOW_BRAIN_A',
                'description' => 'Se Wilson Score Lower Bound ≥ 0.80 e total de execuções ≥ 10, criar task pendente Wagner com proposta de mover event_type pra ALLOW_BRAIN_A em PolicyEngine.php (PR git).',
                'category'    => 'promotion',
                'condition'   => json_encode([
                    'op' => 'AND',
                    'conds' => [
                        ['field' => 'wilson_lower_bound', 'op' => '>=', 'value' => 0.80],
                        ['field' => 'total_count',        'op' => '>=', 'value' => 10],
                        ['field' => 'is_hardcoded',       'op' => '==', 'value' => false],
                    ],
                ]),
                'action'      => json_encode([
                    'type'   => 'create_pending_decision',
                    'params' => ['event_type' => 'pattern_hardcode', 'hitl_level' => 3],
                ]),
                'enabled'     => true,
                'created_by'  => 'wagner',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'rule_key'    => 'archive_unused_pattern',
                'name'        => 'Arquivar padrão não usado',
                'description' => 'Se padrão não foi acionado em 30 dias, marcar como arquivado (não deletar, só ocultar das listas ativas).',
                'category'    => 'archival',
                'condition'   => json_encode([
                    'op' => 'AND',
                    'conds' => [
                        ['field' => 'days_since_last_outcome', 'op' => '>=', 'value' => 30],
                    ],
                ]),
                'action'      => json_encode([
                    'type'   => 'tag_pattern',
                    'params' => ['tag' => 'archived'],
                ]),
                'enabled'     => true,
                'created_by'  => 'wagner',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'rule_key'    => 'escalate_low_confidence_review',
                'name'        => 'Escalar revisão com baixa confiança',
                'description' => 'Se ReviewerAgent retornou confidence < 0.6, escalar pra Wagner mesmo se score ≥ 50.',
                'category'    => 'escalation',
                'condition'   => json_encode([
                    'op' => 'AND',
                    'conds' => [
                        ['field' => 'review_confidence', 'op' => '<', 'value' => 0.60],
                    ],
                ]),
                'action'      => json_encode([
                    'type'   => 'set_destination',
                    'params' => ['destination' => 'pending_wagner', 'hitl_level' => 3],
                ]),
                'enabled'     => true,
                'created_by'  => 'wagner',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'rule_key'    => 'retry_low_score',
                'name'        => 'Retry inteligente em score baixo',
                'description' => 'Se review_score < 70 e attempts < 3, agendar retry em 5min com instrução de ajuste do Reviewer.',
                'category'    => 'retry',
                'condition'   => json_encode([
                    'op' => 'AND',
                    'conds' => [
                        ['field' => 'review_score', 'op' => '<', 'value' => 70],
                        ['field' => 'attempts',     'op' => '<', 'value' => 3],
                    ],
                ]),
                'action'      => json_encode([
                    'type'   => 'schedule_retry',
                    'params' => ['delay_minutes' => 5],
                ]),
                'enabled'     => true,
                'created_by'  => 'wagner',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_governance_rules');
    }
}
