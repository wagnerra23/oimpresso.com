---
date: "2026-06-23"
topic: "Estado-da-arte — 1 clique para sessão agêntica limpa, rule-seeded, isolada (mecanismo do gatilho, eixo A)"
authors: [C]
type: session
---

# Estado-da-arte — "1 clique → sessão agêntica limpa, rule-seeded, isolada"

> Eixo A (mecanismo do gatilho). Sessão `estado-da-arte`, 2026-06-23. Pesquisa limpa (Fase 1) ANTES de ler `memory/`/skill. Foco: os 4 elos (trigger / seed / isolamento / aprovação) e o mínimo viável reusando Claude Code nativo.

---

## Fase 1 — PESQUISA (os melhores, 2026)

Os 4 players convergiram no MESMO contrato ("teammate agêntico": atribui trabalho → roda isolado → devolve PR pra revisar), mas divergem no **clique** e no **isolamento**.

| Produto | TRIGGER (o "1 clique") | SEED (contexto injetado) | ISOLAMENTO | RETORNO / APROVAÇÃO |
|---|---|---|---|---|
| **GitHub Copilot coding agent** | Atribuir Issue ao `@copilot` (github.com / mobile / `gh` / GraphQL+REST API). 1 clique real = assign. Também `@copilot` em comentário de PR. | Issue body + custom instructions `.github/copilot-instructions.md` + `.github/instructions/*.instructions.md` (glob-scoped) + AGENTS.md + Copilot Memory (preview). Aceita repo/branch/instruções extra no momento do assign. | **VM efêmera** GitHub Actions (explora, testa, lint). Self-hosted runners via ARC pra infra privada — segue efêmero+isolado. | Draft PR; itera por comentário `@copilot`; humano dá merge (1 clique). |
| **Cursor background / cloud agents** | Cmd+' (cloud agent), `@Cursor` no **Slack**, emoji-reaction (automation), webhook GitHub/GitLab/Linear, **API**. `/automate` skill. | Prompt + `.cursor/rules/*.mdc` (YAML frontmatter glob-scoped) + AGENTS.md. Lê thread do Slack pra contexto. | **git worktree por agente** (local, parallel) **ou** VM isolada remota (Slack/cloud). Cada agente edita/builda/testa sem pisar no outro. | PR no GitHub direto do Slack; itera no thread. |
| **Devin 2.0** | Atribui ticket via Slack / Linear / MCP / UI. | Ticket + repo + playbooks/knowledge configurados. | **VM sandbox por sessão** (ACI: browser+shell+editor). Pro/Max até 10 concorrentes; Teams/Enterprise ilimitado. Cobra ACUs (tempo de VM). | PR pra revisar; "managed Devins" delegam sub-Devins em VMs paralelas. |
| **Linear AI Agents** | **Atribuir Issue ao agente** (delegação). Humano segue assignee primário, agente é contribuidor. @-mention também. | "Agent guidance" do workspace (repo a usar, formato de commit/PR, processo de review) injetado automático ao trabalhar a issue. | Delega ao backend do agente (Codex/Devin/Cursor) — Linear orquestra, não roda VM. | Agente comenta + abre PR no backend; humano fecha a issue. |

**Padrão emergente (o que importa pro oimpresso):**
1. **Trigger = mutação de estado num tracker** (assign issue / @-mention / emoji), NÃO "colar prompt". O clique é declarativo.
2. **Seed = body do item + arquivos de instrução versionados no repo** (`.github/copilot-instructions.md`, `.cursor/rules`, `AGENTS.md`, `CLAUDE.md`). Fidelidade vem de **scoping** (glob path-scoped) + spec no body.
3. **Isolamento real**: VM efêmera (Copilot/Devin) **ou** git worktree por task (Cursor). Concorrência N exige isolamento N — ninguém roda N agentes na mesma checkout.
4. **Retorno = draft PR**, iteração em comentário, merge 1-clique.

