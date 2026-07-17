<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * clients_feedbacks — abre o canal `web_form` (US-INFRA-002 · ADR 0105 · ADR 0334).
 *
 * POR QUÊ: a tabela nasceu (PR #1711) já citando a ADR 0105 "cliente-como-sinal", mas o
 * ÚNICO caminho de entrada é POST /atendimento/feedback/capture sob `can:whatsapp.access`
 * — ou seja, o sinal só existe quando o WAGNER ouve a Larissa no WhatsApp e clica
 * "Capturar". O [W] é o nervo, manualmente. A ADR 0334 chama isso de atrofia do ÓRGÃO
 * SENSOR: "o aparelho de sentir/rotear sinal do cliente nunca foi instalado".
 *
 * Estas colunas são o que falta pro cliente reportar DIRETO, sem intermediário. O campo
 * `canal` já existe (default 'whatsapp') e é o plug-point: 'web_form' entra ao lado.
 *
 * NÃO cria `mcp_client_signals` (o que o SPEC da US-INFRA-002 pedia à letra): decisão [W]
 * 2026-07-17 — a tabela nova duplicaria dedup-por-signature, relevance_score, workflow de
 * status, link mcp_task_id, dashboard e sync-pro-git que esta já tem e já são Tier 0
 * (o padrão que proibicoes §5 2026-07-09 chama de "duplica régua consolidada").
 *
 * Todas nullable: o canal 'whatsapp' existente não sabe nada disto e segue intacto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients_feedbacks')) {
            return;
        }

        Schema::table('clients_feedbacks', function (Blueprint $table) {
            // Quem reportou. O canal 'whatsapp' resolve a pessoa por contact_id; no form
            // público não há contact — a Larissa digita o nome (ou vem do business).
            if (! Schema::hasColumn('clients_feedbacks', 'reporter_name')) {
                $table->string('reporter_name', 120)->nullable()->after('cliente_slug');
            }

            // Onde ele estava quando doeu. `tela_afetada` é o julgado na triagem; este é
            // o cru, capturado pelo browser — não depende de o cliente saber o nome da tela.
            if (! Schema::hasColumn('clients_feedbacks', 'url_seen')) {
                $table->string('url_seen', 255)->nullable()->after('tela_afetada');
            }

            // "cliente sabe ONDE dói, raramente sabe POR QUÊ" (ADR 0105 §princípio 1).
            // Preenchido pelo APM quando a US-INFRA-003 fechar; hoje o form manda o
            // user-agent + erros de console que já tiver bufferizado.
            if (! Schema::hasColumn('clients_feedbacks', 'browser_console_dump')) {
                $table->text('browser_console_dump')->nullable()->after('url_seen');
            }

            // Reservado pro APM (US-INFRA-003). O form v1 NÃO faz upload: aceitar arquivo
            // em rota pública sem auth é superfície de abuso que não se paga agora.
            if (! Schema::hasColumn('clients_feedbacks', 'screenshot_url')) {
                $table->string('screenshot_url', 255)->nullable()->after('browser_console_dump');
            }

            // O quanto DÓI segundo o cliente (0-4). Distinto de `severity_nng`, que é o
            // julgado por quem triagia e alimenta o relevance_score. Guardamos os dois:
            // o self-reported é dado bruto do cliente e não deve ser sobrescrito na triagem.
            if (! Schema::hasColumn('clients_feedbacks', 'severity_self_reported')) {
                $table->unsignedTinyInteger('severity_self_reported')->nullable()->after('severity_nng');
            }
        });

        // Dashboard/triagem filtram "o que veio direto do cliente" — o corte que separa
        // sinal auto-reportado de sinal transcrito pelo [W]. Nome < 64 chars (MySQL).
        Schema::table('clients_feedbacks', function (Blueprint $table) {
            $table->index(['business_id', 'canal'], 'idx_biz_canal');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('clients_feedbacks')) {
            return;
        }

        Schema::table('clients_feedbacks', function (Blueprint $table) {
            $table->dropIndex('idx_biz_canal');
        });

        Schema::table('clients_feedbacks', function (Blueprint $table) {
            foreach ([
                'reporter_name',
                'url_seen',
                'browser_console_dump',
                'screenshot_url',
                'severity_self_reported',
            ] as $col) {
                if (Schema::hasColumn('clients_feedbacks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
