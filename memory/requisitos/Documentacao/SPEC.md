---
module: Documentacao
version: "1.0"
status: ativo
owners: [W]
last_updated: "2026-08-06"
us_count: 2
us_list: [US-DOC-001, US-DOC-002]
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0070-jira-style-task-management-current-md-removed
anchor_format: v1
---

# SPEC — Documentacao (superfície `/documentacao`)

> **Escopo.** A superfície de leitura da documentação do sistema: o Guia, o acervo, a busca e a
> tela do programa de documentação. Migração Blade→Inertia decidida por [W] em 2026-08-06.
>
> **Fronteira.** Este SPEC cobre a **camada de render e as telas**. O programa de documentação em
> si (ondas, ciclo, DoD) é da [Trilha D do plano mestre](../_Governanca/programa-ondas/PLANO-MESTRE.md),
> rastreada por `US-INFRA-048` — não é duplicada aqui.
>
> **Contrato de paridade:** [ANTI-REGRESSAO-documentacao-blade.md](ANTI-REGRESSAO-documentacao-blade.md) ·
> **Operação:** [RUNBOOK-documentacao.md](RUNBOOK-documentacao.md)

## User stories

| US | Título | Status | Onde está |
|---|---|---|---|
| `US-DOC-001` | Migrar a superfície `/documentacao` de Blade para Inertia/React | `doing` — F1 feita, F2/F3 não | [↓](#us-doc-001--migrar-a-superfície-documentacao-de-blade-para-inertiareact) |
| `US-DOC-002` | Tela do Programa (Trilha D) cruzando plano em git e tasks MCP | `todo` | [↓](#us-doc-002--tela-do-programa-trilha-d-cruzando-plano-em-git-e-tasks-mcp) |

> A tabela é índice, não fonte: o corpo de cada US abaixo é que carrega `**Implementado em:**`,
> owner, priority e DoD. Status aqui repete o do corpo — se divergirem, o corpo vence.

## US-DOC-001 · Migrar a superfície `/documentacao` de Blade para Inertia/React

**Implementado em:** _pendente_ — F1 (plano, RUNBOOK, paridade, trio das 4 telas) concluída em 2026-08-06; F2/F3 ainda não

> owner: wagner · priority: p1 · estimate: 12h · status: doing · type: story
> blocked_by: —
> parent_plan: programa-ondas

**Contexto.** As três telas Blade (`index`, `busca`, `doc`) passam a ser páginas Inertia sem trocar
a fonte de dados: o rail continua derivado do frontmatter em disco e o conteúdo continua vindo do
corpus. O que muda é só a camada de render. A decisão de [W] em 2026-08-06 descartou a convivência
Blade↔React — a superfície migra inteira.

**Escopo:**
- [ ] `DocumentacaoController` devolvendo props em vez de `View`, preservando os três estados de falha distintos (503 sem fonte · 200 `indisponivel` na busca · 503/404 no documento)
- [ ] `Documentacao/Index.tsx`, `Doc.tsx` e `Busca.tsx` sobre `AppShellV2` + `PageHeader` canon
- [ ] rota migrada para o stack completo de middleware (precedente `/modulos`) e o comentário de `routes/web.php` corrigido no mesmo PR
- [ ] rotas nomeadas registradas **antes** de `/documentacao/{slug}`
- [ ] aposentar, no cutover, o caso `a paleta da documentacao nao drifa dos tokens do DS`: ele existe porque a página Blade era **standalone e não carregava o CSS do app**, então os tokens do DS ficavam espelhados no `:root` do layout e o caso travava o espelho. Sob `AppShellV2` a página passa a usar o DS direto — não há mais espelho a drifar, e o caso ficaria lendo um arquivo que o cutover apaga. É aposentadoria por premissa vencida, não por incômodo
- [ ] remoção das views Blade no cutover

**Acceptance criteria:**
- [ ] **todas** as asserções `AR-DOC-NNN` verificadas, com a coluna de verificação preenchida — desvio vira Non-Goal aprovado por [W] ou defeito corrigido antes do cutover. A contagem vive no [próprio contrato](ANTI-REGRESSAO-documentacao-blade.md), não aqui: número restateado em dois docs drifa (§5 2026-07-17)
- [ ] UCs de `Index.casos.md`, `Doc.casos.md` e `Busca.casos.md` com teste executando e passando
- [ ] smoke real das 4 rotas com status literal por rota
- [ ] screenshot 1280 e 1440 aprovado por [W] antes de o charter sair de `draft`

**Refs:** [ANTI-REGRESSAO-documentacao-blade.md](ANTI-REGRESSAO-documentacao-blade.md) · [RUNBOOK-documentacao.md](RUNBOOK-documentacao.md) · [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)

## US-DOC-002 · Tela do Programa (Trilha D) cruzando plano em git e tasks MCP

**Implementado em:** _pendente_ — trio da tela carimbado em 2026-08-06; serviços e página ainda não

> owner: wagner · priority: p1 · estimate: 8h · status: todo · type: story
> blocked_by: US-DOC-001
> parent_plan: programa-ondas

**Contexto.** É a única tela do conjunto que não migra nada: existe porque cruza duas fontes que
markdown sozinho não cruza — a § Trilha D do plano em git e o estado de execução das tasks MCP
(`parent_plan=programa-ondas`). Estado nunca é chumbado em markdown
([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md)).

**Escopo:**
- [ ] `TrilhaDParser` lendo as seções reais da § Trilha D — **D.1** camadas · **D.2** onde o estado vive · **D.3** ondas D0–D10 · **D.4** ciclo de 11 estações · **D.5** caminho por tipo · **D.6** batimento · **D.7** DoD (não existe D.8)
- [ ] `EstadoDasOndas` projetando as tasks MCP sobre as ondas, com estado **indisponível** quando o MCP não responde
- [ ] `Documentacao/Programa.tsx` read-only, props-driven
- [ ] entrada no rail, no grupo `governanca`, apontando para a rota nomeada

**Acceptance criteria:**
- [ ] os 4 UCs de `Programa.casos.md` com teste executando e passando
- [ ] nenhum status de onda escrito no plano, no parser ou no `.tsx` — busca por `doing` nesses arquivos volta zero
- [ ] plano alterado numa fixture muda o payload sem tocar PHP nem TSX
- [ ] payload sem `business_id`, host ou token

**Refs:** [PLANO-MESTRE § Trilha D](../_Governanca/programa-ondas/PLANO-MESTRE.md) · [RUNBOOK-documentacao.md](RUNBOOK-documentacao.md) §8

## Trilha do tempo

- 2026-08-06 · [CC] SPEC nasce com a decisão de [W] de migrar a superfície inteira para React. F1 do MWART concluída: RUNBOOK, contrato de paridade e trio das 4 telas.
- 2026-08-23 · [CC] **Resgate.** Tudo acima existia só no working tree de um worktree parado desde 06/08 — nunca commitado. Trazido para `main` a camada de contrato (este SPEC + 5 RUNBOOKs + o contrato de paridade). ⚠️ **O trio das 4 telas NÃO está em `main`**: as Pages, charters, `casos.md` e specs e2e ficaram na branch `claude/documentacao-trio-pages-f1`, porque entram junto com a F3 (precisam de baseline de regressão visual gerada no runner canônico + aprovação visual de [W], gate F1.5). Enquanto isso, `/documentacao` segue Blade em produção.
- 2026-08-23 · [CC] Contrato de paridade ganhou a seção 6 (`AR-DOC-060..069`) cobrindo `/documentacao/programa`, rota que entrou em `main` **depois** do sha de origem do contrato e por isso faltava.
- 2026-08-23 · [CC] **Defeito achado na tela viva, medido, não consertado aqui** (`AR-DOC-068`): o KPI "em que onda a trilha está" vem de uma linha de tabela escrita à mão no plano — última escrita em 2026-08-05, 18 dias sem mudar — enquanto o rodapé da mesma página afirma ao leitor que aquilo é estado vivo das tasks MCP. Contradiz [ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md). E o literal do `UC-PROGRA-01` **não é implementável hoje** (`AR-DOC-069`): não existe chave que ligue task a onda — `parent_plan` é por plano, e a § D.3 não tem coluna de task. Recomendação medida: estado no nível do **plano**, via `jana:plan-drift --json` (oráculo que já existe, já lê `mcp_tasks` e já tem o `skipped`+`reason` honesto do `UC-PROGRA-03`); o por-onda vira backlog nomeando a chave que falta. A convenção de task é decisão de [W].

## Referências

- Contrato de paridade: [ANTI-REGRESSAO-documentacao-blade.md](ANTI-REGRESSAO-documentacao-blade.md)
- Operação: [RUNBOOK-documentacao.md](RUNBOOK-documentacao.md) · [RUNBOOK-index.md](RUNBOOK-index.md) · [RUNBOOK-busca.md](RUNBOOK-busca.md) · [RUNBOOK-doc.md](RUNBOOK-doc.md) · [RUNBOOK-programa.md](RUNBOOK-programa.md)
- Programa (Trilha D): [PLANO-MESTRE.md](../_Governanca/programa-ondas/PLANO-MESTRE.md) — rastreado por `US-INFRA-048`
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) processo MWART · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) trio executável · [ADR 0329](../../decisions/0329-doutrina-documentacao-de-processo-executavel.md) doutrina de documentação
