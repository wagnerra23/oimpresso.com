<?php

declare(strict_types=1);

namespace Modules\Financeiro\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Modules\Financeiro\Services\LinkDePagamentoDoTitulo;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Services\DescontoPontualidade;

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
            // US-FIN-081: fora da empresa dona do SaaS, só o título que tem link de pagamento recebe
            // baixa — e só com a flag ligada. Desligada, nada muda em relação a antes.
            $this->baixarTituloComLink($event);

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
            $this->baixarTituloExistente($event, $cobranca, 1, 1);

            return;
        }

        $tituloExistente = Titulo::where('business_id', 1)
            ->where('origem', 'manual')
            ->where('origem_id', $event->cobrancaId)
            ->first();

        if ($tituloExistente) {
            return; // Idempotência — listener já processou
        }

        $contaBancariaId = $this->resolveContaBancaria($cobranca, 1);

        DB::transaction(function () use ($event, $cobranca, $contaBancariaId): void {
            $valor = $cobranca->valor_centavos / 100;
            $pagoCentavos = (int) ($cobranca->valor_pago_centavos ?? $cobranca->valor_centavos);
            $valorPago = $pagoCentavos / 100;
            $pagaEm = $event->pagaEm;
            $desconto = $this->descontoReconhecido($cobranca, (int) $cobranca->valor_centavos, $pagoCentavos, $pagaEm) / 100;
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
                // valor_baixa é o principal abatido; o recebido é valor_baixa + juros + multa − desconto
                // (Dashboard, Relatórios, Impostos, ExportacaoContabilService). Com desconto de pontualidade
                // o cliente pagou menos que o principal: o desconto entra à parte, não no valor_baixa.
                'valor_baixa'        => round($valorPago + $desconto, 2),
                'juros'              => 0,
                'multa'              => 0,
                'desconto'           => $desconto,
                'data_baixa'         => $pagaEm->format('Y-m-d'),
                'meio_pagamento'     => $this->mapMeioPagamento($cobranca->tipo, $cobranca->forma_pagamento),
                'idempotency_key'    => $this->buildIdempotencyKey($event->cobrancaId),
                'observacoes'        => 'Auto-baixa via PaymentGateway cobranca #' . $event->cobrancaId,
                'created_by'         => 1,
            ]);
        });
    }

    /**
     * US-FIN-081 — baixa fora da empresa dona do SaaS, restrita ao título que tem link de pagamento.
     *
     * Só com `financeiro.link_pagamento.enabled`; a cobrança é conferida pelo business_id do evento
     * e precisa ter nascido do próprio título. O resto é o mesmo caminho da empresa dona.
     */
    private function baixarTituloComLink(CobrancaPaga $event): void
    {
        if (! LinkDePagamentoDoTitulo::ligado() || $event->origemType !== 'fin_titulo' || ! $event->origemId) {
            return;
        }
        $biz = $event->businessId;
        // SUPERADMIN: webhook roda sem sessão; a cobrança é lida pelo id E pelo business_id do evento.
        $cobranca = Cobranca::withoutGlobalScopes()->where('business_id', $biz)->find($event->cobrancaId);
        if (! $cobranca || $cobranca->origem_type !== 'fin_titulo' || (int) $cobranca->origem_id !== $event->origemId
            || ! LinkDePagamentoDoTitulo::temLink($biz, $event->origemId)) {
            return;
        }
        // created_by é NOT NULL: a baixa fica em nome de quem gerou o link (senão, o 1º usuário da empresa).
        $autor = (int) (DB::table('fin_titulo_links_pagamento')->where('business_id', $biz)->where('titulo_id', $event->origemId)
            ->whereNotNull('created_by')->orderByDesc('id')->value('created_by')
            ?? DB::table('users')->where('business_id', $biz)->orderBy('id')->value('id'));
        if ($autor <= 0) {
            Log::warning('[link-pagamento][financeiro] título não baixado: empresa sem usuário', ['cobranca_id' => $event->cobrancaId]);

            return;
        }

        $this->baixarTituloExistente($event, $cobranca, $biz, $autor);
    }

    /**
     * Baixa um título PRÉ-EXISTENTE quando a cobrança nasceu dele (origem_type
     * 'fin_titulo' — botão "Gerar boleto" do drawer). Cria a TituloBaixa e
     * fecha o título, sem criar título novo (evita duplo-recebível).
     *
     * Idempotente via idempotency_key determinístico da TituloBaixa.
     */
    private function baixarTituloExistente(CobrancaPaga $event, Cobranca $cobranca, int $biz, int $autor): void
    {
        // SUPERADMIN: listener sem sessão; o business_id vem explícito (dono do SaaS ou o da cobrança).
        $titulo = Titulo::withoutGlobalScopes()->where('business_id', $biz)
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
        // SUPERADMIN: idem acima — escopo pelo business_id explícito.
        if (TituloBaixa::withoutGlobalScopes()->where('business_id', $biz)->where('idempotency_key', $idemKey)->exists()) {
            return; // idempotência — baixa já registrada
        }

        $contaBancariaId = $this->resolveContaBancaria($cobranca, $biz);
        $pagoCentavos = (int) ($cobranca->valor_pago_centavos ?? $cobranca->valor_centavos);
        $valorPago = $pagoCentavos / 100;
        $pagaEm = $event->pagaEm;
        // P-086: desconto de pontualidade só entra se o pagamento chegou até a data limite.
        $desconto = $this->descontoReconhecido($cobranca, (int) round(((float) $titulo->valor_aberto) * 100), $pagoCentavos, $pagaEm) / 100;

        DB::transaction(function () use ($titulo, $contaBancariaId, $valorPago, $desconto, $pagaEm, $event, $cobranca, $idemKey, $biz, $autor): void {
            if ($contaBancariaId) {
                TituloBaixa::create([
                    'business_id'       => $biz,
                    'titulo_id'         => $titulo->id,
                    'conta_bancaria_id' => $contaBancariaId,
                    'valor_baixa'       => round($valorPago + $desconto, 2), // principal; desconto à parte (ver acima)
                    'juros'             => 0,
                    'multa'             => 0,
                    'desconto'          => $desconto,
                    'data_baixa'        => $pagaEm->format('Y-m-d'),
                    'meio_pagamento'    => $this->mapMeioPagamento($cobranca->tipo, $cobranca->forma_pagamento),
                    'idempotency_key'   => $idemKey,
                    'observacoes'       => 'Auto-baixa boleto drawer · cobrança #' . $event->cobrancaId,
                    'created_by'        => $autor,
                ]);
            }

            $novoAberto = round(((float) $titulo->valor_aberto) - $valorPago - ($contaBancariaId ? $desconto : 0), 2);
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
    private function resolveContaBancaria(Cobranca $cobranca, int $biz = 1): ?int
    {
        if ($cobranca->payment_gateway_credential_id !== null) {
            // 1. Canon: credencial → conta_bancaria_id (Account UPOS) → fin_contas_bancarias
            // SUPERADMIN: credencial do gateway pertence a biz=1 (dono SaaS); lookup cross-tenant pra mapear a conta bancária da cobrança
            $accountId = \Modules\PaymentGateway\Models\PaymentGatewayCredential::query()
                ->withoutGlobalScopes()
                ->where('id', $cobranca->payment_gateway_credential_id)
                ->value('conta_bancaria_id');

            if ($accountId !== null) {
                $conta = ContaBancaria::where('business_id', $biz)
                    ->where('account_id', $accountId)
                    ->first();
                if ($conta) {
                    return (int) $conta->id;
                }
            }

            // 2. Legacy: FK reverso
            $conta = ContaBancaria::where('business_id', $biz)
                ->where('payment_gateway_credential_id', $cobranca->payment_gateway_credential_id)
                ->first();
            if ($conta) {
                return (int) $conta->id;
            }
        }

        // 3. Fallback
        $conta = ContaBancaria::where('business_id', $biz)
            ->where('ativo_para_boleto', true)
            ->first();

        return $conta ? (int) $conta->id : null;
    }

    /**
     * P-086 — desconto de pontualidade que a baixa reconhece, em centavos.
     *
     * Lê o desconto que foi ao banco (gravado na cobrança na emissão) e só o aceita se o
     * pagamento chegou até a data limite e cobre o valor menos o desconto. Fora disso, 0:
     * o título fica com o restante em aberto, como antes.
     */
    private function descontoReconhecido(Cobranca $cobranca, int $abertoCentavos, int $pagoCentavos, \DateTimeImmutable $pagaEm): int
    {
        $desconto = $cobranca->payload_gateway['desconto_pontualidade'] ?? null;
        if (! is_array($desconto)) {
            return 0;
        }

        return DescontoPontualidade::reconhecidoNaBaixaCentavos($desconto, $abertoCentavos, $pagoCentavos, $pagaEm);
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
