# Sub-agent prompt — Dimensão 5: Cycle goals status

> Prompt canônico do sub-agent #5 da skill `audit-constituicao`.
> Output PT-BR. Limite: ≤500 palavras no diagnóstico final.
>
> ⚠️ **Tools MCP deferred.** Pré-carregar via ToolSearch antes:
> `select:mcp__Oimpresso_MCP___<dev>__cycles-active,mcp__Oimpresso_MCP___<dev>__cycle-goals-track,mcp__Oimpresso_MCP___<dev>__tasks-list`
> (substituir `<dev>` pelo nome no ambiente — Wagner/Felipe/Maiara/Luiz/Eliana)

## Missão

Auditar disciplina de cycles e goals via tools MCP ([ADR 0070](memory/decisions/0070-jira-style-task-management-current-md-removed.md) + [ADR 0091](memory/decisions/0091-daily-brief.md)):
- Cycle ativo tem goals declarados?
- Goals trackados com métrica/baseline/target?
- Cycles passados foram fechados via `cycles-close --rollover` ou abandonados?
- Tasks órfãs (sem cycle) acumulando?

## O que fazer (passo a passo)

1. Chamar `cycles-active` → cycle ativo (id, nome, datas, goals).
2. Chamar `cycle-goals-track cycle:current` → status de cada goal.
3. Chamar `tasks-list cycle:current` → WIP do cycle ativo.
4. Chamar `tasks-list cycle:none` (ou equivalente — orphans) → tasks sem cycle.
5. Listar últimos 3 cycles fechados — confirmar se tem `retro` JSON populado (sinal de close limpo).

## Como entregar

```markdown
# Dimensão 5 — Cycle goals status

## Saúde: 🟢/🟡/🔴
## Headline (1 frase): <ex: "Cycle 02 ativo com 3 goals trackados; 12 tasks órfãs acumulando">

## Métrica
- Cycle ativo: <id> — <nome> — <YYYY-MM-DD a YYYY-MM-DD>
- Goals declarados: <N>
- Goals com métrica/baseline/target: <N>
- Goals batendo target: <N>
- Goals em risco: <N>
- Tasks WIP no cycle: <N>
- Tasks órfãs (sem cycle): <N>

## Status dos goals do cycle ativo

| Goal | Métrica | Baseline | Target | Atual | Status |
|---|---|---|---|---|---|
| <nome> | <%/N/etc> | <X> | <Y> | <Z> | 🟢/🟡/🔴 |

## Cycles passados (últimos 3)

| Cycle | Fechamento | Retro JSON? | Rollover? | Status |
|---|---|---|---|---|
| Cycle 01 | YYYY-MM-DD | ✅ | ✅ | clean |
| ... | | | | |

## Tasks órfãs (top 10 por idade)

| Task ID | Title curto | Idade | Module | Ação sugerida |
|---|---|---|---|---|
| COPI-NNN | <titulo> | XXd | <Mod> | atribuir cycle current ou backlog |

## Recomendação 3-tiers

- **Tier A (safe agora):** atribuir tasks órfãs ao cycle current ou backlog explícito
- **Tier B (precisa ADR):** se cycle ativo sem goals OU último cycle abandonado sem rollover — revisar [ADR 0070]/[ADR 0091]
- **Tier C (backlog):** criar dashboard MCP `cycle-health` se ainda não existe
```

## Heurística de saúde

- 🟢 Cycle ativo com 3+ goals trackados; últimos 3 cycles fechados via rollover; tasks órfãs <5
- 🟡 Cycle ativo com goals mas sem track recente (>14d); tasks órfãs 5-15
- 🔴 Cycle ativo SEM goals OU último cycle abandonado sem rollover OU tasks órfãs >15

## Restrições

- NÃO criar/atualizar tasks automaticamente — só listar gap.
- NÃO fechar cycle automaticamente — Wagner faz `cycles-close --rollover` manual.
- Se tools MCP indisponíveis (token expirado/sem rede), reportar "Dimensão 5 indisponível: <razão>" — não inventar dados.
- Cross-check com brief-fetch é OK (mesma fonte) — usar dados já no brief se foi carregado, evita re-fetch.
