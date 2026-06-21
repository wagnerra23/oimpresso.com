---
slug: 0282-protocolo-v2-colapso-ratificacao
number: 282
title: "Protocolo v2 (colapso) — ratificação: 6→2 papéis · 7→3 fases · memória=git SSOT · intake=Issues/cowork-inbox · gates=CI · code write-path com review-gate"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-17"
module: governance
tags: [protocolo, cowork, loop, governanca, git-ssot, ci, intake, constituicao, tier-0]
supersedes: []
superseded_by: []
related:
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0241-loop-design-cowork-code-autonomo-zero-humano
  - 0238-soberania-constituicao-wagner
  - 0239-governanca-design-system-git-ssot-regressao-ia
  - 0094-constituicao-v2-7-camadas-8-principios
---

# ADR 0282 — Protocolo v2 (colapso): ratificação

## Contexto

A proposta-pai (PR #2871, aprovada por [W]) diagnosticou que ~70% do protocolo do loop Cowork↔Code ([ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md)) era **andaime** de duas restrições — **R1** (Cowork read-only no git) e **R2** (design fora do repo) — e propôs **colapsar** apoiando no nativo (GitHub Issues + CI + branch protection + git=SSOT). As Ondas A–D foram executadas em PRs pequenos e revisáveis, sem big-bang. Esta ADR **ratifica** o resultado como o modelo canônico **v2**, sob autorização explícita de [W] (soberania [ADR 0238](0238-soberania-constituicao-wagner.md); numeração pelo Code sob OK de [W], conforme a regra da proposta-pai).

## Decisão

Fica ratificado o **Protocolo v2 (colapso)**:

| Eixo | v1 ([ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md)) | v2 (esta ADR) |
|---|---|---|
| **Papéis** | 6 | **2 humanos-no-loop**: `[CC]` designer-agente (F1 + abre PR) · `[W]` aprovador. `[CD]`/`[CA]`/`[W2]` → CI. `[CL]` → Cowork commitando / agente de tradução. |
| **Fases** | 7 (F0–F4 + 1.5/3.5) | **3**: F0 brief → F1 design + auto-checks → gates CI → merge. (Já vigente via overlay autônomo de [ADR 0241](0241-loop-design-cowork-code-autonomo-zero-humano.md).) |
| **Memória** | espinha markdown espelhada = autoridade | **git = SSOT**; `STATUS.md`/`MEMORY_INDEX.md` = cache derivado ([ADR 0239](0239-governanca-design-system-git-ssot-regressao-ia.md)). |
| **Intake** | fila `COWORK_NOTES.md` | **GitHub Issue** (`cowork-intake`) ou `cowork-inbox/`; fila congelada. |
| **Gates** | papéis humanos (F1.5/F2/F3.5/F4) | **checks de CI** (visual-regression + a11y-axe, agora required). |
| **Write-path** | `[W]` cola / `[CL]` coda | **`cowork-inbox`** — doc auto-merge; **código (`resources/js/**`) → PR + review humano, nunca auto-merge**. |

## Evidência (executado — esta ADR consolida, não muda código)

- **Onda A** (PR #2877) — git=SSOT, `STATUS.md` demovido a cache; piso de `PROCESSO_MEMORIA_CC.md` (NÚCLEO 1 + §13.3) emendado de forma aditiva. Proposta: [`proposals/protocolo-v2-onda-A-memoria-git-ssot.md`](proposals/protocolo-v2-onda-A-memoria-git-ssot.md) (PR #2874).
- **PR-A3** (PR #2878) — `[W]` para de colar; memória via `cowork-inbox`.
- **Onda B** (PR #2880) — intake via Issue (`.github/ISSUE_TEMPLATE/cowork-intake.yml`) + `COWORK_NOTES.md` congelada.
- **Onda C** — `A11y axe · runtime nos componentes canon` promovido a **required** no branch protection do `main`; lado-doc já no overlay do PROTOCOL.
- **Onda D** (PR #2876) — `cowork-inbox` com write-path de código + review-gate (código nunca auto-merge).

## O que NÃO muda (preservado — o ouro)

- **F1 · loop de protótipo visual** (a razão do Cowork) — intacto.
- **ADRs Nygard**, **tokens/personas/Cockpit**, **gates de CI** — intactos.
- **Multi-tenant Tier 0**, **soberania-[W]** (constituição/ADR/token = só [W]), **memory-health** (segredo + colisão-ADR) — todos intactos. Nenhuma regra de proteção ("Regra 6"/piso-DS/RC-01) removida.

## Consequências

- **Positiva:** o protocolo deixa de re-implementar em markdown + revezamento humano o que o nativo (Issues/CI/branch-protection/git) faz melhor; `[W]` sai de carteiro manual; tudo reversível por PR.
- **Limite:** ratificar **não apaga o v1** (append-only) — `PROTOCOL.md` ganha a marcação **v2** apontando pras ondas e pra esta ADR; o histórico fica. A metade soberania-[W] do piso e os gates de segurança permanecem.
- **Relação:** evolui/emenda [ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md) e [ADR 0241](0241-loop-design-cowork-code-autonomo-zero-humano.md) (não os supersede — o loop e o overlay seguem válidos sob a nova forma).
