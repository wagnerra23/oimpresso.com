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

#### ⚠️ Passo 3.1 — ANTI-COLISÃO do índice (sempre conferir — Wagner 2026-08-12)

**O índice é o ÚNICO arquivo do encerramento que colide.** Handoff e session log têm nome único
(`HHMM` + slug) e nunca conflitam; o `08-handoff.md` recebe append **no topo** de toda sessão que
fecha — e várias fecham no mesmo dia. Medido em 2026-08-11: **8 commits** tocaram esse arquivo em
um único dia. Não é risco teórico: na sessão de 2026-08-12 o [#5647](https://github.com/wagnerra23/oimpresso.com/pull/5647)
apendou enquanto o PR do handoff estava aberto e o conflito **aconteceu**, depois de [W] ter
avisado — *"cuidado para não conflitar o handoff, outra sessão salvando também"*.

**Ordem obrigatória (não é sugestão — é o que encurta a janela):**
1. Escreva **handoff + session log primeiro** e valide o schema. Eles não colidem: trabalhe neles à vontade.
2. **Só então** toque o índice, e com `git fetch` **imediatamente antes** — se estiver atrás, sincronize ANTES de inserir a linha.
3. **Commit + push na sequência**, sem etapa cara no meio. Cada minuto entre o fetch e o push é janela de colisão.

**Se colidir mesmo assim** (vai acontecer — é append concorrente, não erro de ninguém):
- **Resolva APPEND-ONLY: ninguém perde linha.** É a ÚNICA coisa que importa aqui. As duas entradas
  ficam. Descartar a linha alheia apaga o handoff de outra sessão do índice — o arquivo continua no
  disco, mas fica **inacessível pela porta que o time usa pra achar handoff**.
  ⚠️ **NÃO reordene por data — e não "conserte" a ordem de ninguém.** A convenção canônica é
  *"adicionar 1 linha **no topo** da lista"* ([`how-trabalhar.md`](../../../memory/how-trabalhar.md)),
  ou seja **ordem de CHEGADA**, não cronológica. Medido no main em 2026-08-12: as 12 primeiras
  entradas vão `07:40 · 10:43 · 07:49 · 22:00 · 17:51 · 18:11 · 19:00 · 18:58 · 16:15 …` — o índice
  **nunca foi cronológico**. Errata honesta: a 1ª versão deste passo mandava "ordenar por data" e eu
  cheguei a trocar duas linhas de lugar por causa disso — conserto DESNECESSÁRIO, baseado num
  requisito que nenhuma fonte pede. Pior que inútil: reordenar linha alheia gera churn no arquivo
  mais conflitado do repo e **fabrica o próximo conflito**. Colidiu? junte os dois lados, na ordem
  em que estiverem, e siga.
- **Use `git merge`, NUNCA `git rebase`.** Rebase exige `--force-with-lease`, que o hook
  `block-destructive` barra com razão (sobrescreve histórico remoto). Merge resolve igual e aceita
  push normal. Descoberto na marra em 2026-08-12: o hook bloqueou o comando composto INTEIRO,
  então nem a resolução do conflito rodou.
- **Recibo antes de commitar:** `grep -c '^<<<<<<<\|^>>>>>>>' memory/08-handoff.md` → **0**, e
  conte as linhas preservadas (`N minha(s) + M dela(s)`). Resolver conflito sem conferir o
  resultado é como declarar verde sem contar asserção.

**Não vire gate:** o `Merge-marker scan (conflito commitado)` já pega marcador que escapou pro
commit — segunda régua pro mesmo tema seria duplicar dono (§5 2026-07-09). O que falta aqui é
**ordem de execução**, que é receita, não CI.

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
| Editar o índice cedo e só depois escrever o handoff | Índice **por último**, com `fetch` imediatamente antes (passo 3.1) |
| Resolver conflito do índice ficando com "a minha linha" | **Append-only**: as duas ficam — descartar apaga o handoff alheio do índice |
| Reordenar o índice por data ao resolver o conflito | Ordem é de **chegada** (convenção "1 linha no topo"); reordenar gera churn e fabrica o próximo conflito |
| `git rebase` + `--force-with-lease` pra resolver o índice | `git merge` — rebase exige force-push, que o `block-destructive` barra |

## Origem (rastreabilidade canon)

- 2026-05-17: R12 criada no PROTOCOLO ([commit `stupefied-noether-89f83d`](../../memory/reference/PROTOCOLO-WAGNER-SEMPRE.md))
- 2026-05-28 sessão Larissa: R12 não disparou em 17 PRs ~8h. Wagner cobrou. Eu cumpri 4/5 intuitivo + corrigi passo 3 após cobrança.
- 2026-05-28 mesma sessão: Wagner perguntou "como colocar pra funcionar? qual momento tem que ser ativado?". Esta skill + hook companion respondem.

ROI: cada R12 cumprido = ~10-20k tokens economizados na próxima sessão (vs re-aprender contexto). Em ~3 sessões grandes/semana = 1-3M tokens/ano.
