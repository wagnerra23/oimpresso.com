<?php

namespace Modules\Financeiro\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\PlanoConta;

/**
 * Seeder de plano de contas brasileiro padrão (Receita Federal/DCASP simplificado).
 * 47 entries hierárquicas. Contas críticas (Caixa, Receita Bruta) marcadas
 * `protegido=true` — bloqueia delete (TECH-0002).
 *
 * Uso:
 *   php artisan db:seed --class=Modules\\Financeiro\\Database\\Seeders\\PlanoContasBrSeeder -- --business=4
 *
 * Ou auto-disparado em evento `BusinessCreated` (R-FIN-009).
 */
class PlanoContasBrSeeder extends Seeder
{
    public function run(?int $businessId = null): void
    {
        $businessId = $businessId ?? (int) ($this->command?->option('business') ?? 0);

        if ($businessId <= 0) {
            $this->command?->error('Informe --business=ID');

            return;
        }

        // Estrutura: [codigo, nome, tipo, nivel, parent_codigo, natureza, aceita, protegido]
        $contas = [
            // ATIVO
            ['1',         'ATIVO',                          'ativo',     1, null,         'debito',  false, true],
            ['1.1',       'ATIVO CIRCULANTE',               'ativo',     2, '1',          'debito',  false, true],
            ['1.1.01',    'DISPONIBILIDADES',               'ativo',     3, '1.1',        'debito',  false, true],
            ['1.1.01.001', 'Caixa',                         'ativo',     4, '1.1.01',     'debito',  true,  true],
            ['1.1.01.002', 'Bancos Conta Movimento',        'ativo',     4, '1.1.01',     'debito',  true,  true],
            ['1.1.01.003', 'Aplicações Financeiras',        'ativo',     4, '1.1.01',     'debito',  true,  false],
            ['1.1.02',    'CRÉDITOS',                       'ativo',     3, '1.1',        'debito',  false, false],
            ['1.1.02.001', 'Clientes',                      'ativo',     4, '1.1.02',     'debito',  true,  true],
            ['1.1.02.002', 'Adiantamentos a Fornecedores',  'ativo',     4, '1.1.02',     'debito',  true,  false],
            ['1.1.03',    'ESTOQUES',                       'ativo',     3, '1.1',        'debito',  false, false],
            ['1.1.03.001', 'Mercadorias',                   'ativo',     4, '1.1.03',     'debito',  true,  false],

            ['1.2',       'ATIVO NÃO CIRCULANTE',           'ativo',     2, '1',          'debito',  false, false],
            ['1.2.01',    'IMOBILIZADO',                    'ativo',     3, '1.2',        'debito',  false, false],
            ['1.2.01.001', 'Móveis e Utensílios',           'ativo',     4, '1.2.01',     'debito',  true,  false],
            ['1.2.01.002', 'Equipamentos de Informática',   'ativo',     4, '1.2.01',     'debito',  true,  false],

            // PASSIVO
            ['2',         'PASSIVO',                        'passivo',   1, null,         'credito', false, true],
            ['2.1',       'PASSIVO CIRCULANTE',             'passivo',   2, '2',          'credito', false, true],
            ['2.1.01',    'OBRIGAÇÕES TRABALHISTAS',        'passivo',   3, '2.1',        'credito', false, false],
            ['2.1.01.001', 'Salários a Pagar',              'passivo',   4, '2.1.01',     'credito', true,  false],
            ['2.1.01.002', 'INSS a Recolher',               'passivo',   4, '2.1.01',     'credito', true,  false],
            ['2.1.01.003', 'FGTS a Recolher',               'passivo',   4, '2.1.01',     'credito', true,  false],
            ['2.1.02',    'OBRIGAÇÕES FISCAIS',             'passivo',   3, '2.1',        'credito', false, false],
            ['2.1.02.001', 'ICMS a Recolher',               'passivo',   4, '2.1.02',     'credito', true,  false],
            ['2.1.02.002', 'PIS/COFINS a Recolher',         'passivo',   4, '2.1.02',     'credito', true,  false],
            ['2.1.02.003', 'Simples Nacional / DAS',        'passivo',   4, '2.1.02',     'credito', true,  false],
            ['2.1.03',    'FORNECEDORES',                   'passivo',   3, '2.1',        'credito', false, true],
            ['2.1.03.001', 'Fornecedores Nacionais',        'passivo',   4, '2.1.03',     'credito', true,  true],
            ['2.1.04',    'EMPRÉSTIMOS',                    'passivo',   3, '2.1',        'credito', false, false],
            ['2.1.04.001', 'Empréstimos Bancários',         'passivo',   4, '2.1.04',     'credito', true,  false],

            // PATRIMÔNIO LÍQUIDO
            ['2.3',       'PATRIMÔNIO LÍQUIDO',             'patrimonio', 2, '2',         'credito', false, false],
            ['2.3.01.001', 'Capital Social',                'patrimonio', 4, '2.3',       'credito', true,  false],
            ['2.3.01.002', 'Lucros Acumulados',             'patrimonio', 4, '2.3',       'credito', true,  false],

            // RECEITA
            ['3',         'RECEITA',                        'receita',   1, null,         'credito', false, true],
            ['3.1',       'RECEITA OPERACIONAL',            'receita',   2, '3',          'credito', false, true],
            ['3.1.01.001', 'Receita Bruta de Vendas',       'receita',   4, '3.1',        'credito', true,  true],
            ['3.1.01.002', 'Receita de Serviços',           'receita',   4, '3.1',        'credito', true,  false],
            ['3.1.02.001', '(-) Devoluções e Cancelamentos', 'receita',  4, '3.1',        'debito',  true,  false],

            // CUSTO
            ['4',         'CUSTO',                          'custo',     1, null,         'debito',  false, true],
            ['4.1.01.001', 'CMV — Custo Mercadoria Vendida', 'custo',    4, '4',          'debito',  true,  false],

            // DESPESA
            ['5',         'DESPESA',                        'despesa',   1, null,         'debito',  false, true],
            ['5.1',       'DESPESAS OPERACIONAIS',          'despesa',   2, '5',          'debito',  false, false],
            ['5.1.01.001', 'Aluguel',                       'despesa',   4, '5.1',        'debito',  true,  false],
            ['5.1.01.002', 'Energia Elétrica',              'despesa',   4, '5.1',        'debito',  true,  false],
            ['5.1.01.003','Água e Saneamento',              'despesa',   4, '5.1',        'debito',  true,  false],
            ['5.1.01.004', 'Internet e Telefone',           'despesa',   4, '5.1',        'debito',  true,  false],
            ['5.1.01.005', 'Marketing e Publicidade',       'despesa',   4, '5.1',        'debito',  true,  false],
            ['5.1.01.006', 'Material de Escritório',        'despesa',   4, '5.1',        'debito',  true,  false],
            ['5.1.02.001', 'Despesas Bancárias',            'despesa',   4, '5.1',        'debito',  true,  false],
            ['5.1.02.002', 'Tarifas e Taxas',               'despesa',   4, '5.1',        'debito',  true,  false],
        ];

        DB::transaction(function () use ($businessId, $contas) {
            $idsByCodigo = [];

            foreach ($contas as [$codigo, $nome, $tipo, $nivel, $parentCodigo, $natureza, $aceita, $protegido]) {
                $parentId = $parentCodigo ? ($idsByCodigo[$parentCodigo] ?? null) : null;

                $row = PlanoConta::withoutGlobalScope(\Modules\Financeiro\Models\Concerns\BusinessScopeImpl::class)
                    ->updateOrCreate(
                        ['business_id' => $businessId, 'codigo' => $codigo],
                        [
                            'nome' => $nome,
                            'tipo' => $tipo,
                            'nivel' => $nivel,
                            'parent_id' => $parentId,
                            'natureza' => $natureza,
                            'aceita_lancamento' => $aceita,
                            'protegido' => $protegido,
                            'ativo' => true,
                        ]
                    );

                $idsByCodigo[$codigo] = $row->id;
            }
        });

        $this->command?->info('Plano de contas BR seedado: ' . count($contas) . ' entries para business_id=' . $businessId);
    }
}
