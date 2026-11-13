<?php

namespace Modules\Associacoes\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pendências de documentação da rede, para o painel (ADR 0451, onda 5 · US-ASSOC-004).
 *
 * Lê as tabelas direto, com `business_id` explícito em toda consulta: é função pura de
 * (empresa, alcance, hoje), não depende da sessão e serve a job e a teste do mesmo jeito.
 * Só devolve id e nome de associação, tipo e dias — nenhum dado pessoal (D5).
 *
 * Regras, por associação que não está desligada:
 *  - `sem_estatuto`        — nenhum estatuto na lista (documento removido não conta, D3);
 *  - `sem_ata_diretoria`   — nenhuma ata da diretoria na lista;
 *  - `certidao_vencida`    — tem certidão na lista e nenhuma está válida hoje (validade vazia
 *                            conta como válida);
 *  - `diretoria_mudou`     — mudança de diretoria avisada (`diretoria_mudou_em`) sem ata da
 *                            diretoria enviada depois dela (D7). `situacao` = `no_prazo` até
 *                            `config('associacoes.prazo_ata_diretoria_dias')` dias, depois
 *                            `atrasada`. Atrasada avisa, não bloqueia.
 *
 * ⚠️ A onda 3 escreve a mesma regra para a tela de associações. Este Service é deliberadamente
 * pequeno para ser unificado com o dela depois do merge (um dono só da regra).
 */
class PendenciasDaRede
{
    public const SEM_ESTATUTO = 'sem_estatuto';

    public const SEM_ATA_DIRETORIA = 'sem_ata_diretoria';

    public const CERTIDAO_VENCIDA = 'certidao_vencida';

    public const DIRETORIA_MUDOU = 'diretoria_mudou';

    /**
     * @param  list<int>|null  $associacaoIds  alcance do usuário; `null` = a rede inteira
     * @return list<array{associacao_id:int, associacao:string, tipo:string, situacao:string, dias:int|null}>
     */
    public function calcular(int $businessId, ?array $associacaoIds, ?CarbonImmutable $hoje = null): array
    {
        if ($associacaoIds === []) {
            return [];
        }
        $hoje = ($hoje ?? CarbonImmutable::today())->startOfDay();
        $prazo = (int) config('associacoes.prazo_ata_diretoria_dias', 30);

        $associacoes = DB::table('assoc_associacoes')
            ->where('business_id', $businessId)
            ->where('situacao', '!=', 'desligada')
            ->when($associacaoIds !== null, fn ($q) => $q->whereIn('id', $associacaoIds))
            ->orderBy('nome')
            ->get(['id', 'nome', 'diretoria_mudou_em']);

        if ($associacoes->isEmpty()) {
            return [];
        }

        $documentos = DB::table('assoc_documentos')
            ->where('business_id', $businessId)
            ->whereNull('removido_em')
            ->whereIn('associacao_id', $associacoes->pluck('id'))
            ->get(['associacao_id', 'tipo', 'validade', 'enviado_em'])
            ->groupBy('associacao_id');

        $pendencias = [];
        foreach ($associacoes as $a) {
            $docs = $documentos->get($a->id, collect());
            $base = ['associacao_id' => (int) $a->id, 'associacao' => (string) $a->nome];

            if (! $docs->contains('tipo', 'estatuto')) {
                $pendencias[] = $base + ['tipo' => self::SEM_ESTATUTO, 'situacao' => 'pendente', 'dias' => null];
            }

            $atas = $docs->where('tipo', 'ata_diretoria');
            if ($atas->isEmpty()) {
                $pendencias[] = $base + ['tipo' => self::SEM_ATA_DIRETORIA, 'situacao' => 'pendente', 'dias' => null];
            }

            $certidoes = $docs->where('tipo', 'certidao');
            $certidaoValida = $certidoes->contains(
                fn ($d) => $d->validade === null || CarbonImmutable::parse($d->validade)->startOfDay()->gte($hoje)
            );
            if ($certidoes->isNotEmpty() && ! $certidaoValida) {
                $pendencias[] = $base + ['tipo' => self::CERTIDAO_VENCIDA, 'situacao' => 'pendente', 'dias' => null];
            }

            if ($a->diretoria_mudou_em !== null) {
                $mudou = CarbonImmutable::parse($a->diretoria_mudou_em)->startOfDay();
                $ataDepois = $atas->contains(
                    fn ($d) => CarbonImmutable::parse($d->enviado_em)->startOfDay()->gte($mudou)
                );
                if (! $ataDepois) {
                    $dias = (int) $mudou->diffInDays($hoje);
                    $pendencias[] = $base + [
                        'tipo' => self::DIRETORIA_MUDOU,
                        'situacao' => $dias > $prazo ? 'atrasada' : 'no_prazo',
                        'dias' => $dias,
                    ];
                }
            }
        }

        return $pendencias;
    }
}
