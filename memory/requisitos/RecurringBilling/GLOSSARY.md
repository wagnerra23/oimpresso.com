# Glossário — RecurringBilling

> Vocabulário de billing recorrente brasileiro contextualizado pelo módulo.

## Conceitos básicos

- **Plano** — template de cobrança (`rb_plans`): define ciclo, valor, trial, setup_fee, índice de reajuste
- **Contrato (subscription)** — instância de plano contratada por cliente (`rb_contracts`)
- **Fatura** — cobrança gerada em cada ciclo (`rb_invoices`)
- **Charge attempt** — tentativa de cobrança específica (`pg_charge_attempts`); pode haver várias por fatura (smart retry)
- **Anchor date** — data de aniversário do contrato (afeta `next_billing_date`)
- **Competência** — mês YYYY-MM da fatura; UNIQUE por contrato pra idempotência

## Lifecycle de assinatura

| Status | Significado |
|---|---|
| `trialing` | Em trial (config `trial_days`); não cobra ainda |
| `active` | Cobrando normalmente |
| `past_due` | Última cobrança falhou; tentativas em andamento |
| `unpaid` | Múltiplas falhas; serviço bloqueado |
| `canceled_at_period_end` | Cancelamento agendado pro fim do ciclo atual |
| `canceled` | Encerrado |

## Modelos de cobrança

- **MRR** (Monthly Recurring Revenue) — receita recorrente mensalizada (anual / 12)
- **ARR** (Annual Recurring Revenue) — MRR × 12
- **Churn** — % contratos cancelados / total ativos
- **Net Revenue Retention** — incluindo expansion (upgrades) e contraction (downgrades)
- **GMV** (Gross Merchandise Value) — total processado via gateway próprio (base de take rate)
- **Take rate** — % sobre GMV (ARQ-0004: 0,8% capped R$ 19,90)

## Pagamento

- **Soft decline** — falha temporária (saldo, limite, CVV inválido) — retentável
- **Hard decline** — falha permanente (cartão cancelado, conta encerrada) — não retentar
- **Smart retry** — agendamento ML/regra do retry pra maximizar aprovação
- **Tokenização** — converter PAN em token (provider hospedado); oimpresso nunca toca PAN/CVV
- **Idempotency key** — UUID que garante mesma cobrança não é processada 2x

## Pix Automático (BCB)

- **JRC** — Jornada de Recorrência de Consentimento (3 jornadas oficiais: 1, 2, 3)
- **Jornada 3 (PAYMENTONAPPROVAL)** — autoriza + paga primeira no mesmo QR (ARQ-0003)
- **txid** — identificador único da autorização BCB
- **e2e_id** — End-to-End identifier de uma instrução de pagamento Pix
- **PSP** — Payment Service Provider (Banco do Brasil, Itaú, Bradesco, Sicoob, Woovi, etc.)

## Gateways

- **Provider** — gateway específico (Asaas, Iugu, Pagar.me, Stripe, MercadoPago)
- **Adapter** — implementação concreta da interface `PaymentGatewayInterface`
- **Credential** — API key + webhook secret (`pg_credentials`)
- **At-least-once** — garantia de webhook (provider envia 1+ vezes)
- **Webhook signature** — HMAC do payload assinado com secret

## Merchant of Record

- **MoR** — Merchant of Record (quem aparece pro cliente final no extrato)
- **Gateway próprio** — oimpresso é MoR; controla NFSe; cobra take rate
- **Tenant MoR** — tenant é MoR; tenant emite NFSe; oimpresso só dispara

## Dunning

- **Dunning** — fluxo de recuperação de inadimplência
- **Régua (rule)** — sequência configurada de passos (`dun_rules`)
- **Step** — ação individual (email, SMS, WhatsApp, bloqueio, cancelamento)
- **Campaign** — instância ativa de régua pra invoice falhada
- **Resolution** — termo de fim (paga, manualmente cancelada, auto-cancelada)
- **Recovery rate** — % invoices falhadas recuperadas via dunning (meta 30%+)

## Proração

- **Proration** — cálculo proporcional ao tempo de uso em mudança mid-cycle
- **Upgrade** — mudança pra plano mais caro
- **Downgrade** — mudança pra plano mais barato
- **Credit** — saldo a favor (downgrade ou cancelamento mid-cycle)
- **Debit** — débito adicional (upgrade)
- **Reajuste** — atualização de valor por aniversário (IPCA, IGP-M)

## NFSe

- **NFSe** — Nota Fiscal de Serviço Eletrônica
- **DAMSE** — DANFE para NFSe (PDF imprimível)
- **Provider** — Focus NFe, PlugNotas, NFE.io
- **ABRASF** — padrão nacional NFSe (até LC 214/2025 federalizar)
- **LC 214/2025** — Lei Complementar federaliza NFSe a partir de 2026

## UltimatePOS específico

- **business_id** — tenant
- **contact_id** — cliente final do tenant (em `core contacts`)
- **transaction_id** — venda no core, criada quando InvoicePaid (link retro)
- **session('user.business_id')** — scope multi-tenant em queries
- **events** — RecurringBilling, PaymentGateway, etc. publicam/escutam eventos Laravel

## Compliance

- **PCI DSS** — Payment Card Industry Data Security Standard
- **LGPD** — Lei Geral de Proteção de Dados
- **Reforma Tributária 2026-2033** — CBS/IBS substituirão PIS/COFINS/ICMS/ISS
- **Split-payment** — CBS/IBS retidos na fonte e repassados direto pra Receita

## Acrônimos

- **ARR** — Annual Recurring Revenue
- **ABRASF** — Associação Brasileira das Secretarias de Finanças
- **BCB** — Banco Central do Brasil
- **CBS** — Contribuição sobre Bens e Serviços
- **GMV** — Gross Merchandise Value
- **IBS** — Imposto sobre Bens e Serviços
- **JRC** — Jornada de Recorrência de Consentimento
- **MoR** — Merchant of Record
- **MRR** — Monthly Recurring Revenue
- **NFSe** — Nota Fiscal de Serviço Eletrônica
- **PAN** — Primary Account Number (número do cartão)
- **PCI DSS** — Payment Card Industry Data Security Standard
- **PSP** — Payment Service Provider
- **TXID** — Transaction Identifier (BCB Pix)
