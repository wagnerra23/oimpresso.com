<?php

declare(strict_types=1);

namespace Modules\Vestuario\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * GradeCurvaService — curva tamanho × cor pra produtos de vestuário
 * (W27 esqueleto, gap G1+G3 CAPTERRA-FICHA Vestuario).
 *
 * Por que existe:
 * - Vestuário tem matriz tamanho × cor que gera N variations: ex 5 tamanhos
 *   × 4 cores = 20 variations + estoque por SKU. Cadastrar uma-a-uma é dor.
 * - Curva clássica BR: PP, P, M, G, GG, XG, XXG (adulto) + 02, 04, 06... (infantil)
 * - Cada curva tem "proporção sugerida" (ex: PP=1 P=2 M=3 G=3 GG=2 XG=1 XXG=1)
 *   pra compra inicial inteligente baseada em histórico.
 *
 * Multi-tenant: curvas são per-business (cada loja tem suas próprias preferências).
 *
 * Sprint atual: ESQUELETO — apresenta API, persistência ainda via VestuarioSettingsResolver
 * em chave `grades.<id>` (não tabela própria). Próxima iteração (US-VEST-NN+1)
 * promove pra tabela `vestuario_grades` quando sinal qualificado de revenda chegar.
 *
 * @see memory/requisitos/Vestuario/CAPTERRA-FICHA-2026-05-13.md §G1 §G3
 * @see memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md
 * @see Modules/Vestuario/Services/VestuarioSettingsResolver.php
 */
class GradeCurvaService
{
    /**
     * Curvas default BR (semente — usuário pode override via settings).
     */
    private const CURVAS_DEFAULT = [
        'adulto_basico' => [
            'nome'  => 'Adulto básico',
            'tamanhos' => ['PP', 'P', 'M', 'G', 'GG'],
            'proporcao' => [1, 2, 3, 3, 2], // soma 11
        ],
        'adulto_extendido' => [
            'nome'  => 'Adulto extendido',
            'tamanhos' => ['PP', 'P', 'M', 'G', 'GG', 'XG', 'XXG'],
            'proporcao' => [1, 2, 3, 3, 2, 1, 1], // soma 13
        ],
        'infantil_idade' => [
            'nome'  => 'Infantil por idade',
            'tamanhos' => ['02', '04', '06', '08', '10', '12', '14'],
            'proporcao' => [1, 2, 2, 2, 2, 1, 1], // soma 11
        ],
        'feminino_numerico' => [
            'nome'  => 'Feminino numérico (calça/saia)',
            'tamanhos' => ['36', '38', '40', '42', '44', '46', '48'],
            'proporcao' => [1, 2, 3, 3, 2, 1, 1], // soma 13
        ],
        'masculino_numerico' => [
            'nome'  => 'Masculino numérico (calça)',
            'tamanhos' => ['38', '40', '42', '44', '46', '48', '50'],
            'proporcao' => [1, 2, 3, 3, 2, 1, 1], // soma 13
        ],
    ];

    public function __construct(
        private readonly VestuarioSettingsResolver $settings,
    ) {
    }

    /**
     * Lista curvas disponíveis pro business (default + overrides salvos em settings).
     *
     * @param  int      $businessId
     * @param  ?int     $categoryId  Filtro opcional por categoria (futuro)
     * @return array<string, array{nome: string, tamanhos: array<string>, proporcao: array<int>}>
     */
    public function listarCurvas(int $businessId, ?int $categoryId = null): array
    {
        $custom = $this->settings->forBusiness($businessId)->get('grades.curvas', []);
        $custom = is_array($custom) ? $custom : [];

        // Merge: defaults + custom (custom sobrescreve com mesmo key)
        $merged = array_merge(self::CURVAS_DEFAULT, $custom);

        // Filtro categoria placeholder (até categoria→curva mapping existir)
        if ($categoryId !== null) {
            // Hoje: retorna tudo. Futuro: filtra via grades.category_map.{$catId}
            Log::debug('vestuario.grade.categoryid_ignored', [
                'business_id' => $businessId,
                'category_id' => $categoryId,
            ]);
        }

        return $merged;
    }

