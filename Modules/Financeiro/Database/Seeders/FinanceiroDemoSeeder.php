<?php

namespace Modules\Financeiro\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Financeiro\Models\Categoria;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;

/**
 * FinanceiroDemoSeeder
 *
 * Popula `fin_titulos` com 29 títulos demo (17 receber + 12 pagar) pra biz alvo,
 * baseado no mock data canônico em `prototipo-ui/financeiro-data.jsx` (ROTA LIVRE-style
 * comunicação visual, Maio 2026). Distribuição:
 *   - 60% receber / 40% pagar
 *   - 50% liquidado / 50% aberto
 *   - 10-15% atrasado (vencimento < TODAY)
 *
 * Tier 0 multi-tenant: business_id seteado EXPLICITAMENTE em cada insert.
 * Sem session() (CLI). Sem withoutGlobalScope necessário porque setamos explícito.
 *
 * Idempotente: limpa titulos com `observacoes LIKE 'SEEDER_DEMO%'` do business
 * antes de inserir. Não toca em dados reais.
 *
 * Wagner 2026-05-20: pediu popular biz=1 (WR2 Sistemas) pra ter visual de teste.
 * Override via env: BIZ_ID (default 1).
 *
 * Uso:
 *   php artisan db:seed --class="Modules\Financeiro\Database\Seeders\FinanceiroDemoSeeder" --force
 *   BIZ_ID=4 php artisan db:seed --class="Modules\Financeiro\Database\Seeders\FinanceiroDemoSeeder" --force
 */
class FinanceiroDemoSeeder extends Seeder
{
    public const TAG = 'SEEDER_DEMO'; // marker pra idempotência

