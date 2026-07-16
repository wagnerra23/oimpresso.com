<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\OficinaAuto\Database\Seeders\OficinaAutoFsmSeeder;
use RuntimeException;

/**
 * Seeder do PIPELINE FSM DA OFICINA pro gate visual (estados `default` e `dark` do oficina-os).
 *
 * POR QUE EXISTE — o buraco que fecha (defeito de MECANISMO, 2026-07-16):
 *   O Quadro de OS (Pages/OficinaAuto/ServiceOrders/Board.tsx) é data-driven pelas etapas do
 *   processo FSM `oficina_mecanica_os` (charter §Goals). Sem esse processo cadastrado no
 *   business, a tela NÃO renderiza o kanban — cai no EmptyProcessState ("Quadro ainda não
 *   configurado"). Ou seja: o processo FSM é PRÉ-CONDIÇÃO do render, não detalhe de dado.
 *
 *   Só que NENHUM seeder do visual-regression.yml semeava Oficina:
 *     - DatabaseSeeder            → Barcodes/Permissions/Currencies/BusinessLegacyOrigin;
 *     - VisregTenantSeeder        → business 1 + admin + role (mono-tenant, zero domínio);
 *     - VisregFinanceiroFlowSeeder/VisregTenantBLeakSeeder/VisregEmptyTenantSeeder → idem;
 *     - a migration 2026_06_10_000000_seed_oficina_mecanica_os_process_existing_businesses
 *       só semeia business que JÁ tem `service_orders`/processo `cacamba_*` → num CI de schema
 *       novo ela é no-op por construção.
 *
 *   O que fazia o kanban aparecer era um VAZAMENTO de estado entre steps: o
 *   ConformanceProbesTest (step "Run Pest Browser tests") chama OficinaAutoFsmSeeder::
 *   runForBusiness() pra si mesmo, e seu afterEach limpa só a OS/veículo — NUNCA o processo
 *   FSM. O processo sobrevivia e os steps seguintes (matriz de estados) herdavam a
 *   pré-condição de carona.
 *
 *   O vazamento é ASSIMÉTRICO entre os dois modos do workflow, e é isso que quebrava:
 *     - modo verify (push/pull_request): "Run Pest Browser tests" RODA → FSM existe →
 *       a tela renderiza o kanban;
 *     - modo update (workflow_dispatch): aquele step é PULADO (`if: github.event_name !=
 *       'workflow_dispatch'`) → FSM não existe → a baseline nascia com "Quadro ainda não
 *       configurado".
 *   Resultado: a baseline gerada pelo update era rejeitada pelo verify (diff 6.7793% >
 *   τ_alto 2%), determinístico e reprodutível (runs 29512533782 e 29514784095, delta idêntico).
 *
 * O QUE ESTE SEEDER MUDA: o gate passa a ESTABELECER a pré-condição explicitamente, nos DOIS
 *   modos, em vez de HERDAR o vazamento de um teste que roda só num deles.
 *
 * ZERO-DELTA no verify POR CONSTRUÇÃO: chama exatamente o MESMO seeder canônico
 *   (OficinaAutoFsmSeeder) que o ConformanceProbesTest já chamava — então o render do modo
 *   verify não muda; só o update passa a bater com ele. (O #4248 corrigiu a FOTO à mão pro
 *   kanban sem corrigir a CAUSA; por isso o drift voltou no primeiro dispatch seguinte.)
 *
 * SÓ biz=1 — NÃO use OficinaAutoFsmSeeder::run() aqui: ele itera TODOS os business e semearia
 *   também o biz=98 (tenant-vazio), trocando o snapshot do estado `empty` do oficina-os de
 *   "Quadro ainda não configurado" pro kanban vazio — mudança de baseline não pedida. O
 *   IsolatedStatesBaselineTest usa biz=98 SÓ pro estado `empty`; `default` e `dark` usam biz=1.
 *
 * IDEMPOTENTE: OficinaAutoFsmSeeder usa firstOrCreate em processo/stage/action/role (browser
 *   tests não usam RefreshDatabase — Pest.php). Re-rodar é no-op.
 *
 * @see Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php (seeder canônico do módulo)
 * @see tests/Browser/CoreScreens/IsolatedStatesBaselineTest.php (linha ~110: empty=biz98, resto=biz1)
 * @see tests/Browser/CoreScreens/ConformanceProbesTest.php (de onde o estado vazava)
 * @see resources/js/Pages/OficinaAuto/ServiceOrders/Board.charter.md (colunas data-driven pelo FSM)
 * @see .github/workflows/visual-regression.yml (step "Seed demo tenant" — roda nos dois modos)
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md (convenção biz — NUNCA biz=4)
 */
class VisregOficinaFsmSeeder extends Seeder
{
    /** Business self canônico (ADR 0101). Os estados `default`/`dark` do gate visual usam este. */
    public const BIZ_SELF = 1;

    public function run(): void
    {
        // Tier 0: o pipeline FSM em prod é semeado por migration/deploy com critério próprio
        // (só business que já usa a Oficina). Este seeder é fixture de gate — nunca em produção.
        if (app()->isProduction()) {
            throw new RuntimeException(static::class . ': seeder de fixture de gate visual NAO roda em producao (APP_ENV=production).');
        }

        // Sem o business 1 não há o que semear (o VisregTenantSeeder roda antes deste no CI).
        if (! DB::table('business')->where('id', self::BIZ_SELF)->exists()) {
            $this->command?->warn('VisregOficinaFsmSeeder: business ' . self::BIZ_SELF . ' ausente — nada a semear.');

            return;
        }

        (new OficinaAutoFsmSeeder())->runForBusiness(self::BIZ_SELF);
    }
}
