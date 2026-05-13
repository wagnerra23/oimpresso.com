<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\ComunicacaoVisual\Entities\Substrato;

/**
 * SubstratoTributacaoCnae1813Seeder — popula catálogo de substratos canon com tributação
 * CNAE 1813-0/01 (Impressão de material para uso publicitário).
 *
 * US-COMVIS-006: Wizard onboarding pré-popula 8 substratos comuns de comunicação visual
 * com NCM + CFOP + CSOSN default — gráfica nova começa com R$0 de configuração contábil
 * (contador valida depois, mas dashboard "Calcular" funciona dia 0).
 *
 * Substratos canon (referência setor BR 2026 — Calcgraf/Mubisys/Zênite):
 *
 *   1. Lona FrontLight 440g   — NCM 3920.20 (chapas polímero PVC)        — banner fachada
 *   2. Lona BlockOut 510g     — NCM 3920.20                              — bloqueia luz, interno
 *   3. Banner 13oz PVC        — NCM 3920.20                              — banner pesado durab
 *   4. Adesivo Vinil 80μm     — NCM 3919.90 (chapas autoadesivas)        — janela/parede
 *   5. Adesivo Refletivo      — NCM 3919.10 (autoadesivos largura ≤20cm) — sinalização viária NBR
 *   6. ACM 3mm Branco         — NCM 7610.90 (estruturas alumínio)        — fachada rígida durab 10+a
 *   7. Acrílico 4mm transp.   — NCM 3920.59 (outros polímeros)           — placa premium retro
 *   8. MDF 9mm                — NCM 4411.13 (painéis fibra >0,8g/cm³)    — letra caixa/totem
 *
 * Tributação default:
 *   - CFOP 5101 — venda produção própria, dentro da UF (lona/vinil/adesivo — gráfica imprime)
 *   - CFOP 5102 — venda mercadoria adquirida de terceiros (ACM/MDF/acrílico — gráfica revende c/ corte/aplicação)
 *   - CSOSN 102 — Simples Nacional sem permissão de crédito ICMS (gráfica média BR é Simples)
 *
 * Preços de referência (mediana setor MS/SC/PR 2026):
 *   precos_custo_m2 → mediana fornecedor industrial
 *   precos_venda_m2 → markup ~3x (Calcgraf benchmark gráfica média 280-380% sobre custo)
 *
 * Idempotente — `firstOrCreate` por (business_id, nome) — rodar múltiplas vezes não duplica.
 *
 * Pode rodar:
 *   - per-business: `(new SubstratoTributacaoCnae1813Seeder())->runForBusiness($bizId)`
 *   - todos businesses ativos: `$seeder->run()` (default Laravel)
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-006 §12.1
 * @see .claude/agents/comunicacao-visual-expert.md §3 §5 (tributação)
 * @see https://concla.ibge.gov.br/busca-online-cnae.html?subclasse=1813001
 */
