---
name: session-start-check
description: |
  ATIVAR depois do brief-first em toda sessão. Chama tool MCP whats-active
  pra detectar se outra sessão Claude do time tocou paths overlapping nas
  últimas 2h — alerta passivo (não bloqueia). Cobre o único cenário de
  conflito não mitigado por worktree isolada + tasks-update doing: Claude-A
  vs Claude-B mexendo no mesmo arquivo simultaneamente. ADR 0119 Tier 1.
trust_level: 1
tier: B
resumo: whats-active pós-brief — detecta sessão paralela tocando o mesmo path ([ADR 0119](memory/decisions/0119-paralelismo-sessoes-whats-active-tier-1.md))
recalibracao_nota: tier A->B 2026-07-09 (US-GOV-052 P31) — critério ADR 0225 (dispara por momento session_start, não é núcleo segurança/LGPD); banner e CLAUDE.md pós-0225 já não a listavam no núcleo.
parent_mission: mission.constituicao-v2
charter_adr: 0119-paralelismo-sessoes-whats-active-tier-1
auto_trigger: session_start
applies_to:
  - any session in oimpresso project
  - both human-driven and agent-driven sessions
---

# Skill: session-start-check

## Quando ativa

**Sempre, depois do `brief-first`.** Tier A — carrega em todo system prompt
do oimpresso.

Ofensores reais de paralelismo (catalogados em [ADR 0119](../../../memory/decisions/0119-paralelismo-sessoes-whats-active-tier-1.md))
são Cursor (4×) e workflows GitHub Actions (3×) — ambos NÃO consultam MCP.
Esta skill cobre só o caso Claude-A vs Claude-B (Wagner vs Felipe simultâneos
no mesmo arquivo). Cursor segue convenção humana, não MCP.

## Protocolo obrigatório

```
PASSO 1 — Depois de brief-fetch, chame whats-active:
   mcp__Oimpresso_MCP___<SEU_NOME>__whats-active {}

   Sem parâmetro → janela default 2h, paths últimas 24h.

PASSO 2 — Ler markdown retornado:
   - Se "✅ Nenhuma sessão ativa" → siga normalmente, silencioso.
   - Se 1+ sessão de OUTRO dev → comparar paths_tocados ∩ minha_intenção.

PASSO 3 — Heurística overlap (mental):
   - Os paths que a outra sessão tocou intersectam o módulo/arquivo
     que VOCÊ vai mexer agora?
     · Sim: alerte 1 frase no início da resposta
       ("⚠️ Felipe trabalhou em Modules/NfeBrasil/Services/ há 1h —
        confirmar antes de começar").
     · Não: silencioso, não polui contexto.

PASSO 4 — Não bloqueia. Sempre prossiga após o alerta.
   Coordenação é cultura humana, não enforcement automático.
```

## Quando NÃO chamar

- **Sessão muito curta** (1 turno só, perguntar "que horas são") — pular.
- **Tool indisponível** (`whats-active` retorna 503 ou tabelas mcp_cc_*
  ainda não migradas) — silencioso, segue.
- **Wagner pediu pra ignorar** ("estou debugando MCP, ignore checks")
  — pular nesta sessão.

## Quando ampliar a janela

```
mcp__Oimpresso_MCP___<SEU_NOME>__whats-active { "hours": 12 }
```

Use só se:
1. Você entrou em sessão depois de turno mais longo (Wagner em outro
   continente, time noite vs dia, etc.)
2. Você está investigando incidente de overlap suspeito de 6-12h atrás

Default 2h é suficiente pra coordenação cotidiana.

## Anti-padrões (NÃO faça)

❌ Bloquear sessão porque outra Claude está ativa — alerta é passivo
❌ Tentar inferir conflito de PATHS que NÃO toquei (você só tem `tasks-doing`
   no momento — overlap futuro é especulação)
❌ Chamar `whats-active` em loop durante a mesma sessão (1× no início basta)
❌ Confundir Cursor com Claude — Cursor não aparece em mcp_cc_sessions
   (não tem watcher cc-search). Pra Cursor, vale convenção: olhar
   `git status` antes de checkout

## Caso comum (90% das vezes)

```
Você: chama brief-fetch → vê CYCLE-03 + 4 HITL + drift detectado
Você: chama whats-active → "✅ Nenhuma sessão ativa nas últimas 2h"
Você: prossegue com a tarefa do Wagner sem mencionar nada (silencioso).
```

## Caso edge (raro mas importante)

```
Você: brief-fetch → CYCLE-03
Você: whats-active → "Felipe ativo, branch claude/nfe-emit-fix,
                      paths: Modules/NfeBrasil/Services/NfeService.php"
Wagner pede: "ajusta NfeService pra logar cstat=999"
Você: "⚠️ Felipe está mexendo em NfeService.php há 30min (branch
       claude/nfe-emit-fix). Confere com ele antes de prosseguir,
       ou peço pra trabalhar em paralelo via worktree separada?"
```

## Como mede sucesso (Tier 1 → Tier 2 promotion)

Se em 30 dias houver **2× incidentes** de Claude-A vs Claude-B no mesmo
arquivo que `whats-active` deixou passar (alertou mas Claude ignorou,
ou não alertou por bug), promove pra Tier 2 = lease formal com TTL
(ADR 0119 §Tier 2 dormente).

Sinal qualificado antes de feature ([ADR 0105](../../../memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

## Referências

- [ADR 0119](../../../memory/decisions/0119-paralelismo-sessoes-whats-active-tier-1.md) — decisão Tier 1 aceito, Tier 2 dormente
- [ADR 0105](../../../memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — princípio sinal qualificado
- [ADR 0094](../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (transparência §3)
- US-INFRA-006 (`whats-active` tool, dependência) + US-INFRA-007 (esta skill)
