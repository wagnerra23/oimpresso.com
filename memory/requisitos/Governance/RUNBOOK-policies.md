---
slug: governance-runbook-policies
title: "Governance — Runbook da tela Policies (/governance/policies)"
type: runbook
module: Governance
tela: governance/Policies
owner: W
status: rascunho
last_validated: "2026-08-05"
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0086-fase-5-mvp-governance-actiongate-warn
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0366-fronteira-jana-forja-governance-kb
---

# RUNBOOK — `/governance/policies` (Policies)

> **Origem desta versão (recibo):** escrito em **2026-08-05** por leitura do código
> (`PoliciesController` · `PolicyToggleService` · `TogglePolicyRequest` · `Http/routes.php` ·
> `Policies.tsx` · `Policies.charter.md`), pra destravar a ligação da strip `GovernancaSubNav`
> — o hook `block-mwart-violation.mjs` (ADR 0104 §F1) exige RUNBOOK antes de Edit no `.tsx`,
> e a lacuna era **pré-existente**.
>
> `status: rascunho` é literal: **nenhum passo abaixo foi EXECUTADO** nesta sessão (Pest só
> roda no CT 100; sem smoke prod). O que não deu pra derivar do código está **⬜ ABERTO**.

---

## 0. Estado final esperado

| Verificação | Como conferir |
|---|---|
| Rota responde 200 autenticado | `curl -sv https://oimpresso.com/governance/policies` (hop final `200`) |
| Strip com `Policies` ativo | Browser MCP · screenshot · ghost `policies` destacado |
| Toggle persiste | Alternar switch → recarregar a página → estado permanece |
| Toggle falho reverte na UI | Forçar erro (429 do `throttle:10,1`) → switch volta + toast de falha |

---

## 1. Objetivo

Painel operacional de **policies** (`mcp_governance_rules`) — **Constituição Art. 8, Policy
Gating** ([ADR 0079](../../decisions/0079-constituicao-oimpresso-7-camadas-governanca.md)).
Permite ligar/desligar uma rule do `ActionGate` **sem deploy de código** — decisão frequente
(relaxar gate numa janela de incidente, silenciar rule problemática).

Escopo MVP conforme o docblock do controller: **listar + alternar `enabled`**. Edit inline e
create ficam pra quando existir editor JSON.

Layout: `AppShellV2`, header `@/Components/shared/PageHeader` (congelado — ver §10).

---

## 2. Pré-condições

- Módulo `Governance` instalado + `governance_module` no pacote da subscription do business.
- Permission `governance.dashboard.view` (faz a entry/strip aparecer). Existe também a
  permission declarada `governance.policies.edit` — ⬜ **ABERTO**: ela **não** é checada na
  rota nem no controller (que só usa `middleware('auth')`); hoje serve de rótulo no
  `/roles/{id}/edit`, não de gate. Fechar isso é decisão [W].
- Tabela `mcp_governance_rules` com colunas lidas pelo Service (incl. `category`, `enabled`,
  `version`, `triggered_count`).
- Middlewares do grupo + `throttle:60,1` no `index` e **`throttle:10,1` no toggle**
  (deliberadamente apertado — a operação muda enforcement em runtime).

---

## 3. Passo-a-passo

1. **Abrir** `/governance/policies` (`governance.policies.index`).
2. **Controller monta 2 props eager** (`PoliciesController::index`): `rules_by_category` e `kpis`.
3. **Service lista e agrupa**: `PolicyToggleService::listPolicies()` →
   `groupByCategory()` → `kpisFor(rules, byCategory)`.
4. **Front renderiza** um `<Card>` por categoria, com um `<Switch>` por rule.
5. **Alternar** dispara `router.post('/governance/policies/{id}/toggle', { enabled })` com
   `preserveScroll` + `preserveState`.
6. **Estado otimista:** o React grava `overrides[id] = next` **antes** da resposta;
   `onError` faz rollback pro valor anterior e mostra `toast.error`.
7. **Backend valida** com `TogglePolicyRequest` (boolean) → `PolicyToggleService::togglePolicy` →
   `back()->with('status', "Policy #{id} ativada|desativada")`.
8. **Conferir no browser** (R1): screenshot + console limpo + recarregar pra provar persistência.

---

## 4. Tokens CSS

- Nada de cor crua nesta tela: usa `<Switch>` e `<Badge variant="outline">` do DS.
- Neutros semânticos: `text-muted-foreground`, `border-border` (não `zinc-*` cru).
- KPI por `tone`: `info` (total/triggered/categorias) e `success` (ativas).

