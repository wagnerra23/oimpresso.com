<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Business;
use App\Currency;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Cria o tenant DEDICADO de TESTE (biz de QA/demo) — 2026-06-08.
 *
 * Contexto: biz=1 (WR2 Sistemas) passou a ter DADOS REAIS. Todo dado de
 * teste/demo deve ir pra um tenant separado — alvo canônico `test_business_id`
 * (99, config/app.php). Este seeder provisiona esse tenant pela MESMA via que
 * o registro real usa (BusinessUtil::createNewBusiness + newBusinessDefaultResources
 * + addLocation), pra nascer com currency/location/permissions/recursos default
 * corretos — sem hand-roll frágil.
 *
 * Idempotente: se já existe um business com id = test_business_id, não faz nada.
 *
 * ⚠️ PK exato = 99: o id é AUTO_INCREMENT — `createNewBusiness` NÃO força id.
 * Se quiser o id literal 99, o ambiente precisa ter a sequence livre OU criar
 * via Superadmin/DBA. Este seeder cria o tenant e REPORTA o id resultante;
 * aponte `TEST_BUSINESS_ID` (env) pra ele se for diferente de 99.
 *
 * Rodar (CT-100 / Hostinger — NUNCA toca tenant real):
 *   php artisan db:seed --class='Database\Seeders\TestBusinessSeeder' --force
 */
class TestBusinessSeeder extends Seeder
{
    public function run(): void
    {
        $targetId = (int) config('app.test_business_id', 99);

        if (Business::find($targetId)) {
            $this->command->info("Business de teste id={$targetId} já existe — nada a fazer (idempotente).");

            return;
        }

        $currencyId = optional(Currency::where('code', 'BRL')->first())->id
            ?? optional(Currency::first())->id;

        if (! $currencyId) {
            $this->command->error('Nenhuma currency cadastrada — rode o seed base antes.');

            return;
        }

        /** @var BusinessUtil $businessUtil */
        $businessUtil = app(BusinessUtil::class);
        /** @var ModuleUtil $moduleUtil */
        $moduleUtil = app(ModuleUtil::class);

        $createdId = null;

        DB::transaction(function () use ($businessUtil, $moduleUtil, $currencyId, &$createdId): void {
            // 1) Owner do tenant de teste (marcado pra ser óbvio que é QA).
            $owner = User::create_user([
                'surname'    => 'Sr/Sra',
                'first_name' => 'Teste',
                'last_name'  => 'QA',
                'username'   => 'teste.qa',
                'email'      => 'teste.qa@oimpresso.local',
                'password'   => bin2hex(random_bytes(12)), // senha aleatória — login real é via superadmin
                'language'   => config('app.locale'),
            ]);

            // 2) Business pela via canônica (mesma do registro real).
            $businessDetails = [
                'name'              => 'TESTE — QA (não usar p/ dados reais)',
                'currency_id'       => $currencyId,
                'start_date'        => Carbon::now()->toDateString(),
                'time_zone'         => 'America/Sao_Paulo',
                'fy_start_month'    => 1,
                'accounting_method' => 'fifo',
                'owner_id'          => $owner->id,
                'enabled_modules'   => ['purchases', 'add_sale', 'pos_sale', 'stock_transfers', 'stock_adjustment', 'expenses'],
            ];

            $business = $businessUtil->createNewBusiness($businessDetails);
            $createdId = (int) $business->id;

            $owner->business_id = $business->id;
            $owner->save();

            // 3) Recursos default + location + permission (igual ao postRegister).
            $businessUtil->newBusinessDefaultResources($business->id, $owner->id);
            $location = $businessUtil->addLocation($business->id, [
                'name'      => 'Matriz Teste',
                'country'   => 'Brasil',
                'state'     => 'SP',
                'city'      => 'São Paulo',
                'zip_code'  => '00000-000',
                'landmark'  => 'QA',
            ]);
            Permission::firstOrCreate(['name' => 'location.' . $location->id]);

            if (config('app.env') !== 'demo') {
                $moduleUtil->getModuleData('after_business_created', ['business' => $business]);
            }
        });

        $this->command->info("Tenant de teste criado: business_id={$createdId} (owner teste.qa@oimpresso.local).");
        if ($createdId !== $targetId) {
            $this->command->warn(
                "Atenção: id criado ({$createdId}) != TEST_BUSINESS_ID ({$targetId}). " .
                "Ajuste a env TEST_BUSINESS_ID={$createdId} pra os seeders/guards apontarem certo."
            );
        }
    }
}
