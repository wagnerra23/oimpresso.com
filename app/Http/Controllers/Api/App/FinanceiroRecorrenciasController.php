<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Aba Recorrências do Financeiro do app (tela 06, chave RECORRENCIAS). Contrato:
 * memory/requisitos/AppMobile/api/tela-06-financeiro.md §Recorrências; formato `interface Recorrencia` do app.
 *
 * Duas fontes, as que o ERP de fato tem:
 * - contratos a receber = assinaturas da Cobrança Recorrente (rb_subscriptions + rb_plans). Quem gera é o
 *   rb:generate-invoices, só para assinatura `active`, no dia do vencimento (lead-days 0 no schedule).
 * - contas fixas a pagar = despesas recorrentes do UltimatePOS (transactions type expense, is_recurring). Quem
 *   gera é o pos:generateRecurringExpense, que respeita recur_stopped_on.
 *
 * Ids: o app usa um número só por recorrência, e as duas tabelas têm ids próprios — a despesa sai como
 * ID_DESPESA + transactions.id (contratos nunca chegam lá). "Interromper" para a geração futura e não toca em
 * nenhum lançamento já criado: contrato → AssinaturaService::cancelar; despesa → recur_stopped_on.
 *
 * Tier 0 (ADR 0093): toda consulta filtra o business do token (a API não tem sessão para os scopes).
 */
class FinanceiroRecorrenciasController extends Controller
{
    public const ID_DESPESA = 1_000_000_000;

    private const CICLO_RB = ['monthly' => 'mensal', 'quarterly' => 'trimestral', 'semiannual' => 'semestral', 'yearly' => 'anual'];

    private const MESES = ['mensal' => 1, 'trimestral' => 3, 'semestral' => 6, 'anual' => 12];

    public function __construct(private FinanceiroController $financeiro)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->financeiro->podeVerFinanceiro($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;
        $hoje = CarbonImmutable::today()->toDateString();

        $itens = array_merge($this->contratos($bizId), $this->despesas($bizId));

        $contato = (string) $request->query('contato_id', '');
        if ($contato !== '' && ctype_digit($contato)) {
            $itens = array_filter($itens, fn ($r) => $r['parte_id'] === (int) $contato);
        }
        // vigentes=1: ativa e sem término vencido (recorrenciaVigente do app).
        if ((string) $request->query('vigentes', '') === '1') {
            $itens = array_filter($itens, fn ($r) => $r['status'] === 'ativa' && ($r['termino'] === null || $r['termino'] >= $hoje));
        }
        usort($itens, fn ($a, $b) => [$a['status'] === 'ativa' ? 0 : 1, $a['descricao'], $a['id']]
            <=> [$b['status'] === 'ativa' ? 0 : 1, $b['descricao'], $b['id']]);

        return response()->json(['itens' => array_values($itens)]);
    }

