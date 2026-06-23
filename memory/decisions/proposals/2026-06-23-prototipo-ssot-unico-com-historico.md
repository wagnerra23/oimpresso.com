---
title: Protótipo Cowork = UMA fonte da verdade com histórico (fim dos dirs espalhados)
status: proposed
date: 2026-06-23
deciders: [Wagner]
proposers: [Claude Code]
supersedes: []
related_adrs: [0114-prototipo-ui-cowork-loop-formalizado, 0282-protocolo-v2-cowork-intake, 0061-conhecimento-canonico-git-mcp-zero-automem]
tags: [prototipo-ui, cowork, design-loop, governanca, ssot]
---

# Protótipo Cowork = UMA fonte da verdade com histórico

> **Status: PROPOSTA** — aguarda aprovação + número canônico de [W]. Claude Code não cunha número de ADR (regra do projeto).

## Contexto (o que está quebrado)

O protótipo é desenhado no **Cowork (Claude.ai)** e **exportado periodicamente** pro repo (`prototipo-ui/README.md`). Hoje cada export é um **zip flat** (1 export real = `oimpresso-erp-conunica-o-visual/project/`, 1182 arquivos: ~190 fontes na raiz + screenshots + `_arquivo/`). Conceitualmente o `project/` do Cowork **É** o `prototipo-ui/` do repo — mesma coisa.

O problema (confirmado na Fase 0 de `aplicar-prototipo`, 2026-06-23):

1. **Dirs espalhados e fatiados.** `prototipo-ui/prototipos/<tela>/` tem 10 dirs hand-sliced de exports antigos (`caixa-unificada`, `clientes`, `compras`, `compras-grade-matrix`, `crm`, `pageheader-canon-v3`, `producao-oficina`, `sidebar-icons-comparison`, `sidebar-v3-unificado`, `vendas`). São recortes que **driftam** do export real.
2. **Sem linhagem de diff.** O método canônico do RUNBOOK (`git log` do dir → diff sha→HEAD) **não rende**: o path inteiro aparece como **1 commit só** (export ad-hoc, sem overwrite versionado). "O que mudou no protótipo" fica indeterminável por git.
3. **Variantes conflitantes dentro do mesmo dir.** Ex.: `pageheader-canon-v3/` tem 4 HTMLs com 3 tratamentos de primary diferentes (só `b-v2-roxo-kpis.html` é o canon; `index.html`/`3-familias.html` regridem).
4. **Fonte stale.** Telas vivas já **passaram** o protótipo (Caixa V4 charter v19 e Cliente US-CRM-063..076 estão à frente do export) — mas o protótipo continua sendo tratado como "fonte", causando proposta de regressão.

Resumo do Wagner (2026-06-23): _"não pode ter vários protótipos, pode ter 1 só com histórico. senão vira bagunça. apagar os antigos e manter 1 só como fonte da verdade."_ — está certo.

## Decisão

### 1. UMA fonte da verdade, com histórico via git
O export do Cowork (`project/`) **sobrescreve por inteiro** UM único dir do repo a cada handoff, com **commit dedicado**. O **histórico do git passa a ser a linhagem do diff**: `git diff <export-anterior>..<export-atual>` = exatamente o que o design mudou. Acabam os recortes `prototipos/<tela>/`.

- Dir canônico (a confirmar [W] — ver Forks): **`prototipo-ui/cowork/`** = espelho 1:1 do `project/` (camada de design). Mantém os docs de processo repo-canônicos onde estão (`PROTOCOL.md`, `RUNBOOK-aplicar-prototipo-orquestracao.md`, skill `aplicar-prototipo`).
- O clone local atual é **shallow** (`.git/shallow`), então o diff local não enxerga histórico — mas a **linhagem vive no repo remoto**. Para diff local: `git fetch --unshallow`. O mecanismo (overwrite + commit) é o que **cria** a linhagem daqui pra frente.

### 2. Landing ESCOPADO (a pegadinha que evita corromper o canon)
O export traz **cópias stale** de coisas que são canônicas no repo: `memory/` (96 arquivos), charters, ADRs, às vezes espelho de `resources/`. Pelo PORTÃO 1 do próprio Cowork (`STATUS.md`): _"arquivo LOCAL do Cowork ≠ git, SEMPRE; é fotocópia que envelhece."_

Regra de direção da verdade:
- **Camada de design** (`*-page.jsx/.tsx`, `*.css`, `*.html`, `*.charter.md`, `*.casos.md`, docs de design) → **Cowork → repo** (o export manda).
- **Tudo o mais** (`memory/decisions/**`, `memory/requisitos/**`, código em `resources/`, ADRs, lint/tooling) → **repo → Cowork** (o design **lê**, nunca sobrescreve via export).

