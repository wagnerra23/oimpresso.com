<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamentos do app das lojas (tela 15) — LEITURA. Contrato: API-CONTRATO-v1.md §10.5.
 * A escrita (gerar/consultar/cancelar cobrança) mexe em valor e vem em PR separado, pela regra mestre.
 *
 * Fonte: `cobrancas` (PaymentGateway) — a mesma tabela da tela web /financeiro/cobranca.
 * Acesso: a regra do Financeiro (FinanceiroController::podeVerFinanceiro), a mesma da tela 06.
 *
 * Tier 0 (ADR 0093): o model Cobranca filtra pela sessão, que a API não tem — aqui `cobrancas` e o
 * join com `transactions`/`contacts` filtram o business do token explicitamente.
 */
class PagamentosController extends Controller
{
    private const POR_PAGINA = 20;

    public const STATUS = ['pendente', 'pago', 'vencido', 'cancelado'];

    public function podeVerPagamentos(User $user): bool
    {
        return Schema::hasTable('cobrancas') && app(FinanceiroController::class)->podeVerFinanceiro($user);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->podeVerPagamentos($user)) {
            return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Seu usuário não tem acesso aos pagamentos.'], 403);
        }
        $status = (string) $request->query('status', 'todos');
        if (! in_array($status, self::STATUS, true)) {
            $status = 'todos';
        }
        $pagina = max(1, (int) $request->query('pagina', 1));
        $bizId = (int) $user->business_id;
        $hoje = CarbonImmutable::today()->toDateString();
        $statusSql = $this->statusSql($hoje);

        $q = $this->base($bizId)->selectRaw("{$statusSql} as status_app");
        $filtrado = DB::query()->fromSub($q, 'x');
        if ($status !== 'todos') {
            $filtrado->where('x.status_app', $status);
        }
        $linhas = $filtrado->orderByDesc('x.created_at')->orderByDesc('x.id')
            ->forPage($pagina, self::POR_PAGINA + 1)->get();

        $porStatus = DB::query()->fromSub($q, 'x')
            ->groupBy('x.status_app')
            ->selectRaw('x.status_app as s, COUNT(*) as total')
            ->get()
            ->mapWithKeys(fn ($l) => [$l->s => $l->total]);
        $contadores = ['todos' => (int) $porStatus->sum()];
        foreach (self::STATUS as $s) {
            $contadores[$s] = (int) ($porStatus[$s] ?? 0);
        }

        return response()->json([
            'itens' => $linhas->take(self::POR_PAGINA)->map(fn ($c) => $this->item($c))->values(),
            'contadores' => $contadores,
            'pagina' => $pagina,
            'tem_mais' => $linhas->count() > self::POR_PAGINA,
        ]);
    }

    /** Cobranças do business, menos as que deram erro no gateway (nunca viraram cobrança). */
    private function base(int $bizId): Builder
    {
        return DB::table('cobrancas as c')
            ->leftJoin('transactions as t', function ($j) use ($bizId) {
                $j->on('t.id', '=', 'c.origem_id')
                    ->where('c.origem_type', '=', 'sale')
                    ->where('t.business_id', '=', $bizId);
            })
            ->leftJoin('contacts as ct', function ($j) use ($bizId) {
                $j->on('ct.id', '=', 'c.contact_id')->where('ct.business_id', '=', $bizId);
            })
            ->where('c.business_id', $bizId)
            ->where('c.status', '!=', 'erro')
            ->select([
                'c.id', 'c.tipo', 'c.forma_pagamento', 'c.valor_centavos', 'c.vencimento', 'c.paga_em',
                'c.descricao', 'c.payer_name', 'c.pix_emv', 'c.boleto_pdf_url', 'c.created_at',
                't.invoice_no', 'ct.name as cliente',
            ]);
    }

    /** Status do gateway → status do app; emitida/pendente com vencimento passado = vencido. */
    private function statusSql(string $hoje): string
    {
        return "CASE WHEN c.status = 'paga' THEN 'pago'"
            . " WHEN c.status = 'cancelada' THEN 'cancelado'"
            . " WHEN c.status = 'vencida' OR c.vencimento < '{$hoje}' THEN 'vencido'"
            . " ELSE 'pendente' END";
    }

    private function item(object $c): array
    {
        $parte = $c->cliente ?: $c->payer_name;
        $descricao = $c->invoice_no ? 'Pedido #' . $c->invoice_no : (string) $c->descricao;
        if ($parte) {
            $descricao .= ' · ' . $parte;
        }

        return [
            'id' => (int) $c->id,
            'descricao' => $descricao,
            'valor' => round(((int) $c->valor_centavos) / 100, 2),
            'vencimento' => substr((string) $c->vencimento, 0, 10),
            'metodo' => $this->metodo($c),
            'status' => (string) $c->status_app,
            'pago_em' => $c->paga_em ? CarbonImmutable::parse((string) $c->paga_em)->toIso8601String() : null,
            'link' => $c->boleto_pdf_url ? (string) $c->boleto_pdf_url : null,
        ];
    }

    /** card → cartao; pix_* → pix; boleto com PIX embutido (bolepix) → qualquer; boleto → boleto. */
    private function metodo(object $c): string
    {
        if ($c->tipo === 'card' || $c->forma_pagamento === 'cartao') {
            return 'cartao';
        }
        if (str_starts_with((string) $c->tipo, 'pix')) {
            return 'pix';
        }

        return $c->pix_emv ? 'qualquer' : 'boleto';
    }
}
