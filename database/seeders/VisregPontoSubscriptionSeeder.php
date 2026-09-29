<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Assinatura com `ponto_module` pro tenant do E2E (biz=1 do VisregTenantSeeder).
 *
 * POR QUE EXISTE: as abas do Ponto (`PontoSubNav`, dentro do `PontoAreaHeader`) só desenham
 * quando o `shell.menu` traz o item Ponto com ghosts. O `Modules/Ponto/.../DataController::
 * modifyAdminMenu()` só injeta esse item se o business tiver `ponto_module` na assinatura
 * ativa (Camada 1 — `ModuleUtil::hasThePermissionInSubscription(..., 'superadmin_package')`)
 * ou se o usuário for superadmin. O `visreg_admin` não é nenhum dos dois, então no CI o
 * header saía sem abas e os specs `e2e/ponto-{dashboard,espelho}.spec.ts` tiveram de tirar
 * a asserção da aba ativa (#8153). Este seeder devolve a cobertura pelo caminho canônico:
 * um pacote + uma subscription aprovada com `package_details.ponto_module = 1`.
 * Nada de `if ($business_id === N)` em código de produto — só dado de fixture.
 *
 * POR QUE SEPARADO DO VisregTenantSeeder (raio): aquele seeder é compartilhado por três
 * lanes (visual-regression, e2e-gate, design-smoke-ci), e o docblock dele já registra que
 * ligar módulo no tenant muda o `shell.menu` — logo a sidebar de TODAS as baselines de
 * pixel. Aqui a assinatura entra só onde é chamada: hoje, apenas o e2e-gate, que não tem
 * asserção de screenshot. Chamar isto numa lane com baseline de pixel é decisão [W]
 * (ADR 0411), com rebaseline tela a tela.
 *
 * EFEITO COLATERAL DECLARADO: com uma assinatura ativa, `ModuleUtil::isQuotaAvailable()`
 * deixa de devolver false (sem assinatura) e passa a devolver true (contagens ausentes =
 * ilimitado). Os demais módulos seguem sem pacote: só `ponto_module` está no package_details.
 *
 * SEGUNDO PORTÃO (medido no run 36573561266, 2026-09-29): a assinatura sozinha NÃO basta.
 * O `AdminSidebarMenu` chama `ModuleUtil::getModuleData('modifyAdminMenu')`, que só visita o
 * DataController de módulo INSTALADO — `isModuleInstalled()` exige `system.ponto_version`, a
 * chave que o `BaseModuleInstallController` grava no install. O seed do CI não grava nenhuma,
 * então o `modifyAdminMenu` do Ponto nunca rodava e as abas seguiam ausentes com a assinatura
 * já no banco. Por isso este seeder também registra a instalação, pelo mesmo par chave/valor
 * que o install do módulo usa. Com o módulo instalado, os outros hooks do DataController do
 * Ponto (permissões, pacote) também passam a rodar nesta lane.
 *
 * IDEMPOTENTE pelo nome do pacote e pela chave `ponto_version`. Nunca roda em produção (mesma
 * trava do VisregTenantSeeder).
 */
class VisregPontoSubscriptionSeeder extends Seeder
{
    private const PACOTE = 'E2E Ponto (fixture CI)';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(static::class . ': seeder de fixture NAO roda em producao (APP_ENV=production).');
        }

        if (! DB::table('business')->where('id', 1)->exists()) {
            throw new RuntimeException(static::class . ': biz=1 ausente — rode o VisregTenantSeeder antes.');
        }

        // Instalação do módulo: mesmo registro que o BaseModuleInstallController grava.
        if (! DB::table('system')->where('key', 'ponto_version')->exists()) {
            DB::table('system')->insert([
                'key' => 'ponto_version',
                'value' => (string) config('pontowr2.module_version', '0.1'),
            ]);
        }

        $packageId = DB::table('packages')->where('name', self::PACOTE)->value('id');
        if (! $packageId) {
            $packageId = DB::table('packages')->insertGetId([
                'name' => self::PACOTE,
                'description' => 'Pacote de fixture do e2e-gate: habilita o modulo Ponto no tenant de teste.',
                'location_count' => 0,
                'user_count' => 0,
                'product_count' => 0,
                'invoice_count' => 0,
                'interval' => 'years',
                'interval_count' => 10,
                'trial_days' => 0,
                'price' => 0,
                'custom_permissions' => json_encode(['ponto_module' => '1']),
                'created_by' => 1,
                'is_active' => 1,
                'is_private' => 1,
            ]);
        }

        $jaTem = DB::table('subscriptions')
            ->where('business_id', 1)
            ->where('package_id', $packageId)
            ->whereNull('deleted_at')
            ->exists();

        if ($jaTem) {
            return;
        }

        DB::table('subscriptions')->insert([
            'business_id' => 1,
            'package_id' => $packageId,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYears(10)->toDateString(),
            'package_price' => 0,
            'package_details' => json_encode([
                'location_count' => 0,
                'user_count' => 0,
                'product_count' => 0,
                'invoice_count' => 0,
                'name' => self::PACOTE,
                'ponto_module' => '1',
            ]),
            'created_id' => 1,
            'paid_via' => 'fixture',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