**Memória = fonte ÚNICA é o canon do repo (Wagner 2026-06-23)** — sincronizado pro **MCP** (webhook GitHub→push→merge `main`). O Cowork **não mantém memória paralela**: ele **lê a SUA** (via MCP / snapshot). Por isso `project/memory/` **NÃO** vira `cowork/memory/`. **Memória é DESTILADA, não despejada:** 1 assunto = 1 doc ancorado (charter/SPEC/ADR/reference), só o **resumo** + histórico de evolução. O que é novo numa sessão → **destila no charter/SPEC daquela tela** (evolui o doc do assunto, anti-duplicação). A **sessão/conversa crua NÃO entra no canon** (vira ruído + reprova o gate `Session log`) — fica no arquivo do Cowork. O que já é canon = redundante, descarta. `resources/` e cópias de ADR do export = ignorados (o repo manda). Rastreio idempotente em [`prototipo-ui/RECONCILIACAO-COWORK-MEMORIA.md`](../../../prototipo-ui/RECONCILIACAO-COWORK-MEMORIA.md) (livro-razão mantido).

### 3. Apagar os antigos (reversível por git)
`prototipo-ui/prototipos/*` (10 dirs) são **subsumidos** pelo export completo (que tem todo `*-page.jsx`) → **deletados**. Reversível pelo histórico. Antes de deletar: salvar intel única que não esteja no export (ex.: `compras-grade-matrix/NOTES.md`, `pageheader-canon-v3/SPEC.md`) pra `prototipo-ui/cowork/_intel/` ou pra SPEC do módulo.

### 4. Canal reverso code→design ("o que o design está atrasado")
Como o diff agora funciona e o repo é SSOT, o design precisa saber **onde a produção passou o protótipo** (pra parar de usar export stale como fonte) e **onde a produção está atrás** (catch-up real). Mecanismo: **UM** relatório de frescor mantido pelo Code — `prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md` (semente criada 2026-06-23) — lido no início de cada sessão de design (entra na ordem de leitura do `README.md` handoff-entry, junto de `CODE_NOTES.md`). NÃO criar N docs novos (é o anti-padrão que esta ADR combate).

### 5. Processo de handoff (atualiza skill `aplicar-prototipo` Fase 0)
1. Extrair o zip.
2. Overwrite `prototipo-ui/cowork/` com a camada de design do `project/` (filtro do item 2).
3. **Destilar memória NOVA** por-tela/assunto no charter/SPEC/ADR ancorado (resumo + evolução, anti-duplicação) — NÃO despejar sessão crua no canon. Registrar no livro-razão `RECONCILIACAO-COWORK-MEMORIA.md`.
4. `git add` + commit + **push → PR → merge `main`** → webhook **GitHub→MCP** ingere a memória (é assim que "entra no fluxo do MCP").
5. `git diff HEAD~1 -- prototipo-ui/cowork/` = **o que mudou** → roda Fase 1 (mapa) **só nas telas que o diff tocou**.
6. Atualiza `FRESCOR-PRODUCAO-vs-PROTOTIPO.md` + `CODE_NOTES.md`.

## Consequências

**Positivas:** diff de design vira determinístico; fim da bagunça de N recortes; impossível "fonte stale" sobreviver (frescor explícito); economia de token (mapeia só o que mudou).

**Negativas / riscos:** (a) overwrite total exige o filtro do item 2 — se vazar `memory/` do export, corrompe canon (mitigado por filtro + .gitignore); (b) tensão com anti-entropia (NÚCLEO 12 "não deletar, marcar superseded") — mitigado: git history preserva, e esta ADR registra o que saiu; (c) imagens (560 PNGs/export) incham o repo se versionadas — ver Fork C.

## Decisões de [W] (2026-06-23)
- **A — dir canônico:** `prototipo-ui/cowork/` (isolado). ✅
- **B — deleção:** apagar os recortes agora — **com ressalva (adversário):** recortes SEM equivalente no export (`inventario-migracao`, `compras-grade-matrix`) são RESTAURADOS; os demais re-apontam as âncoras de charter pro `cowork/`. ✅
- **C — imagens:** versionar só fontes (leve). ✅
- **D — frescor:** arquivo dedicado `FRESCOR-PRODUCAO-vs-PROTOTIPO.md`. ✅
- **Memória:** absorção CRUA revertida → **destilar, não despejar** (1 assunto ancorado + resumo + evolução). ✅

