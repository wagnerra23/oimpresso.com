---
slug: 0239-governanca-design-system-git-ssot-regressao-ia
number: 239
title: "Governança do Design System — git é fonte única; mudança flui Cowork→Code→git com regressão julgada por IA; 1 spec vigente na raiz + antigos arquivados"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
proposed_at: "2026-05-30"
decided_at: "2026-05-30"
module: governance
quarter: 2026-Q2
tier: CANON
trust_level: tier-0-irrevogavel
tags: [governance, design-system, ssot, git, regressao, ia-judge, ai-judge, append-only, cowork-loop, claude-design, anti-repeticao]
related:
  - 0235-ds-v4-accent-roxo-universal
  - 0236-governanca-evolucao-doc-design
  - 0238-soberania-constituicao-wagner
  - 0237-jana-reconcile-loop-unico
related_adrs: [0061, 0094, 0107, 0114, 0165, 0216, 0220, "UI-0013"]
parent_charter: mission.constituicao-v2
supersedes: []
authors: [wagner, claude-code]
---

# ADR 0239 — Governança do Design System (regra única)

> **Status:** ✅ Decidida por Wagner 2026-05-30 ("isso deveria ser uma regra só… crie uma ADR…
> nunca mais quero repetir isso"). Numerada (0239 · ADR 0028) e portada pro git pelo [CL].
> **Pendente:** merge em `main` por [W] = ratificação. Enforcement (R3) é wiring de follow-up
> (compõe ferramentas que já existem — ver Mecanismo); a regra vale na aceitação.

## Contexto

A casa do Design System sujava a cada refino: versões de spec (`Design System v3/v4/v4.2.html`)
acumulando na raiz, faxinas arquivando **o vigente junto com os velhos** ("cadê o v4?"), e o
protótipo do Cowork ora sendo tratado como fonte, ora como cópia. Wagner, 2026-05-30, fechou a
questão: **"nunca mais quero repetir isso"** — vira regra canônica única, não combinado verbal.

Isto **consolida** (não contradiz) ADR 0235 (tokens/roxo + plugin dono da interface), 0236
(governança de evolução de doc de design), 0114 (loop Cowork) e 0238 (soberania de [W]).

## Decisão — a regra única (4 invariantes)

### R1 · Git é a fonte única do Design System
`design-system.css` + `tokens.css` em `wagnerra23/oimpresso.com@main` **são** o Design System.
Tudo mais — protótipo do Cowork, qualquer `Design System vX.html`, showcases, Figma — é
**derivado/representação**, nunca a fonte. Divergiu do git? O git vence. (ADR 0061, 0235)

### R2 · Mudança de DS flui Cowork→Code→git (Cowork nunca é autoridade)
Cowork **propõe** (F0) uma evolução do DS; **[CL] Code** aplica na fonte do git via **PR**;
**[W] mergeia**. Cowork **não** edita, numera nem commita `design-system.css`/`tokens.css`.
"Enviar pra Code" é obrigatório — não existe DS mudado direto no protótipo virando verdade. (ADR 0238, 0114, 0094)

### R3 · Toda mudança de DS passa por regressão julgada por IA (gate obrigatório)
PR que toca `design-system.css` ou `tokens.css` **não mergeia** sem passar na **regressão
julgada por IA** — compõe o que já existe no repo (não inventa ferramenta nova):
- **Diff visual antes/depois** — `visual-regression.yml` (gate F1.5/F3 · ADR 0107).
- **IA-juíza nota as telas afetadas** — skill `screen-grade` (0–100, **gate ≥80**) + `critique-score`
  do Claude Design plugin (≥80), no mesmo padrão LLM-as-judge do `RagasJudgeService`/`ragas-gate.yml`.
- **Freshness** — `DesignDocsFreshnessChecker` (ADR 0236 máquina-4): nenhum doc cita token aposentado.
Reprovou qualquer um = **não mergeia**. A nota **só sobe** (ratchet · ADR 0236).

### R4 · Arquivo: 1 spec vigente na raiz; antigos linkados; changelog
Entre os **specs visuais** (`Design System vX.html`): **exatamente 1 na raiz — o vigente** (hoje
`Design System v4.html`), ao lado da fonte (`design-system.css`/`tokens.css`). Passados +
propostas → `_arquivo/ds/` (histórico antigo → `_arquivo/ds-historico/`). No **fim** do
`_arquivo/INDEX.md`, seção **"## DS — versões antigas (links)"** com 1 link por versão arquivada.
Troca de vigente = **append-only** (move, não apaga) + 1 linha no **changelog** do INDEX + 1 no `SYNC_LOG`.
(ADR 0003, 0236)

### R5 · Toda regra de design mora no índice-mestre (e o teste garante)
Toda regra/ADR de design — este 0239, mais 0235 (tokens) e 0236 (evolução-doc) — é **referenciada
no `INDEX-DESIGN-MEMORIAS.md`**. Atualizar o índice é **parte do "pronto"** (ADR 0236), e o
**`DesignIndexSingleSourceTest` (invariante c) falha o CI** se uma regra de design canônica não
estiver no índice. Ninguém precisa pedir — é **gate, não memória**.

> **Invariantes (nunca):** nunca 2 specs visuais na raiz · nunca DS mudado sem regressão-IA verde ·
> nunca Cowork como fonte · nunca regra de design fora do índice (testado) · nunca delete (append-only).

## Mecanismo (compõe ferramentas existentes)

| Peça da regra | Já existe no repo | Follow-up de wiring |
|---|---|---|
| R1 fonte git | `prototipo-ui/design-system.css` + `tokens.css` (espelho da raiz do git) | — |
| R2 fluxo Cowork→Code | loop ADR 0114 + canais `CODE_NOTES`/`COWORK_NOTES`/`SYNC_LOG` | — |
| R3 regressão-IA | `visual-regression.yml` · skill `screen-grade` · `RagasJudgeService`/`ragas-gate.yml` · `critique-score` (plugin) · `DesignDocsFreshnessChecker` | adicionar trigger por path `design-system.css`/`tokens.css` num `ds-regression-gate.yml` que orquestra os acima |
| R4 arquivamento | `_arquivo/INDEX.md` + changelog (v1.x) | — (regra operacional; Cowork executa) |
| R5 índice obrigatório | `DesignIndexSingleSourceTest` (inv. c) — teste já existia | **`design-index-gate.yml`** (novo neste PR — antes o teste só rodava local, não gateava PR) |

## Responsabilidades

| Papel | Sobre o DS | Pode | Não pode |
|---|---|---|---|
| **[W]** | soberano | aprovar evolução, mergear, definir vigente | — |
| **[CC] Cowork** | propõe | propor evolução (F0), manter `_arquivo`/changelog, sinalizar drift | editar/numerar/commitar a fonte; ser fonte; 2 specs na raiz |
| **[CL] Code** | executor | aplicar no git via PR, rodar regressão-IA, abrir PR | mergear sem [W]; mergear com regressão-IA vermelha |

## Consequências

- **Positiva:** acaba a repetição — uma regra só, citável. DS protegido de drift; regressão-IA
  pega quebra de layout antes do merge; raiz limpa (1 vigente) com histórico acessível por link.
- **Negativa:** todo PR de token fica mais lento (gate de regressão-IA). **De propósito** — o DS é piso de tudo.
- **Custo de wiring:** 1 workflow `ds-regression-gate.yml` orquestrando ferramentas que já existem (R3).

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-30 | [W] decide + [CL] redige | esta regra única (R1–R4) — "nunca mais repetir" |
