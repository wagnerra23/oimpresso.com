<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Modelos;

use App\Domain\Fsm\Models\ModeloEmpresa;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Regras configuráveis por empresa — aba Fluxos da empresa (ADR 0435).
 *
 * - Ligar/desligar uma regra "ao entrar" do modelo em uso: grava `ligada` na própria regra, em
 *   `sale_process_stages.regras_ao_entrar` do processo que "Usar modelo" montou para a empresa.
 *   A regra é achada pelo índice no catálogo (domínio + posição), como no protótipo
 *   (`OiEtapa.setRegra(modelo, dom, i)`).
 * - Regras das etapas da Compra: grava em `fsm_modelos_empresa.regras_compra` (RegrasCompra).
 *
 * Tier 0 (ADR 0093): sempre o `$businessId` recebido — o controller passa o da sessão.
 * Nunca toca documento. Ligar ou desligar não muda nenhuma execução hoje: as regras do catálogo
 * estão todas planejadas (CatalogoModelos), e as da Compra ainda não são lidas no recebimento.
 */
final class RegrasDaEmpresa
{
    /**
     * `ligada` de cada regra do modelo em uso, por domínio e na ordem do catálogo.
     * Regra sem a chave conta como ligada (padrão do protótipo).
     *
     * @return array<string, list<bool>> domínio => [ligada, …]
     */
    public function ligadas(int $businessId, string $modeloId): array
    {
        $modelo = CatalogoModelos::modelo($modeloId);
        if ($modelo === null) {
            return [];
        }

        $out = [];
        foreach ($modelo['processos'] as $dominio => $def) {
            $porEtapa = $this->etapas($businessId, $modeloId, $dominio);
            $out[$dominio] = array_map(function (array $r) use ($porEtapa) {
                $regra = $this->achar($porEtapa[CatalogoModelos::chaveEtapa($r[0])]->regras_ao_entrar ?? [], $r);

                return $regra === null ? true : (bool) ($regra['ligada'] ?? true);
            }, $def['regras']);
        }

        return $out;
    }

    public function ligar(int $businessId, string $dominio, int $indice, bool $ligada): void
    {
        $modeloId = $this->modeloEmUso($businessId);
        $regra = CatalogoModelos::modelo($modeloId)['processos'][$dominio]['regras'][$indice] ?? null;
        if ($regra === null) {
            throw new InvalidArgumentException("Regra {$indice} de {$dominio} não existe no modelo em uso.");
        }

        DB::transaction(function () use ($businessId, $modeloId, $dominio, $regra, $ligada) {
            $stage = $this->etapas($businessId, $modeloId, $dominio, true)[CatalogoModelos::chaveEtapa($regra[0])] ?? null;
            if ($stage === null) {
                throw new InvalidArgumentException('O processo do modelo não está montado nesta empresa. Use o modelo de novo.');
            }
            $regras = $stage->regras_ao_entrar ?? [];
            $achou = false;
            foreach ($regras as $i => $r) {
                if ($this->mesma($r, $regra)) {
                    $regras[$i]['ligada'] = $ligada;
                    $achou = true;
                    break;
                }
            }
            if (! $achou) {
                throw new InvalidArgumentException('A regra não está na etapa. Use o modelo de novo.');
            }
            $stage->regras_ao_entrar = $regras;
            $stage->save();
        });
    }

    /**
     * Regras da Compra com o que a empresa gravou por cima do padrão.
     *
     * @return list<array<string, mixed>>
     */
    public function compra(int $businessId): array
    {
        return RegrasCompra::daEmpresa($this->linha($businessId)?->regras_compra);
    }

