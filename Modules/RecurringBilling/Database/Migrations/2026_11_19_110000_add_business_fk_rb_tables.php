<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria RecurringBilling d5 (2026-11-19): 8 tabelas do módulo têm `business_id` indexado mas
 * sem FK para `business` (só rb_cartoes_salvos tinha). Regra Tier 0 (ADR 0093 · rule migrations):
 * tabela de negócio tem business_id indexado + FK.
 *
 * ON DELETE CASCADE, igual à FK que já existe em rb_cartoes_salvos.
 *
 * Idempotente: FK que já existe não é recriada. Tabela com linha órfã (business_id que não
 * existe em `business`) NÃO ganha FK — o ALTER falharia e travaria o deploy. Ela é listada no
 * log (warning `rb.fk_business.orfaos`) e na saída do migrate, com a contagem, para tratar à parte.
 * Só MySQL: o SQLite dos testes não altera FK de tabela existente.
 */
return new class extends Migration
{
    /** tabela => nome da FK (≤64 chars) */
    private const TABELAS = [
        'rb_plans'                  => 'rb_plans_business_fk',
        'rb_subscriptions'          => 'rb_subscriptions_business_fk',
        'rb_invoices'               => 'rb_invoices_business_fk',
        'rb_charge_attempts'        => 'rb_charge_attempts_business_fk',
        'rb_boleto_credentials'     => 'rb_boleto_credentials_business_fk',
        'rb_subscription_events'    => 'rb_sub_events_business_fk',
        'rb_subscription_favorites' => 'rb_sub_favorites_business_fk',
        'rb_subscription_notes'     => 'rb_sub_notes_business_fk',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('business')) {
            return;
        }

        foreach (self::TABELAS as $tabela => $fk) {
            if (! Schema::hasTable($tabela) || $this->temFk($tabela, $fk)) {
                continue;
            }

            $orfaos = DB::table($tabela)
                ->whereNotIn('business_id', DB::table('business')->select('id'))
                ->count();
            if ($orfaos > 0) {
                Log::warning('rb.fk_business.orfaos', ['tabela' => $tabela, 'linhas' => $orfaos]);
                fwrite(STDERR, "  ! {$tabela}: {$orfaos} linha(s) com business_id inexistente — FK não criada\n");

                continue;
            }

            Schema::table($tabela, function (Blueprint $t) use ($fk) {
                $t->foreign('business_id', $fk)->references('id')->on('business')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::TABELAS as $tabela => $fk) {
            if (Schema::hasTable($tabela) && $this->temFk($tabela, $fk)) {
                Schema::table($tabela, fn (Blueprint $t) => $t->dropForeign($fk));
            }
        }
    }

    private function temFk(string $tabela, string $fk): bool
    {
        foreach (Schema::getForeignKeys($tabela) as $chave) {
            if (($chave['name'] ?? null) === $fk) {
                return true;
            }
        }

        return false;
    }
};
