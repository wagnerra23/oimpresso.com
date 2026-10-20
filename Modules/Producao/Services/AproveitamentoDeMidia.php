<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use Illuminate\Support\Facades\DB;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * Simulador de aproveitamento de mídia na bobina (UC-FLX-13 · US-PCP-024, referências Holdprint,
 * printIQ e Calcgraf).
 *
 * Junta as peças de várias OS de comunicação visual que usam a mesma mídia e propõe um arranjo na
 * largura útil da bobina, em faixas (prateleiras): as peças vão da mais alta para a mais baixa e cada
 * uma entra na primeira faixa onde ainda cabe na largura; não cabendo em nenhuma, abre outra faixa.
 * Com `permite_girar`, a peça fica deitada (lado maior na largura) quando isso cabe na bobina, que é
 * o que encurta o rolo. A margem vale entre peças da mesma faixa e entre faixas.
 *
 * Números devolvidos: metros lineares do arranjo, área útil (soma das peças), área consumida
 * (largura × metros), % de perda, e os mesmos números para "imprimir uma a uma" (cada peça numa faixa
 * sozinha). As contas são em milímetros inteiros para não acumular erro de ponto flutuante.
 *
 * Simular não grava nada. Aceitar grava o arranjo com o consumo previsto de mídia de cada OS (os
 * metros do arranjo rateados pela área da OS) e gera o romaneio de impressão de cada OS
 * (`RomaneioDeImpressao`, US-PCP-023).
 *
 * Tier 0 (ADR 0093): tudo por `$businessId`; OS ou mídia de outra empresa → NAO_ENCONTRADO.
 */
final class AproveitamentoDeMidia
{
    public function __construct(private readonly RomaneioDeImpressao $romaneios) {}

    /**
     * Cadastra (ou troca) a largura útil, a margem e o giro da mídia.
     *
     * @param  array{largura_util_m: float|string, margem_m?: float|string|null, permite_girar?: bool|null}  $dados
     * @return array<string, mixed>
     */
    public function salvarBobina(int $businessId, int $materialId, array $dados): array
    {
        $this->midia($businessId, $materialId);
        $chave = ['business_id' => $businessId, 'material_id' => $materialId];
        $valores = ['largura_util_m' => $dados['largura_util_m'], 'margem_m' => $dados['margem_m'] ?? 0,
            'permite_girar' => (bool) ($dados['permite_girar'] ?? true), 'updated_at' => now()];
        if (DB::table('pcp_bobinas')->where($chave)->exists()) {
            DB::table('pcp_bobinas')->where($chave)->update($valores);
        } else {
            DB::table('pcp_bobinas')->insert($chave + $valores + ['created_at' => now()]);
        }

        return $this->bobina($businessId, $materialId);
    }

