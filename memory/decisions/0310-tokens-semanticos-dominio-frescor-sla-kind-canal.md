---
slug: 0310-tokens-semanticos-dominio-frescor-sla-kind-canal
number: 310
title: "Tokens semânticos de domínio (DS v6) — frescor · kind · kpi-feature · vip · sla · canal promovidos ao cockpit.css via DTCG; --sla-* é a escala temporal CANÔNICA (4 passos + paid)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-24"
module: design-system
tags: [design-system, ds-v6, tokens, dtcg, cockpit, sla, frescor, kind, canal, vip, kpi-feature, dark-mode, anti-drift, foundations]
supersedes: []
superseded_by: []
related:
  - 0249-ds-v6-naming-amends-0235
  - 0235-ds-v4-accent-roxo-universal
  - 0190-primary-button-roxo-universal-295
  - 0300-errata-0239-nome-real-fonte-design-system
  - 0281-dark-mode-bridge-data-theme-tokens
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
---

> **Proposta por [CC] (Cowork) na sessão 2026-06-24, portada e numerada por [CL] (Claude Code).** Ratificação formal = merge por [W] (vira `status: aceito`).
> Origem: `_PROPOSTA-tokens-semanticos-frescor-kind-sla-canal.md` (read-only no Cowork) + entrega DTCG explícita do [CC]. [W] autorizou verbalmente 2026-06-24 ("promover as cores das telas Clientes/Atendimento pro DS, manter 1 só").
> **Tier 0** (token novo = camada Fundações/Shell). Roxo canônico `oklch(0.55 0.15 295)` ([ADR 0235](0235-ds-v4-accent-roxo-universal.md)/[0190](0190-primary-button-roxo-universal-295.md)) **intocado** — toda mudança é **aditiva**.

# ADR 0310 — Tokens semânticos de domínio (DS v6)

## Contexto (verificado em `origin/main`, worktree desta branch)

Auditoria de drift de cor no protótipo Cowork (`oimpresso.com.html`): **~2.091 `oklch()` + 480 hex crus** em 31 CSS de módulo. A estrutura (bg/borda/texto/raio/fonte/foco) já era 100% token. O drift estava concentrado em **conceitos semânticos que o DS ainda não nomeava** — recriados à mão tela a tela, com overrides `[data-theme=dark]` manuais que envelhecem.

Seis famílias apareciam dispersas (valores próprios por tela, sem nome canônico):

| Família | Tela de origem | Conceito |
|---|---|---|
| `--frescor-*` | Clientes | recência de contato/compra (4 níveis verde→amber→rose) |
| `--kind-*` | Clientes | tipo de entidade (customer/supplier/employee/representative) |
| `--kpi-feature-*` | Clientes (Faturamento) | card KPI escuro de destaque (navy 264, fora do eixo roxo) |
| `--vip` / `--vip-soft` | Clientes | selo dourado VIP |
| `--sla-*` | Atendimento | tempo de resposta/vencimento |
| `--canal-*` | Atendimento (Caixa Unificada) | tom por canal (email/ig/fb/ml) |

Pior caso — **`--sla`**: o conceito de pílula SLA/vencimento existia em **4 implementações divergentes** (Atendimento `.om-sla-pill` 4 passos · Vendas `.vd-sla-*` 3+paid · Financeiro `.fin-frescor-*` 3+paid · KB `.kb-fresh` 4 passos com hues próprios). Quatro rampas temporais competindo, nenhuma canônica.

## Decisão

### D1 · Promover as 6 famílias ao DTCG canônico, escopo `.cockpit`

As 57 vars (light) + 55 (dark) entram em **`resources/css/tokens/semantic.tokens.json`** sob o grupo `cockpit`, cada token com `$extensions.com.oimpresso.source = "cockpit.css .cockpit --<var>"`. O Style Dictionary (`npm run tokens:build`) as emite em `resources/css/tokens/_generated-cockpit-light.css` e `_generated-cockpit-dark.css`, que o `cockpit.css` **já importa** (linhas 11-12). Editar token = editar o JSON portável; o CSS é **saída** ([ADR 0300](0300-errata-0239-nome-real-fonte-design-system.md) errata 0239 + onda DTCG).

### D2 · Camada = `.cockpit` (Shell), consumo via `var()` — não `@theme` utility

**Decisão de camada delegada ao [CL] pela proposta.** Escolhi `.cockpit` (cockpit.css), consumo via `var(--sla-fresh)` etc., **não** `@theme` (inertia.css, que geraria utilities `bg-sla-fresh`). Razão: estas são **escalas semânticas de domínio**, irmãs diretas de `--pos/--neg/--warn` (`cockpit.semantic`), `--stage-*` (`cockpit.stage`) e `--origin-*` (`cockpit.origin`) — que **já vivem em `.cockpit` e se consomem por `var()`**. Todas as telas que as usam (Clientes, Atendimento, Vendas, Financeiro, KB) renderizam dentro do `.cockpit` (AppShellV2). Pôr no `@theme` quebraria a consistência com os irmãos e pesaria no `foundation-guard` (inertia.css é baseline só-desce). Coerência > novidade.

