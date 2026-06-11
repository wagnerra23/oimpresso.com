---
slug: 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
number: 270
title: "Ciclo de vida da informação — porta única + destilação + decaimento + medir o caminho de leitura (anti-elefante-branco)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-11"
accepted_at: "2026-06-11"
accepted_via: "Wagner ratificou no chat 2026-06-11: 'Adr aceita' — após aprovar a direção ('Acho que vou fazer todas faz muito sentido. É exatamente o que eu sinto. Faça') em resposta ao diagnóstico 'parece um elefante branco — ter mais não tá fácil de usar'. Numeração/redação por [CL]."
module: copiloto
quarter: 2026-Q2
tags: [conhecimento, memoria, ciclo-de-vida, destilacao, decaimento, porta-unica, recall, time-decay, governanca, anti-elefante-branco]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0061-conhecimento-canonico-git-mcp-zero-automem", "0091-daily-brief", "0130-handoff-append-only-mcp-first", "0131-tiering-memoria-canonico-local-segredo", "0233-ativacao-memoria-momento-decisao", "0258-processo-adr-estado-arte-indice-gerado-supersede-atomico", "0070-jira-style-task-management-current-md-removed", "0094-constituicao-v2-7-camadas-8-principios"]
pii: false
---

# ADR 0270 — Ciclo de vida da informação: porta única, destilação, decaimento, e medir o caminho de leitura

> **Princípio 1 da Constituição v2** ([ADR 0094]) é *"Context as a product"*. Esta ADR fecha a metade que faltava desse princípio: o conhecimento foi tratado como **produto de escrita** (capturar tudo, guardar pra sempre) e não como **produto de leitura** (achar a verdade atual em 1 pulo). Resultado sentido pelo dono: *"parece um elefante branco — ter mais não tá fácil de usar."*

## Contexto

### O sintoma (palavras de Wagner, 2026-06-11)

> *"Como deve ser o ciclo de vida das informação e como se faz? Por que ter mais não tá fácil de usar. Parece um elefante branco."*

### A causa-raiz: sistema otimizado pra ESCREVER, não pra LER

O oimpresso construiu uma máquina de **captura** excelente — e ela cumpre seu papel de segurança/auditoria (multi-tenant Tier 0, cálculo de valor, PII, drift): "mexeu registra" ([proibicoes]), handoff append-only toda sessão ([ADR 0130]), BRIEFING todo PR (skill `brief-update`), session log todo dia, ADR append-only, audit vira doc, 64 gates de CI.

Mas o **valor** do conhecimento não está em *ter* — está em **achar a verdade atual em 1 pulo**. E aí a máquina é fraca, pelos mesmos motivos catalogados na auditoria da IA (recall degrada em silêncio, sem time-decay, ADR revogada volta como vigente — ver §"TOP gaps" do diagnóstico Jana 2026-06-11).

### Evidência medida (auditoria do caminho de leitura, 2026-06-11)

Volume bruto do cofre:

| Artefato | Quantidade |
|---|---|
| `.md` total em `memory/` | **2.438** |
| ADRs (`decisions/`) | 280 (13 colisões de número; um renumber **bloqueado pelo próprio gate append-only**) |
| Sessions (diário) | 262 — crescimento 40 (abr) → **192 (mai)** → 26 (jun parcial) |
| Handoffs (estado) | 101 |
| Docs em `requisitos/` | 821, em 58 módulos |
| Skills | 69 · CI workflows | 64 |

Caminho de leitura por módulo (a "porta da frente"):

- **22 dos 58 módulos (38%) NÃO têm porta** (sem `BRIEFING.md`) — pra saber o estado atual, lê-se N docs soltos. Piores: **MemCofre 33 docs / ZERO porta**, **Inventory 29 / 0**, LaravelAI 15/0, PontoWr2 13/0, ComunicacaoVisual 10/0, EvolutionAgent 10/0.
- **Docs que competem pelo título de "verdade"** do módulo: 38 BRIEFING + 57 SPEC + 10 CAPTERRA-FICHA + 5 INVENTARIO + 17 AUDIT + 140 RUNBOOK. Vários por módulo → a pergunta *"qual deles é o atual?"* não tem resposta mecânica.
- A própria "data de modificação" do BRIEFING **não é confiável** como sinal de frescor (toque em lote no filesystem) — ou seja, **não existe sinal disciplinado de "este é O doc atual"**.

### O modelo mental: diário ≠ manual

