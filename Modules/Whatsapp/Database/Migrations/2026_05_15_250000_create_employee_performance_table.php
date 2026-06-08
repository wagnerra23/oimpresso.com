<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-VOZ-003 — Employee Performance scorecard.
 *
 * Wagner 2026-05-15: "Avalie o atendimento da empresa, de uma nota para
 * todos cliente e funcionários. Deverá criar um perfil para todos
 * funcionários e clientes."
 *
 * Single source of truth da PERFORMANCE de cada atendente do business
 * (Maiara, Luiz, Felipe, etc). 1 row per (business_id, user_id).
 *
 * Métricas calculadas daily pelo `EmployeePerformanceRebuilder`:
 *   - Volume (n_msgs, n_conversations, n_clientes)
 *   - Velocidade (tempo_resposta_mediana_s, p90, sla_breach)
 *   - Qualidade (reclamacoes_recebidas — soma das reclamações dos clientes atendidos)
 *   - Cobertura (horas_ativas_distintas, hora_pico, dias_ativos_30d)
 *   - Especialidades (temas_dominantes — futuro inferência IA)
 *   - Nota geral 0-100 (scoring transparente — ver Service)
 *
 * Identidade do atendente: PRIMÁRIO via `messages.sender_user_id` (quando
 * atendente responde via UI Inbox oimpresso). FALLBACK heurístico via
 * `messages.body LIKE '%Nome:%'` (quando responde via WhatsApp Web direto).
 *
 * Tier 0 multi-tenant: business_id NOT NULL + UNIQUE composto (biz, user_id).
 *
 * Idempotente.
 *
 * @see Modules/Whatsapp/Entities/EmployeePerformance.php
 * @see Modules/Whatsapp/Services/CustomerMemory/EmployeePerformanceRebuilder.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_performance')) {
            return;
        }

        Schema::create('employee_performance', function (Blueprint $table): void {
            $table->bigIncrements('id');

            // Tier 0 multi-tenant
            $table->unsignedInteger('business_id');

            // Identidade do atendente
            $table->unsignedInteger('user_id')->nullable()
                ->comment('FK users.id — NULL quando identidade só por heurística nome');
            $table->string('heuristic_name', 60)->nullable()
                ->comment('Nome detectado via body *Nome:* — quando sender_user_id NULL');
            $table->string('display_name', 120)->nullable()
                ->comment('Nome canônico — users.first_name ou heuristic_name');

            // Volume agregado
            $table->unsignedInteger('n_msgs_total')->default(0);
            $table->unsignedInteger('n_conversations_atendidas')->default(0);
            $table->unsignedInteger('n_clientes_diferentes')->default(0);

            // Velocidade resposta
            $table->unsignedInteger('tempo_resposta_mediana_s')->nullable()
                ->comment('Mediana segundos entre inbound anterior → outbound do atendente');
            $table->unsignedInteger('tempo_resposta_p90_s')->nullable();
            $table->unsignedInteger('sla_breach_count')->default(0)
                ->comment('Conversas com primeira resposta > SLA (default 4h)');

            // Qualidade (proxy)
            $table->unsignedInteger('reclamacoes_recebidas')->default(0)
                ->comment('Soma de total_reclamacoes dos clientes que esse atendente atendeu');
            $table->decimal('csat_avg', 3, 2)->nullable()
                ->comment('CSAT médio quando integração futura WhatsappCsatResponse pronta');

            // Cobertura temporal
            $table->unsignedTinyInteger('horas_ativas_distintas')->default(0)
                ->comment('Count DISTINCT HOUR(created_at) — 0-24');
            $table->unsignedTinyInteger('hora_pico')->nullable()
                ->comment('Hora 0-23 com maior volume outbound do atendente');
            $table->unsignedTinyInteger('dias_ativos_30d')->default(0);
            $table->timestamp('primeira_atividade_at')->nullable();
            $table->timestamp('ultima_atividade_at')->nullable();

            // Especialidades (futuro IA)
            $table->json('temas_dominantes')->nullable()
                ->comment('["nfe","boleto","caixa"] — inferido temas_recorrentes dos clientes atendidos');

            // Nota agregada 0-100 (scoring transparente — ver Service)
            $table->unsignedTinyInteger('nota_geral')->nullable();
            $table->json('nota_breakdown')->nullable()
                ->comment('{volume:25,diversidade:20,velocidade:25,profundidade:15,cobertura:10,engajamento:5}');
            $table->timestamp('nota_calculada_em')->nullable();

            // Flags operacionais
            $table->json('flags')->nullable()
                ->comment('[{tipo:"top_performer",since},{tipo:"baixo_volume_30d"},{tipo:"ferias",until}]');

            // Tracking rebuild
            $table->timestamp('last_rebuilt_at')->nullable();
            $table->string('rebuilt_via', 24)->nullable();

            $table->timestamps();

            // Constraints
            // Pra user_id real: UNIQUE (biz, user_id) — 1 row per user real
            // Pra heurístico: UNIQUE (biz, heuristic_name) quando user_id NULL
            $table->index(['business_id', 'user_id'], 'ep_biz_user_idx');
            $table->index(['business_id', 'nota_geral'], 'ep_biz_nota_idx');
            $table->index(['business_id', 'heuristic_name'], 'ep_biz_heur_idx');
        });

        if (Schema::hasTable('business')) {
            Schema::table('employee_performance', function (Blueprint $table): void {
                $table->foreign('business_id', 'employee_performance_business_fk')
                    ->references('id')->on('business')
                    ->onDelete('cascade');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('employee_performance', function (Blueprint $table): void {
                $table->foreign('user_id', 'employee_performance_user_fk')
                    ->references('id')->on('users')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_performance');
    }
};