class SubstratoTributacaoCnae1813Seeder extends Seeder
{
    /**
     * 8 substratos canon — schema:
     *   [nome, categoria, gramatura_g_m2|null, custo_m2, venda_m2, minimo_m2|null, ncm, cfop, csosn, obs]
     *
     * @var list<array{
     *   nome: string, categoria: string, gramatura_g_m2: ?int,
     *   custo_m2: float, venda_m2: float, minimo_m2: ?float,
     *   ncm: string, cfop: string, csosn: string, obs: string
     * }>
     */
    private const SUBSTRATOS_CANON = [
        [
            'nome'          => 'Lona FrontLight 440g',
            'categoria'     => 'lona',
            'gramatura_g_m2'=> 440,
            'custo_m2'      => 11.00,
            'venda_m2'      => 28.00,
            'minimo_m2'     => 0.500,
            'ncm'           => '3920.20',
            'cfop'          => '5101',
            'csosn'         => '102',
            'obs'           => 'Banner fachada externa baixa-média durabilidade (6-18m).',
        ],
        [
            'nome'          => 'Lona BlockOut 510g',
            'categoria'     => 'lona',
            'gramatura_g_m2'=> 510,
            'custo_m2'      => 18.00,
            'venda_m2'      => 42.00,
            'minimo_m2'     => 0.500,
            'ncm'           => '3920.20',
            'cfop'          => '5101',
            'csosn'         => '102',
            'obs'           => 'Banner interno bloqueia luz (fundo escuro evita transparência).',
        ],
        [
            'nome'          => 'Banner 13oz PVC',
            'categoria'     => 'lona',
            'gramatura_g_m2'=> 390,
            'custo_m2'      => 9.00,
            'venda_m2'      => 22.00,
            'minimo_m2'     => 0.500,
            'ncm'           => '3920.20',
            'cfop'          => '5101',
            'csosn'         => '102',
            'obs'           => 'Banner imediato evento/promoção (durabilidade 6-12m externo).',
        ],
        [
            'nome'          => 'Adesivo Vinil 80μm',
            'categoria'     => 'vinil',
            'gramatura_g_m2'=> 110,
            'custo_m2'      => 14.00,
            'venda_m2'      => 38.00,
            'minimo_m2'     => 0.250,
            'ncm'           => '3919.90',
            'cfop'          => '5101',
            'csosn'         => '102',
            'obs'           => 'Adesivo para janela/parede/carro — branco ou transparente.',
        ],
        [
            'nome'          => 'Adesivo Refletivo Grau Engenharia',
            'categoria'     => 'adesivo',
            'gramatura_g_m2'=> 220,
            'custo_m2'      => 78.00,
            'venda_m2'      => 195.00,
            'minimo_m2'     => 0.250,
            'ncm'           => '3919.10',
            'cfop'          => '5101',
            'csosn'         => '102',
            'obs'           => 'Sinalização viária NBR 14644 — engenharia ou diamante (urbano/rodovia).',
        ],
        [
            'nome'          => 'ACM 3mm Branco Brilhante',
            'categoria'     => 'acm',
            'gramatura_g_m2'=> null,
            'custo_m2'      => 78.00,
            'venda_m2'      => 165.00,
            'minimo_m2'     => 0.500,
            'ncm'           => '7610.90',
            'cfop'          => '5102',
            'csosn'         => '102',
            'obs'           => 'Fachada rígida durabilidade 10+ anos. Compra placa 1.22×2.44m.',
        ],
        [
            'nome'          => 'Acrílico 4mm Transparente',
            'categoria'     => 'outro',
            'gramatura_g_m2'=> null,
            'custo_m2'      => 95.00,
            'venda_m2'      => 240.00,
            'minimo_m2'     => 0.250,
            'ncm'           => '3920.59',
            'cfop'          => '5102',
            'csosn'         => '102',
            'obs'           => 'Placa premium / retroiluminação / display vitrine.',
        ],
        [
            'nome'          => 'MDF 9mm Cru',
            'categoria'     => 'mdf',
            'gramatura_g_m2'=> null,
            'custo_m2'      => 32.00,
            'venda_m2'      => 78.00,
            'minimo_m2'     => null,
            'ncm'           => '4411.13',
            'cfop'          => '5102',
            'csosn'         => '102',
            'obs'           => 'Letra caixa rústica / totem rústico / display promocional.',
        ],
    ];

    public function run(): void
    {
        $businessIds = \DB::table('business')->pluck('id');
        foreach ($businessIds as $bizId) {
            $this->runForBusiness((int) $bizId);
        }
    }

    /**
     * Roda seeder pra 1 business específico (idempotente — firstOrCreate por nome).
     *
     * Retorna contagem de substratos novos criados (0 se já existiam todos).
     */
    public function runForBusiness(int $businessId): int
    {
        $criados = 0;

        foreach (self::SUBSTRATOS_CANON as $row) {
            $sub = Substrato::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('nome', $row['nome'])
                ->first();

            if ($sub !== null) {
                continue; // já existe, idempotente
            }

            Substrato::withoutGlobalScopes()->create([
                'business_id'    => $businessId,
                'nome'           => $row['nome'],
                'categoria'      => $row['categoria'],
                'gramatura_g_m2' => $row['gramatura_g_m2'],
                'preco_custo_m2' => $row['custo_m2'],
                'preco_venda_m2' => $row['venda_m2'],
                'minimo_m2'      => $row['minimo_m2'],
                'ncm'            => $row['ncm'],
                'cfop_padrao'    => $row['cfop'],
                'csosn_padrao'   => $row['csosn'],
                'ativo'          => true,
                'observacoes'    => $row['obs'],
            ]);
            $criados++;
        }

        return $criados;
    }
}
