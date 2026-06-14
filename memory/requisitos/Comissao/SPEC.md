---
module: Comissao
version: "1.0"
last_updated: "2026-06-13"
owner: wagner
status: rascunho
related_adrs: [0151-modules-comissao-feature-wish, 0105-cliente-como-sinal-guiar-sem-mandar, 0143-fsm-pipeline-live-prod-marco-2026-05-12]
---

# SPEC — Comissão de Vendedores (cross-vertical)

> ## ⛔ DORMENTE — feature-wish ([ADR 0151](../../decisions/0151-modules-comissao-feature-wish.md))
>
> **NÃO atribuir owner às US-COMM-* abaixo.** **NÃO scaffoldear código** em `Comissao` (planejado — não existe).
>
> Razão (ADR 0151): nenhum dos 5 verticais tem cliente pagante que reporta dor explícita de comissão hoje (Larissa opera com `commission_agent` UPos + planilha Eliana[E], ComVis/OficinaAuto/Autopecas inativos, Marketplaces inexistente). [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — sem sinal qualificado, sem código.
>
> Triggers de ativação documentados em [ADR 0151 §"Trigger condições"](../../decisions/0151-modules-comissao-feature-wish.md). Pelo menos UM precisa ser satisfeito + Wagner aprovar ADR de promoção.
>
> **SPEC abaixo permanece intacto como blueprint pré-pago** — quando trigger ativar, dev abre o SPEC e tem ~2 semanas de design já feito (mapping mercado, 6 schema tables, 5 cenários peculiares, 14 US, riscos catalogados).

> **Módulo proposto:** `Comissao` (planejado — não existe) (cross-vertical — consumido por Sells, ComVis, OficinaAuto, Autopecas, Marketplaces)
> **Status:** **dormente** ([ADR 0151](../../decisions/0151-modules-comissao-feature-wish.md) — feature-wish, aguarda-sinal-qualificado)
> **Convenção ID:** `US-COMM-NNN`
> **Owner sugerido (ao ativar):** [W] + [E] (advogada + financeiro — afeta folha/CLT)
> **Cliente piloto candidato:** ROTA LIVRE (biz=4) já usa `commission_agent` UPos hoje (validar)
> **Última atualização:** 2026-05-15 — formalizada como dormente via ADR 0151

---

## §0 (CRUCIAL) Comparação com o que JÁ EXISTE — não duplicar

### Inventário ondes-de-comissão hoje no oimpresso

| # | Artefato | Onde vive | Capacidade real |
|---|----------|-----------|-----------------|
| 1 | `transactions.commission_agent` | core UPos (migration `2018_02_26_134500_add_commission_agent_to_transactions_table.php`) — FK pra `users.id` | **Vendedor único** per venda |
| 2 | `users.cmmsn_percent` | core UPos (migration `2018_02_26_130519_modify_users_table_for_sales_cmmsn_agnt.php`) | **% fixo per vendedor** (uma taxa global, sem segmentação) |
| 3 | `sales_commission_agents` + `SalesCommissionAgentController` + views `sales_commission_agent/{index,create,edit}.blade.php` | core UPos | Cadastro de **vendedor "externo"** (não-user; tipo representante comercial) com nome+contato+`cmmsn_percent` |
| 4 | `essentials_user_sales_targets` (id, user_id, target_start, target_end, **commission_percent**) | `Modules/Essentials` (HR pago UPos) | **1 faixa de meta com % fixo** — NÃO é tier escalonado real; é binário "atingiu/não" |
| 5 | `ReportController::salesRepresentativeTotalCommission` linhas 1309-1356 | core | Relatório single-tier: `cmmsn_percent × (faturado OU recebido)` per vendedor, period filter, location filter |
| 6 | `Modules/Jana/Ai/Agents/BriefDiarioAgent` | Jana | (não toca comissão) |
| 7 | FSM canon ADR 0143: actions `marcar_pago` 🔒 + `cancelar_venda` 🔒 + `CancelarVendaCascade` side-effect | `app/Domain/Fsm/SideEffects/` | **Hooks prontos** — comissão pluga aqui como side-effect novo (sem precisar mudar FSM) |
| 8 | `CrmContactPerson::commissions` mencionado em `Crm/ARCHITECTURE.md:33,154` | `Modules/Crm` | TODO — apenas referência, não implementado |
| 9 | US-COMVIS-011 + US-AUTO-011 + US-OFICINA-019 | SPECs verticais | **Backlog não-construído** — todos pedem multi-papel + escalonado |

### Tabela comparativa feature × estado-atual × mercado × precisa-construir

| Feature | UPos `commission_agent` | UPos `essentials_user_sales_targets` | ComVis SPEC §14 (US-COMVIS-011) | Bling | Tiny | Conta Azul | Omie | Spiff/CaptivateIQ | PRECISA construir? |
|---------|-------------------------|--------------------------------------|--------------------------------|-------|------|------------|------|-------------------|--------------------|
| Vendedor único per venda | ✅ FK | ✅ user_id | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | **Já tem — reusar** |
| % fixo per vendedor (`cmmsn_percent`) | ✅ user.cmmsn_percent | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | **Já tem — reusar** |
| Comissão sobre faturado vs recebido (gatilho) | ✅ ReportController flag | — | ✅ regra "sobre faturado/recebido" | ✅ 4 modos (parcial parcela / integral fatura / 1ª parcela / última parcela) | ✅ "liberação parcial" | ✅ por baixa | ✅ "trigger" config | ✅ | **Parcial — gerenciar via FSM action é melhor** (marcar_pago vs faturar) |
| Comissão multi-papel (vendedor+designer+instalador) | ❌ | ❌ | ✅ proposta | ❌ | ❌ | 🟡 por categoria produto | 🟡 hierarquia bônus | ✅ "multi-product splits" | **Sim — construir (gap real BR-PME)** |
| Comissão escalonada por faixa (tier) | ❌ | 🟡 1 faixa = não-tier real | ❌ | ✅ por faixa de desconto | ✅ R$ [redacted Tier 0]k/R$ [redacted Tier 0]k tiers | 🟡 limitado | ✅ "bonus hierarchies" | ✅ | **Sim — construir** |
| Acceleradores (>meta → bonus extra) | ❌ | ❌ (binário) | ❌ | 🟡 manual | ✅ accelerator | ❌ | ✅ campanhas | ✅ | **Sim — construir** |
| Claw-back (cancelar venda paga → estorno) | ❌ | ❌ | ❌ | 🟡 manual | ❌ | ❌ | ✅ "chargeback rules" | ✅ | **Sim — construir** (slot pronto: side-effect `cancelar_venda`) |
| Cálculo automático pós-pagamento | ❌ (manual via Report) | ❌ | 🟡 proposto | ✅ | ✅ | ✅ | ✅ | ✅ | **Sim — construir** (side-effect `marcar_pago`) |
| Relatório mensal per-vendedor | ✅ Report single-tier | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | **Parcial — UI Inertia + dimensões extras** |
| Folha pagamento mensal (fechamento) | ❌ | — | ✅ "lançamento comissao_pendente" | ✅ | ✅ | ✅ | ✅ | ✅ | **Sim — construir** |
| Comissão por produto / categoria | ❌ | ❌ | ❌ | ✅ "linhas de produto" | ✅ | 🟡 | ✅ | ✅ | **Sim — construir** |
| Comissão sobre líquido (após taxa ML/iFood) vs bruto | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | 🟡 | ✅ | **Sim — construir** (vertical Marketplaces) |
| Comissão por origem (lead CRM, marketplace, balcão) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | 🟡 | ✅ "overlays" | **Sim — construir (P3)** |
| Aprovação manual / workflow / audit trail | ❌ | ❌ | 🟡 "reapuração permitida com motivo" | ❌ | ❌ | ❌ | 🟡 | ✅ | **Sim — construir (P1)** |
| Mobile self-service (vendedor vê próprias comissões) | ❌ | ❌ | ❌ | 🟡 | 🟡 | 🟡 | 🟡 | ✅ "real-time earnings visibility" | **P2 — gap diferencial** |

> **Princípio de não-duplicação:** o módulo `Comissao` (planejado — não existe) **NÃO substitui** `transactions.commission_agent` nem `users.cmmsn_percent`. Eles continuam sendo os "valores semente" — o módulo novo lê e expande quando a policy exige multi-papel/tier/accelerator. Migração silenciosa: vendas legadas continuam funcionando com cálculo single-tier; vendas novas em verticais que adotam policy avançada usam o módulo novo.

---

## §1 Visão

**Comissão como produto cross-vertical** que serve:

| Vertical | Modelo dominante |
|---|---|
| **Sells (padrão)** | Vendedor único (continua UPos), % per usuário, gatilho `marcar_pago` |
| **ComVis** | Multi-papel: vendedor (% venda) + designer (fixo ou % design) + instalador (% instalação) |
| **OficinaAuto** | Vendedor balcão (% peça) + mecânico (% mão-de-obra apontada), multi-mecânico split |
| **Autopecas** | Balconista (% venda balcão), comissão por produto / categoria |
| **Marketplaces (ML/iFood/Shopee)** | Líquido pós-taxa (diferenciador) — comissão sobre `total - taxa_marketplace - frete` |

Objetivos:
1. **Não duplicar** o que UPos já fornece (single-tier)
2. **Acoplar à FSM ADR 0143** — `marcar_pago` dispara `CalcularComissaoJob`, `cancelar_venda` dispara `EstornarComissaoJob` (clawback)
3. **Mensal de fechamento** — folha (Eliana[E] roda dia 1° + audita)
4. **Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — toda tabela `business_id` indexado
5. **LGPD** — valores comissão por vendedor expostos apenas pro próprio vendedor + papéis `comissao.*` autorizados (cite [ADR 0142](../../decisions/0142-notas-internas-sinal-treino-jana.md) padrão classificação)

Anti-objetivos:
- ❌ NÃO substituir UPos `commission_agent` (criar `_legacy_map` ou migration retro é over-engineering)
- ❌ NÃO virar Spiff/CaptivateIQ Brasil — escopo é PME 1-30 vendedores, não enterprise 500+
- ❌ NÃO calcular ICMS/IRRF da comissão automaticamente (folha é Eliana[E]; comissão entra como provisão DRE, não retenção tributária)

---

## §2 Cenários peculiares (testáveis)

### Cenário A — Vendedor único + meta + accelerator (ROTA LIVRE típica)

- Larissa vendedora, `cmmsn_percent=5`, meta mês R$ [redacted Tier 0]k
- Vende R$ [redacted Tier 0]k recebidos no mês
- **R$ [redacted Tier 0]k × 5% = R$ [redacted Tier 0]** (base)
- **R$ [redacted Tier 0]k excedente × 1% accelerator = R$ [redacted Tier 0]** (bonus)
- Total: **R$ [redacted Tier 0]** liberado no fechamento dia 1°

### Cenário B — Multi-papel ComVis (banner customizado)

- Venda banner R$ [redacted Tier 0] paga via boleto Asaas (`marcar_pago`)
- Policy "ComVis com produção+instalação":
  - **Vendedor 5% (R$ [redacted Tier 0])** — Maiara
  - **Designer fixo R$ [redacted Tier 0]** — designer terceirizado externo
  - **Instalador 30% da instalação (R$ [redacted Tier 0] instalação × 30% = R$ [redacted Tier 0])** — Felipe
- 3 lançamentos `commission_assignments` criados no mesmo `transaction_id`

### Cenário C — Claw-back (cancelar venda já paga)

- Venda R$ [redacted Tier 0] paga 10/abr → comissão R$ [redacted Tier 0] paga ao vendedor no fechamento 1°/mai
- Cliente cancela 15/mai (`cancelar_venda` → `CancelarVendaCascade`)
- Side-effect novo `EstornarComissaoJob`: cria `commission_assignments` status `clawback` valor **-R$ [redacted Tier 0]**
- No fechamento 1°/jun, comissão líquida do vendedor = comissões do mês **menos R$ [redacted Tier 0]** (compensação)
- Se líquido negativo → **gerente decide** (cobrar, dar zero, levar pro próximo mês — D4 ADR)

### Cenário D — Vendedor sai da empresa antes do fechamento

- Maiara sai 20/mai com R$ [redacted Tier 0] em comissões pending (faturado 1-20/mai)
- Política CLT/contrato: trabalho realizado **deve ser pago** (Art. 477 CLT) — mas comissão "sobre recebido" só conta vendas efetivamente baixadas
- **DoD comportamento:** rotina fechamento `commission:close --month` dispara warning se vendedor `users.deleted_at IS NOT NULL` com pending — gerente decide manual (não automático)

### Cenário E — Frota B2B (ex: caminhão recapagem R$ [redacted Tier 0]k)

- Policy específica B2B: % menor (ex: 1% vs 5% varejo) — accelerator desligado
- Vinculação: tag `transaction.is_b2b=true` OU contact_type=`wholesale` aplica policy alternativa
- Vendedor não pode "burlar" — policy linkada a customer type no commit-time

### Cenário F — Marketplaces (vende pneu R$ [redacted Tier 0] no ML, taxa 16%)

- Venda bruta R$ [redacted Tier 0]; taxa ML R$ [redacted Tier 0]; frete R$ [redacted Tier 0] (ML cobra do cliente, repassa parcial)
- `transaction.total_amount` = R$ [redacted Tier 0] (bruto exibido)
- `transaction.marketplace_net_amount` (campo novo Marketplaces) = R$ [redacted Tier 0]
- Policy "marketplace_liquido": **base é `marketplace_net_amount` não `total_amount`**
- Comissão: 5% × R$ [redacted Tier 0] = **R$ [redacted Tier 0]** (não R$ [redacted Tier 0] sobre bruto)
- Diferenciador comercial — Bling/Tiny não fazem isto automaticamente

---

## §3 Schema proposto

> Naming: prefixo `commission_*` (singular tabela quando "1 item per business" como `policies`; plural quando lista de eventos como `assignments`).
> Multi-tenant: TODAS tabelas têm `business_id` indexado + FK + global scope. Compatível [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md).

### `commission_policies` — políticas (per-business)

| Coluna | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| business_id | unsignedInt | FK business; idx |
| name | varchar(120) | "Padrão Sells", "ComVis com instalação", "Marketplace líquido" |
| applies_to | enum | `sells`, `services`, `repair`, `marketplace`, `all` |
| base_calc | enum | `gross_total`, `net_after_tax`, `net_after_marketplace_fee`, `service_only`, `product_only` |
| trigger_event | enum | `on_invoice` (faturar), `on_payment` (marcar_pago — default), `on_each_installment` |
| accelerator_enabled | bool | |
| clawback_on_cancel | bool | default true |
| is_default | bool | 1 default per `applies_to` per business |
| customer_type_filter | json nullable | `{"type": "wholesale"}` ou `{"is_b2b": true}` — restringe policy a tag |
| created_at, updated_at | | |

### `commission_role_distributions` — quem ganha quê (multi-papel)

| Coluna | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| policy_id | FK commission_policies | |
| role | enum | `vendedor`, `designer`, `instalador`, `mecanico`, `balconista`, `gerente_overlay`, `outro` |
| calc_mode | enum | `percent_of_base`, `fixed_amount`, `percent_of_role_specific` (ex: 30% da linha "instalação" só) |
| value | decimal(10,4) | % (0-100) ou R$ fixo |
| applies_to_line_filter | json nullable | `{"product_tag": "instalacao"}` — só esta linha entra na base |
| min_assignment | int default 1 | mín pessoas com este papel (1=obrigatório) |
| max_assignment | int default 1 | máx (vendedor=1, mecanico=5 multi-split) |
| created_at, updated_at | | |

### `commission_tiers` — escalonamento (per-user OU per-policy)

| Coluna | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| business_id | unsignedInt | FK + idx |
| user_id | unsignedInt nullable | NULL = tier global; preenchido = override per-vendedor |
| policy_id | FK commission_policies nullable | NULL = aplica a qualquer policy do business |
| tier_order | int | 1, 2, 3 |
| threshold_from | decimal(15,2) | R$ [redacted Tier 0] R$ [redacted Tier 0] R$ [redacted Tier 0] |
| threshold_to | decimal(15,2) nullable | NULL = teto infinito |
| rate | decimal(7,4) | 3.0000, 5.0000, 7.0000 |
| period | enum | `monthly`, `quarterly`, `cycle_custom` |
| created_at, updated_at | | |

### `commission_goals` — metas mensais (per-vendedor)

| Coluna | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| business_id | unsignedInt | FK + idx |
| user_id | unsignedInt | FK users |
| period_start | date | 1° do mês |
| period_end | date | último do mês |
| goal_amount | decimal(15,2) | R$ [redacted Tier 0] |
| achieved_amount | decimal(15,2) | atualizado por cron daily |
| accelerator_rate | decimal(7,4) nullable | % aplicado sobre excedente meta |
| status | enum | `active`, `closed`, `cancelled` |
| created_at, updated_at | | |

### `commission_assignments` — eventos cálculo (1 linha = 1 pagamento)

| Coluna | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| business_id | unsignedInt | FK + idx |
| transaction_id | FK transactions nullable | NULL se assignment vier de repair_job_sheets |
| repair_job_sheet_id | FK nullable | OficinaAuto/Repair OS |
| user_id | unsignedInt | FK users (vendedor/designer/instalador) |
| external_agent_id | unsignedInt nullable | FK sales_commission_agents (legacy UPos — representante externo) |
| role | enum | igual `commission_role_distributions.role` |
| policy_id | FK commission_policies | |
| base_amount | decimal(15,2) | R$ [redacted Tier 0] (valor base computado conforme `base_calc`) |
| rate_applied | decimal(7,4) | 5.0000 |
| accelerator_amount | decimal(15,2) default 0 | bonus accelerator separado pra audit |
| calculated_amount | decimal(15,2) | total final (base × rate + accelerator) |
| status | enum | `pending`, `approved`, `paid`, `clawback`, `disputed`, `void` |
| approved_by | unsignedInt nullable | FK users (gerente) |
| approved_at | timestamp nullable | |
| paid_at | timestamp nullable | |
| commission_payment_id | FK commission_payments nullable | quando agrupado em pagamento |
| metadata | json nullable | `{"fsm_action": "marcar_pago", "stage_history_id": 123}` audit |
| created_at, updated_at | | |

Índices: `(business_id, user_id, status)`, `(business_id, transaction_id)`, `(business_id, period_start, period_end)` via `paid_at`.

### `commission_payments` — agrupamento pagamento mensal

| Coluna | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| business_id | unsignedInt | FK + idx |
| user_id | unsignedInt | FK |
| period_start | date | |
| period_end | date | |
| total_amount | decimal(15,2) | soma assignments approved - clawback |
| status | enum | `draft`, `confirmed`, `paid`, `cancelled` |
| confirmed_by | unsignedInt nullable | FK users |
| confirmed_at, paid_at | timestamp nullable | |
| payment_method | enum nullable | `payroll`, `pix`, `transfer`, `cash`, `linked_to_payroll_id` |
| linked_payroll_id | unsignedInt nullable | FK `essentials_payrolls` se existir |
| notes | text nullable | |
| created_at, updated_at | | |

---

## §4 Integração com FSM canon ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))

> **CRÍTICO:** comissão NÃO precisa estender FSM. Pluga como **side-effect** novo em actions existentes.

### Side-effects propostos

| FSM action | Stage | Side-effect novo | Resultado |
|---|---|---|---|
| `marcar_pago` | `paid` | **`CalcularComissaoSideEffect`** | Cria N `commission_assignments` (1 per role) com status `pending` |
| `marcar_pago` (cada parcela) | `paid` parcial | idem (rateio proporcional) | Se policy=`on_each_installment` |
| `faturar` | `invoiced` | idem | Se policy=`on_invoice` |
| `cancelar_venda` | `cancelled` | **`EstornarComissaoSideEffect`** | Cria assignment `clawback` valor negativo OU marca `void` se ainda `pending` |
| `concluir_producao` | `in_production → ready_for_invoice` | (nenhum) | Comissão NÃO conta aqui — produção concluída ≠ recebido |
| Repair `entregue_completo` | terminal | `CalcularComissaoRepairSideEffect` | Inclui mecânico_apontamentos split |

Slot pra cada side-effect: `app/Domain/Fsm/SideEffects/{Calcular,Estornar}Comissao{Sells,Repair}SideEffect.php`.

### Action FSM nova (Comissao internal)

| Action | Trigger | Side-effect |
|---|---|---|
| `aprovar_comissao_mensal` (no `commission_payments`) | gerente UI | move status `draft → confirmed`; bloqueia mudanças |
| `pagar_comissao` | folha rodada | status `confirmed → paid`; preenche `paid_at` |
| `disputar_assignment` | vendedor UI | status assignment → `disputed`; abre fluxo aprovação manual |

---

## US ativas

> Backlog dormente (US-COMM-*) — blueprint pré-pago. Só ganha owner quando trigger de ativação do ADR 0151 for satisfeito + Wagner aprovar ADR de promoção. Detalhe das 14 US em §5 abaixo.

## §5 User Stories — US-COMM-001 a US-COMM-014

### US-COMM-001 · Schema base + migration + multi-tenant scope — **P0**
> **Área:** Backend
> **Reusa:** [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) `HasBusinessScope`

**Como** arquiteto **quero** as 6 tabelas (`commission_policies` + `_role_distributions` + `_tiers` + `_goals` + `_assignments` + `_payments`) **para** habilitar o módulo

**DoD:**
- [ ] 6 migrations com `business_id` indexado + FK
- [ ] 6 Eloquent Models com `HasBusinessScope` global scope
- [ ] `Comissao/Database/Seeders/CommissionDefaultsSeeder` (módulo planejado — não existe) cria policy default per business existente (preserva backward-compat: rate=`users.cmmsn_percent`, trigger=`on_payment`, base=`gross_total`)
- [ ] Pest: 1 teste `BusinessIdGuardTest` cross-tenant biz=1 vs biz=99
- **Estimate:** 3d (recalibrado IA-pair fator 10x — [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md))

---

### US-COMM-002 · Side-effect `CalcularComissaoSells` no `marcar_pago` — **P0**
> **Área:** Backend / FSM
> **blocked_by:** US-COMM-001
> **Reusa:** FSM ADR 0143 `ExecuteStageActionService`

**Como** dono **quero** que ao marcar venda como paga, o sistema calcule comissão automaticamente baseado na policy ativa **para** parar planilha paralela manual

**DoD:**
- [ ] Classe `app/Domain/Fsm/SideEffects/CalcularComissaoSellsSideEffect.php` implementa contrato `SideEffectContract`
- [ ] Registrar no map `ExecuteStageActionService` (action `marcar_pago` stage `paid`)
- [ ] Resolve policy: customer_type_filter → applies_to=`sells` → default
- [ ] Lê `commission_role_distributions` da policy, cria N `commission_assignments` status `pending`
- [ ] Fallback compat legacy: se policy não existe → usa `users.cmmsn_percent` (single-tier)
- [ ] Pest: 3 cenários (single-tier legacy, multi-papel ComVis, customer wholesale)
- **Estimate:** 2d

---

### US-COMM-003 · Side-effect `EstornarComissao` no `cancelar_venda` (clawback) — **P0**
> **Reusa:** FSM `CancelarVendaCascade` orquestrador

**Como** dono **quero** que ao cancelar venda já paga, comissões pagas sejam estornadas (clawback) **para** não pagar comissão sobre venda que não existiu

**DoD:**
- [ ] `EstornarComissaoSideEffect` chamado dentro de `CancelarVendaCascade`
- [ ] Se assignments status=`pending` → marca `void` (sem impacto folha)
- [ ] Se status=`approved` ou `paid` → cria assignment espelho status=`clawback` valor negativo
- [ ] Audit log: `metadata.cancellation_transaction_id`
- [ ] Pest: 3 cenários (pending → void, approved → clawback, paid → clawback)
- **Estimate:** 2d

---

### US-COMM-004 · UI cadastro de policy + role distributions — **P0**
> **Área:** Frontend MWART
> **Path:** `resources/js/Pages/Comissao/Policies/{Index,Create,Edit}.tsx`
> **Reusa:** skill `mwart-process` 5 fases

**Como** dono **quero** criar/editar policies via UI **para** não depender de tinker

**DoD:**
- [ ] Wagner aprova `Comissao-policies-visual-comparison.md` ANTES de Edit (skill `mwart-comparative` V4)
- [ ] Form Inertia: nome, applies_to, base_calc, trigger_event, accelerator on/off, clawback on/off
- [ ] Sub-tabela inline role_distributions
- [ ] Validação: ao menos 1 role distribution com role=`vendedor` (mín default)
- [ ] Pest browser MCP smoke biz=1 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md))
- **Estimate:** 3d

