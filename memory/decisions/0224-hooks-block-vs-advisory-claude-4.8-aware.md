---
adr: 0224
title: Triagem hooks block vs advisory — Claude 4.8-aware (rebaixa enforcement semântico)
status: accepted
date: 2026-05-28
deciders: [Wagner]
amends: []
references:
  - 0061-conhecimento-canonico-git-mcp-zero-automem.md
  - 0093-multi-tenant-isolation-tier-0.md
  - 0094-constituicao-v2-7-camadas-8-principios.md
  - 0168-protocolo-wagner-sempre-tier-A-irrevogavel.md
lifecycle: active
---

## Contexto

Reavaliação arquitetural à luz do Claude Opus 4.8 ([sessions/2026-05-28-reavaliacao-projeto-claude-4.8.md](../sessions/2026-05-28-reavaliacao-projeto-claude-4.8.md)) identificou que parte da máquina de governança do oimpresso foi erguida sobre a premissa de **modelo que esquece/desobedece** (maio/2026, janela ~200k, instruction-following imperfeito). Com 4.8 (1M context, instruction-following muito melhor), essa premissa encolheu de "lei física" pra "otimização de margem".

Critério canônico Anthropic 2026 (best-practices hooks vs CLAUDE.md):
> **Hook só pra o que precisa rodar sempre sem exceção** (determinístico-obrigatório: format, lint, security). **Comportamento/lembrete → CLAUDE.md ou skill** (advisory, ~80% aderência — suficiente pro 4.8).

### Inventário real (exit-code/decision por hook — 2026-05-28)

15 hooks PreToolUse inspecionados pelo **mecanismo de bloqueio real** (não pelo nome):

