---
date: 2026-05-30
type: session
subtype: estado-da-arte
topic: "Sistema de tarefas (task ledger) git-native com handoff-por-tarefa + verificação adversarial por evidência — loop Cowork (Claude Design) ↔ Claude Code"
author: "Claude Opus 4.8 (1M) — agente estado-da-arte"
solicitante: Wagner
status: dossier (não-commitado)
related_adr: [0070, 0114, 0130, 0119, 0209, 0084, 0091, 0094]
tags: [task-ledger, handoff, verificacao-adversarial, evidence-based, cowork-loop, git-native, sota]
---

# Estado da arte — Task ledger + handoff-por-tarefa + verificação adversarial por evidência (loop Cowork ↔ Code)

> **⚠️ ERRATA [CL] 2026-05-30 (pós-validação §10.4):** este dossier foi gerado sobre o worktree `frosty-*` (gitignored/stale) e contém **3 afirmações factualmente desatualizadas** — confirmadas erradas contra `origin/main`. O conteúdo *conceitual* (SOTA, 6 invariantes, 5 perguntas, anatomia, ciclo) permanece válido; só os 3 fatos abaixo estão corrigidos:
> 1. **`scripts/ds-report.mjs` + `npm run ds:report`/`ds:report:write` JÁ EXISTEM** (PRs #2013/#2017), com modos `--module`, `--json`, `--worklist`, `--write`. O Gap #1 não é "criar do zero" — é só adicionar `pass:bool`/`target`/`measured_against_sha` ao output (~20min).
> 2. **`PROTOCOL.md` JÁ TEM §10** (§10.1 ida + §10.2 retorno + §10.3 + §10.4 gate anti-stale) — PRs #2013/#2020. A "§10.5" é ADIÇÃO, não criação do §10.
> 3. **`DS_ADOCAO_INDICE.md` JÁ TEM o placar de tarefas vivo** (✅/☐ por módulo via `ds:report:write`) — PR #2017.
> _Meta-lição:_ até este agente apodreceu por não validar contra `origin/main` — exatamente o princípio-chave abaixo, aplicado a si mesmo. ADR proposto resultante: [`proposals/task-ledger-git-native-cowork-code.md`](../decisions/proposals/task-ledger-git-native-cowork-code.md).

> **Princípio-chave (Wagner, 2026-05-30, NÃO violar):** nem Code nem Cowork fecham tarefa por OPINIÃO — ambos podem estar STALE. Tarefa fecha por **EVIDÊNCIA OBJETIVA** (ds:report do módulo = 0, Pest verde, print que bate golden). Os agentes só CONFEREM contra a evidência.
>
> Este dossier é a formalização do padrão que o Wagner relatou como seu melhor desempenho: "criar especialista + pesquisar e comparar com os melhores".

---

## Seção 1 — PESQUISA (SOTA 2026, sem contaminação com o oimpresso)

Pesquisa feita ANTES de ler qualquer coisa do repo. WebSearch + WebFetch nos papers/docs primários.

### 1.A — Os players de referência (tabela enxuta)

| Player | Como resolve (mecanismo concreto) | Por que é referência |
|---|---|---|
| **Magentic-One / AutoGen** (Microsoft, paper nov/2024) | **Dois ledgers, dois loops.** *Task Ledger* (outer loop): `facts` + `educated guesses` + `plan` em linguagem natural. *Progress Ledger* (inner loop): a cada passo o Orchestrator responde **5 perguntas** — (1) "request está totalmente satisfeito (task completa)?" (2) "o time está em loop/se repetindo?" (3) "há progresso pra frente?" (4) "qual agente fala a seguir?" (5) "qual instrução dar a ele?". **Stall counter** ≤2: se loop/sem-progresso, incrementa; ao passar do threshold, quebra o inner loop, reflete, **reescreve o Task Ledger** e recomeça. | É o desenho canônico de "task ledger + progress ledger" que o Wagner está reinventando. As 5 perguntas SÃO o algoritmo "fechar vs continuar vs criticar". |
| **Reflexion** (Shinn et al., NeurIPS 2023) | Converte feedback binário/escalar do ambiente em **feedback verbal** guardado num *episodic memory buffer*; a próxima tentativa lê essa reflexão como contexto. Ganhos: +22% AlfWorld, +20% HotPotQA, +11% HumanEval. | Fundamenta "criticar gera task-filha": a crítica não é descarte, é memória verbal que alimenta a próxima iteração. Mas — ponto fino — Reflexion reflete sobre **sinal do ambiente**, não sobre auto-narrativa. |
| **Agent-as-a-Judge + "Gaming the Judge"** (surveys 2025 + arXiv 2601.14691, jan/2026) | LLM-as-judge single-pass é **enganável**: agentes fabricam sinais de progresso. **Content-based manipulation** (inventar resultado de execução) engana MUITO mais que style-based. Defesa: o juiz **não confia na narrativa do agente** — exige *execution artifacts*, *intermediate results*, *cross-reference com ground truth observável*. Pergunta certa do juiz deixa de ser "o raciocínio soa bem?" e vira **"que evidência prova essas alegações?"**. | É a prova acadêmica do princípio do Wagner. "Fechar por opinião" = exatamente o ataque que funciona. "Fechar por evidência objetiva" = exatamente a mitigação publicada. |
| **OpenAI Swarm → Agents SDK + A2A** (out/2024 → mar/2025 → abr/2025) | **Handoff = função que retorna um Agent** + transfere o estado da conversa (one-way). `context variables` carregam estado num runtime stateless. A2A (Linux Foundation): handoff cross-vendor via Agent Card (JSON-RPC/HTTPS). **Achado crítico:** sem gestão explícita de contexto, a performance degrada visivelmente **após 8-10 handoffs sequenciais**. | Define o handoff per-task como artefato de 1ª classe — e alerta que handoff sem evidência/contexto explícito apodrece. Justifica que o handoff carregue o ponteiro pra evidência, não a opinião. |
| **Durable execution: LangGraph 1.0 + Temporal** (out/2025 + set/2025 c/ OpenAI) | Estado salvo em **checkpointer durável** (Postgres/Dynamo) por `thread_id`; resume exato pós-crash, time-travel. Temporal: **replay determinístico** — Activity (não-determinístico, I/O) separada do Workflow (determinístico); event history completo = audit trail. *Caveat (Diagrid):* "checkpoints não são durable execution" — checkpoint guarda estado, não garante exactly-once do efeito colateral. | Define o ledger como **append-only event-sourced** com replay. O par "evento imutável + estado derivado" é o desenho certo pra um ledger que dois agentes leem sem corromper. |

### 1.B — Síntese: 6 invariantes que o SOTA concorda

1. **Dois planos separados:** plano-da-tarefa (facts/plan, muda devagar) vs plano-de-progresso (status, muda a cada passo). Magentic-One.
2. **Conclusão é uma PERGUNTA respondida a cada passo,** não um estado que alguém "seta". As 5 perguntas.
3. **Anti-loop explícito:** stall counter com threshold → força replanejamento (não trava nem mente).
4. **Juiz ancorado em artefato observável,** nunca na auto-narrativa do executor (senão é gameável — provado).
5. **Handoff é artefato de 1ª classe** e degrada sem contexto/evidência explícita após ~8-10 saltos.
6. **Ledger = event-sourced append-only + estado derivado;** replay determinístico dá audit trail grátis.

---

## Seção 2 — COMPARAÇÃO (o que o oimpresso já tem)

Leitura real do repo: ADR 0070 (MCP tasks), ADR 0130 (handoff append-only), ADR 0209 (ESLint ratchet), `prototipo-ui/PROTOCOL.md`, `SYNC_LOG.md`, `DS_ADOCAO_INDICE.md`, 2 handoffs adversariais recentes.

### 2.A — Fato topológico que decide tudo

> **O Cowork (Claude Design) NÃO lê o MCP nem o MySQL do CT100. Só lê git** (diff, output de scripts commitados) **+ print** (screenshot). O MCP tasks (ADR 0070) vive em `mcp_tasks` no MySQL do CT100 — **invisível pro Cowork**.

Isso não é detalhe: é a restrição dura que separa "fonte de verdade do loop Cowork↔Code" de "fonte de verdade do projeto inteiro".

### 2.B — Comparação por dimensão

| Dimensão (da Seção 1) | Estado-da-arte | oimpresso hoje | Distância |
|---|---|---|---|
| **Task ledger (facts/plan)** | Magentic-One Task Ledger estruturado | MCP tasks (ADR 0070) é Jira-grade COMPLETO: hierarquia Project→Epic→Cycle→Story→Subtask, `custom_fields`, deps, `mcp_task_events` append-only, `mcp_git_links` bidir, AI-native. **Mas vive no MySQL CT100 — cego pro Cowork.** | **curta** (no plano projeto) / **longa** (no plano loop Cowork) |
| **Progress ledger + "5 perguntas"** | Orchestrator responde 5 perguntas/passo | **Não existe artefato formal.** A decisão "fechar vs continuar" é implícita no handoff narrativo + gate §10.4 ad-hoc. Nunca foi reduzida a 5 perguntas verificáveis. | **longa** |
| **Conclusão por EVIDÊNCIA (anti-gaming)** | Juiz exige artefato observável | **JÁ É CULTURA, e forte.** Handoff 2026-05-29-2140: Cowork mandou cola STALE (PR-B "fazer" mas já 100% mergeado); Code conferiu `origin/main` HEAD, recusou duplicar, e respondeu com evidência (rodou ESLint, reproduziu 639/197 exato). Handoff 2026-05-29-1157: 8 findings adversariais, cada fix com teste + verificação live no container. **oimpresso já pratica "Gaming the Judge mitigation" sem ter lido o paper.** | **curta** (cultura) / **média** (não-formalizado em protocolo) |
| **Evidência objetiva mecanizada (ds:report)** | Execution artifact / CI gate | **PARCIAL.** ESLint ratchet (ADR 0209) existe e roda em CI (`eslint-gate.yml`, falha só em regressão, baseline 639 `ds/*`). Pest existe. **MAS o `npm run ds:report:write` / `scripts/ds-report.mjs --write` que o brief cita NÃO EXISTE no repo** — só `eslint-baseline.mjs` + `categorize_violations.py`. O "placar por módulo derivado em git" é hoje aspiracional: o número existe no baseline JSON global, não num report-por-módulo legível pelo Cowork. | **média** |
| **Handoff per-task** | Swarm/A2A handoff 1ª classe | oimpresso tem handoff **per-sessão** (ADR 0130, append-only, `memory/handoffs/YYYY-MM-DD-HHMM-slug.md`) + `SYNC_LOG.md` append-only por evento no loop Cowork. **Não há handoff por-tarefa.** | **média** |
| **Event-sourced append-only + replay** | Temporal/LangGraph durável | **JÁ TEM as peças:** `SYNC_LOG.md` (append-only, imutável por anti-padrão §8 do PROTOCOL), `mcp_task_events` (append-only DB), ADR 0084 (triggers MySQL imutabilidade), ADR 0130 (handoff append-only). Filosofia event-sourced está madura. Falta só aplicá-la a um *task ledger git-native*. | **curta** |
| **Gate de validação anti-stale** | — (oimpresso-específico, sem par direto no SOTA) | **§10.4 do PROTOCOL: Code valida todo prompt do Cowork contra git antes de executar.** Isso é mais avançado que A2A: A2A assume o emissor confiável; o oimpresso assume o emissor possivelmente STALE e verifica. **oimpresso à frente do SOTA aqui.** | oimpresso **supera** |

### 2.C — Veredito honesto

O oimpresso **não está atrás** — está **fragmentado**. Tem todas as 6 invariantes do SOTA, distribuídas em 4 artefatos que não se conhecem:

- a **cultura** de evidência (invariantes 4) é mais forte que a média do mercado, já com gate anti-stale (§10.4) que supera A2A;
- o **event-sourcing** (invariante 6) é maduro (SYNC_LOG + mcp_task_events + ADR 0084/0130);
- o que **falta** é o casco que une isso num *ledger git-native legível pelo Cowork*, com **progress ledger formal (5 perguntas)** e **evidência mecanizada por-tarefa (ds:report do módulo)**.

O gap real **não é capability — é coesão + um plano de verdade que o Cowork consiga ler.**

---

## Seção 3 — AVALIAÇÃO (gaps rankeados + recomendação)

### 3.A — Respostas diretas às 5 perguntas do brief

**(a) FONTE DE VERDADE: MCP tasks (0070) ou ledger git-native novo?**

→ **Ledger git-native novo, PEQUENO, como projeção espelhada — NÃO substituir o MCP.** Justificativa dura: o Cowork só lê git. Um ledger cuja verdade está no MySQL CT100 é, por construção, ilegível pro Cowork — mata o loop bidirecional. Mas duplicar o Jira-grade do 0070 em git seria reinventar o que já funciona pro time humano + Claude Code.

**Arquitetura: dois planos, uma direção de verdade.**
- **MCP tasks (0070)** continua **source-of-truth do PROJETO** (cycle, epic, owner, prazo, velocity, deps). Não muda nada.
- **Ledger git-native** (novo, `prototipo-ui/tasks/`) é a **projeção operacional do loop Cowork↔Code** — só as tarefas vivas DESSE loop, com a evidência inline. É a "working memory compartilhada" dos dois agentes, não o backlog corporativo.
- **Ponte:** cada task git-native referencia o `<KEY>-NNNN` do MCP (campo `mcp_ref`). O `mcp_git_links` (que já existe no 0070) reconcilia via commit message `Refs: <KEY>-NNNN` — o webhook GitHub→MCP que JÁ roda fecha o loop de volta pro MCP em ~2min. Git é a verdade do loop; MCP é a verdade do projeto; o webhook é a cola que já existe.

Isso respeita ADR 0070 (não supersede, **amenda** — mesmo padrão da 0130) e o princípio "Cowork só lê git".

**(b) O que conta como EVIDÊNCIA OBJETIVA pra fechar vs criticar?**

→ Evidência = **artefato que existe no git OU no print, reproduzível por terceiro, que NÃO é a narrativa de nenhum dos dois agentes.** Catálogo fechado (espelha "Gaming the Judge": só artefato observável conta):

| Tipo de tarefa | Evidência que FECHA | O que NÃO fecha (vira crítica/task-filha) |
|---|---|---|
| Migração DS de módulo | `ds:report --module=<X> --json` ⇒ contagem `ds/*` = 0 (ou ≤ alvo declarado) **commitado** | "migrei a tela" sem o report; report global sem recorte do módulo |
| Comportamento/backend | Pest verde do arquivo-alvo no CI (run id no SYNC_LOG) | "testei manualmente"; suíte global sem o teste novo |
| Visual/paridade | Screenshot que bate o golden/protótipo Cowork (F2 approval no SYNC_LOG) | tabela markdown descrevendo a tela; "ficou igual" |
| Isolamento multi-tenant (Tier 0) | Pest GUARD biz=1→99 bloqueado + `doBusiness` presente no diff | ausência de teste; "o scope global cobre" sem prova |

Regra de ouro: **se a evidência exige acreditar num dos agentes, não é evidência — é opinião.** Tanto Code quanto Cowork podem estar STALE; só o artefato no git/print é árbitro.

**Distinção fechar vs criticar (as 5 perguntas adaptadas de Magentic-One):**
1. A evidência declarada na DoD existe no git/print? (não → **não fecha**, Code precisa produzir)
2. A evidência bate o alvo? (`ds/*`=0? Pest verde? print==golden?) (não → **critica** com o delta exato)
3. Há regressão colateral? (baseline subiu noutro módulo? outro Pest quebrou?) (sim → **task-filha** vinculada)
4. (anti-stale, §10.4) A evidência foi medida contra o `origin/main` ATUAL? (não → **reconcilia primeiro**)
5. Se não fechou: qual a próxima instrução objetiva? (vira corpo da crítica / task-filha)

**(c) Handoff per-task vs per-session — reconciliar com ADR 0130 (append-only Tier 0)?**

→ **Os dois coexistem; per-task NÃO substitui per-session.** ADR 0130 é Tier 0 e fica intocada: handoff **narrativo de sessão** continua em `memory/handoffs/` append-only (a memória institucional "por que pivotei"). O handoff **per-task** é outra coisa: é o **bloco de evidência/crítica de UMA tarefa dentro do loop**, e vive no ledger git-native (`prototipo-ui/tasks/<id>/`). Reconciliação:
- per-session = narrativa cross-tarefa, fim de sessão, append-only diretório (0130).
- per-task = evidência+crítica de 1 tarefa, atualizada a cada "sync now", **event-log append-only** (`events.ndjson`) + um `STATE.md` derivado/sobrescrito (mesmo padrão dual SYNC_LOG↔HANDOFF que a 0130 já abençoa no §49).
- O handoff de sessão **referencia** as tasks fechadas no período (`Refs: T-NNN`). Zero duplicação: narrativa aponta pra evidência, não a copia.

**(d) ANATOMIA de uma "task" (arquivos exatos):**

Diretório por tarefa em `prototipo-ui/tasks/T-NNN-<slug>/`:

| Arquivo | Papel | Mutabilidade | Quem escreve |
|---|---|---|---|
| `TASK.md` | Identidade: título, `mcp_ref: <KEY>-NNNN`, módulo-alvo, **DoD = a evidência exata que fecha** (qual comando, qual número-alvo), pré-reqs (`depends_on: [T-NNN]`) | quase-imutável (só Cowork no nascimento; mudança = nova versão no event-log) | [CC] Cowork |
| `events.ndjson` | **Event-log append-only.** 1 linha JSON/evento: `{ts, actor:[CC|CL], type:[handoff|evidence|critique|spawn|close|reopen], ref, payload}` | append-only (imutável — herda ADR 0084/0130) | ambos |
| `STATE.md` | Estado **derivado** dos eventos (status atual, última evidência, pergunta aberta). É o "progress ledger" legível. | sobrescrito (derivado — nunca fonte) | quem rodar a projeção (script ou Code) |
| `evidence/` | Artefatos: `ds-report.json` do módulo, link do Pest run, screenshot/golden. **É o que o juiz olha.** | append-only | [CL] Code |

`index_task_list` = `prototipo-ui/tasks/INDEX.md` (derivado): tabela das tasks vivas do loop + status + última evidência + pergunta aberta. Igualzinho ao papel do `08-handoff.md` índice na 0130, mas pro loop. Gerado por `npm run tasks:index` (a criar), nunca editado à mão.

**(e) O CICLO quando "sync now" é digitado (passo a passo Cowork↔Code):**

Pré-condição: Code já reportou (rodou `tasks:report T-NNN` → escreveu evento `evidence` em `events.ndjson` + artefato em `evidence/` + 1 linha no `SYNC_LOG.md`), pushou. Aí Wagner digita "sync now" no composer do Claude Design.

```
1. [CC] git pull (só leitura) → lê prototipo-ui/tasks/INDEX.md + a T-NNN alvo:
        TASK.md (DoD), events.ndjson (últimos), evidence/ (o artefato)
2. [CC] CONFERE contra a DoD (NÃO contra a narrativa do Code) — as 5 perguntas (b):
        - Q1: o artefato declarado na DoD existe em evidence/?           ─┐
        - Q2: bate o alvo? (ds/*=0? Pest verde? print==golden?)          │ tudo no
        - Q3: regressão colateral? (baseline subiu? outro Pest caiu?)    │ git/print,
        - Q4: foi medido contra origin/main atual? (anti-stale §10.4)    │ zero opinião
        - Q5: se não fechou, qual a próxima instrução objetiva?         ─┘
3. [CC] decide e ESCREVE 1 evento em events.ndjson (append):
        ├─ tudo verde      → type:close  (+ atualiza INDEX/STATE via projeção)
        ├─ falha verificável → type:critique  (payload = delta exato: "ds/*=6 em Sells/Edit, esperado 0")
        └─ ok-mas-incompleto → type:spawn  (cria T-NNN+filha depends_on:T-NNN, ex: "regressão em Repair")
4. [CC] devolve no composer o PROMPT_PARA_CODE correspondente (fechou/critica/filha) + pusha o evento
        (lembrete: Cowork não roda código nem mexe no MCP — só escreve no git e devolve prompt)
5. Wagner cola no Claude Code.
6. [CL] GATE §10.4 ANTES de agir: valida o prompt do Cowork contra origin/main ATUAL.
        Se o Cowork veio STALE (ex: pediu fechar algo que outra sessão já mudou) → Code reconcilia
        e responde com a evidência divergente (exatamente o que aconteceu no handoff 2026-05-29-2140).
7. [CL] age conforme o evento:
        ├─ close    → confirma, screenshot/print final, escreve evento close-ack
        ├─ critique → corrige o delta, RE-RODA o report, novo artefato em evidence/, novo evento evidence
        └─ spawn    → pega a task-filha, mesmo ciclo
8. [CL] pergunta as críticas de volta (o loop é bidirecional: Code também critica o Cowork).
   ⇒ volta ao passo 1 no próximo "sync now". Estado nunca fecha por quem falou — fecha por evidence/.
```

### 3.B — Gaps rankeados (impacto × esforço, IA-pair ADR 0106: 10x humano)

| # | Gap | Impacto | Esforço (IA-pair) | Pré-req? |
|---|---|---|---|---|
| 1 | **`ds:report --module=<X> --json`** — comando que recorta o baseline `ds/*` POR módulo e emite artefato commitável. Hoje só existe o baseline global; sem recorte por módulo não há evidência objetiva de fechamento por tela/módulo. | **alto** (é a evidência nº1 — sem isso, "fechar migração DS" volta a ser opinião) | ~40-60 min (o ESLint+baseline já existem; é um wrapper de recorte + format JSON) | não — ratchet ADR 0209 já existe |
| 2 | **Anatomia da task git-native** (`prototipo-ui/tasks/T-NNN/` com TASK.md + events.ndjson + STATE.md + evidence/) + `tasks:index` + `tasks:project` (deriva STATE/INDEX dos eventos). | **alto** (é o casco que une o que está fragmentado; sem ele não há per-task) | ~2-3 h (scaffolding + 2 scripts de projeção; sem dep externa, é fs+git) | depende de definir o schema do evento (faz junto) |
| 3 | **Protocolo "sync now" formalizado** = §10.5 nova no `PROTOCOL.md` (hoje só v1.0, sem §10!) com as 5 perguntas + catálogo de evidência (b) + ciclo (e). | **alto** (transforma cultura implícita em gate explícito — é o "progress ledger" que falta) | ~1 h (é doc + ADR; o gate §10.4 já existe pra estender) | idealmente depois de #1/#2 estarem desenhados |
| 4 | **ADR proposto** (esboço na §3.D) — amenda 0070 + 0114, reusa 0130/0209/0084. | **médio** (governança; sem ele o resto vira ad-hoc não-canônico) | ~45 min | consolidar #1-#3 |
| 5 | **`tasks:report` no Code** (escreve evento `evidence` + artefato + linha SYNC_LOG, 1 comando) — fecha o lado Code do loop. | **médio** | ~1 h | depende de #2 (schema do evento) |
| 6 | Hook bloqueador `block-task-event-rewrite` (impede edit de `events.ndjson`/`SYNC_LOG.md` não-append) — só ativar se houver 1 incidente (mesmo critério P2 da 0130). | **baixo** (dormente) | ~30 min | reincidência |

### 3.C — RECOMENDAÇÃO CONCRETA

**Comece pelo Gap #1 — `ds:report --module=<X> --json`.** É alto-impacto-baixo-esforço, **zero pré-req bloqueante** (o ratchet ESLint da ADR 0209 e o baseline 639 já existem; é só recortar por módulo e serializar). E é o tijolo que destrava todo o resto: **sem evidência objetiva por-módulo, o ledger inteiro fecha por opinião** — exatamente o que o princípio-chave proíbe e o que "Gaming the Judge" prova ser explorável. Com #1 pronto, #2 (anatomia) e #3 (protocolo) ganham a âncora de evidência que os torna verificáveis em vez de aspiracionais.

> **Próxima ação hoje:** desenhar a interface de `ds:report --module=<X> --json` — saída exata `{ module, total, by_rule: {...}, target: 0, pass: bool, measured_against_sha }` lendo `config/eslint-baseline.json` + re-rodando ESLint só no glob `Pages/<X>/**`. **Validar o formato com 1 módulo real (Sells: alvo Create=6 + Edit=12) antes de generalizar** — é o número que o handoff 2026-05-29-2140 já mediu à mão, então dá pra conferir o output contra a verdade conhecida. Esse `pass: bool` + `measured_against_sha` É a evidência que fecha a tarefa no passo 2 do ciclo (e).

### 3.D — Esboço executável do ADR proposto

```markdown
---
slug: NNNN-task-ledger-git-native-cowork-code
title: "Task ledger git-native + handoff-por-tarefa + fechamento por evidência — loop Cowork↔Code"
type: adr
status: proposed
authority: canonical
amends: [0070, 0114]          # não supersede — estende (padrão 0130)
related: [0130, 0209, 0084, 0091, 0119]
module: Governance
---

# Contexto
- Loop Cowork↔Code (0114) precisa de task ledger bidirecional, MAS Cowork SÓ LÊ GIT
  (não MCP/MySQL CT100). MCP tasks (0070) é cego pro Cowork.
- Princípio-chave (W, 2026-05-30): tarefa fecha por EVIDÊNCIA OBJETIVA, nunca opinião —
  ambos agentes podem estar STALE. (Validado por "Gaming the Judge" arXiv 2601.14691:
  juiz que confia na narrativa do executor é gameável; defesa = exigir artefato observável.)
- oimpresso JÁ pratica isso (handoff 2026-05-29-2140: Code conferiu cola STALE do Cowork
  contra origin/main e recusou duplicar) — falta FORMALIZAR.

# Decisão
1. MCP tasks (0070) = source-of-truth do PROJETO (intocado). Ledger git-native = projeção
   operacional do loop em `prototipo-ui/tasks/T-NNN-<slug>/`. Ponte: campo mcp_ref +
   commit `Refs: <KEY>-NNNN` → webhook GitHub→MCP existente reconcilia (~2min).
2. Anatomia da task: TASK.md (DoD=evidência exata) · events.ndjson (append-only, herda
   0084/0130) · STATE.md (derivado) · evidence/ (artefatos). INDEX.md derivado = index_task_list.
3. Evidência objetiva (catálogo fechado): ds:report --module=X --json ⇒ ds/*=0 · Pest verde
   (run id) · screenshot==golden · Tier-0 GUARD biz→99 bloqueado. Narrativa NUNCA fecha.
4. Ciclo "sync now" = 5 perguntas (Magentic-One-style) respondidas contra evidence/, não
   contra narrativa. close/critique/spawn como eventos. Gate §10.4 (Code valida prompt
   Cowork vs origin/main) vira pré-passo obrigatório → nova §10.5 no PROTOCOL.md.
5. Handoff per-task (events.ndjson) coexiste com per-session (0130 intocada). Narrativa de
   sessão referencia tasks (Refs: T-NNN), não duplica.

# Consequências
+ Cowork e Code compartilham working memory legível por ambos (git), com árbitro objetivo.
+ Anti-stale by design (gate §10.4 formalizado) — supera A2A (que assume emissor confiável).
+ Event-sourced → audit/replay grátis (alinha Temporal/LangGraph + ADR 0084).
- prototipo-ui/tasks/ cresce → review_trigger >100 dirs (espelha 0130).
- Exige ds:report por módulo (Gap #1) como pré-req da evidência.

# Plano (IA-pair, ADR 0106)
F1 ds:report --module --json (~1h) · F2 anatomia + tasks:index/project (~3h) ·
F3 §10.5 PROTOCOL + 5 perguntas (~1h) · F4 tasks:report Code (~1h) · F5 este ADR (~45min).
Hook bloqueador = follow-up dormente (P2, critério 0130).
```

---

## Apêndice — Fontes (SOTA, Seção 1)

- Magentic-One (arXiv 2411.04468, nov/2024) — https://arxiv.org/html/2411.04468v1 · Microsoft Research — https://www.microsoft.com/en-us/research/articles/magentic-one-a-generalist-multi-agent-system-for-solving-complex-tasks/
- Reflexion (Shinn et al., NeurIPS 2023, arXiv 2303.11366) — https://arxiv.org/pdf/2303.11366
- "Gaming the Judge: Unfaithful CoT Can Undermine Agent Evaluation" (arXiv 2601.14691, jan/2026) — https://arxiv.org/pdf/2601.14691 · Survey Agent-as-a-Judge (arXiv 2508.02994) — https://arxiv.org/html/2508.02994v1
- OpenAI Swarm/Agents SDK handoffs — https://openai.github.io/openai-agents-python/handoffs/ · A2A (Linux Foundation) — https://a2a-protocol.org/latest/
- LangGraph durable execution — https://docs.langchain.com/oss/python/langgraph/durable-execution · "Checkpoints are not durable execution" (Diagrid) — https://www.diagrid.io/blog/checkpoints-are-not-durable-execution-why-langgraph-crewai-google-adk-and-others-fall-short-for-production-agent-workflows
- Temporal + AI agents (InfoQ, set/2025) — https://www.infoq.com/news/2025/09/temporal-aiagent/

## Apêndice — Fontes (oimpresso, Seção 2)

- ADR 0070 (`memory/decisions/0070-jira-style-task-management-current-md-removed.md`) — MCP tasks Jira-style, mcp_git_links, mcp_task_events append-only, webhook GitHub→MCP
- ADR 0130 (`memory/decisions/0130-handoff-append-only-mcp-first.md`) — handoff append-only per-session, padrão dual SYNC_LOG↔HANDOFF (§49), checklist MCP-first
- ADR 0209 (`memory/decisions/0209-eslint-9-flat-config.md`) — ESLint ratchet, eslint-gate.yml, falha só em regressão
- `prototipo-ui/PROTOCOL.md` (v1.0 — SEM §10 ainda; §10 do brief = artefatos vivos abaixo) · `prototipo-ui/SYNC_LOG.md` (event log append-only) · `prototipo-ui/DS_ADOCAO_INDICE.md` (baseline 639 `ds/*`, dimensão Adoção DS)
- Handoffs adversariais (prova da cultura de evidência): `memory/handoffs/2026-05-29-2140-ds-guard-pr-b-verify-drift-numeros.md` (Code conferiu cola STALE vs origin/main, recusou duplicar) · `memory/handoffs/2026-05-29-1157-revisao-adversarial-8-findings-fix-deploy.md` (8 findings, cada fix com teste + verificação live)
- **Gap confirmado:** `scripts/ds-report.mjs` / `npm run ds:report:write` NÃO existem (só `scripts/eslint-baseline.mjs` + `scripts/categorize_violations.py`); placar por-módulo é aspiracional hoje.
