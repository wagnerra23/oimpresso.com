---
slug: governance-runbook-drift-alerts
title: "Governance — Runbook da tela Drift Alerts (/governance/drift)"
type: runbook
module: Governance
tela: governance/DriftAlerts
owner: W
status: rascunho
last_validated: "2026-08-05"
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0086-fase-5-mvp-governance-actiongate-warn
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0366-fronteira-jana-forja-governance-kb
---

# RUNBOOK — `/governance/drift` (Drift Alerts)

> **Origem desta versão (recibo):** escrito em **2026-08-05** por leitura do código
> (`DriftAlertsController` · `DriftAlertService` · `Http/routes.php` · `DriftAlerts.tsx` ·
> `DriftAlerts.charter.md`), pra destravar a ligação da strip `GovernancaSubNav` — o hook
> `block-mwart-violation.mjs` (ADR 0104 §F1) exige RUNBOOK antes de Edit no `.tsx`, e a
> lacuna era **pré-existente**.
>
> `status: rascunho` é literal: **nenhum passo foi EXECUTADO** nesta sessão. O que não deu
> pra derivar do código está **⬜ ABERTO**.
>
> ⚠️ **Atenção ao nome:** a **rota** é `/governance/drift` e a **key do ghost** é `drift`;
> só o arquivo da Page e deste RUNBOOK falam `drift-alerts`. Usar `drift-alerts` como
> `active` da strip quebra o destaque silenciosamente.

---

## 0. Estado final esperado

| Verificação | Como conferir |
|---|---|
| Rota responde 200 autenticado | `curl -sv https://oimpresso.com/governance/drift` (hop final `200`) |
| Strip com `Drift alerts` ativo | Browser MCP · screenshot · ghost `drift` destacado |
| KPIs batem com a CLI | `php bin/check-scope.php` deve dar o mesmo veredito de drift da tela |
| Card de histórico mostra empty state | Esperado hoje: `persistedAlerts()` retorna `[]` por construção (§10.4) |

---

## 1. Objetivo

Mostrar a divergência entre **intenção declarada** (o `contains[]` do `SCOPE.md` de cada
módulo) e a **realidade do filesystem** (`Modules/<X>/Http/Controllers/`) — **Constituição
Art. 7, Module Charter** ([ADR 0079](../../decisions/0079-constituicao-oimpresso-7-camadas-governanca.md)).

Controller que existe e não está declarado = mudança não registrada = sintoma direto da
violação da REGRA PRIMÁRIA *"mexeu, registra"*. A tela é o espelho em runtime do
`bin/check-scope.php`.

Layout: `AppShellV2`, header `@/Components/shared/PageHeader` (congelado — §10).

---

## 2. Pré-condições

- Módulo `Governance` instalado + `governance_module` no pacote da subscription.
- Permission `governance.dashboard.view` (gate da entry/strip).
- Diretório `Modules/` legível pelo processo PHP — o scan é **filesystem**, não banco.
- `SCOPE.md` de cada módulo com frontmatter YAML parseável (parse falho é logado via
  `Log::error` e **não** derruba a tela).
- Middlewares do grupo + `throttle:20,1` (o mais apertado das telas de leitura da Governança,
  porque o scan de filesystem é caro).

---

## 3. Passo-a-passo

1. **Abrir** `/governance/drift` (`governance.drift.index`).
2. **Controller** (`DriftAlertsController::index`) chama `buildDriftsPayload()` →
   `DriftAlertService::getActiveDrifts(limit: 500)`.
3. **Service varre `Modules/`**, e por módulo:
   - lê `Modules/<X>/SCOPE.md` → `declaredControllers()` (frontmatter YAML);
   - lista os controllers reais → `actualControllers()`;
   - **descarta boilerplate** (constante `BOILERPLATE` — `DataController`, `InstallController`,
     `SuperadminController`, `Controller`), que não precisa ser declarado;
   - o que sobrar e não estiver declarado vira item de `report`.
4. **Módulos sem `SCOPE.md`** entram em `modules_without_scope` (lista separada, não é "drift").
5. **KPIs** (`buildKpisPayload`) derivam do mesmo payload: `total_drift`, `modules_with_drift`,
   `modules_without_scope`, `modules_total`.
6. **`persistedAlerts()`** é chamado e hoje **retorna `[]` por construção** (§10.4).
7. **Front** renderiza 3 blocos: drift em runtime (`<Alert variant="destructive">` por módulo,
   com links pro GitHub), módulos sem SCOPE (badges/links) e histórico (empty state).
8. **Conferir no browser** (R1) + cruzar com `bin/check-scope.php`.

---

## 4. Tokens CSS

- Severidade **nunca** por cor crua: helper `severityVariant()` mapeia
  `critical|high|error → destructive`, `warning|medium → secondary`, resto → `outline`.
