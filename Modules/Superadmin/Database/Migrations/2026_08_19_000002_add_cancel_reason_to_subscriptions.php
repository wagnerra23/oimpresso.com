<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registra o MOTIVO do cancelamento de assinatura (decisão [W] 2026-08-19).
 *
 * Hoje `SubscriptionLifecycleService::cancel($reason)` RECEBE o motivo e o descarta: ele
 * vira só `reason_len` num atributo de span (telemetria), e o texto se perde. Sem coluna,
 * o gráfico de motivos de churn do F1 não tem de onde sair.
 *
 * Duas colunas, não uma:
 *   - `cancel_reason` — categoria curta, o que o gráfico agrega;
 *   - `cancel_note`   — texto livre, o que a pessoa escreveu. Agregar texto livre é o que
 *                       torna gráfico de motivo inútil na prática.
 *
 * ⚠️ A taxonomia abaixo é PROPOSTA a partir de motivos correntes de SaaS — não foi ditada
 * por [W] e não veio de dado nosso, porque não existe dado: em 2026-08-19 havia ZERO
 * assinaturas em `cancelled`/`expired`/`declined` (nada nunca foi cancelado no sistema).
 * Ampliar a lista custa uma migration; se [W] preferir outra taxonomia, é trocar aqui antes
 * de a primeira linha ser gravada. `outro` + `cancel_note` cobre o que a lista não previr.
 *
 * Nullable por construção: assinatura cancelada sem motivo declarado é caso real, e o F1
 * (regra R10) manda deixá-la FORA do gráfico e dita em texto — não inventar categoria.
 *
 * @see Modules/Superadmin/Services/SubscriptionLifecycleService.php::cancel()
 * @see memory/requisitos/Superadmin/RUNBOOK-dashboard.md §1
 */
return new class extends Migration
{
    private const MOTIVOS = ['preco', 'sem_uso', 'trocou_sistema', 'fechou', 'inadimplencia', 'outro'];

    public function up(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        if (! Schema::hasColumn('subscriptions', 'cancel_reason')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->enum('cancel_reason', self::MOTIVOS)
                    ->nullable()
                    ->after('status');
            });
        }

        if (! Schema::hasColumn('subscriptions', 'cancel_note')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->text('cancel_note')->nullable()->after('cancel_reason');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        // Sem guarda de dado aqui, ao contrário da migration do enum de `status`: estas
        // colunas nascem vazias e o rollback não orfana estado de cobrança — só descarta
        // o motivo, que é anotação. Se um dia virar insumo de relatório fiscal, revisar.
        foreach (['cancel_note', 'cancel_reason'] as $coluna) {
            if (Schema::hasColumn('subscriptions', $coluna)) {
                Schema::table('subscriptions', function (Blueprint $table) use ($coluna) {
                    $table->dropColumn($coluna);
                });
            }
        }
    }
};