    /**
     * @param  list<int>  $osIds
     * @return array<string, mixed>
     */
    public function simular(int $businessId, int $materialId, array $osIds): array
    {
        $bobina = $this->bobina($businessId, $materialId);
        $pecas = $this->pecas($businessId, $materialId, $osIds);

        $largura = $this->mm($bobina['largura_util_m']);
        $margem = $this->mm($bobina['margem_m']);
        $girar = (bool) $bobina['permite_girar'];

        $orientadas = [];
        foreach ($pecas as $p) {
            [$w, $h, $girada] = $this->orientar($this->mm($p['largura_m']), $this->mm($p['altura_m']), $largura, $girar);
            if ($w > $largura) {
                throw new ApontamentoRecusado(ApontamentoRecusado::MATERIAL_INVALIDO,
                    "A peça \"{$p['descricao']}\" não cabe na largura útil da bobina.", ['orcamento_item_id' => $p['orcamento_item_id']]);
            }
            $orientadas[] = $p + ['w' => $w, 'h' => $h, 'girada' => $girada];
        }
        // Da mais alta para a mais baixa; empate pela mais larga, depois pela ordem de entrada.
        $ordem = array_keys($orientadas);
        usort($ordem, fn ($a, $b) => [$orientadas[$b]['h'], $orientadas[$b]['w'], $a] <=> [$orientadas[$a]['h'], $orientadas[$a]['w'], $b]);

        $faixas = [];
        foreach ($ordem as $i) {
            $p = $orientadas[$i];
            $onde = null;
            foreach ($faixas as $k => $f) {
                if ($f['usado'] + $margem + $p['w'] <= $largura) {
                    $onde = $k;
                    break;
                }
            }
            if ($onde === null) {
                $faixas[] = ['altura' => $p['h'], 'usado' => $p['w'], 'pecas' => [$p]];
            } else {
                $faixas[$onde]['usado'] += $margem + $p['w'];
                $faixas[$onde]['pecas'][] = $p;
            }
        }

        $comprimento = array_sum(array_column($faixas, 'altura')) + $margem * max(0, count($faixas) - 1);
        $umaAUma = array_sum(array_column($orientadas, 'h')) + $margem * max(0, count($orientadas) - 1);
        $areaUtil = 0;
        $porOs = [];
        foreach ($orientadas as $p) {
            $a = $p['w'] * $p['h'];
            $areaUtil += $a;
            $porOs[$p['os_id']] = ($porOs[$p['os_id']] ?? 0) + $a;
        }

        return [
            'material_id' => $materialId,
            'bobina' => $bobina,
            'faixas' => array_map(fn (array $f) => [
                'altura_m' => $this->m($f['altura']), 'largura_usada_m' => $this->m($f['usado']),
                'pecas' => array_map(fn (array $p) => ['os_id' => $p['os_id'], 'orcamento_item_id' => $p['orcamento_item_id'],
                    'descricao' => $p['descricao'], 'largura_m' => $this->m($p['w']), 'altura_m' => $this->m($p['h']), 'girada' => $p['girada']], $f['pecas']),
            ], $faixas),
            'pecas' => count($orientadas),
            'metros_lineares' => $this->m($comprimento),
            'area_util_m2' => $this->m2($areaUtil),
            'area_consumida_m2' => $this->m2($largura * $comprimento),
            'perda_pct' => $this->perda($areaUtil, $largura * $comprimento),
            'uma_a_uma' => ['metros_lineares' => $this->m($umaAUma), 'area_consumida_m2' => $this->m2($largura * $umaAUma),
                'perda_pct' => $this->perda($areaUtil, $largura * $umaAUma)],
            'por_os' => $this->ratear($porOs, $areaUtil, $comprimento),
        ];
    }

    /**
     * Aceita o arranjo: grava os números do dia, o consumo previsto por OS e gera o romaneio de cada OS
     * na etapa de destino informada. O arranjo é recalculado aqui, nunca vem pronto do cliente.
     *
     * @param  array<int, int>  $etapaPorOs  os_id => programacao_id
     * @return array<string, mixed>
     */
    public function aceitar(int $businessId, int $materialId, array $etapaPorOs, int $userId): array
    {
        return DB::transaction(function () use ($businessId, $materialId, $etapaPorOs, $userId): array {
            $sim = $this->simular($businessId, $materialId, array_keys($etapaPorOs));
            $b = $sim['bobina'];
            $arranjoId = (int) DB::table('pcp_arranjos')->insertGetId([
                'business_id' => $businessId, 'material_id' => $materialId, 'largura_util_m' => $b['largura_util_m'],
                'margem_m' => $b['margem_m'], 'permite_girar' => $b['permite_girar'], 'metros_lineares' => $sim['metros_lineares'],
                'area_util_m2' => $sim['area_util_m2'], 'area_consumida_m2' => $sim['area_consumida_m2'], 'perda_pct' => $sim['perda_pct'],
                'metros_uma_a_uma' => $sim['uma_a_uma']['metros_lineares'], 'aceito_por' => $userId, 'aceito_em' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $porOs = [];
            foreach ($sim['por_os'] as $linha) {
                $romaneio = $this->romaneios->gerar($businessId, $linha['os_id'], (int) $etapaPorOs[$linha['os_id']], $userId);
                DB::table('pcp_arranjo_os')->insert(['business_id' => $businessId, 'arranjo_id' => $arranjoId,
                    'os_tipo' => RomaneioDeImpressao::OS_TIPO, 'os_id' => $linha['os_id'], 'area_m2' => $linha['area_m2'],
                    'metros_previstos' => $linha['metros_previstos'], 'romaneio_id' => $romaneio->id, 'created_at' => now(), 'updated_at' => now()]);
                $porOs[] = $linha + ['romaneio_id' => (int) $romaneio->id, 'romaneio' => $romaneio->numero];
            }

            return array_merge(['arranjo_id' => $arranjoId], $sim, ['por_os' => $porOs]);
        });
    }

    /** @return array{material_id: int, nome: string, largura_util_m: string, margem_m: string, permite_girar: bool} */
    private function bobina(int $businessId, int $materialId): array
    {
        $midia = $this->midia($businessId, $materialId);
        $b = DB::table('pcp_bobinas')->where('business_id', $businessId)->where('material_id', $materialId)
            ->first(['largura_util_m', 'margem_m', 'permite_girar'])
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::MATERIAL_INVALIDO, 'Cadastre a largura útil da bobina desta mídia.');

        return ['material_id' => $materialId, 'nome' => (string) $midia->nome, 'largura_util_m' => (string) $b->largura_util_m,
            'margem_m' => (string) $b->margem_m, 'permite_girar' => (bool) $b->permite_girar];
    }

    private function midia(int $businessId, int $materialId): object
    {
        return DB::table('comvis_materiais')->where('business_id', $businessId)->where('id', $materialId)->whereNull('deleted_at')->first(['id', 'nome'])
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'Mídia não encontrada.');
    }

