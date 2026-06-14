<?php

declare(strict_types=1);

namespace Modules\Financeiro\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Models\Cobranca;

/**
 * Auto-baixa: cobrança paga → Titulo a receber + TituloBaixa quitada em fin_titulos.
 *
 * ADR 0170 Onda 5 SIMPLIFICADA — integração Financeiro pedida por Wagner
 * 2026-05-19 ("fazer integrar com o financeiro").
 *
 * Escopo conservador (Onda 5 inicial):
 *   - Processa SOMENTE cobrancas.business_id=1 (Wagner — dogfooding SaaS).
 *     Cobranças de tenants emitidas para clientes finais deles entram em
 *     ondas separadas (Sells/Invoice listener — fora de escopo aqui).
 *   - Origem 'manual' (enum fin_titulos não inclui 'paymentgateway' — não
 *     mexer no enum agora pra não migrar prod). metadata.source preserva trail.
 *
 * Idempotência:
 *   - Listener verifica se Titulo já existe pra (business_id=1, origem='manual',
 *     origem_id=cobrancaId) antes de inserir.
 *   - TituloBaixa usa idempotency_key UUID derivado de cobranca_id (determinístico).
 *
 * Conta bancária resolver:
 *   1. ContaBancaria.rb_gateway_credential_id == cobranca.payment_gateway_credential_id
 *      (assume FK foi reusada na migração credentials — Onda 2 do roadmap PaymentGateway)
 *   2. Fallback: ContaBancaria.business_id=1 + ativo_para_boleto=true (primeira)
 *   3. Se nenhuma resolve: cria Titulo SEM Baixa + log warning (Wagner reconcilia)
 *
 * Multi-tenant Tier 0: somente biz=1 — sem cross-tenant write.
 */
final class OnCobrancaPagaCreateFinanceiroTitulo
{
    public function handle(CobrancaPaga $event): void
    {
        if ($event->businessId !== (int) config('app.saas_owner_business_id')) {
            return; // Onda 5 dogfooding processa só o business dono (SaaS)
        }

        // SUPERADMIN: listener SaaS roda sem sessão; resolve a Cobranca pelo id do evento sem depender do scope de tenant
        $cobranca = Cobranca::withoutGlobalScopes()->find($event->cobrancaId);
        if (!$cobranca) {
            Log::error('[onda5][financeiro] CobrancaPaga sem registro Cobranca correspondente', [
                'cobranca_id' => $event->cobrancaId,
            ]);

            return;
        }

        // Gerar Boleto no drawer (2026-06-08): cobrança nascida de um título que
        // JÁ existe (origem_type='fin_titulo', botão "Gerar boleto" da Visão
        // Unificada) → dá BAIXA nesse título em vez de criar PG-xxx, senão o
        // recebível contaria em DOBRO (título original aberto + PG-xxx quitado).
        if ($cobranca->origem_type === 'fin_titulo' && $cobranca->origem_id) {
            $this->baixarTituloExistente($event, $cobranca);

            return;
        }

        $tituloExistente = Titulo::where('business_id', 1)
            ->where('origem', 'manual')
            ->where('origem_id', $event->cobrancaId)
            ->first();

        if ($tituloExistente) {
            return; // Idempotência — listener já processou
        }

        $contaBancariaId = $this->resolveContaBancaria($cobranca);

        DB::transaction(function () use ($event, $cobranca, $contaBancariaId): void {
            $valor = $cobranca->valor_centavos / 100;
            $valorPago = ($cobranca->valor_pago_centavos ?? $cobranca->valor_centavos) / 100;
            $pagaEm = $event->pagaEm;
            $clienteDescricao = $cobranca->payer_name
                ?? ($cobranca->descricao !== '' ? $cobranca->descricao : null);

            $titulo = Titulo::create([
                'business_id'        => 1,
                'numero'             => 'PG-' . $event->cobrancaId,
                'tipo'               => 'receber',
                'status'             => $contaBancariaId ? 'quitado' : 'aberto',
                'cliente_id'         => $cobranca->contact_id,
                'cliente_descricao'  => $clienteDescricao,
                'valor_total'        => $valor,
                'valor_aberto'       => $contaBancariaId ? 0 : $valor,
                'moeda'              => 'BRL',
                'emissao'            => $cobranca->created_at?->toDateString() ?? $pagaEm->format('Y-m-d'),
                'vencimento'         => $cobranca->vencimento->toDateString(),
                'competencia_mes'    => $pagaEm->format('Y-m'),
                'origem'             => 'manual',
                'origem_id'          => $event->cobrancaId,
                'observacoes'        => 'PaymentGateway cobrança #' . $event->cobrancaId
                    . ($cobranca->origem_type === 'subscription_license'
                        ? ' (Mensalidade SaaS)' : ''),
                'metadata'           => [
                    'source'                       => 'paymentgateway_cobranca',
                    'cobranca_id'                  => $event->cobrancaId,
                    'cobranca_origem_type'         => $cobranca->origem_type,
                    'cobranca_origem_id'           => $cobranca->origem_id,
                    'cobranca_tipo'                => $cobranca->tipo,
                    'cobranca_forma_pagamento'     => $cobranca->forma_pagamento,
                    'gateway_external_id'          => $cobranca->gateway_external_id,
                    'payment_gateway_credential_id' => $cobranca->payment_gateway_credential_id,
                ],
                'created_by'         => 1, // Wagner — listener roda em contexto webhook
            ]);

            if (!$contaBancariaId) {
                Log::warning('[onda5][financeiro] Titulo criado SEM Baixa (conta bancaria nao resolvida)', [
                    'cobranca_id' => $event->cobrancaId,
                    'titulo_id'   => $titulo->id,
                    'payment_gateway_credential_id' => $cobranca->payment_gateway_credential_id,
                ]);

                return;
            }

            TituloBaixa::create([
                'business_id'        => 1,
                'titulo_id'          => $titulo->id,
                'conta_bancaria_id'  => $contaBancariaId,
                'valor_baixa'        => $valorPago,
                'juros'              => 0,
                'multa'              => 0,
                'desconto'           => 0,
                'data_baixa'         => $pagaEm->format('Y-m-d'),
                'meio_pagamento'     => $this->mapMeioPagamento($cobranca->tipo, $cobranca->forma_pagamento),
                'idempotency_key'    => $this->buildIdempotencyKey($event->cobrancaId),
                'observacoes'        => 'Auto-baixa via PaymentGateway cobranca #' . $event->cobrancaId,
                'created_by'         => 1,
            ]);
        });
    }

