<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ações propostas pelo chat da Jana entram no MESMO ledger do HITL (ADR 0440).
 *
 * A ADR fala em "uma tabela nova de propostas"; não foi preciso. `jana_acao_aprovacoes`
 * já é o recibo do que a pessoa viu no instante do OK (`previa` + `contexto`), com
 * `business_id` NOT NULL e FK — a proposta do chat é a mesma coisa com dois estados a
 * mais. Uma segunda tabela abriria um segundo dono do tema.
 *
 * `origem` separa as duas filas: `painel` (as 5 regras do `AcaoHitlService`, que só
 * registram aprovação) e `chat` (proposta → confirmada e executada, ou cancelada).
 * Default `painel` mantém as linhas existentes com o significado que já tinham.
 *
 * Estados do chat: proposta · executada · cancelada · expirada · falhou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jana_acao_aprovacoes', function (Blueprint $table) {
            if (! Schema::hasColumn('jana_acao_aprovacoes', 'origem')) {
                $table->string('origem', 16)->default('painel')->after('acao_key');
            }
            if (! Schema::hasColumn('jana_acao_aprovacoes', 'conversa_id')) {
                $table->unsignedBigInteger('conversa_id')->nullable()->after('origem');
            }
            if (! Schema::hasColumn('jana_acao_aprovacoes', 'expira_em')) {
                $table->timestamp('expira_em')->nullable()->after('aprovada_em');
            }
            if (! Schema::hasColumn('jana_acao_aprovacoes', 'decidida_em')) {
                $table->timestamp('decidida_em')->nullable()->after('expira_em');
            }
            if (! Schema::hasColumn('jana_acao_aprovacoes', 'resultado')) {
                $table->json('resultado')->nullable()->after('decidida_em');
            }
        });

        if (! Schema::hasIndex('jana_acao_aprovacoes', 'jana_acao_aprov_chat_idx')) {
            Schema::table('jana_acao_aprovacoes', function (Blueprint $table) {
                $table->index(['business_id', 'origem', 'conversa_id', 'status'], 'jana_acao_aprov_chat_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('jana_acao_aprovacoes', 'jana_acao_aprov_chat_idx')) {
            Schema::table('jana_acao_aprovacoes', fn (Blueprint $t) => $t->dropIndex('jana_acao_aprov_chat_idx'));
        }
        $colunas = array_values(array_filter(
            ['origem', 'conversa_id', 'expira_em', 'decidida_em', 'resultado'],
            fn ($c) => Schema::hasColumn('jana_acao_aprovacoes', $c),
        ));
        if ($colunas !== []) {
            Schema::table('jana_acao_aprovacoes', fn (Blueprint $t) => $t->dropColumn($colunas));
        }
    }
};