    /**
     * Peças da mídia nas OS pedidas, uma por unidade da quantidade.
     *
     * @param  list<int>  $osIds
     * @return list<array{os_id: int, orcamento_item_id: int, descricao: string, largura_m: string, altura_m: string}>
     */
    private function pecas(int $businessId, int $materialId, array $osIds): array
    {
        $osIds = array_values(array_unique(array_map('intval', $osIds)));
        $os = DB::table('comvis_os')->where('business_id', $businessId)->whereIn('id', $osIds)->whereNull('deleted_at')
            ->pluck('orcamento_id', 'id');
        if ($osIds === [] || $os->count() !== count($osIds)) {
            throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'OS não encontrada.');
        }

        $pecas = [];
        foreach ($osIds as $osId) {
            $orc = $os[$osId];
            $itens = $orc === null ? collect() : DB::table('comvis_orcamento_itens')->where('business_id', $businessId)
                ->where('orcamento_id', (int) $orc)->where('material_id', $materialId)
                ->whereNotNull('largura_m')->whereNotNull('altura_m')->orderBy('ordem')->orderBy('id')
                ->get(['id', 'descricao', 'largura_m', 'altura_m', 'quantidade']);
            foreach ($itens as $item) {
                for ($q = 0; $q < (int) $item->quantidade; $q++) {
                    $pecas[] = ['os_id' => (int) $osId, 'orcamento_item_id' => (int) $item->id, 'descricao' => (string) $item->descricao,
                        'largura_m' => (string) $item->largura_m, 'altura_m' => (string) $item->altura_m];
                }
            }
        }
        if ($pecas === []) {
            throw new ApontamentoRecusado(ApontamentoRecusado::QUANTIDADE_INVALIDA, 'Nenhuma peça dessas OS usa esta mídia.');
        }

        return $pecas;
    }

    /** @return array{0: int, 1: int, 2: bool} [largura, altura, girada] */
    private function orientar(int $w, int $h, int $largura, bool $girar): array
    {
        // Deitada (lado maior na largura) encurta o rolo; só se couber.
        if ($girar && $h > $w && $h <= $largura) {
            return [$h, $w, true];
        }
        if ($girar && $w > $largura && $h <= $largura) {
            return [$h, $w, true];
        }

        return [$w, $h, false];
    }

    /**
     * Rateio dos metros do arranjo pela área de cada OS; a última OS fica com o resto, para a soma
     * bater com os metros do arranjo.
     *
     * @param  array<int, int>  $porOs  os_id => área em mm²
     * @return list<array{os_id: int, area_m2: string, metros_previstos: string}>
     */
    private function ratear(array $porOs, int $areaUtil, int $comprimento): array
    {
        $out = [];
        $restante = $comprimento;
        $ids = array_keys($porOs);
        foreach ($ids as $i => $osId) {
            $mm = $i === count($ids) - 1 ? $restante : intdiv($comprimento * $porOs[$osId] + intdiv($areaUtil, 2), $areaUtil);
            $restante -= $mm;
            $out[] = ['os_id' => $osId, 'area_m2' => $this->m2($porOs[$osId]), 'metros_previstos' => $this->m($mm)];
        }

        return $out;
    }

    private function mm(string|float|int $metros): int
    {
        return (int) round((float) $metros * 1000);
    }

    private function m(int $mm): string
    {
        return number_format($mm / 1000, 3, '.', '');
    }

    private function m2(int $mm2): string
    {
        return number_format($mm2 / 1_000_000, 3, '.', '');
    }

    private function perda(int $util, int $consumida): string
    {
        return $consumida <= 0 ? '0.00' : number_format(100 * ($consumida - $util) / $consumida, 2, '.', '');
    }
}
