<?php

namespace Modules\Ponto\Services;

use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Intercorrencia;

/**
 * Contagens das abas do Ponto (W9 · ADR 0418) — os números que o protótipo põe nas abas.
 *
 * Âncora: `prototipo-ui/cowork/Wagner/ponto-page.jsx` §PontoPage (a montagem de `abas`):
 *   · aprovacoes      → intercorrências PENDENTE
 *   · intercorrencias → total de intercorrências
 *   · conformidade    → apontamentos da competência corrente
 *   · colaboradores   → total de colaboradores
 * As chaves são as KEYS dos ghosts do `DataController` do Ponto — é por elas que a aba acha
 * o seu número.
 *
 * Conformidade devolve NULL quando a competência não tem apuração: a própria tela mostra "—"
 * nesse caso ("0 violações" sem apuração seria número inventado — charter do protótipo,
 * UC-CONF-09). Aba com 0 afirmaria competência limpa; aba sem número não afirma nada. E o
 * total é o MESMO que a tela soma (`Conformidade.tsx`: soma de `total` das verificações
 * medidas) — derivado do `ConformidadeService`, não recalculado aqui.
 *
 * Tier 0 (ADR 0093): toda contagem filtra `business_id` explicitamente.
 */
class AbasContadoresService
{
    public function __construct(private ConformidadeService $conformidade)
    {
    }

    /**
     * @param  string|null  $mes  competência da Conformidade (Y-m); default = a corrente, como a tela.
     * @return array{aprovacoes:int, intercorrencias:int, conformidade:int|null, colaboradores:int}
     */
    public function contar(int $businessId, ?string $mes = null): array
    {
        return [
            'aprovacoes'      => Intercorrencia::where('business_id', $businessId)
                ->where('estado', Intercorrencia::ESTADO_PENDENTE)
                ->count(),
            'intercorrencias' => Intercorrencia::where('business_id', $businessId)->count(),
            'conformidade'    => $this->apontamentos($businessId, $mes ?? now()->format('Y-m')),
            'colaboradores'   => Colaborador::where('business_id', $businessId)->count(),
        ];
    }

    /** Mesmo total do cabeçalho da tela de Conformidade; null sem apuração. */
    private function apontamentos(int $businessId, string $mes): ?int
    {
        $painel = $this->conformidade->competencia($businessId, $mes);
        if ($painel['cobertura']['estado'] !== ConformidadeService::ESTADO_APURADO) {
            return null;
        }

        return (int) collect($painel['verificacoes'])
            ->where('medido', true)
            ->sum(fn (array $v) => (int) ($v['total'] ?? 0));
    }
}
