<?php

namespace Modules\Financeiro\Services\Conciliacao;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\BankStatementLine;
use Modules\Financeiro\Models\ConciliacaoRegra;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Services\FinanceiroAuditLogger;

/**
 * Conciliação por regras (US-FIN-080 · UC-FCC-19..22).
 *
 * Roda ANTES do casamento por valor e data do `ConciliacaoController::sugerirMatches`, sobre as
 * mesmas linhas pendentes (OFX `status = pendente`, API `status` nulo). Para cada linha, tenta as
 * regras ativas em ordem de prioridade; a primeira que deixa EXATAMENTE UM título aberto de mesmo
 * valor casa a linha. Zero ou dois+ candidatos: a regra não decide e a linha segue para o
 * casamento por valor e data, que é o de sempre.
 *
 * O que casar faz: grava `titulo_id` + `regra_id` na linha e o status (`sugerido` ou
 * `conciliado`, conforme a `acao` da regra). NÃO baixa o título nem muda valor nenhum — é o
 * mesmo vínculo que o botão "Confirmar" da tela grava (Anti-hook do charter: não cria/edita
 * Título).
 *
 * Desligado por padrão: `financeiro.conciliacao.regras_enabled` (FINANCEIRO_CONCILIACAO_REGRAS_ENABLED).
 */
class RegrasConciliacaoService
{
    /** @return array{avaliadas:int, sugeridas:int, conciliadas:int} */
    public function aplicar(int $businessId): array
    {
        $stats = ['avaliadas' => 0, 'sugeridas' => 0, 'conciliadas' => 0];

        if (! config('financeiro.conciliacao.regras_enabled', false)
            || ! Schema::hasTable('fin_conciliacao_regras')) {
            return $stats;
        }

        // SUPERADMIN: o motor também roda sem sessão (comando); o business vem do parâmetro e o filtro é explícito.
        $regras = ConciliacaoRegra::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->where('ativo', true)
            ->orderBy('prioridade')
            ->orderBy('id')
            ->get();

        if ($regras->isEmpty()) {
            return $stats;
        }

        $usados = $this->titulosJaVinculados($businessId);

        foreach ($this->linhasPendentes($businessId) as $linha) {
            $stats['avaliadas']++;

            foreach ($regras as $regra) {
                if (! $regra->atende($linha['descricao'], $linha['valor'], $linha['conta_bancaria_id'])) {
                    continue;
                }
                $candidatos = $this->candidatos($businessId, $regra, $linha, $usados);
                if (count($candidatos) !== 1) {
                    continue;
                }

                $tituloId = $candidatos[0];
                $this->vincular($businessId, $linha, $regra, $tituloId);
                $usados[$tituloId] = true;
                $regra->acao === ConciliacaoRegra::ACAO_CONCILIAR ? $stats['conciliadas']++ : $stats['sugeridas']++;
                break;
            }
        }

        return $stats;
    }

    /**
     * Linhas pendentes das duas origens num shape só, valor COM sinal (mesma regra da tela:
     * OFX já tem sinal; API guarda positivo + tipo, e `D` vira negativo).
     *
     * @return list<array{origem:string,id:int,data:string,descricao:string,valor:float,conta_bancaria_id:?int}>
     */
    private function linhasPendentes(int $businessId): array
    {
        $linhas = [];

        // SUPERADMIN: business do parâmetro, filtro explícito (o motor roda também sem sessão).
        $ofx = BankStatementLine::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->where('status', 'pendente')
            ->toBase()
            ->get(['id', 'data_movimento', 'descricao', 'valor', 'conta_bancaria_id']);
        foreach ($ofx as $l) {
            $linhas[] = [
                'origem' => 'ofx', 'id' => (int) $l->id, 'data' => substr((string) $l->data_movimento, 0, 10),
                'descricao' => (string) $l->descricao, 'valor' => round((float) $l->valor, 2),
                'conta_bancaria_id' => $l->conta_bancaria_id !== null ? (int) $l->conta_bancaria_id : null,
            ];
        }

        if (Schema::hasColumn('fin_extrato_lancamentos', 'regra_id')) {
            $api = DB::table('fin_extrato_lancamentos')
                ->where('business_id', $businessId)
                ->whereNull('status')
                ->get(['id', 'data', 'descricao', 'valor', 'tipo', 'conta_bancaria_id']);
            foreach ($api as $l) {
                $valor = abs((float) $l->valor);
                $linhas[] = [
                    'origem' => 'api', 'id' => (int) $l->id, 'data' => substr((string) $l->data, 0, 10),
                    'descricao' => (string) $l->descricao, 'valor' => round($l->tipo === 'D' ? -$valor : $valor, 2),
                    'conta_bancaria_id' => (int) $l->conta_bancaria_id,
                ];
            }
        }

        return $linhas;
    }

