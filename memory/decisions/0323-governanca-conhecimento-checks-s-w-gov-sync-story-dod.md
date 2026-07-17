---
slug: 0323-governanca-conhecimento-checks-s-w-gov-sync-story-dod
number: 323
title: "Governança de conhecimento em máquina — Checks S–W do memory-health + gov-sync (proponente) + convenção sentinela⇄Story-DoD + registro de letras"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-04"
accepted_via: "Wagner 2026-07-04/05 no chat: aprovou cada máquina em sequência ('Sim aprovo e gostei do domínio' → fact-anchor; 'Isso mesmo ok' → desenho Jira-nativo sentinela⇄Story-DoD; 'Feche todos' → gaps backlog/changelog/ciclos; 'ok pode fazer' → esta formalização em ADR + teste do Check W + gov-sync advisory)."
module: governance
tags: [governanca, conhecimento, anti-drift, memory-health, sentinela, fact-anchor, backlog, story-dod, jira-style]
supersedes: []
superseded_by: []
related:
  - 0070-jira-style-task-management-current-md-removed
  - 0213-audit-creates-tasks-loop-fechado
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0258-processo-adr-estado-arte-indice-gerado-supersede-atomico
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0316-esquecimento-real-adr-morta-tombstone-git-auditoria
  - 0317-maquina-revisao-adr-quando-rever-gatilhos
pii: false
---

# ADR 0323 — Governança de conhecimento em máquina (Checks S–W + gov-sync + Story-DoD)

## Contexto

Sessão 2026-07-04 ("quero um guia do sistema") virou auditoria de drift da base de conhecimento: a camada de entrada humana (README, índices, ARCHITECTURE) tinha **fatos frescos-mas-errados** (React 18 vs 19 real; `Modules/MemCofre` vs SRS; "95+ ADRs" vs ~230; `Modules/Project/` fantasma citado como referência canônica de imitação), **47 links internos mortos**, **90 drafts de ADR em limbo**, dirs homônimos (`dominio/`÷`dominios/`) e um backup morto commitado (`memory_backup/`, 32 arquivos).

Comparação com estado-da-arte 2026 ([sessão-arte](../sessions/2026-07-04-arte-governanca-conhecimento-fato-vs-frescor.md)): frescor-por-idade já era coberto (Check D + ADR 0256/0317), mas **correção-do-fato** e **limbo** não tinham máquina. O SOTA (Dosu: *"detecta drift, não correção"*) não tem solução geral barata — porém o **subconjunto ancorável** (versão↔`package.json`, módulo↔árvore `Modules/`, link↔alvo existe) é 100% determinístico, sem LLM.

Risco adicional materializado na mesma janela: **sessões paralelas** adicionando checks ao mesmo `memory-health.mjs` sem coordenação de namespace (Check X `audit-coverage-gap` nasceu em paralelo ao W desta sessão — sem colidir **por sorte**).

## Decisão

### D1 · Cinco sentinelas novas no `memory-health.mjs` (advisory, warn-only)

