<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dado mínimo e determinístico para os fluxos visuais de Financeiro.
 *
 * Ele é semeado pelo workflow antes do servidor Browser iniciar; por isso é
 * visível tanto ao processo que executa o Pest quanto ao que renderiza Inertia.
 */
class VisregFinanceiroFlowSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('fin_titulos')->updateOrInsert(
            ['business_id' => 1, 'origem' => 'manual', 'origem_id' => 987654, 'parcela_numero' => 1],
            [
                'numero' => 'VISREG-FIN-001',
                'tipo' => 'receber',
                'status' => 'aberto',
                'cliente_descricao' => 'Cliente de prova visual',
                'valor_total' => 1500.00,
                'valor_aberto' => 1500.00,
                'moeda' => 'BRL',
                'emissao' => '2026-06-11',
                'vencimento' => '2026-06-11',
                'competencia_mes' => '2026-06',
                'parcela_total' => 1,
                'created_by' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
