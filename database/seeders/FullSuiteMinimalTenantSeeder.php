<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed mínimo multi-tenant de teste — restaura o tenant canônico biz=1 (+ biz=2 Tier 0)
 * que o `migrate:fresh` de um teste RefreshDatabase apaga no meio da full-suite (ADR 0101).
 *
 * ESPELHA FIELMENTE o seed inline do write-side (scripts/tests/ct100-fullsuite.sh, passo
 * 5 — currencies + permissions + biz=1/biz=2 + conta/location/contact). É a fonte reusável
 * do "self-healing seed" do beforeEach global (tests/Pest.php): quando um RefreshDatabase
 * dá migrate:fresh e some com o biz=1, os testes que dependem do seed persistente (hardcoded
 * business_id=1, ex FsmTransitionTest) quebram com FK `Cannot add or update a child row`
 * (fk_vehicles_business, roles_business_id_foreign, users_business_id_foreign…). Este seeder,
 * chamado idempotentemente, recompõe o pai e mata a cascata.
 *
 * TODO(dedup): o write-side ct100-fullsuite.sh:131-167 ainda tem a cópia inline; unificar
 * pra chamar este seeder é follow-up (mexe em infra do floor — P01/P03, não P04).
 *
 * @see scripts/tests/ct100-fullsuite.sh (passo 5 — seed idêntico ao canon CI)
 * @see .github/actions/pest-mysql-setup/action.yml (biz=1 fixture + biz=2 Tier 0)
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/requisitos/_Governanca/roadmap/P04-burn-down-ate-nightly-verde.md (root-cause: cascata de isolamento)
 */
class FullSuiteMinimalTenantSeeder extends Seeder
{
    public function run(): void
    {
        // pré-requisitos do FK (currencies) + RBAC (permissions) — best-effort, como o
        // write-side (`|| true`): se um seeder próprio já rodou ou falha parcial, não aborta.
        foreach ([CurrenciesTableSeeder::class, PermissionsTableSeeder::class] as $seeder) {
            try {
                $this->call($seeder);
            } catch (\Throwable $e) {
                // best-effort — mesma semântica do `|| true` no ct100-fullsuite.sh
            }
        }

        $curId = optional(DB::table('currencies')->first())->id ?? 1;

        if (! DB::table('business')->where('id', 1)->exists()) {
            $uid = DB::table('users')->insertGetId([
                'first_name' => 'CI', 'username' => 'ci_admin', 'password' => bcrypt('ci'),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $bid = DB::table('business')->insertGetId([
                'name' => 'CI Biz', 'currency_id' => $curId, 'owner_id' => $uid,
                'stop_selling_before' => 0, 'weighing_scale_setting' => '', 'certificado' => '',
                'officeimpresso_numerodemaquinas' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('users')->where('id', $uid)->update(['business_id' => $bid]);
        }

        if (! DB::table('business')->where('id', 2)->exists()) {
            $uid2 = DB::table('users')->insertGetId([
                'first_name' => 'CI Biz2', 'username' => 'ci_admin_b2', 'password' => bcrypt('ci'),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('business')->insert([
                'id' => 2, 'name' => 'CI Biz 2', 'currency_id' => $curId, 'owner_id' => $uid2,
                'stop_selling_before' => 0, 'weighing_scale_setting' => '', 'certificado' => '',
                'officeimpresso_numerodemaquinas' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('users')->where('id', $uid2)->update(['business_id' => 2]);
        }

        $bizId = optional(DB::table('business')->first())->id;
        if (! $bizId) {
            return;
        }

        if (! \Modules\Financeiro\Models\ContaBancaria::query()->where('business_id', $bizId)->exists()) {
            \Modules\Financeiro\Models\ContaBancaria::create([
                'business_id' => $bizId, 'account_id' => 999001, 'agencia' => '0001', 'carteira' => '0',
                'beneficiario_documento' => '00000000000000', 'beneficiario_razao_social' => 'CI Test', 'saldo_cached' => 0,
            ]);
        }

        if (! DB::table('business_locations')->where('business_id', $bizId)->exists()) {
            DB::table('business_locations')->insert([
                'business_id' => $bizId, 'name' => 'Matriz CI', 'country' => 'BR',
                'state' => 'SP', 'city' => 'SP', 'zip_code' => '0', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (! DB::table('contacts')->where('business_id', $bizId)->where('type', '!=', 'lead')->exists()) {
            DB::table('contacts')->insert([
                'business_id' => $bizId, 'type' => 'customer', 'name' => 'Cliente CI',
                'contact_id' => 'CO0001', 'created_by' => 1, 'is_default' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