---

### US-COMM-005 · Tiers escalonados (3 faixas R$/% per user OU policy) — **P1**
> **blocked_by:** US-COMM-002

**Como** dono **quero** definir "até R$ [redacted Tier 0]k 3% / R$ [redacted Tier 0]-20k 5% / >R$ [redacted Tier 0]k 7%" **para** motivar excedente sem mudar lei

**DoD:**
- [ ] UI `Pages/Comissao/Tiers/Index.tsx` (lista per business + per user override)
- [ ] Service `TierResolver::resolveRate($user, $achieved_amount)` retorna rate aplicável
- [ ] Side-effect US-COMM-002 consulta tier no momento do cálculo (não a `users.cmmsn_percent` fixa)
- [ ] Pest: tiers cumulative (R$ [redacted Tier 0]k = 3% sobre 10k + 5% sobre 10k + 7% sobre 5k OU 7% liso? — decidir D-tier no draft ADR)
- **Estimate:** 2d

---

### US-COMM-006 · Metas mensais + accelerator — **P1**
> **blocked_by:** US-COMM-002

**Como** dono **quero** definir meta R$ [redacted Tier 0]k/mês e accelerator +1% sobre excedente **para** turbinar quem bate

**DoD:**
- [ ] UI `Pages/Comissao/Goals/{Index,Edit}.tsx`
- [ ] Cron daily `commission:update-achieved` atualiza `commission_goals.achieved_amount` (soma transactions paid no período)
- [ ] No fechamento: se achieved > goal → calcula `accelerator_amount` separado
- [ ] Pest: 3 cenários (não bateu, bateu na régua, ultrapassou)
- **Estimate:** 2d