| Check | kind | O que ancora |
|---|---|---|
| **S** | `entrada-stale` | doc da camada de ENTRADA (README, GUIA, ARCHITECTURE, índices, why/what/how, CLAUDE.md) sem revisão > 6 meses (marcador in-doc ou `gitLastDate`) |
| **T** | `fato-ancora-drift` | **fact-anchor determinístico**: versão (React/Laravel) ou `Modules/<X>` em doc **current-state** contradizendo `package.json`/`composer.json`/árvore. Escopado a `CURRENT_STATE_DOCS` (docs com seção histórica — ARCHITECTURE, INDEX — ficam FORA: menção a nome antigo lá é legítima; calibrado 18→1 na 1ª rodada). Guarda de migração "X → Y" |
| **U** | `proposta-em-limbo` + `dir-homonimo` | pile de `decisions/proposals/` por **contagem** (idade por git-date é mascarada por squash-restore — lição #2413) + dirs homônimos sob `memory/` |
| **V** | `link-quebrado` | links internos `](...)` na canon front-facing com alvo inexistente (zero-FP por construção; exclui sessions/handoffs/decisions — link morto lá é registro de época) |
| **W** | `backlog-index-stale` | `_BACKLOG-GENERATED.md` (índice gerado das US, ADR 0070) mais antigo que o SPEC mais novo — o índice não drifta calado |

Todos **advisory** (ADR 0314: required = só Tier-0; frescor/higiene de doc não é) e com **teste físico** sensibilidade+especificidade em `tests/memoryHealth.spec.ts` (ADR 0258 — check visto FALHAR e PASSAR antes de valer).

### D2 · `governance-backlog-sync.mjs` = o PROPONENTE (nunca o dono)

Script que roda o memory-health `--json`, filtra os kinds **acionáveis-como-1-task** (curadoria explícita: S/T/U/V — exclui de propósito os de alto volume B/J/K/O/R, onde 1-task-por-achado seria spam/teatro) e **imprime** a proposta de standing-Stories no formato do hook `audit-creates-tasks` (ADR 0213). **Não cria task** — `tasks-create` permanece humano-gated (publication-policy). Roda como **step advisory** no workflow `memory-health.yml` (cron diário + PR), `continue-on-error`.

### D3 · Convenção sentinela⇄Story-DoD (Jira-nativo)

Trabalho de higiene de conhecimento exposto por sentinela vira **standing-Story** no backlog MCP (módulo `Governance`), com **Definition-of-Done verificável = o count daquele kind no memory-health = 0**. A Story fecha ⟺ a sentinela zera — "achei que terminei" não conta. O **count vive na sentinela** (vivo), nunca no corpo da task (apodreceria). 1 Story por kind, idempotente pelo título estável + marcador `<!-- gov-sync: <kind> -->`.

Primeiras instâncias: **US-GOV-046** (triar proposals), **US-GOV-047** (consertar links), **US-GOV-048** (desambiguar dominio/dominios).

### D4 · Registro de letras de check (namespace anti-colisão)

Letra de check do memory-health é **namespace compartilhado entre sessões paralelas**. Estado em 2026-07-05: **A–X ocupadas** (sem P e Q — livres; depois Y, Z; esgotando, usar `A2/B2...`). Regra: **antes de criar check novo, conferir as letras em uso no próprio `.mjs` (`grep "check: '"`) e citar esta ADR no comentário do check**. Colisão de letra entre PRs paralelos = mesmo bug da colisão de nº de ADR (Check A existe por isso).

## Não-goals (avaliados e descartados — não re-propor sem nova ADR)

- **Detector de contradição N² geral (LLM)** — precisão 95% mas recall ~57% (arXiv 2504.00180); como gate seria teatro por construção. Se um dia entrar: só advisory de pares ancorados, `promote_by` obrigatório.
- **Memória auto-consolidante runtime** (estilo MemGPT/Letta) — git-com-supersede + destilação (ADR 0270) já é isso, versionado e auditável.
- **Auto-criação de tasks pelo CI** — viola publication-policy e convida spam; o humano confirma 1×.
- **Bulk-delete de docs banner-quarentenados** — red-team 2026-07-04 rejeitou (quebraria links em ADR append-only por ganho marginal); esquecimento real (0316) só para contradição ativa ou lixo sem inbound.

## Consequências

- Drift de conhecimento deixou de ser invisível: **acha (sentinela) → propõe (gov-sync) → rastreia (Story) → verifica (DoD=count 0)**, tudo em máquina, humano decide.
- Advisory por design: nenhum destes checks bloqueia merge; promoção a required exige reabrir via ADR (0314/0275).
- Fica explícito no canon: CHANGELOG.md manual está **congelado** (o vivo = git log + índices gerados + shipped-logs) e o projeto roda **off-cycle intencional** desde CYCLE-08 (reativar = `cycles-create`).

## Evidência (R1)

PRs #3795–#3813 (16, todos merged, CI verde); memory-health no main pós-merge: **0 fail · 0 check-error**; Check T pegou e corrigiu bug real (`Modules/Project`→`ProjectMgmt`); Check V guiou conserto de 32 links (47→15); Check W pegou índice 2 semanas stale (regen 938 US). Verificação adversarial da sessão: 6 ataques, 0 defeitos sobreviventes.
