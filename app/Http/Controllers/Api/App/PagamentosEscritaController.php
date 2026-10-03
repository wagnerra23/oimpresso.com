<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Contracts\Cobranca\CobrancaDoApp;
use App\Contracts\Cobranca\FalhaCobranca;
use App\Http\Controllers\Controller;
use App\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Pagamentos do app das lojas (tela 15) — ESCRITA. Contrato: API-CONTRATO-v1.md §10.6.
 * Mexe em valor: regra mestre (dupla prova do valor no teste + antes→depois no PR + ok do [W]).
 *
 * Decisões [W] 2026-10-02: valor = SALDO EM ABERTO do documento (o app nunca manda valor);
 * credencial = a padrão do business (a mesma da venda web).
 *
 * - Saldo = final_total − pago, com pago = TransactionUtil::getTotalPaid (devolução subtrai).
 * - O documento precisa ser visível ao usuário na lista de Pedidos (PedidosController::baseVisivel).
 * - Banco, conta/credencial e reconciliação ficam atrás do contrato do núcleo CobrancaDoApp, que o
 *   módulo PaymentGateway implementa (Modules\PaymentGateway\Services\CobrancaDoAppGateway). Sem o
 *   módulo, toda operação responde 503 sem_configuracao. A seta de dependência fica módulo → núcleo.
 * - Se o provedor falhar, a tentativa fica como `erro` (fora da lista) e o app recebe 503.
 * - Consultar: pago no gateway → a mesma reconciliação do webhook. Cancelar: recusa cobrança paga (409).
 *
 * Tier 0 (ADR 0093): toda busca filtra o business do token explicitamente; registro de outra empresa = 404.
 */
class PagamentosEscritaController extends Controller
{
    public const DIAS = [3, 7, 15];

    private const REFERENCIAS_MAX = 30;

    public function __construct(private CobrancaDoApp $cobrancas)
    {
    }

    /** SQL do saldo em aberto: o mesmo SUM de TransactionUtil::getTotalPaid (devolução subtrai). */
    private const SALDO_SQL = 't.final_total - COALESCE((SELECT SUM(IF(tp.is_return = 0, tp.amount, -tp.amount))'
        . ' FROM transaction_payments tp WHERE tp.transaction_id = t.id), 0)';

    public function referencias(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! app(PagamentosController::class)->podeVerPagamentos($user)) {
            return $this->semPermissao();
        }

        $pedidos = app(PedidosController::class);
        $linha = fn ($q, string $tipo) => $q
            ->whereNotNull('t.contact_id')
            ->whereRaw('(' . self::SALDO_SQL . ') > 0')
            ->orderByDesc('t.transaction_date')
            ->limit(self::REFERENCIAS_MAX)
            ->get(['t.id', 't.invoice_no', 'c.name as cliente', 'c.supplier_business_name as empresa', DB::raw('(' . self::SALDO_SQL . ') as saldo')])
            ->map(fn ($l) => [
                'tipo' => $tipo,
                'id' => (int) $l->id,
                'rotulo' => ($tipo === 'pedido' ? 'Pedido #' : 'Orçamento #') . $l->invoice_no,
                'cliente' => (string) ($l->cliente ?: $l->empresa ?: ''),
                'valor' => round((float) $l->saldo, 2),
            ]);

        return response()->json([
            'itens' => $linha($pedidos->baseVisivel($user, 'final'), 'pedido')
                ->concat($linha($pedidos->baseVisivel($user, 'draft'), 'orcamento'))
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! app(PagamentosController::class)->podeVerPagamentos($user)) {
            return $this->semPermissao();
        }

        $v = Validator::make($request->all(), [
            'referencia.tipo' => 'required|in:pedido,orcamento',
            'referencia.id' => 'required|integer',
            'metodo' => 'required|in:qualquer,pix,boleto,cartao',
            'vencimento_dias' => 'required|integer|in:' . implode(',', self::DIAS),
        ], [
            'referencia.tipo.*' => 'Escolha um pedido ou orçamento.',
            'referencia.id.*' => 'Escolha um pedido ou orçamento.',
            'metodo.*' => 'Escolha a forma de pagamento.',
            'vencimento_dias.*' => 'Escolha o vencimento (3, 7 ou 15 dias).',
        ]);
        if ($v->fails()) {
            return $this->validacao(collect($v->errors()->messages())->map(fn ($m) => $m[0])->all());
        }
        $dados = $v->validated();
        if ($dados['metodo'] === 'cartao') {
            return $this->validacao(['metodo' => 'Cobrança no cartão não pode ser gerada pelo app.']);
        }

        $bizId = (int) $user->business_id;
        $status = $dados['referencia']['tipo'] === 'pedido' ? 'final' : 'draft';
        $doc = app(PedidosController::class)->baseVisivel($user, $status)
            ->where('t.id', (int) $dados['referencia']['id'])
            ->first(['t.id', 't.invoice_no', 't.contact_id', 't.final_total', 'c.name as cliente',
                'c.supplier_business_name as empresa', 'c.tax_number', 'c.email']);
        if (! $doc) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Pedido ou orçamento não encontrado.'], 404);
        }
        if (! $doc->contact_id) {
            return $this->validacao(['referencia' => 'Este documento não tem cliente para cobrar.']);
        }