---

### US-COMM-007 · Comando `commission:close --month=YYYY-MM` (fechamento) — **P0**
> **Área:** Backend artisan
> **Reusa:** padrão `fsm:bulk-start-pipeline`

**Como** Eliana[E] **quero** rodar artisan no dia 1° pra fechar mês anterior **para** gerar `commission_payments` per vendedor

**DoD:**
- [ ] `php artisan commission:close --business=4 --month=2026-04 [--dry-run]`
- [ ] Agrupa `commission_assignments` status `approved` + `clawback` no período → cria 1 `commission_payment` per user
- [ ] Marca assignments como `commission_payment_id={id}`
- [ ] Output tabela (totais por vendedor + warnings: vendedor deletado, líquido negativo)
- [ ] Aprovação humana obrigatória (`--confirm` flag) — não auto
- [ ] Pest: dry-run não muda DB; --confirm muda
- **Estimate:** 1d

---

### US-COMM-008 · UI relatório mensal per-vendedor (substitui ReportController legacy) — **P1**
> **Área:** Frontend MWART
> **Path:** `resources/js/Pages/Comissao/Relatorio/Index.tsx`

**Como** dono **quero** ver per vendedor: período × bruto × líquido × accelerator × clawback × total **para** validar antes de pagar

**DoD:**
- [ ] Filtros: period, location, vendedor, status, role
- [ ] Drilldown: clicar linha → modal com lista de `commission_assignments`
- [ ] Export CSV/PDF
- [ ] Skill `mwart-comparative` V4 antes de Edit
- **Estimate:** 3d

