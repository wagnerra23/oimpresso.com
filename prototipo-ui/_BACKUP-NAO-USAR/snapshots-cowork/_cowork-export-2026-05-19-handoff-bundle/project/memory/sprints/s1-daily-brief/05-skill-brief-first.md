# Skill `brief-first` — Tier A (always-on)

> **Path no repo:** `.claude/skills/brief-first/SKILL.md`
>
> **Tier:** A (always-on, carregado em todo system prompt de Claude Code)
>
> **Charter pai:** ADR MEMORY-NNNN (Daily Brief contract)
>
> **Promovida de:** nova (não existe antes do Sprint 1)
>
> **Trust level:** 1 (auto, não requer confirmação humana)

---

## Conteúdo do arquivo `.claude/skills/brief-first/SKILL.md`

```markdown
---
name: brief-first
description: |
  Antes de qualquer ação no projeto Oimpresso, chame mcp__oimpresso__brief-fetch
  para carregar o Daily Brief — estado consolidado do projeto em ~3k tokens.
  Substitui 5-8 chamadas exploratórias (cycles-active, sessions-recent,
  tasks-active, decisions-search) que cada sessão fazia antes.
trust_level: 1
tier: A
parent_mission: mission.constituicao-v2
charter_adr: MEMORY-NNNN-daily-brief
auto_trigger: session_start
applies_to:
  - any session in oimpresso project
  - both human-driven and agent-driven sessions
---

# Skill: brief-first

## Quando ativa

**Sempre.** Esta skill é Tier A — carrega em todo system prompt do Oimpresso.
Toda sessão de Claude Code (Wagner, Felipe, Maíra, Luiz, Eliana, ou qualquer
agente automatizado) deve seguir este protocolo no início.

## Protocolo obrigatório

```
PASSO 1 — SEMPRE chame primeiro:
   mcp__oimpresso__brief-fetch {}

PASSO 2 — Leia o markdown retornado completamente.
   Ele tem 7 seções fixas: ESTADO MACRO, EM VOO AGORA,
   DECISÕES RECENTES, SKILLS USO 7d, CHARTERS APODRECENDO,
   FLAGS, METADATA.

PASSO 3 — Use o brief como base de contexto. NÃO refaça as queries
   que ele já agregou (cycles-active, sessions-recent, tasks-active,
   ads_decisions, skill_telemetry). Elas são REDUNDANTES após o brief.

PASSO 4 — Se vai editar arquivo com .charter.md ao lado:
   mcp__oimpresso__charter-fetch <path>
   (skill charter-first cuida desse passo automaticamente)

PASSO 5 — Demais tools, contexto-específico.
```

## Quando NÃO repetir o brief-fetch

- Mesma sessão, mesmo turno → cached, não chame de novo
- Tarefa puramente local (ex: editar README sem dependência cross-cutting)
  ainda assim chame UMA vez no início, custo é zero (cache)

## Quando força refresh

```
mcp__oimpresso__brief-fetch { "force_refresh": true }
```

Apenas em duas situações:
1. Wagner pediu explicitamente "regerar brief" / "atualizar brief"
2. Você detectou que o brief está com `staleness_minutes` > 240
   (gap entre cron e operação crítica)

Nunca force refresh em loop ou por insegurança — respeita cap diário.

## Como o brief substitui exploração

ANTES (sem essa skill — comportamento antigo):
```
1. mcp__oimpresso__cycles-active        → 4k tokens
2. mcp__oimpresso__sessions-recent      → 6k tokens
3. mcp__oimpresso__tasks-active         → 5k tokens
4. mcp__oimpresso__decisions-search     → 8k tokens
5. mcp__oimpresso__memoria-search       → 7k tokens
TOTAL: ~30k tokens só pra orientação
```

DEPOIS (com brief-first):
```
1. mcp__oimpresso__brief-fetch          → 3k tokens
TOTAL: 3k tokens
```

Economia média: ~27k tokens por sessão. Com 30 sessões/dia → 810k tokens/dia
economizados.

## Anti-padrões (NÃO faça)

❌ Chamar `cycles-active` depois de `brief-fetch` — info já tá no brief
❌ Chamar `sessions-recent` no início — info já tá no brief
❌ Chamar `decisions-search` sem ter lido o brief primeiro
❌ Forçar refresh "por garantia" — quebra o cache, custa $$ desnecessário
❌ Pular o brief porque "é só uma tarefa pequena" — telemetria pega isso

## Como mede sucesso

Telemetria automática em `mcp_skill_telemetry`:
- `trigger_count` 7d → deve ser ≥ N_sessions × 0.9
- `success_count / trigger_count` → deve ser ≥ 0.95
- `tokens_saved_estimate` → soma deve crescer monotônica

Se sua sessão teve `brief-first` count = 0, você violou esta skill.

## Caso de exceção (3 únicos)

1. **Tool MCP indisponível** (`brief-fetch` retorna 503)
   → fallback: chame `cycles-active` + `tasks-active` E avise Wagner via
   mensagem do agent: "⚠ brief-fetch indisponível, usando fallback"

2. **Sessão de healing/incident** (Wagner explicitamente disse "estamos
   investigando MCP, ignore brief")
   → ok pular nesta sessão, telemetria registra com flag

3. **Sessão de bootstrap** (rodando esta skill pela primeira vez antes do
   Sprint 1 estar 100% no ar)
   → ok, mas só uma vez. Próximas sessões devem encontrar o brief.

## Referências

- ADR MEMORY-NNNN (contrato do brief)
- Sprint 1 dossier: `memory/sprints/s1-daily-brief/`
- Cockpit (Sprint 6): `/governance/oimpresso` mostra brief usage rate
```

---

## Como commitar

```bash
mkdir -p .claude/skills/brief-first
# Salvar o conteúdo acima como SKILL.md
git add .claude/skills/brief-first/SKILL.md
git commit -m "feat(skills): adiciona brief-first Tier A — Sprint 1"
git push
```

---

## Como verificar que está ativa

Em qualquer sessão Claude Code dentro do repo, peça:

```
> Liste suas skills carregadas
```

Resposta deve incluir `brief-first` na lista de Tier A.

Em produção, monitore:

```sql
SELECT
    DATE(triggered_at) AS dia,
    COUNT(*) AS triggers,
    COUNT(DISTINCT agent_id) AS agentes_unicos,
    SUM(tokens_saved_estimate) AS tokens_economizados
FROM mcp_skill_telemetry
WHERE skill_name = 'brief-first'
  AND triggered_at > NOW() - INTERVAL 7 DAY
GROUP BY 1
ORDER BY 1 DESC;
```

Após 7 dias de operação, esperado:
- ≥150 triggers/dia (médio)
- 6-10 agentes únicos
- ~4M tokens economizados/dia (estimativa conservadora)
