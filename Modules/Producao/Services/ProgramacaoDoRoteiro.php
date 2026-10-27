<?php

namespace Modules\Producao\Services;

use App\Contracts\Producao\ProgramacaoDaOs;
use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Programacao;

/**
 * Copia as etapas de um roteiro para uma OS como programações do PCP (ADR 0436).
 *
 * Saiu do `CriarOsDaVenda::programar` (mesma conta, sem mudança) para servir também a OS da
 * Comunicação Visual, que entra no quadro com o roteiro padrão da empresa (US-COMVIS-003,
 * decisão [W] 2026-10-26). Previsão simples, sem fila (UC-FLX-06 fica fora): cada bloco começa
 * quando o anterior termina; as trilhas de um bloco começam juntas. Etapa opcional fica de fora.
 * As programações são cópia do roteiro no instante (UC-PCROT-03): editar o roteiro depois não muda
 * a OS já criada.
 *
 * Tier 0: o roteiro tem de ser da empresa; lê e grava só no `$businessId` recebido.
 */
final class ProgramacaoDoRoteiro implements ProgramacaoDaOs
{
    public function programar(int $businessId, string $osTipo, int $osId, int $roteiroId, int $autor): int
    {
        $doNegocio = DB::table('pcp_roteiros')->where('business_id', $businessId)->where('id', $roteiroId)->exists();
        if (! $doNegocio) {
            return 0;
        }
        $etapas = DB::table('pcp_roteiro_etapas')->where('business_id', $businessId)->where('roteiro_id', $roteiroId)
            ->whereNull('opcional_por')->orderBy('bloco')->orderBy('trilha')->orderBy('ordem')->get();
        $cursor = Carbon::now();
        $criadas = 0;
        foreach ($etapas->groupBy('bloco') as $bloco) {
            $fimBloco = $cursor->copy();
            foreach ($bloco->groupBy('trilha') as $trilha) {
                $inicio = $cursor->copy();
                foreach ($trilha as $e) {
                    $fim = $inicio->copy()->addMinutes((int) round(((float) $e->horas) * 60));
                    Programacao::withoutGlobalScope(ScopeByBusiness::class)->create([
                        'business_id' => $businessId, 'os_tipo' => $osTipo, 'os_id' => $osId,
                        'posto_id' => $e->posto_id, 'roteiro_etapa_id' => $e->id, 'bloco' => $e->bloco, 'trilha' => $e->trilha,
                        'ordem' => $e->ordem, 'nome' => $e->nome, 'junta' => $e->junta,
                        'inicio_previsto' => $inicio, 'fim_previsto' => $fim, 'status' => 'pendente', 'created_by' => $autor,
                    ]);
                    $inicio = $fim;
                    $criadas++;
                }
                $fimBloco = $fimBloco->max($inicio);
            }
            $cursor = $fimBloco;
        }

        return $criadas;
    }

    public function roteiros(int $businessId): array
    {
        return DB::table('pcp_roteiros')->where('business_id', $businessId)->where('ativo', true)
            ->orderBy('nome')->get(['id', 'nome', 'familia'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'nome' => (string) $r->nome, 'familia' => (string) $r->familia])
            ->values()->all();
    }
}