Escreveu-se um **diário** gigante (sessions + handoffs, append-only) e tenta-se usá-lo como **manual**. Diário você não relê; manual você mantém atualizado. Faltou a camada que transforma **diário → manual**. Quando essa camada falta, **mais documento = menos sinal por ruído**, e o recall (humano ou da IA) piora à medida que o cofre cresce. Isso é o elefante branco.

### O que já existe (peças, não o todo)

Esta ADR **não cria sistema novo** — costura peças existentes num ciclo coerente e decide a metade que falta:

| Estágio | Peça que já existe | Estado |
|---|---|---|
| Capturar | "mexeu registra", handoff ([0130]), session log, audit | ✅ saturado |
| Servir (porta) | Daily Brief / `brief-fetch` ([0091]); `BRIEFING.md` por módulo (template em `_DesignSystem/`) | 🟡 sem disciplina de porta única |
| Destilar | `ProfileDistiller` (só chat → fatos), skill `brief-update` | 🟡 parcial / quebra em silêncio |
| Decair | `_INDEX-LIFECYCLE.md` (ativa/histórica), §5 "ideias descartadas" | 🟡 sem time-decay no recall |
| Tiering | [ADR 0131] canônico/local/segredo | ✅ |
| Arquivar | append-only + lápide | ✅ (forte demais — aplicado também a conhecimento que devia morrer) |

## Decisões

### D-1 — Informação é TIPADA; nem tudo é append-only

Quatro tipos, cada um com vida própria. O erro foi tratar tudo como o tipo 1.

| Tipo | Exemplo | Regra de vida |
|---|---|---|
| **Lei/auditoria** | marcação ponto, baixa financeira, NFe, `mcp_audit_log` | imortal — append-only **de verdade** (Portaria 671, LGPD) |
| **Decisão** | ADR | append-only **com status de lifecycle**; decai de *relevância*, não some |
| **Verdade atual** | "estado de hoje do módulo X" | **mutável, única, destilada** — reescrita é o caminho normal |
| **Evento/log** | session, handoff, conversa | **decai e arquiva** após destilado; não é caminho quente |

Consequência dura: aplicar append-only a **conhecimento** (tipo "verdade atual") é **proibido** — esquecer/reescrever é feature, não perda.

### D-2 — Porta da frente ÚNICA por assunto

Pra **agir**, lê-se **um** doc: a verdade atual destilada e mutável. Todo o resto é **proveniência** (pesquisável, fora do caminho quente).

- **Global:** o `brief-fetch` / Daily Brief ([0091]) é a porta global. Continua sendo a primeira chamada de toda sessão.
- **Por módulo:** `memory/requisitos/<Mod>/BRIEFING.md` é **A** porta — e é a *verdade já mastigada* (≤1 página), **não** um índice de links nem um acúmulo. SPEC/CAPTERRA/AUDIT/RUNBOOK são **proveniência**, linkados a partir da porta, nunca concorrentes dela.
- **Toda Eloquent Model de negócio tem `business_id`; todo módulo tem BRIEFING.** A simetria é proposital — é tão básico quanto o scope multi-tenant.

### D-3 — Destilar numa cadência (diário → manual)

Um ritual/job que lê os **eventos novos** (sessions, handoffs, audits, PRs mergeados) e **atualiza a verdade atual; depois esfria o cru**.

- Estende o conceito do `ProfileDistiller` de "fatos do chat" → "verdade atual do módulo".
- Gatilho mínimo já existe (skill `brief-update` Tier B ao fechar feature) — esta ADR o torna **obrigatório e auditável**, não best-effort.
- **Destilação que para = incidente** (ver D-5): se o distiller silenciar, a porta envelhece e a IA responde sobre dado velho (classe de erro L-OP-002).

### D-4 — Decaimento e lápide (esquecer é feature)

Conhecimento que morreu ganha **status** e **pesa menos no recall**:

- ADR/RUNBOOK/doc revogado → `lifecycle: historical|arquivado` + **time-decay no recall** (o morto não volta no top-K com o mesmo peso do vigente). Fecha o gap factual nº 10 do diagnóstico Jana.
- Conhecimento (não-lei) que deixou de valer → **lápide** explícita (1 linha: "substituído por X em <data>") + sai do caminho quente. Diferente de dado fiscal/auditoria, que nunca sai.
- Sessions/handoffs **arquivam** após destilados (mantêm proveniência fria; saem do índice quente).

### D-5 — Medir o caminho de LEITURA, não o de escrita

O KPI do sistema de conhecimento **deixa de ser** "quantos docs capturei" e **passa a ser** "consigo achar a verdade atual em 1 pulo?".