    /** POST /api/app/financeiro/recorrencias/{id}/interromper — idempotente: já interrompida devolve como está. */
    public function interromper(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->financeiro->podeVerFinanceiro($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;

        if ($id >= self::ID_DESPESA) {
            $txId = $id - self::ID_DESPESA;
            $linha = $this->despesas($bizId, $txId)[0] ?? null;
            if ($linha === null) {
                return $this->naoEncontrada();
            }
            if (! $user->can('superadmin') && ! $user->can('expense.edit')) {
                return $this->semPermissao('Seu usuário não pode alterar despesas.');
            }
            DB::table('transactions')
                ->where('id', $txId)->where('business_id', $bizId)
                ->where('type', 'expense')->where('is_recurring', 1)
                ->whereNull('recur_stopped_on')
                ->update(['recur_stopped_on' => now()]);

            return response()->json($this->despesas($bizId, $txId)[0]);
        }

        if (! $this->temContratos()) {
            return $this->naoEncontrada();
        }
        $sub = \Modules\RecurringBilling\Models\Subscription::query()->where('business_id', $bizId)->whereKey($id)->first();
        if ($sub === null) {
            return $this->naoEncontrada();
        }
        // Já interrompido: devolve como está (a policy recusa cancelar o cancelado, e repetir não é erro).
        if ($sub->status !== 'canceled') {
            if (! Gate::forUser($user)->allows('cancel', $sub)) {
                return $this->semPermissao('Seu usuário não pode cancelar contratos.');
            }
            app(\Modules\RecurringBilling\Services\AssinaturaService::class)->cancelar($bizId, $id, 'interrompido pelo app');
        }

        return response()->json($this->contratos($bizId, $id)[0]);
    }

    private function temContratos(): bool
    {
        return class_exists(\Modules\RecurringBilling\Models\Subscription::class)
            && Schema::hasTable('rb_subscriptions') && Schema::hasTable('rb_plans');
    }

    /** @return list<array<string, mixed>> */
    private function contratos(int $bizId, ?int $id = null): array
    {
        if (! $this->temContratos()) {
            return [];
        }
        $gerados = DB::table('rb_invoices')
            ->where('business_id', $bizId)->whereNull('deleted_at')
            ->groupBy('subscription_id')
            ->select(['subscription_id', DB::raw('COUNT(*) as n')]);

        return DB::table('rb_subscriptions as s')
            ->join('rb_plans as p', function ($j) use ($bizId) {
                $j->on('p.id', '=', 's.plan_id')->where('p.business_id', '=', $bizId);
            })
            ->leftJoin('contacts as c', function ($j) use ($bizId) {
                $j->on('c.id', '=', 's.contact_id')->where('c.business_id', '=', $bizId);
            })
            ->leftJoinSub($gerados, 'g', 'g.subscription_id', '=', 's.id')
            ->where('s.business_id', $bizId)
            ->whereNull('s.deleted_at')
            ->when($id !== null, fn ($q) => $q->where('s.id', $id))
            ->get([
                's.id', 's.status', 's.start_date', 's.next_due_date', 's.canceled_at', 's.paused_at', 's.contact_id',
                'p.name as plano', 'p.valor', 'p.ciclo', 'p.ciclo_dias', 'c.name as parte', 'g.n as gerados',
            ])
            ->map(function ($s) {
                // Só `active` gera fatura (InvoiceGeneratorService). Cancelada ou pausada = parada.
                $parada = in_array($s->status, ['canceled', 'paused'], true);
                $venc = $s->next_due_date ? substr((string) $s->next_due_date, 0, 10) : null;
                $proxima = $s->status === 'active' ? $venc : null;
                $parouEm = $s->status === 'canceled' ? $s->canceled_at : ($s->status === 'paused' ? $s->paused_at : null);
                $dia = (int) substr((string) ($venc ?? $s->start_date), 8, 2);

                return [
                    'id' => (int) $s->id,
                    'tipo' => 'receber',
                    'descricao' => (string) $s->plano,
                    'parte' => $s->parte !== null && $s->parte !== '' ? (string) $s->parte : null,
                    'parte_id' => $s->contact_id !== null ? (int) $s->contact_id : null,
                    'plano_nome' => null,
                    'valor' => round((float) $s->valor, 2),
                    'frequencia' => self::CICLO_RB[(string) $s->ciclo] ?? $this->frequenciaPorMeses(((int) $s->ciclo_dias) / 30),
                    'dia_geracao' => $dia,
                    'dia_vencimento' => $dia,
                    'inicio' => substr((string) $s->start_date, 0, 10),
                    'termino' => null,
                    'proxima_geracao' => $proxima,
                    'proximo_vencimento' => $proxima,
                    'gerados' => (int) ($s->gerados ?? 0),
                    'status' => $parada ? 'interrompida' : 'ativa',
                    'interrompida_em' => $parada && $parouEm ? substr((string) $parouEm, 0, 10) : null,
                    // O mesmo nº que o filtro `contrato` da lista do Financeiro procura.
                    'contrato' => (string) $s->id,
                ];
            })->all();
    }

    /** @return list<array<string, mixed>> */
    private function despesas(int $bizId, ?int $txId = null): array
    {
        $filhas = DB::table('transactions')
            ->where('business_id', $bizId)->where('type', 'expense')->whereNotNull('recur_parent_id')
            ->groupBy('recur_parent_id')
            ->select(['recur_parent_id', DB::raw('COUNT(*) as n'), DB::raw('MAX(transaction_date) as ultima')]);

        return DB::table('transactions as t')
            ->leftJoin('contacts as c', function ($j) use ($bizId) {
                $j->on('c.id', '=', 't.contact_id')->where('c.business_id', '=', $bizId);
            })
            ->leftJoin('expense_categories as ec', function ($j) use ($bizId) {
                $j->on('ec.id', '=', 't.expense_category_id')->where('ec.business_id', '=', $bizId);
            })
            ->leftJoinSub($filhas, 'f', 'f.recur_parent_id', '=', 't.id')
            ->where('t.business_id', $bizId)
            ->where('t.type', 'expense')
            ->where('t.is_recurring', 1)
            ->whereNull('t.recur_parent_id')
            ->whereNotNull('t.recur_interval')
            ->whereNotNull('t.recur_interval_type')
            ->when($txId !== null, fn ($q) => $q->where('t.id', $txId))
            ->get([
                't.id', 't.transaction_date', 't.final_total', 't.ref_no', 't.additional_notes', 't.contact_id',
                't.recur_interval', 't.recur_interval_type', 't.recur_repetitions', 't.recur_stopped_on', 't.subscription_repeat_on',
                'c.name as parte', 'ec.name as categoria', 'f.n as filhas', 'f.ultima',
            ])
            ->map(function ($t) {
                $inicio = CarbonImmutable::parse((string) $t->transaction_date)->startOfDay();
                $intervalo = max(1, (int) $t->recur_interval);
                $unidade = (string) $t->recur_interval_type;
                $repeteDia = (int) $t->subscription_repeat_on;
                $somar = function (CarbonImmutable $d, int $n) use ($unidade, $intervalo, $repeteDia): CarbonImmutable {
                    $r = match ($unidade) {
                        'days' => $d->addDays($n * $intervalo),
                        'years' => $d->addYearsNoOverflow($n * $intervalo),
                        default => $d->addMonthsNoOverflow($n * $intervalo),
                    };
                    // "Repetir no dia" das despesas mensais: a regra do pos:generateRecurringExpense.
                    return $unidade === 'months' && $repeteDia >= 1 && $repeteDia <= 31
                        ? $r->setDay(min($repeteDia, $r->daysInMonth)) : $r;
                };

                $filhas = (int) ($t->filhas ?? 0);
                $repeticoes = $t->recur_repetitions !== null && (int) $t->recur_repetitions > 0 ? (int) $t->recur_repetitions : null;
                $termino = $repeticoes !== null ? $somar($inicio, $repeticoes)->toDateString() : null;
                $parada = $t->recur_stopped_on !== null;
                $ultima = $t->ultima ? CarbonImmutable::parse((string) $t->ultima)->startOfDay() : $inicio;
                $proxima = ($parada || ($repeticoes !== null && $filhas >= $repeticoes)) ? null : $somar($ultima, 1)->toDateString();
                $meses = match ($unidade) {
                    'days' => $intervalo / 30,
                    'years' => $intervalo * 12,
                    default => $intervalo,
                };
                $dia = $repeteDia >= 1 && $repeteDia <= 31 ? $repeteDia : (int) $inicio->format('j');
                $notas = trim((string) $t->additional_notes);

                return [
                    'id' => self::ID_DESPESA + (int) $t->id,
                    'tipo' => 'pagar',
                    'descricao' => $notas !== '' ? mb_substr($notas, 0, 120) : (string) ($t->categoria ?? ('Despesa ' . $t->ref_no)),
                    'parte' => $t->parte !== null && $t->parte !== '' ? (string) $t->parte : null,
                    'parte_id' => $t->contact_id !== null ? (int) $t->contact_id : null,
                    'plano_nome' => $t->categoria !== null ? (string) $t->categoria : null,
                    'valor' => round((float) $t->final_total, 2),
                    'frequencia' => $this->frequenciaPorMeses($meses),
                    'dia_geracao' => $dia,
                    'dia_vencimento' => $dia,
                    'inicio' => $inicio->toDateString(),
                    'termino' => $termino,
                    'proxima_geracao' => $proxima,
                    'proximo_vencimento' => $proxima,
                    // A despesa de origem também é um lançamento.
                    'gerados' => $filhas + 1,
                    'status' => $parada ? 'interrompida' : 'ativa',
                    'interrompida_em' => $parada ? substr((string) $t->recur_stopped_on, 0, 10) : null,
                    'contrato' => $t->ref_no !== null && $t->ref_no !== '' ? (string) $t->ref_no : null,
                ];
            })->all();
    }

    /** O app só conhece mensal/trimestral/semestral/anual: intervalos fora disso vão para o mais próximo. */
    private function frequenciaPorMeses(float $meses): string
    {
        $melhor = 'mensal';
        foreach (self::MESES as $nome => $m) {
            if (abs($meses - $m) < abs($meses - self::MESES[$melhor])) {
                $melhor = $nome;
            }
        }

        return $melhor;
    }

    private function semPermissao(string $mensagem = 'Seu usuário não tem acesso ao financeiro.'): JsonResponse
    {
        return response()->json(['erro' => 'sem_permissao', 'mensagem' => $mensagem], 403);
    }

    private function naoEncontrada(): JsonResponse
    {
        return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Recorrência não encontrada.'], 404);
    }
}
