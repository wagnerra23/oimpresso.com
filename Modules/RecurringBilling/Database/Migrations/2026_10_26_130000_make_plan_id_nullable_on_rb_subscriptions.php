<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UC-RBSUB-05 ([W] 2026-10-19, remédio "a") — assinatura com valor negociado não tem plano.
 *
 * O #9606 ensinou o gerador a faturar `plan_id = null` pelo `metadata.valor`, mas a coluna nasceu
 * `NOT NULL` (create_rb_subscriptions, 2026-05-06) e assim estava em produção (medido 2026-10-26:
 * 162 assinaturas, 0 sem plano). O `store()` estourava `1048 Column 'plan_id' cannot be null` e a
 * assinatura negociada nunca existia. A FK para `rb_plans` fica: plano informado continua checado.
 *
 * `down()` só volta a `NOT NULL` se não houver assinatura sem plano — senão recusa, em vez de
 * apagar assinatura de cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rb_subscriptions') || DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('rb_subscriptions', function (Blueprint $t) {
            $t->unsignedBigInteger('plan_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rb_subscriptions') || DB::getDriverName() !== 'mysql') {
            return;
        }

        $semPlano = DB::table('rb_subscriptions')->whereNull('plan_id')->count();
        if ($semPlano > 0) {
            throw new RuntimeException("Não é possível voltar plan_id a NOT NULL: {$semPlano} assinatura(s) sem plano.");
        }

        Schema::table('rb_subscriptions', function (Blueprint $t) {
            $t->unsignedBigInteger('plan_id')->nullable(false)->change();
        });
    }
};
