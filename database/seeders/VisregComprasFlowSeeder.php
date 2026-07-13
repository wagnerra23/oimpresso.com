<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dado mínimo e determinístico para os fluxos visuais de Compras (L2.5).
 *
 * Espelha o VisregFinanceiroFlowSeeder, mas com uma diferença que evita os 11
 * patches do Financeiro: o cockpit de Compras NÃO filtra a lista por data (os
 * filtros são só q/stage/sort/dir/per_page — ver Modules/Compras/Services/
 * ComprasService::listarComprasInterno). Por isso a compra semeada aparece
 * SEMPRE, independente do relógio do browser (processo separado do Pest, onde
 * Carbon::setTestNow NÃO congela). Logo a `transaction_date` pode ser um literal
 * FIXO ('2026-06-11') e a coluna "Data" renderiza estável — sem drift diário.
 *
 * O único agregado data-dependente é o KPI "Volume do mês" (soma do mês corrente):
 * com a data fixa no passado ele é estavelmente R$ 0,00 fora de junho/2026.
 *
 * IDEMPOTENTE (updateOrInsert por ref_no): re-rodar é no-op estrutural. Semeado
 * pelo próprio ComprasFlowBaselineTest antes do visit() — a inserção via query
 * builder auto-commita (browser tests NÃO usam RefreshDatabase), então já está
 * visível ao processo do browser quando ele faz o GET /compras.
 *
 * @see tests/Browser/CoreScreens/ComprasFlowBaselineTest.php
 * @see tests/Browser/visreg-flows.json (contrato dos fluxos)
 * @see app/Utils/TransactionUtil.php::getListPurchases (join que faz a linha aparecer)
 */
class VisregComprasFlowSeeder extends Seeder
{
    public function run(): void
    {
        // Fornecedor determinístico: leftJoin em getListPurchases mostra o
        // supplier_business_name na linha e o drawer (aba Resumo) usa os dados.
        $supplierId = DB::table('contacts')
            ->where('business_id', 1)
            ->where('contact_id', 'VISREG-SUP-1')
            ->value('id');

        if (! $supplierId) {
            $supplierId = DB::table('contacts')->insertGetId([
                'business_id' => 1,
                'type' => 'supplier',
                'name' => 'Fornecedor Visual',
                'supplier_business_name' => 'Fornecedor Visual LTDA',
                'contact_id' => 'VISREG-SUP-1',
                'tax_number' => '00000000000191',
                'city' => 'Sao Paulo',
                'mobile' => '',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Compra fixa. `location_id => 1` é obrigatório (INNER join com
        // business_locations em getListPurchases). `status => received` +
        // `payment_status => partial` deixam KPI "A pagar"=1 e a coluna "A pagar"
        // com valor, tudo determinístico. Query builder = sem observers/FSM guard.
        DB::table('transactions')->updateOrInsert(
            ['business_id' => 1, 'type' => 'purchase', 'ref_no' => 'VISREG-COM-001'],
            [
                'contact_id' => $supplierId,
                'location_id' => 1,
                'status' => 'received',
                'payment_status' => 'partial',
                'transaction_date' => '2026-06-11 10:00:00',
                'total_before_tax' => 850.00,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'shipping_charges' => 0,
                'final_total' => 850.00,
                'created_by' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
