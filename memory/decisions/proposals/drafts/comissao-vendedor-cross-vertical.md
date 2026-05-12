---
status: proposed
date: 2026-05-12
deciders: Wagner, Eliana[E], Felipe
consulted: Cursor (paralelo), GPT/Claude pesquisa mercado
informed: time interno
supersedes: []
superseded_by: []
related: [0093, 0104, 0106, 0129, 0143]
lifecycle: active
tags: [comissao, cross-vertical, fsm, financeiro, sells, comvis, oficina-auto]
---

# ADR DRAFT — Comissão de Vendedores cross-vertical: módulo `Modules/Comissao` plugado na FSM

> **Status:** `proposed` — aguarda revisão Wagner antes de virar ADR numerada.
> **Origem:** consolidação de US-COMVIS-011, US-AUTO-011, US-OFICINA-019, planilha paralela Eliana[E] ROTA LIVRE.
> **Restrição absoluta:** não pode duplicar nem substituir UPos `commission_agent`/`users.cmmsn_percent` (Tier 0 — quebra coexistência legacy).

---

## Contexto

Discovery (cf [SPEC §0](../../../requisitos/Comissao/SPEC.md#0-crucial-comparação-com-o-que-já-existe--não-duplicar)) mostrou que o oimpresso já tem **cinco artefatos de comissão fragmentados**:

1. `transactions.commission_agent` (FK core UPos) — vendedor único
2. `users.cmmsn_percent` (core UPos) — % fixo per usuário
3. `sales_commission_agents` (core UPos) — vendedor externo não-user
4. `essentials_user_sales_targets` (Modules/Essentials) — meta com % linear
5. `ReportController::salesRepresentativeTotalCommission` (relatório single-tier)

Esses cobrem **único cenário**: 1 vendedor, % fixo, sobre faturado OU recebido. Não cobrem:

- Multi-papel (ComVis: vendedor+designer+instalador)
- Tiers escalonados reais (>1 faixa de meta)
- Accelerators (% extra sobre excedente)
- Claw-back automático em cancelamento
- Comissão sobre líquido marketplace (após taxa ML/iFood)
- Cálculo automático pós-pagamento (hoje é planilha Eliana[E] manual)

Três SPECs verticais (ComVis US-COMVIS-011, OficinaAuto US-AUTO-011, US-OFICINA-019) propõem **soluções similares isoladas** — cada uma cria sua tabela `*_comissao_regras`. Risco real de **3 implementações divergentes** caso não unifique agora.

A FSM canônica ADR 0143 entrou em prod com **slots side-effect prontos** (`marcar_pago`, `cancelar_venda`, `CancelarVendaCascade`). É a janela perfeita pra plugar comissão sem expandir FSM.

ROTA LIVRE (biz=4, 99% volume) é o piloto natural — já usa `commission_agent` UPos e Eliana[E] fecha planilha manual hoje.

## Decisões pendentes pra Wagner aprovar

### D1 — Estender UPos `commission_agent` vs schema novo `commission_*`

| Opção | Prós | Contras |
|---|---|---|
| **A — Estender UPos (adicionar colunas em `transactions`, `users`)** | menos schema, retrocompat zero-friction | viola Tier 0 ([ADR 0093](../../0093-multi-tenant-isolation-tier-0.md) "não modificar tabelas core UltimatePOS sem bridge"); pollutes UPos namespace; impede futura migração schema |
| **B — Schema novo `commission_*` + bridge (Comissao lê `commission_agent` legacy se sem policy)** ← **proposta** | preserva UPos intocado; novo módulo evolutivo; coexistência clean | 1 join extra em queries de relatório; cuidado pra não esquecer fallback legacy |
| **C — Module-agnostic JSON em `transactions.metadata`** | flexível extremo | sem schema = sem query performante; sem FK = sem audit fiscal; gosma |

**Proposta:** **B**. Justificativa: Tier 0 IRREVOGÁVEL não dá margem pra A; C falha audit fiscal (folha CLT exige linha contábil).

---

### D2 — Comissão sobre bruto vs líquido (após taxas marketplace/iFood/cartão)

| Opção | Prós | Contras |
|---|---|---|
| **A — Sempre bruto (`transaction.total_amount`)** | simples, retrocompat UPos exata | vendedor "ganha" sobre taxa marketplace que oimpresso paga — distorção real (pneu R$350 ML taxa R$56 = oimpresso recebe R$294 mas paga 5% × R$350 = R$17,50; margem real ~3.5%) |
| **B — Sempre líquido** | reflete margem | quebra retrocompat UPos; vendedores antigos ficam confusos ("ganhei menos pelo mesmo valor") |
| **C — Policy escolhe (`base_calc` enum: gross_total, net_after_tax, net_after_marketplace_fee, ...)** ← **proposta** | flexível per business, vertical-aware | mais complexo configurar (mas só ao criar policy — depois roda sozinho) |

**Proposta:** **C** com default `gross_total` (= comportamento UPos legado). Verticais que quiserem líquido criam policy nova explícita.

---

### D3 — Pagamento: folha mês fechado vs sob demanda (cada venda paga libera comissão)

| Opção | Prós | Contras |
|---|---|---|
| **A — Sob demanda (paga junto)** | vendedor recebe rápido (motivação) | quebra fluxo financeiro: cada venda paga vira AP separado; muito ruído na DRE; clawback fica caótico (paga depois cobra de volta?) |
| **B — Folha mensal fechada dia 1° + clawback compensa próximo mês** ← **proposta** | alinha com folha CLT; compatível com `essentials_payrolls`; clawback fácil compensar; padrão BR-PME (Bling/Tiny/Omie todos fazem assim) | vendedor espera até 31 dias |
| **C — Híbrido: adiantamento quinzenal + acerto mensal** | best-of-both | complexo demais pra P0; pode entrar como US futura P3 |

**Proposta:** **B** P0. Híbrido C vira US futura quando alguém pedir.

---

### D4 — Claw-back: automático (estorna sozinho) vs manual (gerente decide)

| Opção | Prós | Contras |
|---|---|---|
| **A — Sempre automático** | consistente, audit-friendly, sem viés humano | injusto em casos genuínos (ex: cliente cancelou por erro nosso, vendedor não tem culpa) |
| **B — Sempre manual (gerente review)** | considera contexto | gargalo gerente; esquece e pendência acumula |
| **C — Automático quando `pending` (sem impacto); manual aprovação quando `approved`/`paid`** ← **proposta** | rápido onde é seguro, humano onde dói | ligeiramente mais código (2 branches no side-effect) |

**Proposta:** **C**. Default policy `clawback_on_cancel=true` mas com escalonamento: status muda → `disputed`/`clawback_pending_review` se já `paid`, criando task pra gerente aprovar via UI `comissao.assignment.approve`.

---

### D5 — Multi-papel: JSON na `transactions` vs tabela `commission_role_distributions`

| Opção | Prós | Contras |
|---|---|---|
| **A — JSON em `transactions.commission_distribution`** | sem tabela nova, embutido | queries tipo "quanto Maiara ganhou de comissão fev/26?" exigem JSON path em todo SELECT; performance ruim; audit fiscal exige linha contábil |
| **B — Tabela `commission_role_distributions` linkada a policy + `commission_assignments` linkado a transaction** ← **proposta** | queryável, auditável, scoped, FK enforce | 2 tabelas extras |

**Proposta:** **B**. JSON A é tentação de protótipo; SQL nativo escala melhor + Pest cross-tenant funciona naturalmente.

---

## Decisão (proposta)

Criar `Modules/Comissao` cross-vertical com:

1. **Schema novo** (`commission_policies`, `_role_distributions`, `_tiers`, `_goals`, `_assignments`, `_payments`) — **D1 opção B**
2. **Base calc enum policy-driven** (default `gross_total` retrocompat) — **D2 opção C**
3. **Fechamento mensal via artisan** (`commission:close`) — **D3 opção B**
4. **Clawback automático quando `pending`, manual quando `paid`** — **D4 opção C**
5. **Multi-papel via tabela** `commission_role_distributions` — **D5 opção B**
6. **Plugar à FSM ADR 0143 via side-effects** (não estender FSM)
7. **Coexistência:** sem policy ativa → fallback `cmmsn_percent` legacy (zero-friction migration)
8. **Multi-tenant Tier 0** ([ADR 0093](../../0093-multi-tenant-isolation-tier-0.md)) — `business_id` indexado todas tabelas
9. **MWART obrigatório** ([ADR 0104](../../0104-processo-mwart-canonico-unico-caminho.md)) — telas Inertia com `mwart-comparative` V4 antes do código
10. **Supersede** US-COMVIS-011 + US-AUTO-011 + US-OFICINA-019 (apontam pra este ADR + SPEC Comissao)

## Consequências

### Positivas
- Eliana[E] sai da planilha paralela (~3h/mês recuperadas — métrica sucesso)
- ComVis pode entrar produção com fluxo multi-papel sem reinventar
- OficinaAuto pode entrar produção com fluxo multi-mecânico sem reinventar
- Diferenciador comercial: comissão sobre líquido marketplace (Bling/Tiny/Omie não fazem)
- Audit trail completo (audit fiscal CLT-compliant)

### Negativas
- 6 tabelas novas (custo schema)
- Side-effects FSM aumentam superfície de bugs — Pest cobertura obrigatória
- ROTA LIVRE precisa de **canary 7d** ([ADR 0104 F5](../../0104-processo-mwart-canonico-unico-caminho.md#f5-cutover)) — comissão é dinheiro, surprise mata confiança
- Necessita **counsel jurídico LGPD** (Eliana[E] está estudando — não-bloqueante; comissão é dado pessoal sensível)
- D4 opção C: gargalo gerente se time crescer — revisitar quando >10 vendedores ativos

### Neutras
- Retrocompat preservada: ReportController legacy continua funcionando (não removido)
- Migração legacy é P3 (US-COMM-014) — opcional

## Como propor mudança a este ADR

Append-only ([ADR 0094](../../0094-constituicao-v2-7-camadas-8-principios.md) §6 SoC brutal). Criar novo ADR com `supersedes: [N]` quando este for numerado.

## Pendências bloqueantes pra virar ADR numerada

1. [ ] Wagner aprova D1-D5 escolhas (pode mudar D4 pra "sempre manual" se preferir)
2. [ ] Validar com Eliana[E] (advogada+financeiro) impacto CLT Art. 466 (comissão é salário diferido) — risco trabalhista se errar valor pago
3. [ ] Confirmar ROTA LIVRE como piloto — Larissa concorda com canary 7d?
4. [ ] Verificar se `essentials_payrolls` existe ou é só `essentials_user_sales_targets` (impacta US-COMM-007 linked_payroll_id)
5. [ ] Decidir naming: `Modules/Comissao` (PT-BR consistente com OficinaAuto/Marketplaces) ou `Modules/Commission` (consistente com `users.cmmsn_percent` legacy) — **proposta: Comissao PT-BR**

## Referências externas (mercado consultado)

- Bling: liberação parcial vinculada ao pagamento parcela (4 modos) + comissão por linha de produto + alíquota por faixa de desconto
- Tiny: tiers escalonados R$0-10k/10k-20k/>20k + accelerator sobre excedente meta
- Conta Azul: relatório per vendedor por competência/vencimento/baixa
- Omie: trigger configurável (invoice vs liquidação) + chargeback automático + bonus hierarchies + reports per period
- Spiff/CaptivateIQ/Xactly: multi-product splits + clawback rules + real-time earnings visibility + audit trails (referência aspiracional, não-target BR-PME)

(Detalhes em MATRIZ-ROI.md)