    /**
     * Baixa um título PRÉ-EXISTENTE quando a cobrança nasceu dele (origem_type
     * 'fin_titulo' — botão "Gerar boleto" do drawer). Cria a TituloBaixa e
     * fecha o título, sem criar título novo (evita duplo-recebível).
     *
     * Idempotente via idempotency_key determinístico da TituloBaixa.
     */
    private function baixarTituloExistente(CobrancaPaga $event, Cobranca $cobranca): void
    {
        $titulo = Titulo::where('business_id', 1)
            ->where('id', $cobranca->origem_id)
            ->first();

        if (!$titulo) {
            Log::warning('[boleto-drawer][financeiro] CobrancaPaga origem fin_titulo sem título correspondente', [
                'cobranca_id' => $event->cobrancaId,
                'titulo_id'   => $cobranca->origem_id,
            ]);

            return;
        }

        if (in_array($titulo->status, ['quitado', 'cancelado'], true)) {
            return; // já resolvido
        }

        $idemKey = $this->buildIdempotencyKey($event->cobrancaId);
        if (TituloBaixa::where('business_id', 1)->where('idempotency_key', $idemKey)->exists()) {
            return; // idempotência — baixa já registrada
        }

        $contaBancariaId = $this->resolveContaBancaria($cobranca);
        $valorPago = ($cobranca->valor_pago_centavos ?? $cobranca->valor_centavos) / 100;
        $pagaEm = $event->pagaEm;

        DB::transaction(function () use ($titulo, $contaBancariaId, $valorPago, $pagaEm, $event, $cobranca, $idemKey): void {
            if ($contaBancariaId) {
                TituloBaixa::create([
                    'business_id'       => 1,
                    'titulo_id'         => $titulo->id,
                    'conta_bancaria_id' => $contaBancariaId,
                    'valor_baixa'       => $valorPago,
                    'juros'             => 0,
                    'multa'             => 0,
                    'desconto'          => 0,
                    'data_baixa'        => $pagaEm->format('Y-m-d'),
                    'meio_pagamento'    => $this->mapMeioPagamento($cobranca->tipo, $cobranca->forma_pagamento),
                    'idempotency_key'   => $idemKey,
                    'observacoes'       => 'Auto-baixa boleto drawer · cobrança #' . $event->cobrancaId,
                    'created_by'        => 1,
                ]);
            }

            $novoAberto = round(((float) $titulo->valor_aberto) - $valorPago, 2);
            $titulo->valor_aberto = max(0, $novoAberto);
            if ($contaBancariaId && $titulo->valor_aberto <= 0.001) {
                $titulo->status = 'quitado';
            }
            $titulo->metadata = array_merge($titulo->metadata ?? [], [
                'boleto_pago' => [
                    'cobranca_id' => $event->cobrancaId,
                    'paga_em'     => $pagaEm->format('Y-m-d'),
                ],
            ]);
            $titulo->save();
        });

        if (!$contaBancariaId) {
            Log::warning('[boleto-drawer][financeiro] Título NÃO baixado (conta bancária não resolvida)', [
                'cobranca_id' => $event->cobrancaId,
                'titulo_id'   => $titulo->id,
            ]);
        }
    }

