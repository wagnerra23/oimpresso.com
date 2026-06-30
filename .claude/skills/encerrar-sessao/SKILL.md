---
name: encerrar-sessao
description: BLOQUEADOR — ATIVAR SEMPRE que user disser "encerrar sessão", "fim de sessão", "vamos parar", "continua depois", "salvar tudo", "salve as memórias", "ciclos", "outra sessão", "vai pra MCP continua depois", "tchau", "obrigado", "valeu", "fim", "fechar", "encerrar", "tá bom", "beleza", "show", "perfeito", "depois eu vejo", "fica pra depois", "baixa prioridade". TAMBÉM ativar quando agente cogitar terminar trabalho produtivo (≥3 PRs mergeados na sessão OR ≥1 ADR proposto OR ≥4h trabalho). Skill canônica que CARREGA conteúdo R12 PROTOCOLO inline NO MOMENTO do trigger (vs Tier A always-on que carrega no SessionStart e perde em sessão longa). Origem 2026-05-28 Wagner — sessão Larissa 17 PRs faltou cumprir R12 passo 3 porque conteúdo saiu do contexto após 200+ turnos. Wagner palavras textuais "mas não está funcionando porque? se existe mas não funciona ta errado. como colocar para funcionar? qual momento tem que ser ativado?". Solução: ativação lazy via description-match no momento exato vs eager always-on. Pareada com hook UserPromptSubmit `force-r12-closing-signal.mjs` (defesa em depth).
trust_level: L2
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
tier: B
parent_adr: 0130
---

# Encerrar sessão — ativação lazy de R12 PROTOCOLO

> **Wagner palavras textuais 2026-05-28:**
> *"mas não está funcionando porque????? se existe mas não funciona ta errado. como colocar para funcionar? qual momento tem que ser ativado?"*
>
> Catalogou exatamente o gap: R12 do PROTOCOLO-WAGNER-SEMPRE existe desde 2026-05-17 mas é **regra passiva**. Carregada Tier A no SessionStart, sai de contexto em sessão longa (200+ turnos / 4h+). Esta skill é o **mecanismo de ativação lazy** — dispara via description-match no momento exato dos trigger words.

## Por que existir (não é redundante com R12)

| Mecanismo | Tipo carga | Quando dispara | Risco |
|---|---|---|---|
| R12 PROTOCOLO-WAGNER-SEMPRE | Eager (SessionStart) | Tier A always-on | Sai de contexto sessão longa |
| **Skill `encerrar-sessao`** (esta) | **Lazy (description-match)** | **No momento do trigger word** | **Garantido** |
| Hook `force-r12-closing-signal.mjs` | UserPromptSubmit | Antes do Claude responder | Defesa em depth |

3 camadas = R12 dispara mesmo em sessão de 8h+ com 17 PRs.

## Quando ATIVAR (description-match)

Trigger words que **DEVEM** disparar a skill (description vem do header acima — keywords explícitas):

### Wagner explícito (qualquer um)
- "encerrar", "encerre", "encerra"
- "fim", "fechar", "fecha"
- "vamos parar", "para aqui"
- "continua depois", "outra sessão", "próxima sessão"
- "salvar tudo", "salve as memórias", "salve no protocolo"
- "vai pra MCP continua depois"
- "tchau", "obrigado", "valeu"
- "tá bom", "beleza", "show", "perfeito"
- "depois eu vejo", "fica pra depois", "baixa prioridade"

### Auto-detect produtivo (cogitar antes de Wagner falar)
- ≥3 PRs mergeados na sessão atual
- ≥1 ADR `status: proposto` criado
- ≥4h trabalho consecutivo
- Wagner aprovou item final E não introduziu novo escopo por 2+ turnos

### Bloqueio externo (encerra estado parcial)
- GraphQL rate-limit esgotado
- biz=4 inacessível (Wagner-account só vê biz=1)
- SSH Hostinger down

## Os 6 passos (idênticos a R12)

Quando ativada, executa **OBRIGATORIAMENTE** os 5 passos de R12 + 1 reforço:

### Passo 1 — MCP-first checklist (snapshot pro handoff)
```
mcp__oimpresso__cycles-active                   # cycle ativo + goals + drift
mcp__oimpresso__my-work                         # tasks DOING/REVIEW/TODO
Glob memory/handoffs/2026-MM-*.md               # handoffs irmãos
mcp__oimpresso__decisions-search since:<data>   # ADRs aceitas
```

### Passo 2 — Handoff append-only
Path: `memory/handoffs/YYYY-MM-DD-HHMM-<slug-kebab>.md` (~30-80 linhas máximo)

