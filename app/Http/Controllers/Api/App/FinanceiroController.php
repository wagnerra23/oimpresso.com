<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use App\Utils\ModuleUtil;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Financeiro do app das lojas (tela 06). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §10.1.
 * Só leitura — baixar e pagar ficam na tela 15 (regra mestre).
 *
 * - resumo.a_receber / a_pagar: Financeiro\UnificadoService::kpis, o mesmo número do bloco
 *   financeiro do /api/app/inicio e do cockpit web /financeiro/unificado.
 * - resumo.vencido: parte vencida do a_receber (mesmos filtros, vencimento < hoje), já contida nele.
 * - resumo.recebido / pago: baixas do mês sem as de estorno, regra do FluxoRealizadoService.
 *
 * Tier 0 (ADR 0093): o global scope dos models do Financeiro lê a sessão, que a API não tem — por
 * isso toda consulta aqui filtra business_id do token explicitamente, em cada tabela.
 */
class FinanceiroController extends Controller
{
    private const POR_PAGINA = 20;

    public function __construct(private ModuleUtil $moduleUtil)
    {
    }

    /** A regra da área `financeiro` do Início e do bloco financeiro dele (§6). */
    public function podeVerFinanceiro(User $user): bool
    {
        if (! class_exists(\Modules\Financeiro\Services\UnificadoService::class)) {
            return false;
        }
        if ($user->can('superadmin')) {
            return true;
        }

        return $this->moduleUtil->hasThePermissionInSubscription((int) $user->business_id, 'financeiro_module')
            && $user->can('financeiro.access');
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->podeVerFinanceiro($user)) {
            return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Seu usuário não tem acesso ao financeiro.'], 403);
        }

        $aba = (string) $request->query('aba', 'receber');
        if (! in_array($aba, ['receber', 'pagar', 'extrato'], true)) {
            $aba = 'receber';
        }
        $pagina = max(1, (int) $request->query('pagina', 1));
        $bizId = (int) $user->business_id;
        $hoje = CarbonImmutable::today();
        $ini = $hoje->startOfMonth()->toDateString();
        $fim = $hoje->endOfMonth()->toDateString();

        $query = match ($aba) {
            'extrato' => $this->liquidados($bizId, $ini, $fim)->orderByDesc('b.pago_em')->orderByDesc('t.id'),
            default => $this->abertos($bizId, $aba)->orderBy('t.vencimento')->orderBy('t.id'),
        };
        $linhas = $query->forPage($pagina, self::POR_PAGINA + 1)->get();
        $temMais = $linhas->count() > self::POR_PAGINA;
        $hojeIso = $hoje->toDateString();