### D3 · `--sla-*` (4 passos + paid) é a escala temporal CANÔNICA

**[W] escolheu (2026-06-24) a escala de 4 passos da Atendimento (`om-sla-pill`) como canon.** Vendas, Financeiro e KB convergem para `--sla-*` — mata 3 famílias redundantes (uma só rampa temporal no sistema):

- **Vendas:** fresco→`fresh` · atrasando→`aging` · estourado→`expired` · paga→`paid`
- **Financeiro:** fresh/soon→`fresh` · warning→`aging` · **today→`late`** (ganhou o passo laranja que não tinha) · overdue→`expired` · paid→`paid`
- **KB:** fresh→`fresh` · aging→`aging` · stale→`late` · expired→`expired`

`--sla-paid` / `--sla-paid-soft` = estado neutro, resolvidos como **alias var-ref** (`var(--text-mute)` / `var(--bg-2)`) — propagam dark pela cascata, sem override próprio (mesmo padrão de `--bubble-me: var(--accent)`).

### D4 · `--frescor-*` NÃO consolida com `--sla-*`

Recência de contato/compra (Clientes) ≠ tempo de resposta/vencimento. São conceitos distintos; mantêm escalas separadas. (Resposta à pergunta "em aberto p/ [W]" da proposta — mantida a decisão do [CC] de não consolidar.)

## Não-goals (honestidade de escopo)

- ❌ **Não migro os consumidores nesta PR.** Trocar `.vd-sla-*`/`.fin-frescor-*`/`.kb-fresh`/`.cli-*` (raw oklch) por `var(--sla-*)`/`var(--frescor-*)` é **follow-up por bundle**, cada um com smoke visual (1 PR = 1 intent, `commit-discipline`). Esta PR só **cria os tokens** — eles passam a existir canonicamente e ficam disponíveis. A troca de raw→`var()` só **derruba** a contagem de cor-crua do `conformance-gate` (baseline só-desce), nunca sobe.
- ❌ **Não toco no protótipo Cowork** (`prototipo-ui/**`). A reconciliação do espelho é do [CC] via `CODE_NOTES.md` (handoff). O git é a fonte ([ADR 0300](0300-errata-0239-nome-real-fonte-design-system.md)).
- ❌ **Não renomeio/movo** `cockpit.css`/`inertia.css`/`foundations.css`.
- ❌ **Não atualizo a vitrine** do DS publicado (painel "Office Impresso / Ponto WR2") — item de handoff, não código.
- ❌ **Roxo 295 intocado.** Mudança 100% aditiva.

## Consequências

✅ **Boas:**
- Uma só rampa temporal (`--sla-*`) no sistema — mata 3 famílias divergentes na fonte.
- Light pixel-idêntico ao anterior (valores verbatim das telas de origem). Dark vem do token (mesmos valores dos overrides manuais), menos CSS, sem drift.
- Dark "de graça" via DTCG: cada token carrega seu par dark; telas que migrarem param de manter `[data-theme=dark]` à mão.

⚠️ **Tradeoffs:**
- Os consumidores (`.vd-sla-*` etc.) ainda têm raw oklch até serem migrados (follow-up). Os tokens existem; a adoção é incremental.
- `kpi-feature` usa navy 264 (fora do eixo roxo) **de propósito** (card escuro de destaque) — documentado pra não ser confundido com drift verde×roxo.

## Validação

- ✅ `npm run tokens:build` — emite as 57+55 vars em `_generated-cockpit-{light,dark}.css` (cockpit.css já importa).
- ✅ `node scripts/governance/dtcg-equivalence.mjs` — **320 valores fiéis, 0 divergências** (208 prévios + 112 novos).
- ✅ `node scripts/foundation-guard.mjs` — fundação íntegra (0 espalhamento novo; 29 .css na allowlist; mudança vive no subdir `tokens/`, fora do scan top-level).
- ✅ `node scripts/conformance-gate.mjs --all` — `--accent` segue roxo 250–330; sem papel de token invertido; sem cor-crua nova em regra de tela.
- ✅ `node scripts/stylelint-baseline.mjs` — **419/419, delta 0** (zero regressão; novos tokens são oklch/var, sem hex novo).

## Notas

- Os valores canônicos vieram do DTCG explícito do [CC] (`_PARCIAL-domain-semantic.tokens.json`), idênticos aos das telas de origem (light) + overrides dark afinados — promoção é lift mecânico, não reinvenção (R2 cópia literal).
- O grupo JSON `cockpit` ↔ arquivo `cockpit.css` segue a convenção do `semantic.tokens.json` (grupo ≈ arquivo-alvo); o nome de var emitido vem do `source`, não do path JSON.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-06-24 | [W] autoriza + [CC] propõe/entrega DTCG + [CL] porta/redige | 6 famílias semânticas de domínio promovidas ao DTCG `.cockpit`; `--sla-*` 4 passos vira escala temporal canônica |
