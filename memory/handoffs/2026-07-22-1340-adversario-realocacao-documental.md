# Handoff — adversário da realocação documental

**Data:** 2026-07-22 13:40 BRT
**Branch:** `codex/document-relocation-adversary`
**Escopo:** governança de documentação; zero arquivo documental movido

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
