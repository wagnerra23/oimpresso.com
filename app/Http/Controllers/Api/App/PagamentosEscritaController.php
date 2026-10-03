<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\PaymentGateway\Contracts\PaymentGatewayContract;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Exceptions\GatewayUnavailableException;
use Modules\PaymentGateway\Exceptions\IdempotencyConflictException;
use Modules\PaymentGateway\Exceptions\InvalidPayerException;
use Modules\PaymentGateway\Exceptions\PaymentGatewayException;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Services\ReconciliarCobrancaService;

/**
 * Pagamentos do app das lojas (tela 15) — ESCRITA. Contrato: API-CONTRATO-v1.md §10.6.
 * Mexe em valor: regra mestre (dupla prova do valor no teste + antes→depois no PR + ok do [W]).
 *
 * Decisões [W] 2026-10-02: valor = SALDO EM ABERTO do documento (o app nunca manda valor);
 * credencial = a padrão do business (ContaBancaria::padraoParaCobranca, a mesma da venda web).
 *
 * - Saldo = final_total − pago, com pago = TransactionUtil::getTotalPaid (devolução subtrai).
 * - O documento precisa ser visível ao usuário na lista de Pedidos (PedidosController::baseVisivel).
 * - Emissão síncrona pelo PaymentGatewayContract (o mesmo da venda web). Se o provedor falhar,
 *   a linha `pending` que o serviço já criou vira `erro` — não aparece na lista e não conta como
 *   cobrança aberta (o app recebe 503 e nada fica pendurado).
 * - Consultar: pago no gateway → ReconciliarCobrancaService::marcarPaga (o mesmo do webhook, que
 *   baixa o título no Financeiro). Cancelar: recusa cobrança já paga (409).
 *
 * Tier 0 (ADR 0093): os models do gateway filtram pela sessão, que a API não tem — toda busca aqui
 * filtra o business do token explicitamente; registro de outra empresa = 404.
 */
class PagamentosEscritaController extends Controller
{
    public const DIAS = [3, 7, 15];

    private const REFERENCIAS_MAX = 30;

    public function __construct(private PaymentGatewayContract $gateway)
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
        $existente = Cobranca::query()->withoutGlobalScopes() // SUPERADMIN: API sem sessão; business_id explícito abaixo.
            ->where('business_id', $bizId)
            ->where('origem_type', 'sale')
            ->where('origem_id', (int) $doc->id)
            ->where(fn ($q) => $q->where('status', 'paga')->orWhere(fn ($a) => $a
                ->whereIn('status', ['pending', 'emitida'])
                ->whereDate('vencimento', '>=', CarbonImmutable::today()->toDateString())))
            ->orderByDesc('id')
            ->first();
        if ($existente) {
            $paga = $existente->getAttribute('status') === 'paga';

            return response()->json([
                'erro' => 'ja_existe',
                'mensagem' => $paga
                    ? 'Este documento já tem cobrança paga. Registre o pagamento na venda antes de cobrar de novo.'
                    : 'Já existe uma cobrança em aberto para este documento.',
                'item' => app(PagamentosController::class)->itemPorId($bizId, (int) $existente->getAttribute('id')),
            ], 409);
        }

        $conta = ContaBancaria::padraoParaCobranca($bizId);
        $account = $conta ? \App\Account::query()->where('business_id', $bizId)->find($conta->getAttribute('account_id')) : null;
        if (! $account) {
            return $this->indisponivel('sem_configuracao', 'Nenhuma conta com gateway de cobrança configurada.');
        }

        $chave = (string) Str::uuid();
        $prefixo = $status === 'final' ? 'Pedido #' : 'Orçamento #';
        $input = new EmitirCobrancaInput(
            businessId: $bizId,
            contactId: (int) $doc->contact_id,
            valorCentavos: $centavos,
            vencimento: CarbonImmutable::today()->addDays((int) $dados['vencimento_dias'])->toDateTimeImmutable(),
            descricao: $prefixo . $doc->invoice_no,
            idempotencyKey: $chave,
            origemType: 'sale',
            origemId: (int) $doc->id,
            meta: [
                'payer_name' => $doc->empresa ?: $doc->cliente,
                'payer_cpf_cnpj' => $doc->tax_number,
                'payer_email' => $doc->email,
                'origem' => 'app',
            ],
        );