---

### US-COMM-009 · Multi-papel split ComVis (vendedor+designer+instalador) — **P1**
> **blocked_by:** US-COMM-002, US-COMM-004
> **Reusa:** US-COMVIS-011 (este SPEC substitui aquela US)

**Como** dono ComVis **quero** que banner R$ [redacted Tier 0] distribua 5% vendedor + R$ [redacted Tier 0] fixo designer + 30% sobre linha instalação **para** automatizar regra ComVis SPEC §14

**DoD:**
- [ ] Policy seed "ComVis Padrão" com 3 role_distributions
- [ ] Side-effect resolve `applies_to_line_filter` (linha "instalação" calculada separado)
- [ ] UI cadastro: ao criar venda, escolher designer/instalador (dropdown filtra por role)
- [ ] Pest cenário B do §2
- [ ] Marcar [US-COMVIS-011 do SPEC ComVis](../ComunicacaoVisual/SPEC.md) como `supersedes_by: US-COMM-009`
- **Estimate:** 3d

---

### US-COMM-010 · Multi-mecânico split OficinaAuto (apontamentos por % mão-de-obra) — **P2**
> **blocked_by:** US-COMM-002
> **Reusa:** US-AUTO-006 atribuições + US-AUTO-011 (este substitui)

**Como** dono **quero** OS com 2 mecânicos (Junior 60% / Pleno 40% das horas) dividir comissão proporcional **para** pagar correto multi-mecânico