    /** @param array<string, mixed> $params */
    public function mudarCompra(int $businessId, string $id, ?bool $ativo, array $params): void
    {
        $this->modeloEmUso($businessId);

        DB::transaction(function () use ($businessId, $id, $ativo, $params) {
            $linha = ModeloEmpresa::withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $businessId)->lockForUpdate()->firstOrFail();
            $linha->regras_compra = RegrasCompra::mudar($linha->regras_compra, $id, $ativo, $params);
            $linha->save();
        });
    }

    /** Margem mínima do orçamento, em % (UC-FLX-35). Nula = regra desligada. */
    public function margemMinima(int $businessId): ?float
    {
        $v = $this->linha($businessId)?->margem_minima_pct;

        return $v === null ? null : (float) $v;
    }

    /** Grava a margem mínima da empresa; nula desliga a regra. */
    public function mudarMargemMinima(int $businessId, ?float $pct): void
    {
        $this->modeloEmUso($businessId);
        if ($pct !== null && ($pct < 0 || $pct > 100)) {
            throw new InvalidArgumentException('A margem mínima vai de 0% a 100%.');
        }

        DB::transaction(function () use ($businessId, $pct) {
            $linha = ModeloEmpresa::withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $businessId)->lockForUpdate()->firstOrFail();
            $linha->margem_minima_pct = $pct === null ? null : round($pct, 2);
            $linha->save();
        });
    }

    /**
     * Sinal mínimo para a produção liberar a OS, em % do total da venda (UC-FLX-28 · US-PCP-025).
     * Nulo = regra desligada. Devolve o decimal como texto, sem passar por float.
     */
    public function sinalMinimo(int $businessId): ?string
    {
        $v = $this->linha($businessId)?->getRawOriginal('sinal_minimo_pct');

        return $v === null ? null : (string) $v;
    }

    /** Grava o sinal mínimo da empresa; nulo desliga a regra. De 0,01% a 100%, até 2 casas. */
    public function mudarSinalMinimo(int $businessId, ?string $pct): void
    {
        $this->modeloEmUso($businessId);
        if ($pct !== null && (preg_match('/^\d{1,3}(\.\d{1,2})?$/', $pct) !== 1 || (float) $pct <= 0 || (float) $pct > 100)) {
            throw new InvalidArgumentException('O sinal mínimo vai de 0,01% a 100%.');
        }

        DB::transaction(function () use ($businessId, $pct) {
            $linha = ModeloEmpresa::withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $businessId)->lockForUpdate()->firstOrFail();
            $linha->setAttribute('sinal_minimo_pct', $pct);
            $linha->save();
        });
    }

    /** Modelo do catálogo em uso; recusa empresa sem modelo ou no legado. */
    private function modeloEmUso(int $businessId): string
    {
        $modelo = $this->linha($businessId)?->modelo;
        if ($modelo === null || CatalogoModelos::modelo($modelo) === null) {
            throw new InvalidArgumentException('Escolha um modelo antes de configurar as regras.');
        }

        return $modelo;
    }

    private function linha(int $businessId): ?ModeloEmpresa
    {
        return ModeloEmpresa::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)->first();
    }

    /** @return array<string, SaleProcessStage> chave da etapa => etapa */
    private function etapas(int $businessId, string $modeloId, string $dominio, bool $travar = false): array
    {
        $processId = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('key', CatalogoModelos::chaveProcesso($modeloId, $dominio))
            ->value('id');
        if ($processId === null) {
            return [];
        }
        $q = SaleProcessStage::query()->where('process_id', $processId);
        if ($travar) {
            $q->lockForUpdate();
        }

        return $q->get()->keyBy('key')->all();
    }

    /** @param list<array<string, mixed>> $regras @param array $catalogo [etapa, efeito, uc, …] */
    private function achar(array $regras, array $catalogo): ?array
    {
        foreach ($regras as $r) {
            if ($this->mesma($r, $catalogo)) {
                return $r;
            }
        }

        return null;
    }

    /** Mesma regra = mesmo efeito e mesmo UC (AplicarModelo grava UC vazio como ausente). */
    private function mesma(array $gravada, array $catalogo): bool
    {
        return ($gravada['efeito'] ?? null) === $catalogo[1]
            && ($gravada['uc'] ?? '') === $catalogo[2];
    }
}
