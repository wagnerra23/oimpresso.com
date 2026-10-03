<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services;

use App\Contracts\Cobranca\CobrancaDoApp;
use App\Contracts\Cobranca\FalhaCobranca;
use Illuminate\Support\Facades\DB;
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

/**
 * Implementação do contrato do núcleo CobrancaDoApp (tela 15 do app das lojas): o mesmo caminho
 * de emissão da venda web (PaymentGatewayContract + ContaBancaria::padraoParaCobranca) e a mesma
 * reconciliação do webhook (ReconciliarCobrancaService::marcarPaga).
 *
 * O valor chega pronto do núcleo (saldo em aberto, decisão [W] 2026-10-02); aqui não se calcula
 * valor nenhum.
 *
 * - Se o provedor falhar, a linha `pending` que o serviço já criou vira `erro` — fora da lista do
 *   app e não conta como cobrança aberta.
 * - Cancelar recusa cobrança já paga (`nao_cancelavel`).
 *
 * Tier 0 (ADR 0093): o model Cobranca filtra pela sessão, que a API não tem — toda busca aqui
 * filtra o business_id recebido; cobrança de outra empresa = `nao_encontrado`.
 */
final class CobrancaDoAppGateway implements CobrancaDoApp
{
    public function __construct(
        private PaymentGatewayContract $gateway,
        private ReconciliarCobrancaService $reconciliar,
    ) {
    }

    public function emitir(int $businessId, array $dados): int
    {
        $conta = ContaBancaria::padraoParaCobranca($businessId);
        $account = $conta
            ? \App\Account::query()->where('business_id', $businessId)->find($conta->getAttribute('account_id'))
            : null;
        if (! $account) {
            throw new FalhaCobranca('sem_configuracao', 'Nenhuma conta com gateway de cobrança configurada.');
        }

        $chave = (string) Str::uuid();
        $input = new EmitirCobrancaInput(
            businessId: $businessId,
            contactId: (int) $dados['contact_id'],
            valorCentavos: (int) $dados['valor_centavos'],
            vencimento: new \DateTimeImmutable($dados['vencimento']),
            descricao: (string) $dados['descricao'],
            idempotencyKey: $chave,
            origemType: 'sale',
            origemId: (int) $dados['origem_id'],
            meta: [
                'payer_name' => $dados['pagador']['nome'] ?? null,
                'payer_cpf_cnpj' => $dados['pagador']['documento'] ?? null,
                'payer_email' => $dados['pagador']['email'] ?? null,
                'origem' => 'app',
            ],
        );

        try {
            $gw = $this->gateway->for($account);
            $res = $dados['metodo'] === 'pix' ? $gw->emitirPix($input, 'cobv') : $gw->emitirBoleto($input);
        } catch (CredentialMisconfiguredException | DriverNotSupportedException $e) {
            $this->marcarErro($businessId, $chave);
            throw new FalhaCobranca('sem_configuracao', 'O gateway de cobrança não está configurado para isso.', $e);
        } catch (GatewayUnavailableException $e) {
            $this->marcarErro($businessId, $chave);
            throw new FalhaCobranca('provedor_indisponivel', 'O banco não respondeu. Tente de novo em instantes.', $e);
        } catch (InvalidPayerException $e) {
            $this->marcarErro($businessId, $chave);
            throw new FalhaCobranca('pagador_invalido', 'O cadastro do cliente não serve para cobrança (documento ou endereço).', $e);
        } catch (IdempotencyConflictException $e) {
            throw new FalhaCobranca('ja_existe', 'Já existe uma cobrança para este documento.', $e);
        } catch (PaymentGatewayException $e) {
            $this->marcarErro($businessId, $chave);
            report($e);
            throw new FalhaCobranca('provedor_indisponivel', 'Não foi possível gerar a cobrança.', $e);
        }

        return (int) $res->cobrancaId;
    }

    public function consultar(int $businessId, int $cobrancaId): void
    {
        $cobranca = $this->cobranca($businessId, $cobrancaId);
        if (! in_array($cobranca->getAttribute('status'), ['pending', 'emitida', 'vencida'], true)) {
            return;
        }
        try {
            $st = $this->gateway->consultar($cobranca);
        } catch (PaymentGatewayException $e) {
            throw new FalhaCobranca('provedor_indisponivel', 'O banco não respondeu. Tente de novo em instantes.', $e);
        }
        if ($st->status === 'paga') {
            $this->reconciliar->marcarPaga(
                $cobranca,
                $businessId,
                (int) ($st->valorPagoCentavos ?? $cobranca->getAttribute('valor_centavos')),
                $st->pagaEm ?? new \DateTimeImmutable(),
                (string) ($st->formaPagamento ?? 'pix'),
            );
        }
    }

    public function cancelar(int $businessId, int $cobrancaId): void
    {
        $cobranca = $this->cobranca($businessId, $cobrancaId);
        if ($cobranca->getAttribute('status') === 'paga') {
            throw new FalhaCobranca('nao_cancelavel', 'Cobrança já paga não pode ser cancelada.');
        }
        if ($cobranca->getAttribute('status') === 'cancelada') {
            return;
        }
        try {
            $this->gateway->cancelar($cobranca, 'Cancelada pelo app');
        } catch (PaymentGatewayException $e) {
            throw new FalhaCobranca('provedor_indisponivel', 'O banco não respondeu. Tente de novo em instantes.', $e);
        }
    }

    private function cobranca(int $businessId, int $cobrancaId): Cobranca
    {
        $c = Cobranca::query()->withoutGlobalScopes() // SUPERADMIN: API sem sessão; business_id explícito abaixo.
            ->where('business_id', $businessId)
            ->where('status', '!=', 'erro')
            ->find($cobrancaId);
        if (! $c) {
            throw new FalhaCobranca('nao_encontrado', 'Cobrança não encontrada.');
        }

        return $c;
    }

    /** Linha `pending` que o serviço criou antes de o provedor falhar vira `erro` (fora da lista). */
    private function marcarErro(int $businessId, string $chave): void
    {
        DB::table('cobrancas')
            ->where('business_id', $businessId)
            ->where('idempotency_key', $chave)
            ->where('status', 'pending')
            ->update(['status' => 'erro', 'updated_at' => now()]);
    }
}
