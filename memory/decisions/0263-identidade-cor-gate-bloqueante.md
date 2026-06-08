---
slug: 0263-identidade-cor-gate-bloqueante
number: 263
title: "Identidade de cor vira gate bloqueante no main: chrome roxo único + semântica governada, enforçados por required status checks"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-08"
accepted_at: "2026-06-08"
accepted_via: "Wagner pediu na sessão (handoff Cowork 'Mapa de Identidade ERP - CC'): 'como nunca mais me preocupar com isso, e que ninguém avacalhe com minha identidade' → 'fundamenta isso e reconstrua para que comece a valer, urgente'. Branch protection do main aplicado na mesma sessão."
module: governance
quarter: 2026-Q2
tags: [identidade-visual, design-system, cor, ci, ratchet, branch-protection, governance, enforcement]
supersedes: []
supersedes_partially: []
superseded_by: []
pii: false
---

# ADR 0263 — Identidade de cor é gate bloqueante no `main`

**Funda em:** [0190](0190-primary-button-roxo-universal-295.md) (primary roxo universal `oklch(0.55 0.15 295)`), [0235](0235-ds-v4-accent-roxo-universal.md) (accent roxo universal DS v4), [0249](0249-ds-v6-naming-amends-0235.md) (naming DS v6), [0094](0094-constituicao-v2-7-camadas-8-principios.md) (Constituição v2 — Pilar "loop fechado por métrica").
**Espelha o padrão de:** [0155](0155-module-grade-v3-sub-dimensoes-gate-ci.md) (catraca de nota de módulo no CI), [0108](0108-regressao-visual-pest-browser-tier-2.md) + [0250](0250-screen-qa-specialist-sustentavel.md) (CI vigia · ratchet só-desce).

## Contexto

O sistema **já tinha** uma identidade de cor canônica — roxo `oklch(0.55 0.15 295)` ([0190](0190-primary-button-roxo-universal-295.md)/[0235](0235-ds-v4-accent-roxo-universal.md)) — e ela já estava aplicada em Shell, Vendas, Financeiro e Oficina. O modelo é de **2 camadas**, e confundir as duas é o único erro real:

- **Chrome (identidade)** — botão, foco, link, estado ativo, primary. **1 cor única, travada: o roxo.** Diz "é o mesmo produto".
- **Semântica (significado)** — status (ok/atenção/erro/info) + origem (`--origin-*`) + etapa de pipeline (`--stage-*`). **N cores governadas, de propósito** — é wayfinding, não decoração. Padronizar o chrome **não** apaga essa variedade.

O handoff Cowork **"Mapa de Identidade ERP - CC"** (2026-06-08) consolidou isto e apontou que a verdade do estado é o `@main` (os espelhos locais `resources/css/` driftam). Auditando o `@main` real, restava **uma única ilha**: o módulo Compras com accent em navy hex cru (`#1f3a5f`). Essa ilha **já havia sido fechada** no `main` (commit `design(compras): chrome navy → roxo canon`) — o Compras passou a herdar `var(--accent)`.

**O problema que sobrou não era de pintura — era de garantia.** Os gates que protegem a identidade (`conformance-gate`, `ui-lint`, `ui-architecture-gate`) existiam e rodavam em todo PR, mas **não eram bloqueantes**: um PR com cor fora do padrão ficava com o check **vermelho** e **ainda assim podia ser mergeado**; e admin podia furar. A identidade dependia de disciplina/revisão humana — exatamente o que Wagner pediu para nunca mais ter que fazer ("não aguento mais revisar isso").

## Decisão

Tornar a identidade de cor **mecanicamente inviolável no `main`**, não dependente de revisão:

1. **Regra-mestre (canon, 2 camadas):**
   - **Chrome = 1 cor.** Roxo `oklch(0.55 0.15 295)`. Nenhum módulo redefine `--accent` com cor própria.
   - **Semântica = N cores governadas.** Status/`--origin-*`/`--stage-*` continuam variando — informação, não inconsistência.
   - **Tela nova herda, nunca declara token.** Um bundle de tela só pode usar `var(--…)`; não carrega bloco de definição de cor própria (foi o que o Compras violava). Compras `@main` é o **modelo** desse padrão.

2. **Enforcement determinístico** — os 3 gates de cor viram **required status checks** no `main`:
   - `Conformance · cor-crua ratchet vs baseline` — cor crua (hex/oklch numérico) em `.css` de tela só-desce; `--accent*` sempre roxo hue 250–330; inclui `foundation-guard` (token-def só em arquivos da allowlist).
   - `UI Lint · ratchet vs baseline` — R1: famílias Tailwind `-NNN` + hex em `.tsx/.jsx`/`.css` proibidas (passa só token semântico sem `-NNN`, `bg-[var(--x)]`, `#fff/#000`).
   - `UI architecture (AppShell + accent + core screens)` — accent canon (≠ roxo 295 falha) + AppShellV2.

3. **`enforce_admins: true`** — nem admin fura por cima de gate vermelho sem desligar a proteção conscientemente. Fecha o último buraco manual.

Os gates são **catraca (ratchet só-desce)**: travam a **piora** (cor nova fora do padrão), não quebram o que já existe — o estado atual vira o piso. São path-filtered (rodam em CSS/TSX); PR que não toca esses paths não fica preso (precedente: `module-grades-gate`).

## Consequências

**O que fica garantido (sem revisão humana):**

| Garantia | Mecanismo |
|---|---|
| PR (humano **ou** IA) com cor fora do roxo **não mergeia** | 3 gates de cor = required checks no `main` |
| Estado atual de identidade não regride | catraca só-desce vs `origin/main` |
| Nem admin fura sem ação consciente | `enforce_admins: true` |
| Tela nova nasce obrigada a herdar | `conformance-gate` + `foundation-guard` (token-def fora da allowlist = 🔴) |

- **Positivo:** a identidade saiu da cabeça/disciplina do time e entrou na máquina. Wagner não precisa mais revisar cor.
- **Custo:** quem mandar cor crua/fora-do-padrão terá o PR travado até conformar (ou usar override consciente de catraca). É o objetivo, não um efeito colateral.
- **Não-objetivo:** isto **não** é regressão visual de pixel (`visual-regression` segue STUB — [0108](0108-regressao-visual-pest-browser-tier-2.md)); a rede de pixel continua sendo UI-Judge + olho humano/staging. E **não** força tokenização total de todo neutro de uma vez — a catraca só garante que nunca piora e melhora incrementalmente.

**Reversão:** desligar qualquer gate dos required checks (ou `enforce_admins`) é mudança de branch protection — Tier 0, decisão de Wagner, via nova ADR.