    /**
     * Resolve a conta bancária que recebeu a cobrança.
     *
     * Canon Onda 5 (Wagner 2026-05-19) — resolve em 3 camadas:
     *   1. CANON: payment_gateway_credentials.conta_bancaria_id (wizard step 3)
     *      → fin_contas_bancarias.account_id matchando
     *   2. LEGACY: fin_contas_bancarias.payment_gateway_credential_id (FK reverso)
     *   3. FALLBACK: primeira ContaBancaria ativa em biz=1
     */
    private function resolveContaBancaria(Cobranca $cobranca): ?int
    {
        if ($cobranca->payment_gateway_credential_id !== null) {
            // 1. Canon: credencial → conta_bancaria_id (Account UPOS) → fin_contas_bancarias
            // SUPERADMIN: credencial do gateway pertence a biz=1 (dono SaaS); lookup cross-tenant pra mapear a conta bancária da cobrança
            $accountId = \Modules\PaymentGateway\Models\PaymentGatewayCredential::query()
                ->withoutGlobalScopes()
                ->where('id', $cobranca->payment_gateway_credential_id)
                ->value('conta_bancaria_id');

            if ($accountId !== null) {
                $conta = ContaBancaria::where('business_id', 1)
                    ->where('account_id', $accountId)
                    ->first();
                if ($conta) {
                    return (int) $conta->id;
                }
            }

            // 2. Legacy: FK reverso
            $conta = ContaBancaria::where('business_id', 1)
                ->where('payment_gateway_credential_id', $cobranca->payment_gateway_credential_id)
                ->first();
            if ($conta) {
                return (int) $conta->id;
            }
        }

        // 3. Fallback
        $conta = ContaBancaria::where('business_id', 1)
            ->where('ativo_para_boleto', true)
            ->first();

        return $conta ? (int) $conta->id : null;
    }

    private function mapMeioPagamento(?string $cobrancaTipo, ?string $formaPagamento): string
    {
        if ($formaPagamento === 'pix' || in_array($cobrancaTipo, ['pix_cob', 'pix_cobv', 'pix_recv'], true)) {
            return 'pix';
        }
        if ($formaPagamento === 'boleto' || $cobrancaTipo === 'boleto') {
            return 'boleto';
        }
        if ($formaPagamento === 'cartao' || $cobrancaTipo === 'card') {
            return 'cartao_credito';
        }

        return 'outro';
    }

    /**
     * UUID determinístico baseado em cobrancaId — garante idempotência
     * mesmo se listener rodar 2x (uk_baixa_idempotency previne dupla baixa).
     *
     * Formato 36-char UUID derivado de md5 (não criptográfico — apenas anti-dupla
     * dentro do mesmo business_id pelo (business_id, idempotency_key) unique).
     */
    private function buildIdempotencyKey(int $cobrancaId): string
    {
        $hash = md5('paymentgateway.onda5.cobranca-' . $cobrancaId);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }
}