**DoD:**
- [ ] Repair `RepairFsmActionController` action `entregue_completo` chama `CalcularComissaoRepairSideEffect`
- [ ] Lê tabela apontamentos (US-AUTO-006) → split proporcional
- [ ] Pest: 1 OS 2 mecânicos cada calcula correto
- **Estimate:** 3d

---

### US-COMM-011 · Comissão sobre líquido (após taxa marketplace) — **P2**
> **blocked_by:** módulo Marketplaces (planejado — não existe)

**Como** dono **quero** que vendas ML/iFood/Shopee usem base líquida (já descontada taxa marketplace) **para** comissão refletir margem real

**DoD:**
- [ ] policy `base_calc='net_after_marketplace_fee'`
- [ ] Side-effect lê `transaction.marketplace_net_amount` (campo a criar em Marketplaces)
- [ ] Fallback: se campo ausente → log warning + usar `gross_total`
- [ ] Pest cenário F do §2
- **Estimate:** 2d

---

### US-COMM-012 · Aprovação workflow + audit trail (manager review) — **P1**
> **Reusa:** Spatie permissions + `AuditLog`

**Como** gerente **quero** revisar/ajustar assignments antes do fechamento **para** corrigir erros legítimos

**DoD:**
- [ ] UI lista assignments status `pending` → botão approve/dispute/edit (com motivo obrigatório)
- [ ] Permissões Spatie `comissao.aprovar` (gerente+) | `comissao.editar` (só admin)
- [ ] AuditLog em toda mudança valor `calculated_amount` (campo `was`, `now`, `reason`, `user_id`)
- [ ] Comissão `approved` é congelada — alteração exige `unlock` + ADR (não-default)
- **Estimate:** 2d