- **Métrica `front_door_coverage`**: % de módulos com BRIEFING fresco e único. Hoje = **62%** (36/58). Meta: 100%.
- **Métrica `read_path_hops`**: nº de docs que se abre pra saber o estado atual de um módulo. Meta: **1**.
- **Métrica `distiller_freshness`**: dias desde a última destilação por porta. Alarme se > 7d.
- Esses checks entram no `jana:health-check` como **DUROS** (derrubam o cron), espelhando o tratamento de `profile_distiller_drift` que já existe.

### D-6 — Parar de crescer o que ninguém lê

- Antes de criar doc novo em `memory/`, a regra `Glob`/`Grep` ([proibicoes]) continua — mas agora com um teste positivo: *"isto atualiza uma porta existente ou abre paralelo?"* Paralelo = barrado (§5 código + design).
- Captura continua (segurança/auditoria), mas o **default do conhecimento é destilar para a porta**, não acumular um doc a mais.

## Consequências

**Positivas:**
- A pergunta "qual o estado atual de X?" passa a ter resposta de **1 pulo** (a porta), para humano e para a Jana (melhor recall → menos alucinação sobre dado velho).
- O cofre para de crescer em ruído; cresce em **sinal destilado**.
- "Deixar a IA focada" (pergunta anterior do Wagner) ganha base: recall com time-decay + porta única = contexto limpo pro LLM.

**Custos / riscos:**
- Exige **disciplina de reescrita** (mutar a porta) contra o instinto append-only enraizado. Mitigação: D-5 mede e o health-check cobra.
- Time-decay mal calibrado pode **esconder** conhecimento ainda válido. Mitigação: decay afeta *ranking*, nunca *existência*; tudo continua pesquisável.
- As mudanças de **código** (distiller estendido, time-decay no recall, métricas no health-check) tocam comportamento de IA em prod → **exigem validação no CT 100** (Tier 0 — teste nunca local/cego). Por isso entram como **roadmap faseado**, não neste PR.

## Roadmap de implementação (faseado — código valida no CT 100)

Esta ADR é a **decisão**. A execução vira tasks no MCP, cada uma com smoke real:

| Fase | Entrega | Onde | Bloqueio |
|---|---|---|---|
| **F1 — Porta** | Criar BRIEFING nos 22 módulos órfãos (começar pelos piores: MemCofre, Inventory, ComunicacaoVisual) | docs (seguro agora) | nenhum |
| **F2 — Medir** | 3 checks no `jana:health-check` (`front_door_coverage`, `read_path_hops`, `distiller_freshness`) | `Modules/Jana/Console/Commands/HealthCheckCommand.php` | CT 100 |
| **F3 — Destilar** | Estender `ProfileDistiller` → verdade-do-módulo; tornar `brief-update` obrigatório/auditável | `Modules/Jana/Services/` | CT 100 |
| **F4 — Decair** | Time-decay no recall (peso por `lifecycle` + idade) | `Modules/Jana/Services/Retrieval/` (`RrfReranker` et al.) | CT 100 |
| **F5 — Arquivar** | Job que esfria sessions/handoffs destilados (índice quente só recente) | `Modules/Jana/` | CT 100 |

## Métricas de sucesso

- `front_door_coverage` 62% → 100% (F1)
- `read_path_hops` mediano → 1 (F1+F3)
- Recall: ADR `historical` **não** aparece no top-3 de uma query sobre tema vigente (F4)
- `distiller_freshness` < 7d em 100% das portas (F3, monitorado por F2)

## Referências

- [ADR 0094] Constituição v2 — Princípio 1 "Context as a product", Princípio 4 "loop fechado por métrica"
- [ADR 0091] Daily Brief (a porta global) · [ADR 0130] Handoff append-only · [ADR 0131] Tiering memória
- [ADR 0061] Zero auto-mem (conhecimento canônico = git/MCP) · [ADR 0233] Ativação no momento da decisão · [ADR 0258] Índice gerado + supersede atômico
- [proibicoes.md] regra "Mexeu, REGISTRA" + "não duplicar em memory/" + §5 "ideias descartadas"
- Diagnóstico Jana 2026-06-11 (esta sessão): gaps de qualidade não-medida + degradação silenciosa
- `_INDEX-LIFECYCLE.md` (status de lifecycle das ADRs — onde o decaimento de decisão já vive)

---

**Origem:** sessão 2026-06-11. Wagner perguntou "o que a IA ainda não faz bem / como deixá-la focada" → diagnóstico → "como deve ser o ciclo de vida da informação? parece um elefante branco" → auditoria do caminho de leitura → esta ADR. Aprovação de direção no chat; ratificação formal = merge (sair de draft).
