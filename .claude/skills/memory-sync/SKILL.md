---
name: memory-sync
description: ATIVAR após criar/editar arquivo em memory/, atualizar SPEC.md/TEAM.md, salvar ADR/session log, ou usar trigger "salve no cofre"/"guarde"/"grave na memória". Lembra Claude que conhecimento canônico precisa ir pro MCP server via git push antes de encerrar — o webhook GitHub→MCP só sincroniza após push, então team (Eliana/Felipe) só enxerga via tools MCP depois disso. Tasks → tools MCP `tasks-*` (ADR 0070), nunca markdown.
trust_level: L1
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: 0080
tier: B
parent_adr: 0095
---

# memory-sync — propagar memória pro team via MCP

## O problema

Knowledge no oimpresso é **git → webhook GitHub → MCP server (`mcp_memory_documents`) → tools MCP**. Se você cria SPEC, ADR, session log mas **não dá push**, o time não vê via `decisions-search`, `cycles-active`, `memoria-search`. Já queimou: arquivo `memory/requisitos/NFSe/SPEC.md` criado mas não-pushed = Eliana abre Claude e procura "NFSe" via MCP, vem vazio, ela acha que não foi planejado ainda.

## Quando esta skill ativa

Triggers literais:
- "salve no cofre" / "guarde no cofre" / "grave na memória"
- "atualiza/cria SPEC/ADR/session log"
- Após Write/Edit em `memory/**`, `MEMORY.md`, `TEAM.md`, `CLAUDE.md`, `DESIGN.md`, `INFRA.md`, `MANUAL_CLAUDE_CODE.md`, `HOW_TO_ASK_CLAUDE.md`
- Após criar arquivo em `memory/decisions/`, `memory/sessions/`, `memory/handoffs/`, `memory/requisitos/<Mod>/` (incluindo `SPEC.md` que é canônico TaskRegistry — ADR 0070)
- **ANTES** de Write em `memory/handoffs/*.md` ([ADR 0130](../../../memory/decisions/0130-handoff-append-only-mcp-first.md)) — checklist MCP-first OBRIGATÓRIO, veja seção dedicada
- ⚠️ `CURRENT.md`/`TASKS.md` REMOVIDOS (ADR 0070) — tasks vão pra tools MCP `tasks-create`/`tasks-update`/`tasks-comment`, status do cycle via `cycles-active`/`cycle-goals-track`
- Após decisão arquitetural ou pattern novo

## O que fazer

1. **Não commitar a cada Write** — agrupe mudanças relacionadas no mesmo turno
2. **No fim do turno** (antes de encerrar resposta), rode `/sync-mem` se houver pendências em paths de memória
3. Se `/sync-mem` reportar "nada pendente" → tudo certo
4. Se reportar arquivos pendentes → confirma com usuário o título, executa `git add+commit+push`

## O que NÃO fazer

- ❌ Tentar commitar código junto (`Modules/`, `app/`, `resources/`) — esses seguem fluxo PR normal
- ❌ Usar `git add -A` ou `git add .` — pode pegar `.env`, locks, código não-relacionado
- ❌ Usar `--no-verify` (gitleaks/pre-commit existem por razão)
- ❌ Pular `/sync-mem` "porque é mudança pequena" — pequena também precisa ir pro MCP
- ❌ Recriar `CURRENT.md`/`TASKS.md` — foram removidos pelo ADR 0070; tasks ficam em `mcp_tasks` via tools
- ❌ **Sobrescrever `memory/08-handoff.md` ou editar handoff antigo em `memory/handoffs/`** — append-only desde [ADR 0130](../../../memory/decisions/0130-handoff-append-only-mcp-first.md). Veja seção dedicada abaixo.

## Handoff append-only — MCP-first OBRIGATÓRIO (ADR 0130)

`memory/08-handoff.md` é **índice puro**. Narrativa de fechamento vai em arquivo novo em `memory/handoffs/YYYY-MM-DD-HHMM-<slug>.md` (append-only — uma vez criado, nunca editado).

**BLOQUEADOR:** antes de qualquer `Write` em `memory/handoffs/*.md`, executar nessa ordem e capturar resultado pra incluir no handoff:

1. `cycles-active` — cycle ativo + goals + drift detectado
2. `my-work` — tasks DOING/REVIEW reais (não confiar em memória)
3. `sessions-recent limit:3` — handoffs/sessions irmãs nas últimas horas (detecta paralela)
4. `decisions-search since:<data-último-handoff>` — ADRs aceitas no intervalo
5. (se suspeita paralela) `whats-active` ([ADR 0119](../../../memory/decisions/0119-paralelismo-sessoes-whats-active-tier-1.md))

O handoff novo **deve** ter uma seção `## Estado MCP no momento do fechamento` com snapshot dos passos 1-4. Sem essa seção = sinal de overwrite cego (problema que motivou [ADR 0130](../../../memory/decisions/0130-handoff-append-only-mcp-first.md)).

**Após criar handoff novo:** editar `memory/08-handoff.md` (índice) apenas pra adicionar 1 linha no topo da lista "Últimos handoffs" + truncar 5º item se passou. Nada mais.

**Sessão offline (sem MCP):** documentar `## Estado MCP — INDISPONÍVEL` + qual fonte alternativa usou (`git log`, leitura direta de SPEC, etc). Ainda melhor que silêncio.

## Hook complementar

`.claude/hooks/memory-pending.mjs` (Stop hook) detecta pendências no fim de cada turno e mostra warning. Não bloqueia; só avisa. Se ver o warning, rode `/sync-mem`.

## Exemplos de bom uso

✅ Criou `memory/requisitos/NFSe/SPEC.md` + `adr/arq/0001-*.md` + atualizou ADR-0002 RecurringBilling como superseded → fim de turno: `/sync-mem` → 1 commit "docs(nfse): SPEC + ADR + tasks Eliana" → push → MCP webhook em <60s → Eliana vê via `decisions-search nfse`.

✅ Wagner: "fechei a task COPI-145" → `tasks-update COPI-145 status:done` (tool MCP, sem commit). Se também criou ADR ou session log no caminho → `/sync-mem` no fim do turno.

❌ Editou SPEC.md no início, fez 5 outras tasks de código, esqueceu o SPEC.md no final. Hook avisa, mas você já encerrou turno. Resultado: time não vê a SPEC até próxima sessão lembrar.

## Mensagem ao usuário no fim do turno

Se houver pendências e você fez commit+push via /sync-mem, encerre com 1 linha:
> ✅ Memória propagada (commit `<hash>`). Webhook MCP em <60s. Time enxerga via tools MCP.

Se não houver pendências, não mencione (silêncio = tudo ok).