---

### US-COMM-013 · Mobile self-service vendedor — **P2**
> **Reusa:** PWA (mesma infra US-AUTO-012)

**Como** vendedor **quero** abrir `/minhas-comissoes` no celular e ver acumulado mês + meta + projeção **para** acompanhar sem pedir ao chefe

**DoD:**
- [ ] Page `/comissao/minhas` (Inertia mobile-first Tailwind 4)
- [ ] LGPD: vendedor vê APENAS suas próprias linhas (Policy scope `where('user_id', auth()->id())`)
- [ ] Push notification (Centrifugo CT 100) quando assignment muda status
- **Estimate:** 2d

---

### US-COMM-014 · Migração legacy `users.cmmsn_percent` + `transactions.commission_agent` — **P3**
> **Reusa:** padrão `*_legacy_map` skill `feedback_legacy_migration_python_importer`

**Como** Wagner **quero** preservar dados históricos de comissão UPos **para** retroatividade não quebrar

**DoD:**
- [ ] Migration cria policies "Legacy Single-Tier" per business existente
- [ ] Backfill `commission_assignments` apenas pra transactions últimos 90d com `commission_agent NOT NULL` e `payment_status=paid`
- [ ] Idempotente (re-run não duplica)
- [ ] ADR registrando decisão "não retroagir tudo" (custo: histórico antigo permanece via ReportController legacy)
- **Estimate:** 2d

