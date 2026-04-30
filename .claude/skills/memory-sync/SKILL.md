---
name: memory-sync
description: ATIVAR após criar/editar arquivo em memory/, atualizar TASKS.md/CURRENT.md/TEAM.md, salvar SPEC/ADR/session log, ou usar trigger "salve no cofre"/"guarde"/"grave na memória". Lembra Claude que conhecimento canônico precisa ir pro MCP server via git push antes de encerrar — o webhook GitHub→MCP só sincroniza após push, então team (Eliana/Felipe) só enxerga via tools MCP depois disso.
---

# memory-sync — propagar memória pro team via MCP

## O problema

Knowledge no oimpresso é **git → webhook GitHub → MCP server (`mcp_memory_documents`) → tools MCP**. Se você cria SPEC, ADR, session log, ou edita TASKS/CURRENT mas **não dá push**, o time não vê via `decisions-search`, `tasks-current`, `memoria-search`. Já queimou: arquivo `memory/requisitos/NFSe/SPEC.md` criado mas não-pushed = Eliana abre Claude e procura "NFSe" via MCP, vem vazio, ela acha que não foi planejado ainda.

## Quando esta skill ativa

Triggers literais:
- "salve no cofre" / "guarde no cofre" / "grave na memória"
- "atualiza/cria SPEC/ADR/session log"
- Após Write/Edit em `memory/**`, `MEMORY.md`, `CURRENT.md`, `TASKS.md`, `TEAM.md`, `CLAUDE.md`, `DESIGN.md`, `INFRA.md`, `MANUAL_CLAUDE_CODE.md`
- Após criar arquivo em `memory/decisions/`, `memory/sessions/`, `memory/requisitos/<Mod>/`
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

## Hook complementar

`.claude/hooks/memory-pending.ps1` (Stop hook) detecta pendências no fim de cada turno e mostra warning. Não bloqueia; só avisa. Se ver o warning, rode `/sync-mem`.

## Exemplos de bom uso

✅ Criou `memory/requisitos/NFSe/SPEC.md` + `adr/arq/0001-*.md` + atualizou ADR-0002 RecurringBilling como superseded → fim de turno: `/sync-mem` → 1 commit "docs(nfse): SPEC + ADR + tasks Eliana" → push → MCP webhook em <60s → Eliana vê via `decisions-search nfse`.

✅ Wagner: "atualiza CURRENT.md e marca cycle como concluído" → editou CURRENT.md → fim de turno: `/sync-mem`.

❌ Editou SPEC.md no início, fez 5 outras tasks de código, esqueceu o SPEC.md no final. Hook avisa, mas você já encerrou turno. Resultado: time não vê a SPEC até próxima sessão lembrar.

## Mensagem ao usuário no fim do turno

Se houver pendências e você fez commit+push via /sync-mem, encerre com 1 linha:
> ✅ Memória propagada (commit `<hash>`). Webhook MCP em <60s. Time enxerga via tools MCP.

Se não houver pendências, não mencione (silêncio = tudo ok).