    public function run(): void
    {
        $businessId = (int) (env('BIZ_ID') ?? 1);
        $createdBy = $this->resolveCreatedBy($businessId);

        if (! $createdBy) {
            $this->command->error("Nenhum user encontrado pra business_id={$businessId}. Abort.");
            return;
        }

        // TODAY referência do mock (2026-05-09) — vou usar Carbon::now() na vida real
        // pra ficar relativo. Datas dos titulos relativas a now() (não 2026-05-09 fixo).
        $TODAY = Carbon::now()->startOfDay();

        DB::transaction(function () use ($businessId, $createdBy, $TODAY) {
            $this->command->info("Limpando titulos demo anteriores (biz={$businessId})...");
            // Limpa só os com tag SEEDER_DEMO. Soft-delete forçado via DB:: pra ignorar override do Model::delete().
            DB::table('fin_titulos')
                ->where('business_id', $businessId)
                ->where('observacoes', 'LIKE', self::TAG . '%')
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);

            $categorias = $this->seedCategorias($businessId);

            $contaBancariaId = $this->resolveContaBancaria($businessId);

            $rows = $this->mockRows($TODAY);

            $this->command->info("Inserindo " . count($rows) . " titulos demo em biz={$businessId}...");

            $contadorNumero = (int) (DB::table('fin_titulos')
                ->where('business_id', $businessId)
                ->whereNull('deleted_at')
                ->max(DB::raw('CAST(numero AS UNSIGNED)')) ?? 0);

            foreach ($rows as $row) {
                $contadorNumero++;
                $categoriaId = $categorias[$row['category']] ?? null;
                $paidAt = $row['paid_at'] ?? null;
                $isQuitado = $paidAt !== null;

                $valorTotal = $row['amount'];
                $valorAberto = $isQuitado ? 0 : $valorTotal;
                $status = $isQuitado ? 'quitado' : 'aberto';

                Titulo::create([
                    'business_id'      => $businessId,
                    'numero'           => str_pad((string) $contadorNumero, 6, '0', STR_PAD_LEFT),
                    'tipo'             => $row['kind'] === 'receivable' ? 'receber' : 'pagar',
                    'status'           => $status,
                    'cliente_id'       => null,
                    'cliente_descricao' => $row['party'],
                    'valor_total'      => $valorTotal,
                    'valor_aberto'     => $valorAberto,
                    'moeda'            => 'BRL',
                    'emissao'          => $row['due']->copy()->subDays(15)->format('Y-m-d'),
                    'vencimento'       => $row['due']->format('Y-m-d'),
                    'competencia_mes'  => $row['due']->format('Y-m'),
                    'origem'           => 'manual',
                    'origem_id'        => null,
                    'parcela_numero'   => null,
                    'parcela_total'    => null,
                    'plano_conta_id'   => null,
                    'categoria_id'     => $categoriaId,
                    'observacoes'      => self::TAG . ' :: ' . $row['desc'],
                    'metadata'         => [
                        'demo_id'      => $row['id'],
                        'channel'      => $row['channel'] ?? null,
                        'invoice'      => $row['invoice'] ?? null,
                        'seeder'       => 'FinanceiroDemoSeeder',
                        'seeded_at'    => now()->toIso8601String(),
                    ],
                    'created_by'       => $createdBy,
                ]);
            }

            $this->command->info("Done. " . count($rows) . " titulos criados em biz={$businessId}.");
        });
    }

    private function resolveCreatedBy(int $businessId): ?int
    {
        // Tenta user com business_id explícito; fallback pra qualquer user (superadmin via business_id null).
        return (int) (DB::table('users')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id') ?? DB::table('users')
                ->orderBy('id')
                ->value('id'));
    }

    private function resolveContaBancaria(int $businessId): ?int
    {
        // ContaBancaria primeira ativa do business — usada em baixas (não criamos baixa neste seeder,
        // mas mantém referência futura).
        return (int) (DB::table('fin_contas_bancarias')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id') ?? 0) ?: null;
    }

    /**
     * Cria/garante categorias do mock pra biz. Retorna map [nome => id].
     */
    private function seedCategorias(int $businessId): array
    {
        $nomes = [
            'Banner', 'Adesivo', 'Fachada', 'Placa', 'Gráfica rápida',
            'Insumo', 'Utilidade', 'Aluguel', 'Imposto', 'Folha', 'Serviço',
        ];

        $map = [];
        foreach ($nomes as $nome) {
            $existing = Categoria::where('business_id', $businessId)
                ->where('nome', $nome)
                ->first();
            if ($existing) {
                $map[$nome] = $existing->id;
                continue;
            }
            $cat = Categoria::create([
                'business_id'    => $businessId,
                'nome'           => $nome,
                'cor'            => $this->corPorNome($nome),
                'plano_conta_id' => null,
                'tipo'           => in_array($nome, ['Insumo', 'Utilidade', 'Aluguel', 'Imposto', 'Folha', 'Serviço']) ? 'despesa' : 'receita',
                'ativo'          => true,
            ]);
            $map[$nome] = $cat->id;
        }
        return $map;
    }

    private function corPorNome(string $nome): string
    {
        return match ($nome) {
            'Banner', 'Adesivo', 'Fachada', 'Placa', 'Gráfica rápida' => '#10b981', // verde — receita
            'Insumo', 'Serviço' => '#f59e0b', // âmbar — operação
            'Utilidade', 'Aluguel' => '#3b82f6', // azul — fixos
            'Imposto', 'Folha' => '#ef4444', // vermelho — obrigatórios
            default => '#6b7280',
        };
    }

    /**
     * Mock rows portado de prototipo-ui/financeiro-data.jsx (29 títulos).
     * Datas relativas a $today (não-fixo) pra que demo sempre tenha relevância
     * temporal (atrasados, vencendo hoje, futuros).
     */
    private function mockRows(Carbon $today): array
    {
        // Helper: $today + N days
        $d = fn (int $offset) => $today->copy()->addDays($offset);

        return [
            // ── A RECEBER (17 — 60% do total) ──────────────────────────────────
            ['id' => 'R-2641',  'kind' => 'receivable', 'desc' => 'Banner lona 4x1m promo dia das maes',            'party' => 'Padaria Pao Quente',       'category' => 'Banner',          'amount' => 480.00,  'due' => $d(3),   'paid_at' => null,   'channel' => 'PIX',     'invoice' => 'NF 4112'],
            ['id' => 'R-2641a', 'kind' => 'receivable', 'desc' => 'Banner anuncio venda Pascoa',                    'party' => 'Padaria Pao Quente',       'category' => 'Banner',          'amount' => 320.00,  'due' => $d(-48), 'paid_at' => $d(-47), 'channel' => 'PIX',  'invoice' => 'NF 4080'],
            ['id' => 'R-2641b', 'kind' => 'receivable', 'desc' => 'Etiqueta adesiva 1000un',                        'party' => 'Padaria Pao Quente',       'category' => 'Adesivo',         'amount' => 290.00,  'due' => $d(-25), 'paid_at' => $d(-24), 'channel' => 'PIX',  'invoice' => 'NF 4096'],
            ['id' => 'R-2642',  'kind' => 'receivable', 'desc' => 'Adesivagem frota 12 veiculos',                    'party' => 'Auto Posto Trevo',         'category' => 'Adesivo',         'amount' => 2340.00, 'due' => $d(6),   'paid_at' => null,   'channel' => 'Boleto', 'invoice' => 'NFe 8422'],
            ['id' => 'R-2643',  'kind' => 'receivable', 'desc' => 'Lona fachada 2x6m + instalacao',                  'party' => 'Loja Bella Moda',          'category' => 'Fachada',         'amount' => 1890.00, 'due' => $d(-6),  'paid_at' => null,   'channel' => 'Boleto', 'invoice' => 'NFe 8418'],
            ['id' => 'R-2644',  'kind' => 'receivable', 'desc' => 'Placas de obra 8un + suporte',                    'party' => 'Construtora Vertice',      'category' => 'Placa',           'amount' => 3200.00, 'due' => $d(13),  'paid_at' => null,   'channel' => 'Boleto', 'invoice' => 'NFe 8425'],
            ['id' => 'R-2645',  'kind' => 'receivable', 'desc' => 'Cartao fidelidade laminado 1000un',               'party' => 'Farmacia Saude Total',     'category' => 'Grafica rapida',  'amount' => 720.00,  'due' => $d(-3),  'paid_at' => $d(-3),  'channel' => 'PIX',  'invoice' => 'NFe 8419'],
            ['id' => 'R-2646',  'kind' => 'receivable', 'desc' => 'Cardapio menu plastificado 40un',                 'party' => 'Restaurante Sabor Cia',    'category' => 'Grafica rapida',  'amount' => 340.00,  'due' => $d(9),   'paid_at' => null,   'channel' => 'PIX',     'invoice' => 'NFe 8426'],
            ['id' => 'R-2647',  'kind' => 'receivable', 'desc' => 'Banner 3x1m show local',                          'party' => 'Studio Foco',              'category' => 'Banner',          'amount' => 380.00,  'due' => $d(-5),  'paid_at' => $d(-5),  'channel' => 'PIX',  'invoice' => 'NFe 8417'],
            ['id' => 'R-2648',  'kind' => 'receivable', 'desc' => 'Wind banner 2.5m + base',                         'party' => 'Imobiliaria Horizonte',    'category' => 'Banner',          'amount' => 560.00,  'due' => $d(11),  'paid_at' => null,   'channel' => 'Boleto', 'invoice' => 'NFe 8427'],
            ['id' => 'R-2649',  'kind' => 'receivable', 'desc' => 'Envelopamento veiculo Hilux',                     'party' => 'Transporte Veloz Ltda',    'category' => 'Adesivo',         'amount' => 4200.00, 'due' => $d(-11), 'paid_at' => null,   'channel' => 'Boleto', 'invoice' => 'NFe 8410'],
            ['id' => 'R-2650',  'kind' => 'receivable', 'desc' => 'Lona backdrop 3x2m + ilhos',                      'party' => 'Academia Movimento',       'category' => 'Banner',          'amount' => 290.00,  'due' => $d(1),   'paid_at' => null,   'channel' => 'PIX',     'invoice' => 'NFe 8423'],
            ['id' => 'R-2651',  'kind' => 'receivable', 'desc' => 'Placa ACM fachada 1.5x0.6m',                      'party' => 'Clinica Vida Plena',       'category' => 'Fachada',         'amount' => 880.00,  'due' => $d(-2),  'paid_at' => $d(-2),  'channel' => 'PIX',  'invoice' => 'NFe 8420'],
            ['id' => 'R-2652',  'kind' => 'receivable', 'desc' => 'Faixa aniversario 5x0.7m',                        'party' => 'Maria Aparecida PF',       'category' => 'Banner',          'amount' => 120.00,  'due' => $d(0),   'paid_at' => null,   'channel' => 'PIX',     'invoice' => 'NFe 8424'],
            ['id' => 'R-2653',  'kind' => 'receivable', 'desc' => 'Cartoes de visita 4x4 4000un',                    'party' => 'Imobiliaria Horizonte',    'category' => 'Grafica rapida',  'amount' => 540.00,  'due' => $d(16),  'paid_at' => null,   'channel' => 'Boleto', 'invoice' => 'NFe 8428'],
            ['id' => 'R-2654',  'kind' => 'receivable', 'desc' => 'Rotulo adesivo perolado 2000un',                  'party' => 'Cervejaria Lupulada',      'category' => 'Adesivo',         'amount' => 1640.00, 'due' => $d(-9),  'paid_at' => null,   'channel' => 'Boleto', 'invoice' => 'NFe 8412'],

            // ── A PAGAR (12 — 40% do total) ────────────────────────────────────
            ['id' => 'P-1882',  'kind' => 'payable', 'desc' => 'Papel couche 250g 4 resmas',                         'party' => 'Suprigraf Distribuidora',  'category' => 'Insumo',          'amount' => 2450.00, 'due' => $d(1),   'paid_at' => null,   'channel' => 'Boleto', 'invoice' => 'NF 11402'],
            ['id' => 'P-1883',  'kind' => 'payable', 'desc' => 'Energia eletrica abril',                              'party' => 'Equatorial Energia',       'category' => 'Utilidade',       'amount' => 1180.40, 'due' => $d(5),   'paid_at' => null,   'channel' => 'Debito autom', 'invoice' => 'Fat 9981'],
            ['id' => 'P-1884',  'kind' => 'payable', 'desc' => 'Aluguel galpao maio',                                 'party' => 'Imobiliaria Centro',       'category' => 'Aluguel',         'amount' => 4500.00, 'due' => $d(-7),  'paid_at' => $d(-7),  'channel' => 'Transferencia', 'invoice' => 'Recibo 045'],
            ['id' => 'P-1885',  'kind' => 'payable', 'desc' => 'Internet + telefonia maio',                           'party' => 'Vivo Empresas',            'category' => 'Utilidade',       'amount' => 320.00,  'due' => $d(-6),  'paid_at' => $d(-6),  'channel' => 'Debito autom', 'invoice' => 'Fat 7711'],
            ['id' => 'P-1886',  'kind' => 'payable', 'desc' => 'IPTU 5a parcela',                                     'party' => 'Prefeitura Municipal',     'category' => 'Imposto',         'amount' => 890.00,  'due' => $d(11),  'paid_at' => null,   'channel' => 'Boleto', 'invoice' => 'DAM 0058'],
            ['id' => 'P-1887',  'kind' => 'payable', 'desc' => 'Tinta solvente CMYK plotter',                         'party' => 'Tinta Solvent BR',         'category' => 'Insumo',          'amount' => 650.00,  'due' => $d(7),   'paid_at' => null,   'channel' => 'PIX',     'invoice' => 'NF 03340'],
            ['id' => 'P-1888',  'kind' => 'payable', 'desc' => 'Laminacao BOPP 200m',                                 'party' => 'Alphagraf Acabamento',     'category' => 'Serviço',         'amount' => 1120.00, 'due' => $d(-4),  'paid_at' => $d(-4),  'channel' => 'PIX',  'invoice' => 'NF 02281'],
            ['id' => 'P-1889',  'kind' => 'payable', 'desc' => 'Manutencao plotter Roland',                           'party' => 'TecPlot Assistencia',      'category' => 'Serviço',         'amount' => 780.00,  'due' => $d(2),   'paid_at' => null,   'channel' => 'PIX',     'invoice' => 'OS 1144'],
            ['id' => 'P-1890',  'kind' => 'payable', 'desc' => 'Salario Larissa abril compl',                         'party' => 'Folha Larissa Souza',      'category' => 'Folha',           'amount' => 2800.00, 'due' => $d(-4),  'paid_at' => $d(-4),  'channel' => 'Transferencia', 'invoice' => 'Folha 04/26'],
            ['id' => 'P-1891',  'kind' => 'payable', 'desc' => 'Lona 440g blackout 50m',                              'party' => 'Suprigraf Distribuidora',  'category' => 'Insumo',          'amount' => 1980.00, 'due' => $d(10),  'paid_at' => null,   'channel' => 'Boleto', 'invoice' => 'NF 11418'],
            ['id' => 'P-1892',  'kind' => 'payable', 'desc' => 'Simples Nacional DAS abril',                           'party' => 'Receita Federal',          'category' => 'Imposto',         'amount' => 2110.00, 'due' => $d(-18), 'paid_at' => $d(-18), 'channel' => 'Boleto', 'invoice' => 'DAS 04/26'],
            ['id' => 'P-1893',  'kind' => 'payable', 'desc' => 'Recolhimento INSS abril',                              'party' => 'Receita Federal',          'category' => 'Imposto',         'amount' => 612.00,  'due' => $d(12),  'paid_at' => null,   'channel' => 'Boleto', 'invoice' => 'GPS 04/26'],
        ];
    }
}