        return response()->json([
            'resumo' => $this->resumo($bizId, $hoje, $ini, $fim),
            'contas' => $this->contas($bizId),
            'itens' => $linhas->take(self::POR_PAGINA)->map(fn ($t) => $this->item($t, $hojeIso))->values(),
            'contadores' => [
                'receber' => $this->abertos($bizId, 'receber')->count(),
                'pagar' => $this->abertos($bizId, 'pagar')->count(),
                'extrato' => $this->liquidados($bizId, $ini, $fim)->count(),
            ],
            'pagina' => $pagina,
            'tem_mais' => $temMais,
        ]);
    }

    /** Títulos em aberto (aberto|parcial) do tipo — o mesmo universo do a_receber/a_pagar do kpis. */
    private function abertos(int $bizId, string $tipo): Builder
    {
        return $this->base($bizId)
            ->where('t.tipo', $tipo)
            ->whereIn('t.status', ['aberto', 'parcial'])
            ->addSelect([DB::raw('NULL as pago_em'), 't.valor_aberto as valor']);
    }

    /**
     * Títulos quitados cuja última baixa (sem estorno) caiu no mês. `valor` = soma das baixas do
     * título, o que de fato entrou/saiu (a mesma coluna que compõe recebido/pago).
     */
    private function liquidados(int $bizId, string $ini, string $fim): Builder
    {
        $baixas = DB::table('fin_titulo_baixas')
            ->where('business_id', $bizId)
            ->whereNull('estorno_de_id')
            ->groupBy('titulo_id')
            ->select(['titulo_id', DB::raw('MAX(data_baixa) as pago_em'), DB::raw('SUM(valor_baixa) as pago')]);

        return $this->base($bizId)
            ->joinSub($baixas, 'b', 'b.titulo_id', '=', 't.id')
            ->where('t.status', 'quitado')
            ->whereBetween('b.pago_em', [$ini, $fim])
            ->addSelect(['b.pago_em', 'b.pago as valor']);
    }

    private function base(int $bizId): Builder
    {
        return DB::table('fin_titulos as t')
            ->leftJoin('contacts as c', function ($j) use ($bizId) {
                $j->on('c.id', '=', 't.cliente_id')->where('c.business_id', '=', $bizId);
            })
            ->where('t.business_id', $bizId)
            ->whereNull('t.deleted_at')
            ->select([
                't.id', 't.tipo', 't.status', 't.numero', 't.vencimento', 't.parcela_numero', 't.parcela_total',
                DB::raw("COALESCE(NULLIF(t.cliente_descricao, ''), c.name) as parte"),
            ]);
    }

    private function item(object $t, string $hojeIso): array
    {
        $vencimento = $t->vencimento ? substr((string) $t->vencimento, 0, 10) : null;
        $status = $t->status === 'quitado'
            ? 'liquidado'
            : ($vencimento !== null && $vencimento < $hojeIso ? 'vencido' : 'aberto');
        $descricao = 'Título ' . $t->numero;
        if ((int) $t->parcela_total > 1) {
            $descricao .= ' · parcela ' . (int) $t->parcela_numero . '/' . (int) $t->parcela_total;
        }

        return [
            'id' => (int) $t->id,
            'tipo' => (string) $t->tipo,
            'descricao' => $descricao,
            'parte' => $t->parte !== null && $t->parte !== '' ? (string) $t->parte : null,
            'vencimento' => $vencimento,
            'pago_em' => $t->pago_em ? substr((string) $t->pago_em, 0, 10) : null,
            'valor' => round((float) $t->valor, 2),
            'status' => $status,
        ];
    }

    /** @return array{mes: string, recebido: float, pago: float, saldo: float, a_receber: float, vencido: float, a_pagar: float} */
    private function resumo(int $bizId, CarbonImmutable $hoje, string $ini, string $fim): array
    {
        $k = app(\Modules\Financeiro\Services\UnificadoService::class)->kpis($bizId, $hoje);

        $vencido = (float) DB::table('fin_titulos')
            ->where('business_id', $bizId)
            ->where('tipo', 'receber')
            ->whereIn('status', ['aberto', 'parcial'])
            ->where('vencimento', '<', $hoje->toDateString())
            ->whereNull('deleted_at')
            ->sum('valor_aberto');

        $mov = DB::table('fin_titulo_baixas as b')
            ->join('fin_titulos as t', function ($j) use ($bizId) {
                $j->on('t.id', '=', 'b.titulo_id')->where('t.business_id', '=', $bizId);
            })
            ->where('b.business_id', $bizId)
            ->whereBetween('b.data_baixa', [$ini, $fim])
            ->whereNull('b.estorno_de_id')
            ->whereNull('t.deleted_at')
            ->groupBy('t.tipo')
            ->selectRaw('t.tipo as tipo, SUM(b.valor_baixa) as total')
            ->get()
            ->pluck('total', 'tipo');

        $recebido = round((float) ($mov['receber'] ?? 0), 2);
        $pago = round((float) ($mov['pagar'] ?? 0), 2);

        return [
            'mes' => $hoje->format('Y-m'),
            'recebido' => $recebido,
            'pago' => $pago,
            'saldo' => round($recebido - $pago, 2),
            'a_receber' => round((float) $k['total_receber'], 2),
            'vencido' => round($vencido, 2),
            'a_pagar' => round((float) $k['total_pagar'], 2),
        ];
    }

    /** Contas bancárias do Financeiro; saldo `null` quando o ERP não tem o saldo em cache. */
    private function contas(int $bizId): array
    {
        return DB::table('fin_contas_bancarias as f')
            ->leftJoin('accounts as a', function ($j) use ($bizId) {
                $j->on('a.id', '=', 'f.account_id')->where('a.business_id', '=', $bizId);
            })
            ->where('f.business_id', $bizId)
            ->whereNull('f.deleted_at')
            ->orderBy('f.id')
            ->get(['f.id', 'f.banco_codigo', 'f.agencia', 'f.saldo_cached', 'a.name'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'nome' => (string) ($c->name ?? 'Conta ' . $c->id),
                'detalhe' => trim((new \Modules\Financeiro\Models\ContaBancaria(['banco_codigo' => (string) $c->banco_codigo]))->banco_nome . ($c->agencia ? ' · ag. ' . $c->agencia : '')),
                'saldo' => $c->saldo_cached === null ? null : round((float) $c->saldo_cached, 2),
            ])->all();
    }
}