- KPI muda de `tone` conforme o dado (`warning` quando > 0, `success` quando zero) —
  é o padrão desta tela: **cor comunica veredito, não decora**.
- Neutros: `text-muted-foreground`, `border-border`.

---

## 5. Estados visuais

| Estado | Onde | Comportamento real |
|---|---|---|
| Sem drift | `report.length === 0` | `<EmptyState icon="check-circle" title="Sem drift">` |
| Com drift | `report.map` | `<Alert variant="destructive">` + contagem `N de M controllers` + hint de remediação |
| Sem SCOPE.md | `modules_without_scope.length > 0` | card inteiro só aparece se a lista não estiver vazia |
| Histórico vazio | `persisted_alerts.length === 0` | `<EmptyState>` explicando que o cron ainda não roda |
| Loading | — | ⬜ **ABERTO**: scan é síncrono e sem skeleton (o charter já lista isso como dívida) |

---

## 6. Responsividade

- Container `mx-auto max-w-7xl p-6 space-y-4`; KPIs em `<KpiGrid cols={4}>`.
- Badges de módulo em `flex flex-wrap gap-2` — quebram sem estourar largura.
- ⬜ **ABERTO**: sem verificação registrada em 1280px.

---

## 7. Atalhos

- Herdados do shell (`G G`, primary `P`).
- ⬜ **ABERTO**: nenhum atalho próprio em `DriftAlerts.tsx`.

---

## 8. Component contract

```ts
interface ReportItem { module: string; undeclared: string[];
  undeclared_count: number; total_actual: number }
interface PersistedAlert { id: number; category: string; severity: string;
  module: string | null; detail: string; created_at: string }

interface Props {
  kpis: { total_drift: number; modules_with_drift: number;
          modules_without_scope: number; modules_total: number }
  report: ReportItem[]
  modules_without_scope: string[]
  persisted_alerts: PersistedAlert[]
}
```

Sub-navegação: `<GovernancaSubNav active="drift" />` — **`drift`, não `drift-alerts`** (§0).

---

## 9. DoD checklist

- [ ] `npx tsc --noEmit` sem aumento nos erros de `Pages/governance`
- [ ] `npm run lint:baseline:check` verde
- [ ] `npm run a11y:check` verde
- [ ] `npm run layout:check` verde
- [ ] Strip com `drift` ativo (screenshot) — conferir que o destaque de fato acendeu
- [ ] Veredito da tela == veredito de `bin/check-scope.php` (paridade obrigatória)
- [ ] Pest de Governance verde **no CT 100**

---

## 10. Pegadinhas

1. **Rota `drift` × arquivo `DriftAlerts`.** A key do ghost é `drift`. Passar `drift-alerts`
   pro `GovernancaSubNav` **não gera erro** — só não acende nada. Falha silenciosa.
2. **Header congelado** (`shared/PageHeader`) — migrar é PR próprio.
3. **Paridade com a CLI é contrato.** O charter registra `bin/check-scope.php` como teste
   anti-regressão: mudar a regra de detecção aqui sem mudar lá cria dois vereditos pro mesmo
   fato. Se divergirem, o conserto é dos dois no MESMO PR.
4. **`persistedAlerts()` retorna `[]` por construção**, não por falta de dado: o comentário
   no Service diz que falta adicionar `module_drift` ao enum de `mcp_alertas` (exige migration
   + ADR). Ou seja, o card "Histórico" **está correto ao ficar vazio** — não é bug a caçar.
5. **Boilerplate filtrado é decisão, não descuido.** Tirar um nome da constante `BOILERPLATE`
   faz ~todos os módulos aparecerem em drift de uma vez. Mexer só com motivo escrito.
6. **`getActiveDrifts` é chamado 2×** por render (`index` + `buildKpisPayload`) — e cada
   chamada é um **scan de filesystem**. É o custo mais alto das telas de Governança;
   qualquer piora de performance começa a olhar por aqui.
7. **Sem auto-fix, sem snooze** (anti-padrões explícitos do charter): a tela alerta, o humano
   resolve. Botão "ignorar" vira gambiarra silenciosa.

---

## 11. ADR de origem

- [ADR 0079](../../decisions/0079-constituicao-oimpresso-7-camadas-governanca.md) — Art. 7 (Module Charter).
- [ADR 0086](../../decisions/0086-fase-5-mvp-governance-actiongate-warn.md) — Fase 5 MVP.
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (REGRA PRIMÁRIA "mexeu, registra").
- [ADR 0366](../../decisions/0366-fronteira-jana-forja-governance-kb.md) — fronteira Jana/Governança (motivo da strip própria).
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — processo que exige este RUNBOOK.
