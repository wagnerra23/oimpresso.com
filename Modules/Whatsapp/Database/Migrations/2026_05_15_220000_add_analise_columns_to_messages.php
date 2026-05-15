<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-095 — Análise IA por mensagem (Voz do Cliente).
 *
 * Adiciona colunas em `messages` para guardar resultado da classificação
 * automática Jana (laravel/ai) por mensagem inbound. Wagner request
 * 2026-05-15: "tudo que receber aqui vai ter que ser analisado. vai usar
 * as reclamações do cliente para administrar melhor a empresa."
 *
 * Idempotente — `if (! Schema::hasColumn)` em cada coluna.
 *
 * Tier 0 multi-tenant: não adiciona colunas com FK cross-business.
 * `business_id` já presente na tabela via migration original.
 *
 * @see Modules/Whatsapp/Services/Analise/AnaliseMensagemService.php
 * @see Modules/Jana/Ai/Agents/AnalisarMensagemAgent.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('messages', 'analise_categoria')) {
                $table->string('analise_categoria', 32)->nullable()
                    ->comment('reclamacao|elogio|duvida|pedido|agendamento|spam|outro');
            }
            if (! Schema::hasColumn('messages', 'analise_tema')) {
                $table->string('analise_tema', 32)->nullable()
                    ->comment('preco|qualidade|prazo|atendimento|produto|pagamento|tecnico|outro');
            }
            if (! Schema::hasColumn('messages', 'analise_urgencia')) {
                $table->string('analise_urgencia', 16)->nullable()
                    ->comment('baixa|media|alta|critica');
            }
            if (! Schema::hasColumn('messages', 'analise_resumo')) {
                $table->string('analise_resumo', 280)->nullable()
                    ->comment('Resumo 1 linha do sinal extraído da mensagem');
            }
            if (! Schema::hasColumn('messages', 'analise_at')) {
                $table->timestamp('analise_at')->nullable()
                    ->comment('Quando análise IA foi feita; NULL = pendente');
            }
            if (! Schema::hasColumn('messages', 'analise_model')) {
                $table->string('analise_model', 64)->nullable()
                    ->comment('Modelo usado (ex: gpt-4o-mini, claude-haiku-4-5)');
            }
            if (! Schema::hasColumn('messages', 'analise_tokens_in')) {
                $table->unsignedInteger('analise_tokens_in')->nullable();
            }
            if (! Schema::hasColumn('messages', 'analise_tokens_out')) {
                $table->unsignedInteger('analise_tokens_out')->nullable();
            }
            if (! Schema::hasColumn('messages', 'analise_cost_centavos')) {
                $table->unsignedInteger('analise_cost_centavos')->nullable()
                    ->comment('Custo em centavos BRL da chamada Jana');
            }
        });

        // Índices pra agregação rápida (dashboard voz-cliente). Nome explícito
        // <=64 chars (limit MySQL/MariaDB). Verifica antes pra idempotência.
        Schema::table('messages', function (Blueprint $table): void {
            $indexes = collect(\Illuminate\Support\Facades\DB::select('SHOW INDEX FROM messages'))
                ->pluck('Key_name')
                ->unique()
                ->all();

            if (! in_array('msg_biz_categ_created_idx', $indexes, true)) {
                $table->index(['business_id', 'analise_categoria', 'created_at'], 'msg_biz_categ_created_idx');
            }
            if (! in_array('msg_biz_urg_created_idx', $indexes, true)) {
                $table->index(['business_id', 'analise_urgencia', 'created_at'], 'msg_biz_urg_created_idx');
            }
            if (! in_array('msg_biz_analise_at_idx', $indexes, true)) {
                $table->index(['business_id', 'analise_at'], 'msg_biz_analise_at_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $indexes = collect(\Illuminate\Support\Facades\DB::select('SHOW INDEX FROM messages'))
                ->pluck('Key_name')
                ->unique()
                ->all();

            foreach (['msg_biz_categ_created_idx', 'msg_biz_urg_created_idx', 'msg_biz_analise_at_idx'] as $idx) {
                if (in_array($idx, $indexes, true)) {
                    $table->dropIndex($idx);
                }
            }

            foreach ([
                'analise_categoria', 'analise_tema', 'analise_urgencia',
                'analise_resumo', 'analise_at', 'analise_model',
                'analise_tokens_in', 'analise_tokens_out', 'analise_cost_centavos',
            ] as $col) {
                if (Schema::hasColumn('messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