---

## 5. Estados visuais

| Estado | Onde | Comportamento real |
|---|---|---|
| Vazio | `rules_by_category.length === 0` | `<EmptyState>` com texto pedagógico (ADS ainda não criou rules) |
| Pendente | `pendingId === rule.id` | `<Switch disabled>` enquanto a request está em voo |
| Otimista | `overrides[id]` | switch reflete o alvo antes da resposta |
| Rollback | `onError` | volta ao valor anterior + `toast.error('Falha ao alterar a policy. Revertido.')` |
| Sucesso | flash `status` | ⬜ **ABERTO**: `back()->with('status')` é gravado, mas não achei render dessa flash nesta tela — o feedback visível é o próprio switch |

---

## 6. Responsividade

- Container `mx-auto max-w-7xl p-6 space-y-4`; KPIs em `<KpiGrid cols={4}>`.
- Item da rule é `flex items-start gap-3` — texto quebra, o switch e os badges usam `shrink-0`.
- ⬜ **ABERTO**: sem verificação registrada em 1280px.

---

## 7. Atalhos

- Herdados do shell: `G G` (Governança) e `P` (primary "Gerenciar policies", que aponta
  justamente pra esta tela).
- ⬜ **ABERTO**: nenhum atalho próprio registrado em `Policies.tsx`.

---

## 8. Component contract

```ts
interface Rule { id: number; rule_key: string; name: string; description: string;
  enabled: boolean; version: number; triggered_count: number;
  created_by: string | null; updated_at: string }
interface Group { category: string; rules: Rule[] }

interface Props {
  rules_by_category: Group[]
  kpis: { total: number; enabled: number; triggered: number; categories: number }
}
```

Sub-navegação: `<GovernancaSubNav active="policies" />` — key `policies` vem dos `ghosts`
do `DataController` (fonte única).

---

## 9. DoD checklist

- [ ] `npx tsc --noEmit` sem aumento nos erros de `Pages/governance`
- [ ] `npm run lint:baseline:check` verde
- [ ] `npm run a11y:check` verde (o `<Switch>` tem `aria-label` dinâmico — não remover)
- [ ] `npm run layout:check` verde
- [ ] Strip com `policies` ativo (screenshot)
- [ ] Toggle persiste após reload
- [ ] Rollback visível quando a request falha
- [ ] Pest de Governance verde **no CT 100**

---

## 10. Pegadinhas

1. **Header congelado** — `shared/PageHeader`. Migrar pro canon é PR próprio com aprovação
   visual, nunca "de passagem".
2. **`throttle:10,1` no toggle é defesa, não sobra.** Alternar rules em rajada mexe em
   enforcement de runtime. Se a UI ganhar bulk toggle um dia, o throttle vira o gargalo —
   e isso é intencional (o charter proíbe bulk: *"1 rule por vez — força reflexão"*).
3. **O otimismo pode mentir se o `onError` sumir.** O par `overrides` + `onError` é o que
   impede a UI afirmar "ativada" quando o backend recusou. Mexer num sem o outro cria
   estado falso na tela.
4. **`Inertia::defer` foi revertido de propósito** (`ROLLBACK Wave W7 #953` no controller).
5. **`listPolicies()` roda 2×** por render (uma pra `rules_by_category`, outra dentro de
   `buildKpisPayload`). Custo conhecido; não é regressão nova.
6. **Toggle ainda não gera histórico.** O docblock e o charter apontam
   `mcp_governance_rule_history` como Fase 5+1 — hoje a mudança de enforcement **não deixa
   trilha própria**. Quem for auditar "quem desligou a rule" não encontra aqui. ⬜ ABERTO.
7. **Não esconder rules desativadas** (anti-padrão explícito do charter): [W] precisa
   enxergar as desligadas justamente pra religar.

---

## 11. ADR de origem

- [ADR 0079](../../decisions/0079-constituicao-oimpresso-7-camadas-governanca.md) — Art. 8 (Policy Gating).
- [ADR 0086](../../decisions/0086-fase-5-mvp-governance-actiongate-warn.md) — Fase 5 MVP, `ActionGate` em modo warn.
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2.
- [ADR 0366](../../decisions/0366-fronteira-jana-forja-governance-kb.md) — fronteira Jana/Governança (motivo da strip própria).
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — processo que exige este RUNBOOK.
