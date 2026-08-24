<?php

namespace Tests;

use Database\Seeders\FullSuiteMinimalTenantSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Tests\Support\WithSeededTenant;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use WithSeededTenant;

    protected function setUp(): void
    {
        parent::setUp();

        // (US-GOV-018 A.2 FULLSUITE_FK_OFF removido — REVERTIDO em US-GOV-020 por net-harmful;
        //  era dead-code: a flag nunca mais é setada. Ledger §E.)

        $this->healCanonicalTenantIfWiped();
    }

    /**
     * Self-healing do tenant canônico biz=1 (SDD P04 — cascata de isolamento).
     *
     * ROOT-CAUSE (junit 20260701, ~57% do floor): no nightly full-suite (MySQL persistente),
     * o 1º teste RefreshDatabase dá `migrate:fresh` e APAGA o seed biz=1; os testes seguintes
     * que dependem do seed persistente (hardcoded business_id=1, ex FsmTransitionTest) quebram
     * com FK "Cannot add or update a child row" (fk_vehicles_business, roles/users_business_id
     * — 454 falhas = 73% do floor). Este guard recompõe o pai e mata a cascata.
     *
     * DISCRIMINADOR SEGURO (não regride os 84 RefreshDatabase): testes RefreshDatabase rodam
     * DENTRO de transação (transactionLevel>0) e gerenciam o próprio DB — NÃO os tocamos; os
     * dependentes do seed persistente rodam sem transação (level 0) — só esses curamos. Guardas:
     * mysql-only (lane sqlite intacta) · idempotente (só quando biz=1 sumiu — roda ~1×/suite) ·
     * try/catch best-effort (nunca derruba um teste; se um teste real precisar do seed e a
     * recomposição falhar, ele quebra sozinho no 1º uso, sem mascaramento). Efeito medido pela
     * queda do floor no próximo nightly CT100 (regra "MEDIR cada passo, nunca previsão-como-fato").
     *
     * @see memory/requisitos/_Governanca/roadmap/P04-burn-down-ate-nightly-verde.md
     * @see database/seeders/FullSuiteMinimalTenantSeeder.php (seed reusável, espelha ct100-fullsuite.sh)
     */
    private function healCanonicalTenantIfWiped(): void
    {
        try {
            $conn = DB::connection();
            if ($conn->getDriverName() !== 'mysql') {
                return; // lane sqlite não sofre a cascata do migrate:fresh persistente
            }
            if ($conn->transactionLevel() > 0) {
                return; // teste RefreshDatabase — gerencia o próprio DB, não tocar
            }
            // O predicado olha os DOIS tenants que o write-side semeia (biz=1 e o canonico
            // biz=98 · ADR 0358), nao so o 1: se um teste RefreshDatabase recompoe biz=1 mas
            // nao biz=98, checar so o 1 devolve "seed intacto" com 98 ainda ausente — e todo
            // teste que usa o tenant canonico quebra em FK. Foi o que aconteceu entre 07-28
            // (quando o write-side adotou o 98) e 08-24. `whereIn(...)->count() === 2` e
            // idempotente e continua rodando ~1x por suite.
            if (DB::table('business')->whereIn('id', [1, 98])->count() === 2) {
                return; // ambos os tenants semeados presentes — nada a curar
            }
            (new FullSuiteMinimalTenantSeeder())->run();
        } catch (\Throwable $e) {
            // best-effort — teste sem DB booted / recomposição parcial: nunca mascara nem crasha
        }
    }
}