---

## Fase 2 — COMPARA com o que o oimpresso tem

O padrão alvo do Wagner é **exatamente** o contrato acima, restrito a UMA jornada (tela mudou → sessão limpa seedada → PR draft → Wagner aprova SCREENSHOT). O oimpresso já tem ~80% — o gap é o **mecanismo do gatilho** e o **isolamento real**.

| Dimensão | Estado-da-arte (Fase 1) | Estado oimpresso hoje | Distância |
|---|---|---|---|
| **Seed packager** | Issue body + instruction files + glob-scoping | `aplicar-prototipo` Fase 1-3 já produz `<tela>-gap.md` (GAP-SPEC) + task MCP com GAP embutido + charter + `.claude/rules/*` path-scoped + skills Tier A. **Contexto canônico já existe e é destilado.** | **curta** — falta empacotar num artefato consumível por 1 comando |
| **Trigger ("1 clique")** | Assign issue / @-mention / emoji / API | "Wagner pede em linguagem natural" → Claude decide spawnar. NÃO há botão. `tasks-create` MCP existe mas não dispara execução. | **média** — não há gatilho declarativo; é prompt manual |
| **Sessão limpa seedada** | VM/worktree com instruction files auto-carregados | `Agent(subagent_type:"general-purpose")` carrega CLAUDE.md + `.claude/rules` + skills automaticamente. Frontmatter `skills:`/`memory:`/`mcpServers:`/`isolation:` existe nativo. **Esse elo está pronto no harness.** | **curta** — primitivo nativo, só compor |
| **Isolamento** | worktree por task (Cursor) / VM (Copilot) | `Agent(isolation:"worktree")` existe e está **citado na Fase 4** da skill. MAS `coordenador-paralelo` roda os N sub-agents na **MESMA worktree** (calibração: "Omnichannel tentou spawn em worktree filha = morreram"). Hoje o paralelismo é Write/Edit-only sem isolamento git real. | **média** — o primitivo existe mas o agente em uso NÃO usa; risco de colisão DS/baseline |
| **Retorno / aprovação** | Draft PR + merge 1-clique | PORTÃO screenshot 1280/1440 light+dark → Wagner aprova SCREENSHOT (ADR 0114) → CI (pr-ui-judge + visual-regression + contrato-de-tela). **Mais rígido que o mercado** (mercado aprova diff; oimpresso exige screenshot). | **oimpresso SUPERA** — gate visual é diferencial |
| **Concorrência N tasks** | N VMs / N worktrees paralelas | `Agent` paralelo (N tool_use na mesma msg) + agent-view (mai/2026) lista sessões. Mas sem worktree real, N telas que tocam mesmo DS/`config/*baseline*.json` colidem (incidente #2495, anti-padrão já documentado). | **média** — concorrência segura exige worktree por wave |

**Veredito honesto:** o oimpresso tem o **seed** (melhor que a média — destila charter+GAP+rules path-scoped) e o **gate de aprovação** (supera o mercado — screenshot, não diff). Falta o **trigger declarativo** e o **isolamento worktree por task de verdade**. Não é "construir um Devin" — é compor 3 primitivos nativos que já existem no harness.

---

## Fase 3 — AVALIA o que falta (rankeado)

| Gap | Impacto | Esforço (IA-pair, ADR 0106 10x) | Pré-req? |
|---|---|---|---|
| **G1. `seed-tela.mjs`** (empacota o seed num artefato 1-comando) | **alto** | ~30 min IA-pair | nenhum bloqueante |
| **G2. `coordenador-paralelo` usar `isolation:"worktree"` de verdade** | **alto** (corrige o risco de colisão #2495 + destrava concorrência real) | ~45 min IA-pair (editar frontmatter + Fase 4 do agente + smoke 2 waves) | G1 ajuda mas não bloqueia |
| **G3. Trigger declarativo** (assign task MCP → dispara) | médio | depende do MCP server expor webhook/poll — **humano-limitado** (não é codável puro) | precisa decisão de infra (CT 100 vs Hostinger, ADR 0062) |
| **G4. Botão real no MCP/UI** (o "1 clique" literal) | baixo (cosmético — o valor está em G1+G2) | médio | bloqueado por G3 |

### O que é botão real HOJE vs o que ainda é colar prompt

- **JÁ É ~1 clique** (sem construir nada): `claude --agents '<JSON>'` ou um `@agent-aplica-tela <tela>` com a skill carregando o GAP-SPEC. O harness auto-injeta CLAUDE.md + `.claude/rules` + skills. Falta só o **packager** que produz o JSON/prompt de seed determinístico.
- **AINDA É colar prompt**: disparar a partir de um evento (task atribuída, diff do `cowork/`). Não há webhook MCP → execução. Isso é G3, humano-limitado (infra), NÃO codável puro — não inventar.
- **MÍNIMO VIÁVEL HOJE** = `seed-tela.mjs <tela>` imprime o bloco de seed pronto → cola num `Agent(isolation:"worktree", run_in_background:true)`. Isso é "1 comando + 1 paste", a ~80% do "1 clique" sem construir UI. O clique literal (G4) espera G3.

### Especificação concreta do `seed-tela.mjs` (G1)

```
INPUT:
  --tela <slug>            # ex: caixa-unificada, cliente
  [--base <branch>]        # default: main
  [--bg]                   # marca run_in_background:true no bloco emitido

EMPACOTA (lê do repo canônico, NÃO inventa — falha se faltar):
  1. GAP-SPEC      ← memory/requisitos/<Mod>/<tela>-gap.md  (produzido pela Fase 1-2 de aplicar-prototipo)
  2. charter       ← path do *.charter.md ao lado do .tsx (resolve nome↔Page via mapa: crm→Cliente etc.)
  3. visual_source ← prototipo-ui/cowork/ (ponteiro do charter; ADR ssot 2026-06-23)
  4. rules Tier 0  ← lista fixa: ADR 0093 (business_id), 0062 (Hostinger≠CT100), 0061 (zero auto-mem), PT-BR
  5. skills auto   ← mwart-process, cowork-prototype-replication, charter-first, multi-tenant-patterns, preflight-modulo
  6. diff sha      ← git log -1 --format=%H -- prototipo-ui/cowork/ (linhagem do que mudou)

OUTPUT (stdout, 2 formatos via --format):
  a) bloco markdown "prompt de seed" pronto pra colar (default)
  b) JSON do --agents (frontmatter: prompt, skills, isolation:"worktree", background, tools, mcpServers:["oimpresso"])

GUARDA-CHUVAS (Tier 0):
  - se <tela>-gap.md não existe → ERRO "rode Fase 1-2 de aplicar-prototipo primeiro" (não inventa gap — LICOES_F3)
  - se charter tem contrato-de-tela / módulo silenciado → ERRO "exige OK [W]" (Fase 2 governança)
  - PT-BR no domínio; nunca embute PII real (cowork = mock por contrato)
  - NÃO commita, NÃO cria task, NÃO dispara — só EMITE o seed. Disparo = Wagner cola.
```

`seed-tela.mjs` é o que falta pra fechar o elo SEED→TRIGGER reusando 100% do harness. É read-only, determinístico, idempotente.

### Riscos do isolamento (worktree real vs mesma-worktree)

- **`coordenador-paralelo` hoje FALHA aqui**: roda N sub-agents na MESMA worktree (Write/Edit-only). Funciona pra áreas disjuntas por path, mas **colide determinístico** quando 2 waves tocam mesmo DS component ou rebaselinam o mesmo `config/*baseline*.json` (incidente #2495 — já é anti-padrão na skill). A "lição Omnichannel" ("spawn em worktree filha = morreram") é de quando se tentava spawnar *dentro* de uma worktree criada à mão — NÃO é o `isolation:"worktree"` nativo, que o harness gerencia (cria branch do default, checkout em `~/.claude/worktrees/`, auto-cleanup se zero mudanças).
- **Correção (G2)**: trocar o spawn do `coordenador-paralelo` pra `Agent(isolation:"worktree")` por wave. Cada wave ganha worktree+branch real → fim da colisão DS/baseline → concorrência N segura → retorno já vem como branch (diff inspecionável, alinhado ao "draft PR" do mercado).
- **Trade-off**: worktree por wave custa disco + perde o contexto in-flight do parent (worktree parte do default branch, não do HEAD). Pra telas independentes (o caso de `aplicar-prototipo`) isso é exatamente o desejado. Pra trabalho turn-by-turn colaborativo, NÃO usar worktree (manter same-session).

### Ranking impacto × esforço (construir vs reusar)

| # | Ação | Construir ou reusar? | Impacto×esforço |
|---|---|---|---|
| 1 | `seed-tela.mjs` (G1) | **construir** (1 script ~30min) | alto × baixo ← **comece aqui** |
| 2 | `coordenador-paralelo` → `isolation:"worktree"` (G2) | **reusar** primitivo nativo (editar agente) | alto × baixo |
| 3 | Agent-view pra supervisão | **reusar** (já existe mai/2026) | médio × zero |
| 4 | Trigger declarativo MCP (G3) | **construir** infra (webhook/poll) — humano-limitado | médio × médio, **bloqueado** |
| 5 | Botão UI literal (G4) | construir | baixo × médio, bloqueado por G3 |

---

## RECOMENDAÇÃO

**Comece por G1 (`seed-tela.mjs`) — alto-impacto-baixo-esforço, sem pré-req bloqueante.** Fecha o elo SEED→TRIGGER reusando 100% do harness Claude Code: o "1 clique" vira "1 comando + 1 paste num `Agent(isolation:"worktree")`", que é o máximo de "1 clique" alcançável HOJE sem construir UI. Em seguida G2 (trocar o spawn do `coordenador-paralelo` pra worktree nativa) destrava concorrência segura e corrige o risco de colisão #2495.

**Próxima ação hoje:** escrever `scripts/governance/seed-tela.mjs` com a spec acima — input `--tela <slug>`, lê GAP-SPEC + charter + visual_source + rules Tier 0 + skills, emite bloco de seed pra colar. Read-only, falha se GAP-SPEC não existe (anti-invenção LICOES_F3), nunca dispara sozinho.

---

## Fontes
- GitHub Copilot coding agent — about: https://docs.github.com/copilot/concepts/agents/coding-agent/about-coding-agent
- Assign issues to Copilot via API: https://github.blog/changelog/2025-12-03-assign-issues-to-copilot-using-the-api/
- Copilot self-hosted runners (ephemeral): https://github.com/orgs/community/discussions/177903
- Cursor Automations (triggers): https://cursor.com/docs/cloud-agent/automations
- Cursor Background Agents in Slack: https://cursor.com/changelog/1-1
- Devin 2.0 technical design (VM por sessão): https://medium.com/@takafumi.endo/agent-native-development-a-deep-dive-into-devin-2-0s-technical-design-3451587d23c0
- Linear AI Agents (assign = delegate): https://linear.app/docs/agents-in-linear
- AGENTS.md vs CLAUDE.md vs Cursor rules vs Copilot: https://amux.io/guides/agent-config-files-compared/
- Claude Code subagents (isolation/worktree/skills/--agents/fork): https://code.claude.com/docs/en/sub-agents
- Claude Code background agents em worktree isolada: https://www.claudedirectory.org/how-to/background-agents-worktrees
- Claude Agent SDK headless: https://code.claude.com/docs/en/agent-sdk/overview
- Claude Code agent-view + scheduled tasks: https://claudefa.st/blog/guide/agents/agent-view