Estrutura obrigatória:
- **Frontmatter (conforme `scripts/memory-schemas/handoff.schema.json` — o gate `Handoff (memory/handoffs/*.md)` valida):**
  - **Required:** `date: "YYYY-MM-DD"` (STRING entre aspas — sem aspas o YAML parseia como Date e o gate falha `/date must be string`), `slug: <kebab-case>` (`^[a-z0-9-]+$`), `tldr: "<resumo 10-500 chars>"`.
  - **Opcionais úteis:** `time: "HH:MM BRT"`, `cycle: CYCLE-NN`, `prs: [NNNN, ...]` (ints), `decided_by: [W|F|M|L|E]`, `related_adrs: [NNNN-slug]` (slug `^[0-9]{4}-[a-z0-9-]+$`), `next_steps: ["..."]`.
  - ⚠️ NÃO usar `hour`/`topic`/`duration`/`authors` (template legado — `additionalProperties` aceita mas faltam os 3 required). Validar local antes do PR: skill `memory-schema-preflight`.
- `## Estado MCP no momento` (snapshot passo 1)
- `## O que aconteceu` (narrativa interpretativa)
- `## Artefatos gerados` (arquivos + linhas + canon path)
- `## Persistência` (3 canais: git, MCP, BRIEFING quando aplicável)
- `## Próximos passos pra retomar` (comando único)
- `## Lições catalogadas` (especialmente violações de protocolo)
- `## Pointers detalhados` (consultar on-demand — NÃO duplicar conteúdo)

### Passo 3 — Atualizar índice `memory/08-handoff.md`
- Linha NO TOPO da lista "Últimos handoffs"
- Formato: `[YYYY-MM-DD HH:MM — Título curto (chave: PRs/ADRs/métricas)](handoffs/...)` + parêntese denso

### Passo 4 — Commit + push (worktree filha OK)
1 commit com handoff + índice. Webhook GitHub→MCP propaga em ~2min.

### Passo 5 — Reportar fechamento ≤8 linhas
Tabela "passos do protocolo + ✅/❌" + caveats + branch final + próxima ação.

### Passo 6 — Citar explícito skill + R12 no report
> "Cumprindo R12 PROTOCOLO via skill `encerrar-sessao` (ativação lazy)."

Garante auditoria do mecanismo (Wagner pode verificar que disparou).

## Sinal de violação (defesa em depth se skill falhar)

Hook `force-r12-closing-signal.mjs` (Node.js **cross-platform** — Windows/macOS/Linux) detecta os mesmos triggers no UserPromptSubmit e injeta `<system-reminder>` forçando execução R12. Se nem skill nem hook dispararem, Wagner cobra "esta esquecendo das regras de fechamento" — reincidência ativa hook P2 dormente bloqueador.

## Heurística de duração

| Sinal | Skill comportamento |
|---|---|
| Sessão <2h, 0-1 PRs | Pula passo 1-2 (session log) — só handoff curto |
| Sessão 2-4h, 2-3 PRs | Executa 6 passos completos |
| Sessão ≥4h, ≥4 PRs (épico) | Executa 6 passos + atualiza BRIEFING.md módulos tocados (skill `brief-update` Tier B auto-trigger) |

## Pareada com

- [R12 PROTOCOLO-WAGNER-SEMPRE](../../memory/reference/PROTOCOLO-WAGNER-SEMPRE.md) — origem da regra (texto canon)
- [Hook `force-r12-closing-signal.mjs`](../../.claude/hooks/force-r12-closing-signal.mjs) — camada 2 (UserPromptSubmit)
- [ADR 0130 — handoff append-only MCP-first](../../memory/decisions/0130-handoff-append-only-mcp-first.md) — base
- [Skill `memory-sync`](../memory-sync/SKILL.md) — Tier B, push pro git canon
- [Skill `brief-update`](../brief-update/SKILL.md) — Tier B, atualiza BRIEFING.md módulos
- [Skill `continuar`](../continuar/SKILL.md) — counterpart (mesma sessão fecha → próxima abre)

## Anti-padrões

| ❌ Errado | ✅ Certo |
|---|---|
| User digitou "encerrar" — Claude responde sem disparar skill | Skill DEVE disparar (description-match Tier B) |
| Cumprir R12 intuitivo sem citar | Citar explícito "Cumprindo R12 via skill" no report |
| Handoff de 300+ linhas duplicando session log | Handoff 30-80 linhas com pointers |
| Aceitar ADR sozinho sem Wagner confirmar | Espera "aceito" textual + sed batch |
| Webhook sync não-confirmado | Aguarda 2min + valida via `tasks-list` |

## Origem (rastreabilidade canon)

- 2026-05-17: R12 criada no PROTOCOLO ([commit `stupefied-noether-89f83d`](../../memory/reference/PROTOCOLO-WAGNER-SEMPRE.md))
- 2026-05-28 sessão Larissa: R12 não disparou em 17 PRs ~8h. Wagner cobrou. Eu cumpri 4/5 intuitivo + corrigi passo 3 após cobrança.
- 2026-05-28 mesma sessão: Wagner perguntou "como colocar pra funcionar? qual momento tem que ser ativado?". Esta skill + hook companion respondem.

ROI: cada R12 cumprido = ~10-20k tokens economizados na próxima sessão (vs re-aprender contexto). Em ~3 sessões grandes/semana = 1-3M tokens/ano.