---

## §6 Riscos identificados

| # | Risco | Mitigação |
|---|-------|-----------|
| R1 | Drift cálculo entre policy nova e `cmmsn_percent` legacy gera divergência valor pago | Pest cross-checa snapshot ReportController vs novo cálculo per transaction (último mês) |
| R2 | Clawback gera líquido negativo no mês → como cobrar vendedor que já recebeu? | D4 ADR — proposta: levar saldo negativo pro próximo mês (até zerar); >90d sem zerar = gerente decide manual |
| R3 | Multi-papel ComVis exige campo "designer/instalador" no form de venda — UPos UI hoje só tem "commission_agent" | US-COMM-009 inclui mudança UI POS — coordenar com `Sells` SPEC |
| R4 | Marketplace líquido depende de campo não existente → US-COMM-011 fica blocked | OK — P2, não bloqueia P0/P1 |
| R5 | LGPD: vendedor não pode ver comissões de colegas | `commission_assignments` policy scope obrigatório + Pest cross-user |
| R6 | Mudanças retroativas em assignments `paid` poluem audit fiscal | Default: `paid` é congelado; unlock exige razão + log + ADR |

---

## §7 Métricas de sucesso (1 cycle = 2 semanas pós-lançamento P0)

- [ ] ROTA LIVRE (biz=4) opera 1 mês completo (mai/26) com comissão automática (substitui planilha Eliana[E])
- [ ] 0 divergências de valor entre folha rodada vs ReportController legacy (sanity)
- [ ] 1+ business novo ComVis ativa policy multi-papel (Vargas ou Mhundo — candidatos)
- [ ] Tempo fechamento mensal Eliana[E] cai de ~3h pra <30min (mensurar)

