<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Controllers;

use Modules\RecurringBilling\Http\Controllers\Concerns\ExigePermissaoRb;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Controller — Page Configurações Cobrança Recorrente (Inertia React).
 *
 * Onda 8 v9,75 — primeira página de configurações read-only:
 *   - Gateways de boleto/pix cadastrados (credenciais do PaymentGateway por business;
 *     até a onda 3 de 2026-11-24 vinham de rb_boleto_credentials)
 *   - Régua de dunning (cobrança) hardcoded (1ª +3d / 2ª +7d / 3ª +15d)
 *   - NFe-de-boleto-pago automática (toggle stub — US-RB-044 dispara via listener)
 *   - Webhooks (URLs canônicas — botão copy clipboard)
 *
 * Refs:
 *   - Charter: resources/js/Pages/RecurringBilling/Configuracoes/Index.charter.md
 *   - Plano:   memory/requisitos/RecurringBilling/Index-visual-comparison.md (Onda 8)
 *   - ADR 0093 Multi-tenant Tier 0 — session('user.business_id') scopo
 *   - ADR 0104 MWART — read-only stub (escrita em Ondas futuras)
 *   - Skill inertia-defer-default Tier B — gateways usa Inertia::defer
 */
class ConfiguracoesController extends Controller
{
    use ExigePermissaoRb;