## Método operacional endurecido (pós-adversário 2026-06-23)
Red-team da integração de memória pegou furos que viraram regra — não repetir:
1. **Memória: destilar, não despejar.** Sessão crua do export NÃO entra no canon (reprova gate `Session log` + duplica). Destila o resumo por-tela no charter/SPEC ancorado. Não há gate que force a destilação → é **débito consciente registrado no livro-razão**, não promessa.
2. **Deletar recorte = re-apontar âncora (mesma PR).** `prototipo-ui/prototipos/` é alvo de ~40 ponteiros (`blueprint_cowork`/`visual_source`/`related_prototipo`) de charters vivos. Rodar `node scripts/governance/charter-blueprint-pointers.mjs` antes/depois e re-apontar pro equivalente em `cowork/`. Tela SEM equivalente no export (ex. `inventario-migracao`) → NÃO mapear pra arquivo errado: **restaurar/manter o recorte**.
3. **`cowork/` = BUILD-ONLY (jsx/tsx/css/html/json), ZERO `.md`.** Charters/casos/memória/process-docs (CLAUDE/STATUS/PROTOCOL/CODE_NOTES…) são canon (`resources/js/Pages/**`, `memory/**`, `prototipo-ui/` root) → duplicar no cowork/ = 2ª fonte que drifta (PORTÃO 1). **Todos os `.md` removidos do `cowork/`** + o filtro de landing exclui `.md`. **Bundles datados antigos** (`prototipo-ui/cowork-2026-05-26-*`) **deletados** — eram cópias completas do workspace = dupla fonte. Regra futura simples e checável: _nenhum `.md` em `prototipo-ui/cowork/`_.
4. **PII esteira.** `cowork/` é mock por contrato (CPF/CNPJ fake) → excluído do PII scan (como `cowork-**`). Produção segue scaneada. Risco residual aceito: PII REAL no export passa batido — contrato "cowork = fake".
5. **Gates a checar no handoff:** `charter-blueprint-pointers` (âncoras) · `Session log` (sem despejo) · `PII scan` (esteira ok) · `UI architecture`/`CharterVisualSourceGate` (visual_source vivo).
6. **Contrato do design:** o Cowork lê [`prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md`](../../../prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md) (estrutura + como buscar info + telas a desenvolver).

## G4 — gatilhos separados + loop de aprendizado por tela (2026-06-23)
**Dois gatilhos, NÃO conflar** (Wagner: "por que essa decisão?"):
- **Gatilho 1 — Handoff (paste/diff):** SÓ por **MUDANÇA**. `scripts/governance/detect-handoff.mjs` diffa o `cowork/`; tela mudada → 1 chip (`spawn_task`); **0 mudanças → 0 chips**. Proveniência: todo chip rastreia uma mudança real do export.
- **Gatilho 2 — Backlog (sob demanda):** por **GAP em aberto** (FRESCOR 🟠). Fluxo **SEPARADO** ("groom o backlog → chips"), NÃO acoplado ao paste. Gap costuma ser backend/feature, não "aplicar o design que chegou".
> Conflar os dois (criar chip de backlog num paste sem mudança) confunde proveniência + mistura tipos de trabalho. O `detect-handoff` é **by-change-only**; FRESCOR nele é só **classificação** das telas mudadas (🔵 produção-à-frente = skip), não gatilho.

**Loop de aprendizado por tela (memória que CRESCE):**
- **History:** o `<Tela>.charter.md` tem a seção append-only "Decisões da tela" = o histórico autoritativo. `charter-first` obriga ler antes de editar; `seed-tela.mjs` aponta como 1º a ler.
- **Recall (RAG):** ao re-tratar, o `seed-tela` manda rodar `decisions-search`/`memoria-search` do domínio → enriquece com decisões/lições de telas **PARECIDAS** (cross-screen) que não estão no charter.
- **Distill-back:** ao aprovar o screenshot, a sessão faz **append** da decisão nova na seção "Decisões da tela" do charter, ANTES do PR. Sem isso a memória é só re-lida, nunca acumulada. _Elo que fecha o loop — hoje o ponto fraco (débito de destilação)._

## Refs
- ADR 0114 (loop Cowork formalizado) · ADR 0282 (protocolo v2 Cowork intake) · ADR 0061 (canon no git, zero auto-mem)
- Skill `aplicar-prototipo` + `prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md`
- Fase 0/1 de 2026-06-23: `memory/requisitos/{Atendimento,Crm,Compras,_DesignSystem}/*-gap.md`