---

## §8 Permissões Spatie propostas

| Permissão | Quem |
|---|---|
| `comissao.policy.view` / `.create` / `.update` / `.delete` | Admin |
| `comissao.tier.view` / `.update` | Admin + Gerente |
| `comissao.goal.update` | Admin + Gerente |
| `comissao.assignment.view.all` | Gerente |
| `comissao.assignment.view.own` | Vendedor (default todos com role `sales`) |
| `comissao.assignment.approve` | Gerente |
| `comissao.assignment.dispute.own` | Vendedor |
| `comissao.assignment.edit_after_approved` | Admin (raro — exige ADR) |
| `comissao.payment.view` / `.confirm` / `.mark_paid` | Eliana[E] (financeiro) |

Convenção per-business: sufixo `#{biz}` ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) padrão).

---

## §9 Dependências e ordem

```
US-COMM-001 (schema)
    └─→ US-COMM-002 (CalcularComissao side-effect)
            └─→ US-COMM-003 (Estornar/clawback)
            └─→ US-COMM-004 (UI policy)
                    └─→ US-COMM-005 (tiers)
                    └─→ US-COMM-006 (metas + accelerator)
                            └─→ US-COMM-007 (close --month)
                                    └─→ US-COMM-008 (relatório UI)
                            └─→ US-COMM-009 (ComVis multi-papel) — P1
                            └─→ US-COMM-010 (Repair multi-mecanico) — P2
                            └─→ US-COMM-011 (marketplace) — P2 + dep externa
                            └─→ US-COMM-012 (workflow approval) — P1
                            └─→ US-COMM-013 (mobile) — P2
                            └─→ US-COMM-014 (migration legacy) — P3
```

---

## §10 Referências cruzadas

- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 — Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0101 — Tests business_id=1 (não cliente)](../../decisions/0101-tests-business-id-1-nunca-cliente.md)
- [ADR 0104 — Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0106 — Recalibração velocidade 10x](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- [ADR 0129 — FSM canon RBAC](../../decisions/0129-state-machine-canonica-fsm-rbac.md)
- [ADR 0143 — FSM pipeline live prod](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [SPEC ComVis §14 — US-COMVIS-011](../ComunicacaoVisual/SPEC.md)
- [SPEC OficinaAuto US-AUTO-011 + US-OFICINA-019](../OficinaAuto/SPEC.md)
- [SPEC Sells](../Sells/SPEC.md)
- ADR proposta complementar: [drafts/comissao-vendedor-cross-vertical.md](../../decisions/proposals/drafts/comissao-vendedor-cross-vertical.md)
- MATRIZ ROI: [MATRIZ-ROI.md](MATRIZ-ROI.md)
- ROADMAP: [ROADMAP.md](ROADMAP.md)
