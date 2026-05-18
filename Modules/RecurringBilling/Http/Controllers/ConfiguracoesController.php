<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\RecurringBilling\Models\BoletoCredential;

/**
 * Controller — Page Configurações Cobrança Recorrente (Inertia React).
 *
 * Onda 8 v9,75 — primeira página de configurações read-only:
 *   - Gateways de boleto/pix cadastrados (BoletoCredential por business)
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
    /**
     * GET /recurring-billing/configuracoes — Page Inertia Configurações.
     *
     * Props:
     *   - gateways    (defer)  — lista BoletoCredential do business_id
     *   - regua_dunning (eager) — config hardcoded leitura-only
     *   - nfe_auto    (eager)  — boolean stub leitura-only
     *   - webhooks    (eager)  — URLs canônicas Asaas + Inter PJ PIX
     */
    public function index(Request $request): InertiaResponse
    {
        $businessId = (int) session('user.business_id');

        return Inertia::render('RecurringBilling/Configuracoes/Index', [
            // Régua de dunning canônica v1 (read-only — Onda 8 expõe, Ondas
            // futuras tornam editável via US-RB-XXX e materializam como
            // rb_dunning_rules table per-business). Espelha pattern catalogado
            // em CAPTERRA-FICHA.md (Stripe Smart Retries / Asaas reenvios).
            'regua_dunning' => [
                'descricao' => 'Régua padrão de cobrança em caso de falha de pagamento. '
                    . 'Cada retentativa dispara dunning (boleto novo, novo email, novo WhatsApp). '
                    . 'Após a 3ª retentativa, a assinatura entra em past_due → fail e exige ação manual.',
                'retentativas' => [
                    [
                        'ordem'      => 1,
                        'dias'       => 3,
                        'rotulo'     => '1ª retentativa',
                        'descricao'  => 'Soft decline — provavelmente saldo insuficiente ou intermitência gateway.',
                        'severidade' => 'info',
                    ],
                    [
                        'ordem'      => 2,
                        'dias'       => 7,
                        'rotulo'     => '2ª retentativa',
                        'descricao'  => 'Cobrança persistente — emite dunning escalado (email + WhatsApp).',
                        'severidade' => 'warn',
                    ],
                    [
                        'ordem'      => 3,
                        'dias'       => 15,
                        'rotulo'     => '3ª retentativa (final)',
                        'descricao'  => 'Falha definitiva — assinatura vira past_due → fail, requer ação manual no detail drawer.',
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
                'descricao'  => 'Quando uma fatura é paga (Asaas/Inter PJ webhook), '
                    . 'o sistema dispara automaticamente a emissão de NFe via Módulo NfeBrasil. '
                    . 'Requer plano com fiscal_type = nfe ou nfse cadastrado.',
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
                    'url'         => url('/webhooks/asaas/' . $businessId),
                    'auth'        => 'Header X-Webhook-Token configurado no Asaas (Account → Integrações → Webhooks).',
                    'docs_link'   => 'https://docs.asaas.com/docs/sobre-os-webhooks',
                ],
                [
                    'gateway'     => 'inter',
                    'gateway_label' => 'Banco Inter PJ',
                    'rotulo'      => 'Webhook PIX recebido (pix.recebido)',
                    'metodo'      => 'POST',
                    'url'         => url('/webhooks/inter/pix/' . $businessId),
                    'auth'        => 'Header X-Inter-Webhook-Secret validado contra config_json.webhook_secret da credencial Inter ativa.',
                    'docs_link'   => 'https://developers.bancointer.com.br/v4/reference/criarwebhookpix',
                ],
            ],

            // Gateways de boleto/pix cadastrados — defer porque toca DB
            // (skill inertia-defer-default Tier B). Scopo multi-tenant Tier 0
            // via HasBusinessScope automático em BoletoCredential.
            'gateways' => Inertia::defer(function () use ($businessId) {
                return BoletoCredential::query()
                    ->where('business_id', $businessId)
                    ->orderByDesc('ativo')
                    ->orderBy('banco')
                    ->get(['id', 'banco', 'ambiente', 'ativo', 'nome_display', 'conta_bancaria_id', 'created_at'])
                    ->map(fn (BoletoCredential $c) => [
                        'id'                => $c->id,
                        'banco'             => $c->banco,
                        'banco_label'       => match ($c->banco) {
                            'inter' => 'Banco Inter PJ',
                            'c6'    => 'C6 Bank',
                            'asaas' => 'Asaas',
                            default => ucfirst($c->banco),
                        },
                        'ambiente'          => $c->ambiente,
                        'ambiente_label'    => $c->ambiente === 'production' ? 'Produção' : 'Sandbox',
                        'ativo'             => (bool) $c->ativo,
                        'nome_display'      => $c->nome_display,
                        'conta_bancaria_id' => $c->conta_bancaria_id,
                        'criado_em'         => optional($c->created_at)->toIso8601String(),
                    ])
                    ->values()
                    ->toArray();
            }),
        ]);
    }
}
