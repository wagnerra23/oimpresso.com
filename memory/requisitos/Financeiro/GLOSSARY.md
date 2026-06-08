# Glossário — Financeiro

> Vocabulário do domínio financeiro brasileiro, contextualizado pelo módulo.

## Conceitos contábeis

- **Título** — direito a receber (`tipo=receber`) ou obrigação a pagar (`tipo=pagar`). Persistido em `fin_titulos`.
- **Baixa** — registro de pagamento parcial ou total de um título. Persistido em `fin_titulo_baixas`. SEMPRE tem `idempotency_key` único.
- **Estorno** — anulação de baixa via row negativa apontando pra original. NUNCA hard delete.
- **Aging** — bucket de inadimplência por prazo de vencimento (`<30d / 30-60d / 60-90d / >90d / >180d`).
- **Regime contábil** — `caixa` (receita = data da baixa) vs `competência` (receita = data da emissão). Configurado por business; afeta DRE.
- **Plano de contas** — estrutura hierárquica padrão Receita Federal/DCASP que organiza receitas, custos, despesas, etc. Pré-seedado com 47 contas.
- **DRE** — Demonstração de Resultado do Exercício. Relatório periódico com Receita → Custo → Despesa → Lucro Líquido.
- **Razão** — extrato analítico de uma conta específica em um período.
- **Aging** — bucket de inadimplência (`<30d / 30-60d / 60-90d / >90d / >180d`).
- **Conciliação** — processo de bater extrato bancário (OFX) com lançamentos do oimpresso.
- **Saldo projetado** — saldo de caixa estimado para data futura, considerando todos os títulos abertos com vencimento até essa data.

## Meios de pagamento BR

- **Boleto** — instrumento de cobrança bancário com linha digitável + código de barras + agora QR-PIX (boleto híbrido). Pode ser emitido via CNAB direto (cliente↔banco) ou via gateway (Asaas, Iugu, Pagar.me).
- **CNAB 240** — formato remessa/retorno bancário em arquivo texto, 240 caracteres por linha. Padrão FEBRABAN. Usado por bancos grandes (BB, Itaú, Bradesco).
- **CNAB 400** — formato CNAB legacy (400 chars). Cobre Caixa Econômica e bancos médios.
- **PIX** — sistema de pagamentos instantâneo do BCB. Tem cobrança imediata (QR estático) e cobrança com vencimento (QR dinâmico).
- **PIX Automático** — autorização recorrente do BCB (2025+) — payer autoriza débito recorrente como Direct Debit. Detalhado em `RecurringBilling/`.
- **TED / DOC** — transferência interbancária. TED é mesmo dia, DOC é D+1. Em queda livre desde PIX.
- **Linha digitável** — string de 47 dígitos do boleto (campo livre + código de barras formatado).

## Termos fiscais

- **NF-e** — Nota Fiscal Eletrônica. B2B, modelo 55. Detalhado em `NfeBrasil/`.
- **NFC-e** — Nota Fiscal de Consumidor Eletrônica. B2C ponto-de-venda, modelo 65.
- **NFS-e** — Nota Fiscal de Serviço Eletrônica. Municipal hoje, federal a partir de 2026 (Lei Complementar 214/2025).
- **DAS** — Documento de Arrecadação do Simples Nacional. Imposto unificado.
- **Juros de mora** — taxa diária por atraso. Padrão BR: 0,033% a.d. (1% ao mês). Configurável por business.
- **Multa atraso** — % adicional fixa ao atraso. Padrão BR: 2% (Código de Defesa do Consumidor).

## Conceitos UltimatePOS específicos

- **business_id** — tenant. Toda query do módulo tem `where('business_id', session('user.business_id'))`.
- **Transaction** (core UltimatePOS) — venda, compra, despesa, ajuste de estoque, etc. Tipo (`type`) decide. Financeiro escuta esses eventos pra criar título.
- **transaction_payment** (core) — registro de pagamento de uma `Transaction`. Quando o módulo Financeiro baixa um título, **também** cria/atualiza `transaction_payment` correspondente (se título veio de venda/compra) — evita 2 fontes de verdade.
- **session('business_timezone')** — timezone do business. NÃO usar `session('business.time_zone')` (Eloquent retorna null) — ver `auto-memória: project_session_business_model.md`.
- **format_now_local()** — helper pra "agora" sem shift +3h. NÃO usar `format_date(now())` que tem shift histórico intencional.
- **Spatie role format** — `{Nome}#{biz_id}`, ex: `Vendas#4` pra ROTA LIVRE.

## Idempotência e eventos

- **idempotency_key** — UUID por mutação que garante que retentativa não duplique.
- **event_id** — ID do webhook do gateway (Asaas: `id`; Iugu: `id`). Persistido em `pg_webhook_events.event_id` UNIQUE.
- **at-least-once** — garantia mínima de webhook gateway. Sempre receber 1+ vezes.
- **Strategy pattern** — escolha de implementação por config do business (ex: `BoletoStrategy` = `GatewayStrategy` ou `CnabDirectStrategy`).

## Revenue model

- **Subscription** — mensalidade fixa (Free / Pro / Enterprise).
- **Take rate** — % sobre valor processado. Ver ADR ARQ-0004 (0,5% capped R$ 9,90 só em Gateway).
- **Quota** — limite de uso por mês (50 títulos free / 500 pro / ilimitado enterprise).
- **GMV** (Gross Merchandise Value) — volume total processado. Métrica de saúde do tenant.

## Acrônimos

- **OFX** — Open Financial Exchange (formato extrato bancário)
- **CNAB** — Centro Nacional de Automação Bancária (FEBRABAN)
- **DRE** — Demonstração de Resultado do Exercício
- **GMV** — Gross Merchandise Value
- **PSP** — Payment Service Provider (Asaas, Iugu, etc.)
- **MoR** — Merchant of Record
- **JTBD** — Jobs To Be Done
- **DCASP** — Plano de Contas Aplicado ao Setor Público (referência para plano BR)