        try {
            $gw = $this->gateway->for($account);
            $res = $dados['metodo'] === 'pix' ? $gw->emitirPix($input, 'cobv') : $gw->emitirBoleto($input);
        } catch (CredentialMisconfiguredException | DriverNotSupportedException $e) {
            $this->marcarErro($bizId, $chave);

            return $this->indisponivel('sem_configuracao', 'O gateway de cobrança não está configurado para isso.');
        } catch (GatewayUnavailableException $e) {
            $this->marcarErro($bizId, $chave);

            return $this->indisponivel('provedor_indisponivel', 'O banco não respondeu. Tente de novo em instantes.');
        } catch (InvalidPayerException $e) {
            $this->marcarErro($bizId, $chave);

            return $this->validacao(['referencia' => 'O cadastro do cliente não serve para cobrança (documento ou endereço).']);
        } catch (IdempotencyConflictException $e) {
            return response()->json(['erro' => 'ja_existe', 'mensagem' => 'Já existe uma cobrança para este documento.'], 409);
        } catch (PaymentGatewayException $e) {
            $this->marcarErro($bizId, $chave);
            report($e);

            return $this->indisponivel('provedor_indisponivel', 'Não foi possível gerar a cobrança.');
        }

        return response()->json(app(PagamentosController::class)->itemPorId($bizId, $res->cobrancaId), 201);
    }

    public function consultar(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! app(PagamentosController::class)->podeVerPagamentos($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;
        $cobranca = $this->cobranca($bizId, $id);
        if (! $cobranca) {
            return $this->naoEncontrada();
        }

        if (in_array($cobranca->getAttribute('status'), ['pending', 'emitida', 'vencida'], true)) {
            try {
                $st = $this->gateway->consultar($cobranca);
            } catch (PaymentGatewayException $e) {
                return $this->indisponivel('provedor_indisponivel', 'O banco não respondeu. Tente de novo em instantes.');
            }
            if ($st->status === 'paga') {
                app(ReconciliarCobrancaService::class)->marcarPaga(
                    $cobranca,
                    $bizId,
                    (int) ($st->valorPagoCentavos ?? $cobranca->getAttribute('valor_centavos')),
                    $st->pagaEm ?? new \DateTimeImmutable(),
                    (string) ($st->formaPagamento ?? 'pix'),
                );
            }
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
        $cobranca = $this->cobranca($bizId, $id);
        if (! $cobranca) {
            return $this->naoEncontrada();
        }
        if ($cobranca->getAttribute('status') === 'paga') {
            return response()->json(['erro' => 'nao_cancelavel', 'mensagem' => 'Cobrança já paga não pode ser cancelada.'], 409);
        }
        if ($cobranca->getAttribute('status') !== 'cancelada') {
            try {
                $this->gateway->cancelar($cobranca, 'Cancelada pelo app');
            } catch (PaymentGatewayException $e) {
                return $this->indisponivel('provedor_indisponivel', 'O banco não respondeu. Tente de novo em instantes.');
            }
        }

        return response()->json(app(PagamentosController::class)->itemPorId($bizId, $id));
    }

    /** Saldo em aberto: final_total − TransactionUtil::getTotalPaid (devolução subtrai). */
    public function saldoEmAberto(int $transactionId, float $finalTotal): float
    {
        $pago = (float) app(\App\Utils\TransactionUtil::class)->getTotalPaid($transactionId);

        return round($finalTotal - $pago, 2);
    }

    private function cobranca(int $bizId, int $id): ?Cobranca
    {
        return Cobranca::query()->withoutGlobalScopes() // SUPERADMIN: API sem sessão; business_id explícito abaixo.
            ->where('business_id', $bizId)
            ->where('status', '!=', 'erro')
            ->find($id);
    }

    /** Linha `pending` que o serviço criou antes de o provedor falhar vira `erro` (fora da lista). */
    private function marcarErro(int $bizId, string $chave): void
    {
        DB::table('cobrancas')
            ->where('business_id', $bizId)
            ->where('idempotency_key', $chave)
            ->where('status', 'pending')
            ->update(['status' => 'erro', 'updated_at' => now()]);
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