| Hook | Trigger | Mecanismo real | Natureza | Veredito |
|---|---|---|---|---|
| `block-automem` | Write/Edit auto-mem path | JSON `deny` | determinístico (path match) | **KEEP block** — Tier 0 (ADR 0061) |
| `block-memory-drift` | Write/Edit memory schema | JSON `deny` | determinístico (schema) | **KEEP block** |
| `block-module-drift` | Write/Edit Modules | JSON `deny` | determinístico (SCOPE.md) | **KEEP block** |
| `block-mwart-violation` | Write/Edit Pages/*.tsx | JSON `deny` | determinístico (F1 gate) | **KEEP block** |
| `block-bom-encoding` | Write/Edit | JSON `deny` | determinístico (bytes BOM) | **KEEP block** |
| `block-merge-markers` | Write/Edit | JSON `deny` | determinístico (`<<<<` markers) | **KEEP block** |
| `block-routes-string-legacy` | Write/Edit routes | JSON `deny` | determinístico (string vs FQCN) | **KEEP block** |
| `block-destructive` | Bash | JSON `deny` | determinístico (rm -rf/DROP) | **KEEP block** |
| `pii-redactor` | Bash | JSON `deny` | determinístico (PII regex) | **KEEP block** — LGPD |
| `charter-validate` | Write/Edit tsx | `exit 0` | semântico | **JÁ advisory** — OK |
| `modulo-preflight-warning` | Write/Edit Modules | `exit 0` | lembrete | **JÁ advisory** — OK |
| `mcp-first-warning` | Read/Glob/Grep | `exit 0` | lembrete | **JÁ advisory** — OK |
| `commit-discipline-check` | Bash git | `exit 0` | semântico | **JÁ advisory** — OK |
| `post-merge-ui-smoke-required` | Bash/UI | `exit 2` | semântico (UI quality) | **KEEP block** — gate de qualidade UI determinístico (pós-merge UI) |
| `block-claim-without-evidence` | Bash gh pr/push | `exit 2` | **semântico (regex infra+evidência)** | **REBAIXAR → advisory** |

**Achado central:** 9 dos 15 hooks já bloqueiam só o determinístico (via JSON `deny`); 4 já são advisory de fato (apesar do nome `block-*` ou não). **Apenas `block-claim-without-evidence` bloqueia (`exit 2`) sobre detecção semântica frágil** — e tem contraparte CI mais robusta.

## Decisão

### 1. Mudança concreta (este PR): rebaixar `block-claim-without-evidence` → advisory

`exit 2` → `exit 0` (mantém a mensagem stderr como aviso). Razões:

- **Detecção é semântica/regex** (heurística "PR toca infra crítica" + "tem curl/HTTP literal") — frágil, gera falso-bloqueio.
- **Enforcement real já existe na Camada A:** `.github/workflows/infra-contract-required.yml` (87 linhas, CI gate) — mais robusto, **não bypassável por `--admin` local**.
- **Skill cultural Tier B** `smoke-prod-evidence` cobre o comportamento.
- **Claude 4.8** segue a disciplina de evidência lendo o aviso; o portão de merge real fica no CI, onde deve estar.

Defesa em profundidade preservada: aviso local (advisory) + CI gate (block) + skill (cultural).

### 2. Documentar critério canônico (governança futura)

Todo hook novo nasce classificado: **`block`** (determinístico-obrigatório — path match, bytes, regex sintática inequívoca, Tier 0/LGPD) OU **`advisory`** (semântico, lembrete, heurística — `exit 0` + mensagem). Naming `block-*` em hook advisory é dívida cosmética (renomeação futura, não-bloqueante).

### 3. NÃO mexer (intocados — determinístico-obrigatório, Tier 0/LGPD)

`block-automem`, `block-destructive`, `pii-redactor`, `block-routes-string-legacy`, `block-bom-encoding`, `block-merge-markers`, `block-memory-drift`, `block-module-drift`, `block-mwart-violation`, `post-merge-ui-smoke-required`. Todos permanecem `deny`/`exit 2`.

## Não-goals

- ❌ **Não remove nenhum hook** — só rebaixa 1 de block pra advisory
- ❌ **Não toca hooks Tier 0** (multi-tenant, automem, destructive, pii, routes, bom, merge-markers)
- ❌ **Não renomeia hooks** (`block-*` advisory fica como dívida cosmética)
- ❌ **Não mexe em skills Tier A** — isso é ADR 0225 (separada)
- ❌ **Não remove a Camada A CI** `infra-contract-required.yml` — ela vira o enforcement primário

## Consequências

✅ **Boas:**
- Remove o único falso-bloqueio semântico do fluxo local (devolve agência ao 4.8)
- Enforcement de evidência continua via CI (Camada A) — mais robusto, não bypassável local
- Critério canônico documentado: time MCP (Felipe/Maiara/Eliana/Luiz) sabe classificar hook novo
- Inventário dos 15 hooks vira referência viva (estado real ≠ nomes enganosos)
- Zero risco Tier 0 — todos os 9 hooks `deny` determinísticos permanecem

⚠️ **Tradeoffs:**
- Local não bloqueia mais PR-sem-evidência; depende do CI gate pegar (aceitável — CI é authority)
- Naming `block-claim-without-evidence` agora é enganoso (advisory) — dívida cosmética até renomeação
- Mudança de governança de enforcement — reversível via `git revert` se algum dev abusar (Wagner monitora via `governance:detect-drift`)

## Validação

- ✅ Inventário 15 hooks por mecanismo real (tabela acima é evidência)
- ✅ `block-claim-without-evidence.ps1` linha final `exit 2` → `exit 0` + comentário ADR 0224
- ✅ Camada A CI `infra-contract-required.yml` confirmada existente (87 linhas)
- ✅ 9 hooks `deny` intactos (grep `deny` confirma)

## Notas

- Parte da sequência de reavaliação 4.8: **0224 (hooks)** → 0225 (skills Tier A) → 0226 (brief v2) → 0227 (MWART single-layer) → 0228 (subagent nativo)
- Reavaliação completa: [sessions/2026-05-28-reavaliacao-projeto-claude-4.8.md](../sessions/2026-05-28-reavaliacao-projeto-claude-4.8.md)
- Wagner aprovou direção 2026-05-28 ("vamos fazer") após reavaliação propor 0224 como maior atrito removido por menor esforço
- ADRs 0225+ ficam pra próxima sessão (skills tiering precisa cuidado extra — Tier A é Wagner-sensível)
