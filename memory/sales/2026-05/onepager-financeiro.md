# oimpresso · Financeiro — Visão Unificada AR/AP

## Problema
Dono de gráfica abre 4 telas pra entender se fechou o mês: contas a receber (AR), contas a pagar (AP), banco/Asaas, e planilha de fluxo de caixa. Cada lugar tem o número parcial. **Ninguém vê o todo na mesma tela.**

## Solução
Tela única **Visão Unificada** que junta:
- AR (faturas a receber, com aging 0-30/30-60/60-90/90+)
- AP (contas a pagar, com vencimento)
- Saldo em conta (Asaas/Inter/C6 — cada conta é "banco virtual" com extrato e boletos recebidos)
- Projeção fluxo 30/60/90 dias

Tudo em **um único Page Inertia** com filtros vivos por cliente/fornecedor/conta. Nada de exportar Excel pra somar.

## Diferenciais únicos
- **Asaas tratado como conta bancária** (saldo + extrato + boletos recebidos batem direto na Visão Unificada — mesmo padrão Inter/C6)
- **Retro-vínculo automático:** boleto pago no Asaas vincula sozinho ao `transaction_payment` da venda original (sem dupla digitação)
- **Multi-tenant Tier 0:** cada gráfica vê só o que é dela (`business_id` global scope, nunca vaza)

## 3 features-killer
1. **Aging visual com cor:** vermelho 90+, laranja 60-90, amarelo 30-60, verde 0-30. Bate o olho e sabe o que tá podre.
2. **Projeção fluxo de caixa em gráfico** — vendas confirmadas + AR previsto - AP previsto, dia a dia.
3. **Drill-down vivo:** clica em "R$ [redacted Tier 0]k a receber > 90 dias" → abre lista dos clientes responsáveis, com botão "abrir cobrança no Asaas" sem sair da tela.

## Pricing tier proposto
- **Starter:** incluso (limite 200 faturas AR ativas)
- **Pro:** incluso + integração Asaas/Inter/C6 nativa
- **Enterprise:** incluso + multi-business consolidação

`[draft — Wagner valida]`

## CTA
"Manda **screenshot da sua planilha atual de fluxo de caixa** que eu te devolvo print da mesma info na Visão Unificada (com seus dados anonimizados)."

---

**Refs internas:** ADR arq/0008 (Asaas como banco), ADR arq/0005 (Financeiro × Accounting paralelo), US-FIN-013 (tela unificada).
