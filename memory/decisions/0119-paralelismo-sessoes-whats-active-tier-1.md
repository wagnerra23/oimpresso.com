---
slug: 0119-paralelismo-sessoes-whats-active-tier-1
number: 119
title: "Paralelismo de sessões — Tier 1 `whats-active` aceito, Tier 2 lease formal dormente"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-05-09'
quarter: 2026-Q2
related:
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0063-prevenir-composer-lock-drift
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0105-cliente-como-sinal-guiar-sem-mandar
pii: false
---

# ADR 0119 — Paralelismo de sessões — Tier 1 `whats-active` aceito, Tier 2 lease formal dormente

**Status:** ✅ Aceita
**Data:** 2026-05-09
**Decisão por:** Wagner Rocha
**Princípio aplicado:** [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — sinal qualificado antes de feature

---

## Contexto

Wagner reportou (2026-05-09) que conflitos recorrentes do projeto foram sempre "alguém alterou mesmo escopo simultaneamente" — propôs MCP gravar trabalho **em curso** (não só finalizado em `tasks-update doing`) pra duas sessões trabalharem em paralelo sem bater.

Estudo catalogou 13 incidentes de paralelismo (período 2026-04-18 → 2026-05-09) cruzando session logs, ADRs, git history e documentação:

| Classe de conflito | Frequência | Lease MCP resolveria? |
|---|---|---|
| Claude vs **Cursor** (checkout race, uncommitted) | 4× | ❌ Cursor não consulta nosso MCP |
| Claude vs **GitHub Actions** (quick-sync, mwart-gate, check-scope global) | 3× | ❌ Workflow é "terceiro ator" |
| Claude vs **composer.lock drift** | 1× | ❌ Resolvido por [ADR 0063](0063-prevenir-composer-lock-drift.md) |
| Claude vs **9 sistemas memória conflitantes** | 1× | ❌ Resolvido por [ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md) |
| Claude vs **drift Controllers/SCOPE** | 2× | ❌ Descoberta estática (audit) |
| **PR vs PRs paralelos mwart-gate** | 1× | 🟡 Lease ajudaria a sinalizar; resolução real é `git rebase origin/main` |
| Claude vs **quota Anthropic mid-flight** | 1× | ❌ Externo |
| **Claude-A vs Claude-B** (sessões mesmas tasks) | 0 catalogado | ✅ Único caso que se beneficiaria |

**Conclusão:** ofensor recorrente é **Cursor** (4 incidentes) e **workflows GitHub Actions** (3 incidentes). Ambos não consultam nosso MCP — não vão honrar lease. O caso "Claude-A vs Claude-B" não tem incidente catalogado.

## O que JÁ mitiga (e funciona)

1. **Worktree isolada por sessão** (`.claude/worktrees/<nome>`) — git ok
2. **`business_id` global scope Tier 0** ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) — dados ok
3. **`tasks-update doing`** — sinaliza ownership coarse-grained
4. **[ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md)** zero auto-mem privada — memória ok
5. **`memory/sessions/<data>-*.md`** — handoff async

## Decisão

### Tier 1 — ACEITO (1-2h, baixa complexidade)

**Tool MCP nova `whats-active`** (read-only):
- Lista sessões com `tasks status:doing` + worktree path + último commit (timestamp + paths tocados)
- Derivada de dados já existentes (`mcp_session_logs` via watcher cc-search + tabela `tasks`)
- **Zero estado novo** — agregação de sinais existentes
- Resposta JSON simples: `[{owner, task_id, worktree, last_commit_at, paths_touched_24h:[...]}]`

**Skill Tier A `session-start-check`**:
- Hook `SessionStart` — chama `whats-active` antes do brief-fetch
- Alerta se outra sessão tocou os mesmos paths nas últimas 2h
- Não bloqueia — só sinaliza ("⚠️ Felipe trabalhou em `Modules/NfeBrasil/Services/` há 1h — confirmar antes de começar")

### Tier 2 — DORMENTE até bater no problema 2×

Lease formal `tasks-claim <ID> scope:[paths] ttl:30m` + `whats-locked`:
- Heartbeat + recovery + lock fantasma após TTL
- **Custo:** complexidade alta (TTL, recovery, override)
- **Promove pra ativo se** Tier 1 deixar passar 2 incidentes Claude-A vs Claude-B no mesmo arquivo
- Aplica princípio [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md): sinal qualificado antes de feature

### Tier 3 — NÃO FAZER

Lock fino arquivo-a-arquivo. Custo > benefício pra projeto deste tamanho.

## Cursor — fora do escopo MCP

Cursor é ofensor #1 (4/13 incidentes) mas **não consulta MCP server**. Solução é **convenção humana documentada**, não tool:
- Cursor commita `git add -A && git commit -m "wip: cursor session"` antes de qualquer `git checkout`
- Cursor sempre opera em worktree isolada `.cursor/worktrees/<nome>` (mesmo padrão Claude)
- Documentar em `memory/regras-time.md` se Wagner aprovar

(Não-decisão neste ADR — ficar na auto-mem `reference_cursor_collaboration` até virar problema novamente.)

## Workflows GitHub Actions — fora do escopo

Ofensor #2 (3/13 incidentes). Já mitigado por:
- `mwart-gate` virtual rebase `refs/pull/N/merge` (PR #268 padrão)
- `composer-lock-sync.yml` strict gate ([ADR 0063](0063-prevenir-composer-lock-drift.md))
- `quick-sync.yml` `.git/*.lock` cleanup (PR #291)

## Tasks geradas

- **US-INFRA-NNN** Tool MCP `whats-active` (read-only, agregação de sinais existentes) — p2, ~4h
- **US-INFRA-NNN** Skill Tier A `session-start-check` (hook + alerta passivo) — p2, ~2h

## Métricas de saúde

- **Critério promoção Tier 2:** 2× incidentes Claude-A vs Claude-B no mesmo arquivo registrados em `memory/sessions/*.md` com tag `#paralelismo-bateu`
- **Critério retro Tier 1:** 30 dias após implementar — count de alertas `whats-active` que evitaram conflito real (sample manual)

## Consequências

- ✅ Resolve a única classe de conflito não coberta hoje (Claude-A vs Claude-B) com mecanismo barato
- ✅ Não cria estado novo no MCP — agregação derivada
- ✅ Adia complexidade (Tier 2 lease) até evidência empírica
- ⚠️ Não resolve Cursor nem GitHub Actions — explicitado escopo
- ⚠️ Tier 1 é alerta passivo — depende de Claude ler o aviso e decidir não bater (cultura, não enforcement)

## Referências

- [ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md) — Zero auto-mem privada
- [ADR 0063](0063-prevenir-composer-lock-drift.md) — Composer lock drift
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (mãe)
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente/sinal qualificado antes de feature
