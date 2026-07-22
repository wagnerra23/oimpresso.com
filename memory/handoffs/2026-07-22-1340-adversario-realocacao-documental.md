---
date: "2026-07-22"
time: "1340 BRT"
slug: "adversario-realocacao-documental"
tldr: "Adversário read-only da realocação documental pronto na PR #4675: 15/15 contraprovas, smoke real sem mover arquivos e job específico verde. A máquina classificadora e o executor ainda são etapas futuras separadas."
decided_by: [W]
cycle: null
prs: [4675]
us: []
next_steps:
  - "W revisar a PR #4675; não fazer merge automático."
related_adrs:
  - "0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento"
  - "0314-poda-gates-onda-2-lei-fusoes"
---

# Handoff — adversário da realocação documental

**Data:** 2026-07-22 13:40 BRT
**Branch:** `codex/document-relocation-adversary`
**PR:** [#4675](https://github.com/wagnerra23/oimpresso.com/pull/4675)
**Escopo:** governança de documentação; zero arquivo documental movido

## TL;DR

O adversário anterior ao `git mv` está pronto e publicado na PR #4675. Ele valida o plano e o relink, mas não classifica, move nem altera documentos; o executor futuro continua separado.

## Resultado

Foi construída a contraprova read-only que deve rodar entre a futura classificação documental e qualquer executor de `git mv`.

- `scripts/governance/document-relocation-adversary.mjs` valida um plano JSON v1 e só retorna `safe_to_apply: true` quando o plano está estruturalmente seguro.
- `.claude/agents/document-relocation-adversary.md` faz a segunda lente, semântica e cética, mas não pode sobrepor um erro determinístico nem executar movimentos.
- O plano é pinado ao `base_sha`; baixa confiança vira `REVIEW`; erro vira `REJECT`.
- O validador protege ADRs/sessions/handoffs, portas globais e de módulo, `AGENTS.md`, `SCOPE.md`, automações por path e artefatos gerados.
- O inventário cobre links Markdown, code-spans a partir da raiz e paths literais em scripts/configs.
- O relink é bidirecional: verifica quem aponta para o arquivo e também os links relativos que saem do documento movido. Âncoras devem ser preservadas.
- Se um relink exigiria editar ADR/session/handoff append-only, o movimento é rejeitado.

## Contraprovas executadas

- Selftest: **15/15** — um plano bom solta; backlink omitido, link de saída omitido, âncora perdida, SHA stale, colisão, traversal, pasta inexistente, baixa confiança, histórico imutável, gerado, referrer append-only e owner divergente mordem.
- Smoke na árvore real, sem mover nada: plano sintético para `SUMMARY.md` só foi aprovado quando declarou a correção do link de saída `README.md` após a mudança de nível.
- `memory-health`: **0 fail** (warnings preexistentes).
- Vitest `tests/memoryHealth.spec.ts`: **38/38**.
- `documentation-loop --selftest`: verde.
- `selftest-registry --check`: **121 testes, 0 órfãos**.
- `git diff --check`: verde.

## Limite deliberado

O adversário **não classifica, não move e não relinka**. Ele valida a proposta. Referências externas ao repositório continuam risco residual para a lente semântica/humana. Um executor pós-aprovação e uma verificação pós-movimento ainda são entregas separadas do ciclo de realocação.

## Estado MCP no momento do fechamento

As tools MCP de `cycles-active`, `my-work`, `sessions-recent` e `decisions-search` não estavam disponíveis neste runtime Codex. Estado verificável usado no lugar: `main` base `2904a68bcafd1752e4429a41f9636d3801bc051d`; branch isolada; `memory-health` sem falhas; nenhuma alteração de valor, estoque, banco ou runtime.