        $saldo = $this->saldoEmAberto((int) $doc->id, (float) $doc->final_total);
        $centavos = (int) round($saldo * 100);
        if ($centavos <= 0) {
            return $this->validacao(['referencia' => 'Este documento não tem saldo em aberto.']);
        }

        // Bloqueia cobrar de novo: (a) cobrança em aberto e no prazo; (b) cobrança JÁ PAGA — o
        // pagamento no gateway não é lançado na venda (o listener do CobrancaPaga só cria título
        // para o business dono do SaaS), então o saldo da venda não cai e a 2ª cobrança seria em dobro.
        $existente = DB::table('cobrancas')
            ->where('business_id', $bizId)
            ->where('origem_type', 'sale')
            ->where('origem_id', (int) $doc->id)
            ->where(fn ($q) => $q->where('status', 'paga')->orWhere(fn ($a) => $a
                ->whereIn('status', ['pending', 'emitida'])
                ->whereDate('vencimento', '>=', CarbonImmutable::today()->toDateString())))
            ->orderByDesc('id')
            ->first(['id', 'status']);
        if ($existente) {
            $paga = $existente->status === 'paga';

            return response()->json([
                'erro' => 'ja_existe',
                'mensagem' => $paga
                    ? 'Este documento já tem cobrança paga. Registre o pagamento na venda antes de cobrar de novo.'
                    : 'Já existe uma cobrança em aberto para este documento.',
                'item' => app(PagamentosController::class)->itemPorId($bizId, (int) $existente->id),
            ], 409);
        }

        $prefixo = $status === 'final' ? 'Pedido #' : 'Orçamento #';
        try {
            $cobrancaId = $this->cobrancas->emitir($bizId, [
                'contact_id' => (int) $doc->contact_id,
                'valor_centavos' => $centavos,
                'vencimento' => CarbonImmutable::today()->addDays((int) $dados['vencimento_dias'])->toDateString(),
                'descricao' => $prefixo . $doc->invoice_no,
                'origem_id' => (int) $doc->id,
                'metodo' => $dados['metodo'] === 'pix' ? 'pix' : 'boleto',
                'pagador' => [
                    'nome' => $doc->empresa ?: $doc->cliente,
                    'documento' => $doc->tax_number,
                    'email' => $doc->email,
                ],
            ]);
        } catch (FalhaCobranca $e) {
            return match ($e->codigo) {
                'pagador_invalido' => $this->validacao(['referencia' => $e->getMessage()]),
                'ja_existe' => response()->json(['erro' => 'ja_existe', 'mensagem' => $e->getMessage()], 409),
                'sem_configuracao' => $this->indisponivel('sem_configuracao', $e->codigo === $e->getMessage()
                    ? 'Nenhuma conta com gateway de cobrança configurada.' : $e->getMessage()),
                default => $this->indisponivel('provedor_indisponivel', $e->getMessage()),
            };
        }

        return response()->json(app(PagamentosController::class)->itemPorId($bizId, $cobrancaId), 201);
    }

    public function consultar(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! app(PagamentosController::class)->podeVerPagamentos($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;
        try {
            $this->cobrancas->consultar($bizId, $id);
        } catch (FalhaCobranca $e) {
            return $this->falha($e);
        }

        return response()->json(app(PagamentosController::class)->itemPorId($bizId, $id));
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! app(PagamentosController::class)->podeVerPagamentos($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;
        try {
            $this->cobrancas->cancelar($bizId, $id);
        } catch (FalhaCobranca $e) {
            return $this->falha($e);
        }

        return response()->json(app(PagamentosController::class)->itemPorId($bizId, $id));
    }

    /** Saldo em aberto: final_total − TransactionUtil::getTotalPaid (devolução subtrai). */
    public function saldoEmAberto(int $transactionId, float $finalTotal): float
    {
        $pago = (float) app(\App\Utils\TransactionUtil::class)->getTotalPaid($transactionId);

        return round($finalTotal - $pago, 2);
    }

    /** Falha de consultar/cancelar → HTTP do contrato (§10.6). */
    private function falha(FalhaCobranca $e): JsonResponse
    {
        return match ($e->codigo) {
            'nao_encontrado' => $this->naoEncontrada(),
            'nao_cancelavel' => response()->json(['erro' => 'nao_cancelavel', 'mensagem' => 'Cobrança já paga não pode ser cancelada.'], 409),
            'sem_configuracao' => $this->indisponivel('sem_configuracao', 'O gateway de cobrança não está configurado.'),
            default => $this->indisponivel('provedor_indisponivel', 'O banco não respondeu. Tente de novo em instantes.'),
        };
    }

    private function semPermissao(): JsonResponse
    {
        return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Seu usuário não tem acesso aos pagamentos.'], 403);
    }

    private function naoEncontrada(): JsonResponse
    {
        return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Cobrança não encontrada.'], 404);
    }

    /** @param array<string, string> $campos */
    private function validacao(array $campos): JsonResponse
    {
        return response()->json(['erro' => 'validacao', 'mensagem' => 'Confira os campos.', 'campos' => $campos], 422);
    }

    private function indisponivel(string $codigo, string $mensagem): JsonResponse
    {
        return response()->json(['erro' => $codigo, 'mensagem' => $mensagem], 503);
    }
}
