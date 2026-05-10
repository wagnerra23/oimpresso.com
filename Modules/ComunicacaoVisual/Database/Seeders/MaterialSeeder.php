<?php

namespace Modules\ComunicacaoVisual\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\ComunicacaoVisual\Entities\Material;

/**
 * MaterialSeeder — 5 materiais default para vertical ComunicacaoVisual.
 *
 * Chamado automaticamente pelo InstallController após migrations, garantindo
 * que o demo funcione out-of-the-box (gate 3/3 cartas warming Sprint 1).
 *
 * Idempotente: skip se material com mesmo nome+business_id já existe.
 * Aceita $businessId explícito — não usa session() pra suportar CLI/queue.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * cada business recebe seu próprio catálogo de materiais, sem cruzamento.
 *
 * Preços de mercado 2026 — vertical gráfica/comunicação visual CNAE 1813-0/01.
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-001
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md
 */
class MaterialSeeder extends Seeder
{
    /**
     * 5 materiais defaults para comunicação visual (lona, vinil, ACM, plotter).
     * Preços de custo/venda por m² em BRL — mercado brasileiro 2026.
     */
    private const MATERIAIS_DEFAULT = [
        [
            'nome'            => 'Lona Front 280g',
            'categoria'       => 'lona',
            'unidade'         => 'm2',
            'gramatura_g_m2'  => 280,
            'preco_custo_m2'  => 12.00,
            'preco_venda_m2'  => 35.00,
            'estoque_minimo_m2' => 50,
            'observacoes'     => 'Lona front-light fosca pra fachada/banner externo',
        ],
        [
            'nome'            => 'Lona Back 440g',
            'categoria'       => 'lona',
            'unidade'         => 'm2',
            'gramatura_g_m2'  => 440,
            'preco_custo_m2'  => 18.00,
            'preco_venda_m2'  => 50.00,
            'estoque_minimo_m2' => 30,
            'observacoes'     => 'Lona back-light pra caixa luminosa',
        ],
        [
            'nome'            => 'Vinil Adesivo Brilho Branco',
            'categoria'       => 'vinil_adesivo',
            'unidade'         => 'm2',
            'gramatura_g_m2'  => null,
            'preco_custo_m2'  => 25.00,
            'preco_venda_m2'  => 60.00,
            'estoque_minimo_m2' => 20,
            'observacoes'     => 'Vinil monomérico recorte+impressão',
        ],
        [
            'nome'            => 'ACM 3mm Branco',
            'categoria'       => 'acm',
            'unidade'         => 'm2',
            'gramatura_g_m2'  => null,
            'preco_custo_m2'  => 80.00,
            'preco_venda_m2'  => 180.00,
            'estoque_minimo_m2' => 5,
            'observacoes'     => 'ACM 3mm branco fosco — fachada/letras caixa',
        ],
        [
            'nome'            => 'Vinil Plotter Recorte Branco',
            'categoria'       => 'plotter_vinil',
            'unidade'         => 'm2',
            'gramatura_g_m2'  => null,
            'preco_custo_m2'  => 18.00,
            'preco_venda_m2'  => 45.00,
            'estoque_minimo_m2' => 10,
            'observacoes'     => 'Vinil monomérico pra recorte (oracal 651/equiv)',
        ],
    ];

    /**
     * Roda o seeder para o business indicado.
     *
     * NÃO usa session() — pode ser chamado via CLI, queue ou tests.
     * Idempotente: firstOrCreate por (nome, business_id).
     *
     * @param  int  $businessId  ID do business a receber os materiais defaults.
     * @return void
     */
    public function run(int $businessId): void
    {
        $criados  = 0;
        $skipados = 0;

        foreach (self::MATERIAIS_DEFAULT as $dados) {
            // Isolamento multi-tenant: chave única por (nome, business_id)
            $material = Material::withoutGlobalScopes()
                ->firstOrCreate(
                    [
                        'nome'        => $dados['nome'],
                        'business_id' => $businessId,
                    ],
                    array_merge($dados, [
                        'business_id' => $businessId,
                        'ativo'       => true,
                    ])
                );

            if ($material->wasRecentlyCreated) {
                $criados++;
            } else {
                $skipados++;
            }
        }

        $total = count(self::MATERIAIS_DEFAULT);
        $this->command?->info(
            "MaterialSeeder [biz={$businessId}]: criados {$criados} de {$total} (skipados: {$skipados})"
        );
    }
}
