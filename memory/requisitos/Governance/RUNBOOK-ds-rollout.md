---
slug: governance-runbook-ds-rollout
title: "Governance — Runbook da tela DS Rollout (/governance/ds-rollout)"
type: runbook
module: Governance
tela: governance/DsRollout
owner: W
status: rascunho
last_validated: "2026-08-05"
related_adrs:
  - 0189-pageheader-canon-v3-1-cadastro-roxo
  - 0190-primary-button-roxo-universal-295
  - 0239-governanca-design-system-git-ssot-regressao-ia
  - 0240-task-ledger-git-native-cowork-code
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0366-fronteira-jana-forja-governance-kb
---

# RUNBOOK — `/governance/ds-rollout` (DS Rollout + Ledger de Conformidade)

> **Origem desta versão (recibo):** escrito em **2026-08-05** por leitura do código
> (`DsRolloutController` · `Http/routes.php` · `DsRollout.tsx` · `DsRollout.charter.md` ·
> `DsRollout.casos.md` · `scripts/ds-ledger.mjs` · `governance/ds-ledger.json`), pra destravar
> a ligação da strip `GovernancaSubNav` — o hook `block-mwart-violation.mjs` (ADR 0104 §F1)
> exige RUNBOOK antes de Edit no `.tsx`, e a lacuna era **pré-existente**.
>
> `status: rascunho` é literal: **nenhum passo foi EXECUTADO** nesta sessão. O que não deu
> pra derivar do código está **⬜ ABERTO**.
>
> ⚠️ **Esta tela é a exceção do módulo:** é a única das cinco que usa o **PageHeader canon**
> (`@/Components/PageHeader`); as outras usam o `shared/PageHeader` congelado. Não uniformizar.

---

## 0. Estado final esperado

| Verificação | Como conferir |
|---|---|
| Rota responde 200 autenticado | `curl -sv https://oimpresso.com/governance/ds-rollout` (hop final `200`) |
| Strip com `DS Rollout` ativo | Browser MCP · screenshot · ghost `ds-rollout` destacado |
| O número do Ledger veio de gate | Badge `medido @sha · timestamp`; sem o artefato, badge `TODO ledger` + `0%` |
| Contrato dos casos continua válido | Os UC estão em [`DsRollout.casos.md`](../../../resources/js/Pages/governance/DsRollout.casos.md) — **ler lá, não restatear aqui** |

---

## 1. Objetivo

Mostrar, numa tela só, (a) o **plano** de portar o Design System inteiro em ondas medíveis e
(b) o **Ledger de Conformidade DS** — o placar por-tela × por-teste que prova "tudo aplicado"
**mecanicamente**, não na palavra de ninguém.

Divisão canônica do conteúdo, e ela importa:

- **O PLANO é estático e vive no `.tsx`** (blocos A/B/C/D, tabelas de onda, cards de prova) —
  é o próprio design traduzido, não dado de runtime.
- **O LEDGER é a parte viva**, e chega por uma prop só: `census`.

Layout: `AppShellV2`, header `@/Components/PageHeader` (canon v3 — ADR 0189/0190).

---

## 2. Pré-condições

- Módulo `Governance` instalado + `governance_module` no pacote da subscription.
- Permission `governance.dashboard.view` (gate da entry/strip).
- Middlewares do grupo + `throttle:60,1` (render estático, throttle leve).
- **Opcional (é o ponto):** `governance/ds-ledger.json` gerado por
  `npm run ds:ledger -- --write`. **Sem o artefato a tela não quebra** — cai no
  `staticFallback()` rotulado. Ausência é caminho previsto, não erro.

---

## 3. Passo-a-passo

1. **Abrir** `/governance/ds-rollout` (`governance.ds-rollout.index`).
2. **Controller** (`DsRolloutController::index`) monta **uma** prop: `census = loadCensus()`.
3. **`loadCensus()`** lê `base_path('governance/ds-ledger.json')`; só aceita o JSON se ele for
   array **e** tiver a chave `ledger` como array. Qualquer outra coisa → fallback.
4. **`staticFallback()`** devolve `measured=false`, `progressPct=0`,
   `progressLabel='snapshot estático · TODO ledger'` e uma linha literal
   `Rode 'npm run ds:ledger -- --write'`. **A trava é essa:** a tela nunca exibe número
   não-medido como se fosse real.
5. **Front** calcula `pct` com clamp `0..100` e
   `measured = census.measured !== false && !!census.generatedAt` — precisa das **duas**
   condições (flag + carimbo de tempo).
6. **Regenerar o Ledger** (quando quiser o número fresco): `npm run ds:ledger -- --write`.
7. **Conferir no browser** (R1): screenshot + console limpo + checar o badge de medição.

---

## 4. Tokens CSS

- **Proibido bloco `<style>` com OKLCH cru** — era justamente o débito do protótipo Cowork
  que este plano combate; barraria o `conformance-gate`.
- Paleta semântica: `primary` (roxo canônico ADR 0190) · emerald (positivo/probe) ·
  amber (atenção/referência) · rose (cor crua/gap).
- Dark mode por `dark:` herdado do `AppShellV2` — **sem** override per-tela.
- Primitivas de layout do DS (`Inline`, `Grid` de `@/Components/layout`) em vez de flex à mão.

