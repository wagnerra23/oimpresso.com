<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-INFRA-002 · Voz do Cliente — a tabela onde o sinal passa a existir.
 *
 * ADR 0105 estabelece "cliente como sinal qualificado": o backlog só recebe
 * item se cliente paga + reporta OU métrica detecta drift. Hoje Larissa
 * (ROTA LIVRE biz=4) relata a dor por WhatsApp e o sinal morre em backlog
 * mental — não é rastreável, não é contável, não vira US. Aqui ele vira dado.
 *
 * ── DUAS DIVERGÊNCIAS DELIBERADAS vs a US escrita ────────────────────────────
 * A regra de precedência (memory/proibicoes.md) manda corrigir o perdedor no
 * MESMO PR — a US foi atualizada junto:
 *
 * 1. CANAL — a US previa portal PÚBLICO (`/feedback?biz=X&token=Y`, "sem login,
 *    token por biz expira em 30d"). [W] decidiu (2026-07-28) o oposto: dentro
 *    do sistema, autenticado. Consequência boa: `business_id` volta a vir da
 *    SESSÃO (padrão canônico do global scope), e morrem a expiração de 30d, o
 *    endpoint anônimo e a superfície de spam.
 *
 * 2. NOME — a US dizia `mcp_client_signals`. O prefixo `mcp_` é do MCP server
 *    (governança interna: mcp_audit_log, mcp_tasks). Isto virou módulo de
 *    produto vendável por business, então segue o padrão de prefixo por módulo
 *    (fin_* do Financeiro, cv_* da ComunicacaoVisual) → `voz_sinais`.
 *
 * Tier 0 ({@see ADR 0093}): COM business_id — um sinal é dado de TENANT
 * (diferente de cowork_handoffs / mcp_ingest_heartbeat, que são artefatos do
 * repo e cross-tenant por design). Global scope obrigatório na leitura
 * {@see Modules\VozDoCliente\Entities\Sinal}.
 *
 * Append-only na prática: o status caminha pending → triaged → closed e
 * `texto` NUNCA é reescrito — é a prova do que a pessoa disse. Correção vira
 * sinal novo, não UPDATE do anterior.
 *
 * LGPD: `texto` é livre e PODE conter dado pessoal. Sem expurgo por tempo —
 * decisão [W] 2026-07-27: num ERP não se apaga PII; o controle é por permissão
 * de acesso, não por retenção.
 *
 * MySQL 8: sem índice parcial — o composto (business_id, status) cobre a
 * consulta quente "pendentes deste business".
 *
 * Idempotente (hasTable) + reversível (down dropIfExists).
 *
 * @see memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md
 * @see memory/requisitos/Infra/SPEC.md (US-INFRA-002)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('voz_sinais')) {
            return;
        }

        Schema::create('voz_sinais', function (Blueprint $t) {
            $t->bigIncrements('id');

            $t->unsignedInteger('business_id')
                ->comment('Tier 0 ADR 0093 — dono do sinal; vem da sessao do usuario logado');
            $t->unsignedInteger('user_id')->nullable()
                ->comment('Quem relatou. Nullable: sinal de origem automatica nao tem usuario');
            $t->string('autor_nome', 120)->nullable()
                ->comment('Snapshot do nome no momento do relato — sobrevive a remocao do usuario');

            $t->string('canal', 24)->default('sistema')
                ->comment('sistema|whatsapp|erro_automatico|call|presencial');
            $t->text('texto')
                ->comment('O que a pessoa disse, LITERAL. Nunca reescrever (e a prova).');

            $t->unsignedTinyInteger('severidade')->nullable()
                ->comment('0-4 auto-declarada por quem relata; a triagem pode discordar');
            $t->string('url_vista', 500)->nullable()
                ->comment('Onde doeu — URL da tela no momento do relato (capturada, nao digitada)');
            $t->string('modulo_sugerido', 40)->nullable()
                ->comment('Modulo roteado pelo vocabulario do dicionario de dominio');

            $t->string('status', 16)->default('pending')
                ->comment('pending|triaged|closed');
            $t->string('triado_para_us', 40)->nullable()
                ->comment('US gerada na triagem (ex: US-PROD-031) — o loop fechado');
            $t->unsignedInteger('triado_por')->nullable();

            $t->char('hash_origem', 64)
                ->comment('sha256(business_id|texto) — dedup de reenvio identico');

            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('triado_em')->nullable();

            $t->index(['business_id', 'status'], 'voz_sinais_biz_status_idx');
            $t->index('created_at', 'voz_sinais_created_idx');
            $t->unique(['business_id', 'hash_origem'], 'voz_sinais_biz_hash_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voz_sinais');
    }
};