    /**
     * Aplica curva → gera matriz SKU (tamanho × cor × quantidade) pronta pra
     * criar variations no UltimatePOS.
     *
     * Output não persiste — Controller/Job que chama decide criar variations.
     * Service é puro (sem side-effect DB).
     *
     * @param  int               $productId
     * @param  array<string>     $tamanhos     Ex: ['PP', 'P', 'M', 'G', 'GG']
     * @param  array<string>     $cores        Ex: ['Azul', 'Preto', 'Branco']
     * @param  array<int>|int    $quantidades  Array paralelo a $tamanhos OU int único
     * @return array<int, array{tamanho: string, cor: string, quantidade: int, sku: string}>
     */
    public function aplicarCurva(int $productId, array $tamanhos, array $cores, array|int $quantidades): array
    {
        if (empty($tamanhos)) {
            throw new InvalidArgumentException('aplicarCurva: tamanhos vazio');
        }
        if (empty($cores)) {
            throw new InvalidArgumentException('aplicarCurva: cores vazio');
        }

        // Normaliza quantidades: int único = aplica em todos tamanhos
        if (is_int($quantidades)) {
            $quantidades = array_fill(0, count($tamanhos), $quantidades);
        }

        if (count($quantidades) !== count($tamanhos)) {
            throw new InvalidArgumentException(
                'aplicarCurva: quantidades deve ter mesmo tamanho de tamanhos '
                .'('.count($tamanhos).' tamanhos vs '.count($quantidades).' quantidades)'
            );
        }

        $matrix = [];
        foreach ($tamanhos as $idxT => $tam) {
            $qtdTam = (int) $quantidades[$idxT];
            foreach ($cores as $idxC => $cor) {
                $sku = $this->buildSku($productId, $tam, $cor, $idxC);
                $matrix[] = [
                    'tamanho'    => $tam,
                    'cor'        => $cor,
                    'quantidade' => $qtdTam,
                    'sku'        => $sku,
                ];
            }
        }

        Log::info('vestuario.grade.aplicada', [
            'product_id'  => $productId,
            'tamanhos'    => count($tamanhos),
            'cores'       => count($cores),
            'variations'  => count($matrix),
            'qtd_total'   => array_sum(array_column($matrix, 'quantidade')),
        ]);

        return $matrix;
    }

    /**
     * Calcula compra sugerida com base em curva nomeada + total de peças.
     *
     * Ex: curva `adulto_basico` (1,2,3,3,2 / soma 11), total=66 →
     *     PP=6, P=12, M=18, G=18, GG=12 (proporção exata)
     *
     * @return array<string, int>  ['PP' => 6, 'P' => 12, ...]
     */
    public function calcularProporcao(string $curvaId, int $totalPecas, int $businessId): array
    {
        if ($totalPecas <= 0) {
            throw new InvalidArgumentException('calcularProporcao: totalPecas deve ser > 0');
        }

        $curvas = $this->listarCurvas($businessId);

        if (! isset($curvas[$curvaId])) {
            throw new InvalidArgumentException("Curva '{$curvaId}' não encontrada pra business {$businessId}");
        }

        $curva     = $curvas[$curvaId];
        $tamanhos  = $curva['tamanhos'];
        $proporcao = $curva['proporcao'];
        $somaProp  = array_sum($proporcao);

        if ($somaProp === 0) {
            throw new InvalidArgumentException("Curva '{$curvaId}' com proporção zerada");
        }

        $result = [];
        foreach ($tamanhos as $idx => $tam) {
            $qtd = (int) floor(($totalPecas * $proporcao[$idx]) / $somaProp);
            $result[$tam] = $qtd;
        }

        // Distribui resto (totalPecas - soma alocada) começando pelos tamanhos mais populares (maior proporção)
        $alocado = array_sum($result);
        $resto   = $totalPecas - $alocado;
        if ($resto > 0) {
            // Ordena índices por proporção desc
            $ordemIdx = $proporcao;
            arsort($ordemIdx);
            foreach (array_keys($ordemIdx) as $idx) {
                if ($resto <= 0) break;
                $result[$tamanhos[$idx]]++;
                $resto--;
            }
        }

        return $result;
    }

    /**
     * SKU determinístico baseado em product_id + tamanho + cor.
     * Formato: VST-{productId}-{T}-{idxC}
     */
    private function buildSku(int $productId, string $tamanho, string $cor, int $idxCor): string
    {
        $tamSlug = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $tamanho) ?? 'X');
        return sprintf('VST-%d-%s-C%02d', $productId, $tamSlug, $idxCor + 1);
    }
}