    /**
     * Títulos abertos que a regra aponta para a linha: lado certo (entrada → receber, saída →
     * pagar), mesmo valor em aberto, vencimento na janela, alvo da regra, e ainda não vinculados
     * a outra linha.
     *
     * @param  array<int,bool>  $usados
     * @return list<int>
     */
    private function candidatos(int $businessId, ConciliacaoRegra $regra, array $linha, array $usados): array
    {
        $janela = max(0, (int) config('financeiro.conciliacao.janela_dias', 5));
        $data = CarbonImmutable::parse($linha['data']);
        $valor = abs($linha['valor']);

        // SUPERADMIN: business do parâmetro, filtro explícito (o motor roda também sem sessão).
        $q = Titulo::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->where('tipo', $linha['valor'] < 0 ? 'pagar' : 'receber')
            ->whereIn('status', ['aberto', 'parcial'])
            // Parcial: o que entra no banco é o que falta pagar, não o valor cheio.
            ->whereRaw("ABS((CASE WHEN status = 'parcial' THEN valor_aberto ELSE valor_total END) - ?) < 0.005", [$valor])
            ->whereBetween('vencimento', [$data->subDays($janela)->toDateString(), $data->addDays($janela)->toDateString()]);

        foreach (['cliente_id', 'plano_conta_id', 'categoria_id'] as $alvo) {
            if ($regra->{$alvo} !== null) {
                $q->where($alvo, (int) $regra->{$alvo});
            }
        }

        $ids = $q->limit(10)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_filter($ids, fn (int $id) => ! isset($usados[$id])));
    }

    /** @return array<int,bool> títulos já presos a uma linha sugerida ou conciliada (qualquer origem). */
    private function titulosJaVinculados(int $businessId): array
    {
        // SUPERADMIN: business do parâmetro, filtro explícito.
        $ids = BankStatementLine::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->whereIn('status', ['sugerido', 'conciliado'])
            ->whereNotNull('titulo_id')
            ->pluck('titulo_id')
            ->concat(DB::table('fin_extrato_lancamentos')
                ->where('business_id', $businessId)
                ->whereIn('status', ['sugerido', 'conciliado'])
                ->whereNotNull('titulo_id')
                ->pluck('titulo_id'));

        return $ids->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    private function vincular(int $businessId, array $linha, ConciliacaoRegra $regra, int $tituloId): void
    {
        $conciliar = $regra->acao === ConciliacaoRegra::ACAO_CONCILIAR;
        $tabela = $linha['origem'] === 'api'
            ? DB::table('fin_extrato_lancamentos')
            // SUPERADMIN: business do parâmetro, filtro explícito logo abaixo.
            : BankStatementLine::withoutGlobalScope(BusinessScopeImpl::class)->toBase();

        $afetadas = $tabela->where('id', $linha['id'])
            ->where('business_id', $businessId)
            ->where(fn ($q) => $linha['origem'] === 'api' ? $q->whereNull('status') : $q->where('status', 'pendente'))
            ->update([
                'status'        => $conciliar ? 'conciliado' : 'sugerido',
                'titulo_id'     => $tituloId,
                'regra_id'      => $regra->id,
                'match_score'   => 1.00,
                'conciliado_by' => null, // casado pela regra, não por uma pessoa
                'conciliado_at' => $conciliar ? now() : null,
                'updated_at'    => now(),
            ]);

        app(FinanceiroAuditLogger::class)->info(
            $conciliar ? 'conciliacao.regra: linha conciliada pela regra' : 'conciliacao.regra: linha sugerida pela regra',
            [
                'business_id' => $businessId,
                'line_id'     => $linha['id'],
                'origem'      => $linha['origem'],
                'titulo_id'   => $tituloId,
                'regra_id'    => $regra->id,
                'status'      => $conciliar ? 'conciliado' : 'sugerido',
                'afetadas'    => $afetadas,
            ]
        );
    }
}