---

## 5. Estados visuais

| Estado | Onde | Comportamento real |
|---|---|---|
| Medido | `measured === true` | badge com `@sha` + `generatedAt` |
| Não medido | fallback ou `generatedAt` nulo | badge âmbar `snapshot estático · TODO ledger`, `0%` |
| Linha-referência | `row.reference === true` | destaque `★`, fora da conta do `%` |
| Célula não medida | `CellState = 'na'` | `–` com tooltip "não medido" — **nunca `✓`** |
| Tree guard | `census.treeGuard` | pass/violations; opcional (pode vir `null`) |

---

## 6. Responsividade

- Container `mx-auto max-w-5xl` (**mais estreito** que as outras telas do módulo, que usam
  `max-w-7xl`) — é escolha de leitura de documento longo, não descuido.
- Corpo com `px-6 pb-24 pt-7`.
- ⬜ **ABERTO**: sem verificação registrada em 1280px.

---

## 7. Atalhos

- Herdados do shell (`G G`, primary `P`).
- ⬜ **ABERTO**: nenhum atalho próprio em `DsRollout.tsx`.

---

## 8. Component contract

```ts
type CellState = 'yes' | 'no' | 'ref' | 'na'

interface LedgerRow { screen: string; note?: string; reference?: boolean
  cells: { tokens: CellState; primitivos: CellState; probe: CellState
           dark: CellState; approved: CellState } }
interface TreeGuard { pass: boolean; violations: number | null }

interface Census {
  ledger: LedgerRow[]
  progressPct: number
  progressLabel?: string
  measured?: boolean
  measuredAgainstSha: string | null
  generatedAt: string | null
  treeGuard?: TreeGuard | null
  counts?: { screens: number; done: number; references: number }
}

interface Props { census: Census }
```

Sub-navegação: `<GovernancaSubNav active="ds-rollout" />` — key `ds-rollout` vem dos `ghosts`
do `DataController` (fonte única).

---

## 9. DoD checklist

- [ ] `npx tsc --noEmit` sem aumento nos erros de `Pages/governance`
- [ ] `npm run lint:baseline:check` verde
- [ ] `npm run a11y:check` verde
- [ ] `npm run layout:check` verde
- [ ] `npm run ds:report` / `ds:canon:check` sem regressão de cor crua nesta Page
- [ ] Strip com `ds-rollout` ativo (screenshot)
- [ ] Badge de medição correto **nos dois caminhos** (com e sem `governance/ds-ledger.json`)
- [ ] UCs de [`DsRollout.casos.md`](../../../resources/js/Pages/governance/DsRollout.casos.md) revisitados se o comportamento mudar
- [ ] Pest de Governance verde **no CT 100**

---

## 10. Pegadinhas

1. **Header canon aqui, congelado nas irmãs.** Importar `@/Components/shared/PageHeader`
   nesta tela **falha o `pageheader-gate`** (é tela nova). O inverso — trocar o header das
   outras três por este — é migração com aprovação visual, PR próprio.
2. **A trava do número é o valor da tela.** Fazer o fallback exibir um `%` "estimado" destrói
   o propósito inteiro (vira placar que não veio de gate). O `progressLabel` e o `measured=false`
   existem pra isso.
3. **`measured` exige DUAS condições** (`measured !== false` **e** `generatedAt`). Simplificar
   pra uma só reintroduz o verde falso.
4. **`status: draft` no charter é real** — aguarda aprovação visual [W] (gate F2 do PROTOCOL:
   [W] aprova **screenshot**, não tabela). Marcar `live` sem isso é anti-padrão explícito.
5. **O `.tsx` tem ~32k** (o plano inteiro é conteúdo estático). Edição cirúrgica: mexer só no
   trecho alvo, nunca reescrever o arquivo.
6. **A tela não executa onda nenhuma** (Non-Goal explícito). Botão que dispare tokenização/
   port de tela é Tier 0 e passa por ADR + [W].
7. **O plano ficou datado.** O charter/`.tsx` falam do `scripts/ds-ledger.mjs` como "próximo
   passo", mas o script **e** o artefato `governance/ds-ledger.json` **já existem** hoje.
   ⬜ ABERTO: reconciliar a prosa do `.tsx` com o estado real é PR de conteúdo, com aprovação
   visual — não fazer de passagem.

---

## 11. ADR de origem

- [ADR 0189](../../decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md) — PageHeader canon v3.1.
- [ADR 0190](../../decisions/0190-primary-button-roxo-universal-295.md) — primary roxo universal (hue 295).
- [ADR 0239](../../decisions/0239-governanca-design-system-git-ssot-regressao-ia.md) — DS em git é SSOT (o que o Ledger prova).
- [ADR 0240](../../decisions/0240-task-ledger-git-native-cowork-code.md) — ledger git-native / evidência fecha task.
- [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — loop Cowork ↔ Code (esta tela é tradução F3).
- [ADR 0366](../../decisions/0366-fronteira-jana-forja-governance-kb.md) — fronteira Jana/Governança (motivo da strip própria).
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — processo que exige este RUNBOOK.
