---
date: "2026-07-11"
topic: "Loop diff-first DS-sync: prova a frio, 3 ferramentas, adversário (código + nota), fechamento"
authors: [C, W]
prs: [4101, 4102, 4103]
related_adrs: [0335-fechamento-loop-diff-first-ds-sync-nota-honesta, 0299-figma-nao-e-fonte-de-design]
---

# 2026-07-11 — Loop diff-first DS-sync: prova a frio, 3 ferramentas, adversário (código + nota), fechamento

Sessão que testou o loop de sync de tokens git↔espelho (claude.ai/design), estendeu com 3 ferramentas, submeteu tudo a revisão adversarial (código **e** a própria nota), e fechou com [ADR 0335](../decisions/0335-fechamento-loop-diff-first-ds-sync-nota-honesta.md).

## 1. Prova a frio do loop (sessão limpa)

`ds-token-diff.mjs --companion` fechou **`VALOR:0`** contra (a) o espelho commitado (git offline) e (b) o espelho VIVO (`DesignSync get_file`). O `--companion` fecha o falso `git-only` dos domínios `.cockpit` (sem ele, `cockpit-light/dark` sobe pra 68/76; com ele, 10/16 = só tokens de shell não-domínio, esperado). Reprodutível sem contexto prévio.

## 2. Três ferramentas (PRs #4101/#4102/#4103, mergeadas)

- **#4101** `ci(visual-regression)`: adiciona os inputs de token de render (`*.tokens.json` + `_generated-*.css` + config) ao filtro `ui:` → um delta de token dispara o `visual-regression` **required** (fecha o skip-as-pass; gap #8).
- **#4102** `feat: ds-push`: orquestra o push git→espelho num comando (monta scaffold + companion, valida `VALOR:0`, imprime a chamada DesignSync). Não faz o upload (interativo, precisa login claude.ai) — honesto.
- **#4103** `feat: ds-token-version`: semver + changelog do contrato de tokens (296 tokens/4 escopos, fingerprint sha256; MAJOR=remoção · MINOR=add/valor).

## 3. Adversário de CÓDIGO — 4 achados, todos corrigidos

1. 🔴 **blocker** — `ds-token-version --write` fingia **MINOR numa REMOÇÃO** (baseline default era `git HEAD` → prev==cur pós-commit → delta vazio → changelog vazio). Fix: baseline = `origin/main` + recusa quando não vê delta. Provado no repo real (remoção → MAJOR).
2. 🟡 glob `tokens/**` acoplava o visreg pesado a `version.json`/`CHANGELOG.md` → estreitado.
3. 🟡 `ds-push.test` acoplava drift do snapshot (redundante com `ds-mirror-drift`) → relaxado pra só a plumbing.
4. 🟢 `ds-push.test` sujava `.push-bundle/` → `--out` temp + limpeza.
Refutou (bom sinal) um red-herring plantado: "tokens maiúsculos perdidos" — falso, o regex tem flag `i`.

## 4. Adversário da NOTA (não só do código)

A nota inicial (oimpresso A−/B+ × SOTA A−, "outra função de custo", loop = "régua da máquina") foi dada **pelo agente sobre o próprio design**. O avaliador adversarial deflacionou **~1 grau inteiro**:
- **oimpresso ≈ B−/C+ · SOTA ≈ A−.**
- Inversões/concessões: dim 5 (diff de VALOR é **cego** a layout/a11y/uso-errado + pula aliases → SOTA ganha); dim 12 (`--companion` cura ferida auto-infligida do espelho, não é capacidade); dim 4 (CI compara git vs snapshot-cópia-do-git; a metade viva não roda no CI); dogfood-negativo (o blocker #1 foi na própria ferramenta desta sessão; o motor central estava **sem teste**).
- 5 dimensões OMITIDAS (viés de seleção), todas onde perde: workflow-do-designer, maturidade (N=1), manutenção-bespoke, distribuição-npm, ecossistema.
- Régua: **não** resiste como "régua da máquina" — é advisory-lint (git↔snapshot) + check manual (git↔vivo).

## 5. Fechar gap (ADR 0335)

- **Motor central `ds-token-diff` ganhou selftest** (diverge/designOnly/gitOnly/**alias-skip**/companion) — fecha o gap de maturidade mais afiado.
- Nota honesta + não-goals conscientes registrados (anti-inflação).
- Errata parcial da [0299](../decisions/0299-figma-nao-e-fonte-de-design.md): Figma volta a ser fonte legítima (opt-in via hook **mantido**); MCP Figma desligado pelo Wagner.

## Lição perene

Nota dada sobre o próprio design **infla ~1 grau** por default (dimensão escolhida a dedo + strawman do concorrente + N=1 tratado como battle-tested). O adversário na NOTA — não só no código — é o que pega isso. Regra: antes de canonizar superioridade, rodar o avaliador adversarial da própria afirmação.
