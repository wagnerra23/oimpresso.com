# RecurringBilling — Comparativo Concorrência (estilo Capterra)

**Última atualização:** 2026-04-25 | **Próx. revisão:** 2026-07-25

## Sobre o módulo

| Campo | Valor |
|---|---|
| **Best for** | "PMEs/SaaS BR com cobrança recorrente (assinatura, mensalidade, contrato)" |
| **Setor** | Subscription billing + cobrança recorrente |
| **Stage** | Spec-ready (sem código), promovido 2026-04-24 |
| **Persona** | Larissa-financeiro + Gestor SaaS/escola/academia |
| **JTBD** | "Cobrar mensalidade recorrente sem esquecer ninguém + retry automático em falha" |

## Cards comparados

### 🟢 RecurringBilling (oimpresso)
- ⭐ **Score:** 0/100 (não implementado)
- 💰 **Preço planejado:** Free / R$ 99 Pro / R$ 299 Enterprise + take rate 1% capped R$ 19,90
- 🎯 **Best for:** Tenant UPos com mensalidade (academia, escola, contrato serviço)
- ✨ **Diferencial planejado:** Integrado Financeiro + UPos sem dupla licença
- ☁️ Cloud

### 🔴 Vindi
- ⭐ **Capterra:** 4,5/5 (~250 reviews)
- 💰 1,99% por transação + R$ 0,99 mensalidade
- 🎯 **Best for:** SaaS BR maduro, líder mid-market
- ✨ **Diferencial:** Dunning management maduro + retry inteligente
- ☁️ Cloud

### 🔴 Pagar.me Subscription
- ⭐ **Capterra:** 4,3/5 (~180 reviews)
- 💰 1,99% + R$ 0,30 por transação
- 🎯 **Best for:** Marketplace + e-commerce
- ✨ **Diferencial:** Suite Pagar.me completa (PIX/cartão/boleto unificados)
- ☁️ Cloud

### 🟡 Asaas Subscription
- ⭐ **Capterra:** 4,4/5 (~200 reviews)
- 💰 1,99% recorrência + R$ 1,49/cobrança
- 🎯 **Best for:** Negócio físico (academia, escola)
- ✨ **Diferencial:** Boleto recorrente sem fricção + cobrança automática SMS/WhatsApp
- ☁️ Cloud

### 🟡 Iugu
- ⭐ **Capterra:** 4,2/5 (~120 reviews)
- 💰 2,99% PIX/cartão + R$ 0,40
- 🎯 **Best for:** SaaS startup
- ✨ **Diferencial:** API dev-friendly + carteira digital
- ☁️ Cloud

## Matriz de features

| Feature | 🟢 Nós | Vindi | Pagar.me | Asaas | Iugu | Importância |
|---|---|---|---|---|---|---|
| Plano + período (mês/ano) | ❌ planejado | ✅ | ✅ | ✅ | ✅ | **P0** |
| Cartão recorrente | ❌ | ✅ | ✅ | ✅ | ✅ | **P0** |
| PIX recorrente | ❌ | ✅ | ✅ | ✅ | ✅ | **P0** |
| Boleto recorrente | ❌ | ✅ | ✅ | ✅ | ⚠ | **P0** |
| Dunning (retry em falha) | ❌ | ✅ killer | ⚠ | ⚠ | ⚠ | **P0** |
| Lembrete SMS/WhatsApp | ❌ | ⚠ | ⚠ | ✅ killer | ❌ | P1 |
| Self-service portal | ❌ | ✅ | ✅ | ⚠ | ✅ | P1 |
| Métricas SaaS (MRR/ARR/churn) | ❌ | ✅ | ⚠ | ❌ | ⚠ | P2 |
| Integração Financeiro UPos | ✅ planejado | ❌ | ❌ | ❌ | ❌ | **diferencial** |

## Score (Capterra-style)

| Critério | 🟢 Nós | Vindi | Pagar.me | Asaas | Iugu |
|---|---|---|---|---|---|
| Easy of use | 0 | 8 | 8 | **9** | 7 |
| Customer service | 0 | **9** | 8 | 8 | 7 |
| Features | 0 | **9** | 9 | 8 | 8 |
| Value for money | 0 | 7 | 8 | **9** | 7 |
| Integrations | 0 | **9** | **9** | 8 | 8 |
| Performance | 0 | 8 | **9** | 8 | 8 |
| **Total /60** | **0** | **50** | **51** | **50** | **45** |
| **Score /100** | **0** | **83** | **85** | **83** | **75** |

## Estratégia

### Posicionamento (planejado)
> _"Cobrança recorrente integrada ao seu ERP — mensalidade vira título Financeiro automático."_

### Track imitar
- **MVP:** Plano + período + boleto recorrente + cartão (matar P0)
- **Onda 2:** PIX recorrente + dunning automático
- **Onda 3:** Self-service portal + lembrete WhatsApp

### Track diferenciar
- **Integração Financeiro UPos** (cobrança recorrente vira título Financeiro auto)
- **Sem double-entry** (concorrentes obrigam tenant a sincronizar 2 sistemas)
- **Take rate 1% capped R$ 19,90** (vs Vindi 1,99% sem cap)

### Preço
- Free 10 cobranças/mês (entrada PME)
- Pro R$ 99 sem fee de transação (vs Vindi 1,99% transação)
- Enterprise R$ 299 ilimitado

## Refs

- [Vindi — Capterra](https://www.capterra.com.br/software/.../vindi) (4,5/5)
- [Asaas — Capterra](https://www.capterra.com.br/software/.../asaas) (4,4/5)
- ADR _Ideias/CobrancaRecorrente + roadmap promoção 2026-04-24