    /**
     * GET /recurring-billing/configuracoes — Page Inertia Configurações.
     *
     * Props:
     *   - gateways    (defer)  — credenciais Inter/C6/Asaas do business em payment_gateway_credentials
     *   - regua_dunning (eager) — config hardcoded leitura-only
     *   - nfe_auto    (eager)  — boolean stub leitura-only
     *   - webhooks    (eager)  — URLs canônicas Asaas + Inter PJ PIX
     */
    public function index(Request $request): InertiaResponse
    {
        $this->exigirPermissao('recurringbilling.configuracoes.manage');
        $businessId = (int) session('user.business_id');

        return Inertia::render('RecurringBilling/Configuracoes/Index', [
            // Régua de dunning canônica v1 (read-only — Onda 8 expõe, Ondas
            // futuras tornam editável via US-RB-XXX e materializam como
            // rb_dunning_rules table per-business). Espelha pattern catalogado
            // em CAPTERRA-FICHA.md (Stripe Smart Retries / Asaas reenvios).
            'regua_dunning' => [
                // Copy em português, sem identificador técnico (protótipo rb-config; UC-RBCFG-12).
                'descricao' => 'Régua padrão de cobrança em caso de falha de pagamento. '
                    . 'Após a 3ª retentativa, a assinatura passa para “falhou” e pede ação manual.',
                // Auditoria RB b7 (2026-11-23): o módulo não envia e-mail nem WhatsApp de cobrança
                // (nenhum Mail/Notification/Whatsapp em Modules/RecurringBilling). A tela dizia que
                // cada retentativa mandava os dois; agora diz que os canais não estão disponíveis.
                'canais_aviso' => [
                    ['canal' => 'E-mail', 'disponivel' => false],
                    ['canal' => 'WhatsApp', 'disponivel' => false],
                ],
                'retentativas' => [
                    [
                        'ordem'      => 1,
                        'dias'       => 3,
                        'rotulo'     => '1ª retentativa',
                        'descricao'  => 'Falha leve — provavelmente saldo insuficiente ou instabilidade do banco.',
                        'severidade' => 'info',
                    ],
                    [
                        'ordem'      => 2,
                        'dias'       => 7,
                        'rotulo'     => '2ª retentativa',
                        'descricao'  => 'Cobrança persistente — nova tentativa no gateway.',
                        'severidade' => 'warn',
                    ],
                    [
                        'ordem'      => 3,
                        'dias'       => 15,
                        'rotulo'     => '3ª retentativa (final)',
                        'descricao'  => 'Falha definitiva — a assinatura passa para “falhou” e pede ação manual no detalhe da assinatura.',
                        'severidade' => 'bad',
                    ],
                ],
                'editavel_em' => 'Em breve (Onda futura — régua per-business + per-plan).',
            ],

            // NFe-de-boleto-pago automática (read-only stub) — US-RB-044 implementa
            // listener NfeBrasil escutando InvoicePaid → emite NFe automaticamente.
            // Esta tela só expõe o estado lido (provisório: hardcoded false até
            // listener real estar em produção biz=1).
            'nfe_auto' => [
                'ativo'      => false,
                'descricao'  => 'Quando uma fatura é paga (aviso do Asaas ou do Banco Inter), '
                    . 'o sistema emite a nota fiscal automaticamente. '
                    . 'Vale para planos com emissão NFe ou NFS-e.',
                'us_ref'      => 'US-RB-044',
                'editavel_em' => 'Em breve (Onda futura — toggle por business + per-plan override).',
            ],

            // Webhooks canônicos — URLs que Wagner cola no painel admin
            // do Asaas/Inter PJ durante onboarding da credencial.
            // - Asaas: webhook único por business (config no painel Asaas Account → Integrações)
            // - Inter PJ: webhook PIX por business (config via PUT /webhooks/pix-recebidos)
            'webhooks' => [
                [
                    'gateway'     => 'asaas',
                    'gateway_label' => 'Asaas',
                    'rotulo'      => 'Webhook unificado (pagamento, assinatura, transferência)',
                    'metodo'      => 'POST',
                    // URL e header derivados da rota nomeada e da constante que o
                    // AsaasWebhookController lê — a tela não monta nenhum dos dois à mão.
                    'url'         => route('webhooks.asaas', ['businessId' => $businessId]),
                    'auth_header' => AsaasWebhookController::AUTH_HEADER,
                    'auth'        => 'Header ' . AsaasWebhookController::AUTH_HEADER
                        . ' com o token de autenticação configurado no Asaas (Account → Integrações → Webhooks).',
                    'docs_link'   => 'https://docs.asaas.com/docs/sobre-os-webhooks',
                ],
                [
                    'gateway'     => 'inter',
                    'gateway_label' => 'Banco Inter PJ',
                    'rotulo'      => 'Webhook PIX recebido (pix.recebido)',
                    'metodo'      => 'POST',
                    'url'         => route('webhooks.inter.pix', ['businessId' => $businessId]),
                    'auth_header' => InterWebhookController::AUTH_HEADER,
                    'auth'        => 'Header ' . InterWebhookController::AUTH_HEADER
                        . ' validado contra o segredo de webhook da credencial Inter ativa.',
                    'docs_link'   => 'https://developers.bancointer.com.br/v4/reference/criarwebhookpix',
                ],
            ],

            // Gateways de boleto/pix cadastrados — defer porque toca DB
            // (skill inertia-defer-default Tier B). Desde a onda 3 (2026-11-24) a fonte é a
            // credencial do PaymentGateway, a mesma que cobra (CobrancaNoGateway). Só Inter, C6 e Asaas: são os bancos que o RB usa e que a tela sabe desenhar.
            // Tier 0: global scope HasBusinessScope + where explícito. select() fechado: o
            // config_json (segredos) nunca sai daqui.
            'gateways' => Inertia::defer(function () use ($businessId) {
                return PaymentGatewayCredential::query()
                    ->where('business_id', $businessId)
                    ->whereIn('gateway_key', ['inter', 'c6', 'asaas'])
                    ->orderByDesc('ativo')
                    ->orderBy('gateway_key')
                    ->get(['id', 'gateway_key', 'ambiente', 'ativo', 'nome_display', 'conta_bancaria_id', 'created_at'])
                    ->map(fn (PaymentGatewayCredential $c) => [
                        'id'                => $c->id,
                        'banco'             => $c->gateway_key,
                        'banco_label'       => match ($c->gateway_key) {
                            'inter' => 'Banco Inter PJ',
                            'c6'    => 'C6 Bank',
                            'asaas' => 'Asaas',
                        },
                        'ambiente'          => $c->ambiente,
                        'ambiente_label'    => $c->ambiente === 'production' ? 'Produção' : 'Sandbox',
                        'ativo'             => (bool) $c->ativo,
                        'nome_display'      => $c->nome_display,
                        // No PG a conta é accounts.id, não fin_contas_bancarias.id; a tela não usa o campo.
                        'conta_bancaria_id' => $c->conta_bancaria_id,
                        'criado_em'         => optional($c->created_at)->toIso8601String(),
                    ])
                    ->values()
                    ->toArray();
            }),
        ]);
    }
}
