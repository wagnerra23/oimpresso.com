---
date: "2026-09-23"
time: "11:40 BRT"
slug: financeiro-gates-sessao-dre-depara
tldr: "Financeiro fechado para o primeiro uso: gate de permissão nas rotas que só exigiam login (#7766), SetSessionData no grupo de rotas (#7771), financeiro:install derivando as 17 permissões (#7772), de-para conta→linha da DRE (#7789) e conserto do teste que o #7766 quebrou no main (#7793). Todos mergeados e em produção. Pendente: a classificação das 130 contas da WR2 está com [E] para revisão — nada foi gravado em produção."
decided_by: [W]
cycle: null
prs: [7766, 7771, 7772, 7789, 7793]
us: []
next_steps:
  - "Quando [E] devolver a planilha DRE-de-para-WR2-proposta.csv revisada: comando artisan idempotente que grava fin_planos_conta.dre_linha no biz=1, com --dry-run mostrando a DRE de ago/set antes→depois por dois caminhos; gravar em prod só com OK [W] (regra mestre de valor)."
  - "Catraca 'Nota de tela não desce' compara com a PONTA do main, não com a merge-base — deu falso positivo no #7766. Tarefa aberta em sessão separada (task_7aece256); o resultado dela não foi conferido aqui."
  - "Aba anônima: abrir /financeiro/dre logo após o login e confirmar que os dados carregam (o caso 'sessão nova' só foi provado no CT 100)."
related_adrs: [0093-multi-tenant-isolation-tier-0, 0358-doutrina-de-teste-tenant-98-supersede-0101, 0409-zero-baseline-de-tolerancia-conformidade-absoluta]
---

# Handoff 2026-09-23 11:40 BRT — Financeiro: gates, sessão e de-para da DRE

## TL;DR

Os dois buracos medidos na sessão `hopeful-cerf-da6f30` (permissão e sessão, `RUNBOOK-paridade-ondas §12.5` / `dre-visual-comparison.md FIN-0a`) estão fechados e em produção. A DRE ganhou de-para por conta (`fin_planos_conta.dre_linha`), mas **nenhuma conta foi classificada ainda**: a proposta para a WR2 está com a Eliana. A DRE em produção segue zerada até isso.

## Cronologia desta sessão

| Quando (UTC) | Evento |
|---|---|
| 12:0x | #7766 aberto: gate `can:` em 7 controllers (19 rotas), teste de contrato 38 passed no CT 100 + controle 19 failed |
| 12:1x | #7771 aberto: `SetSessionData` no grupo; controle reproduz `0` vs `98`; suíte Financeiro 611 casos antes×depois sem falha nova |
| 12:2x | #7772 aberto: `financeiro:install` deriva a lista do `DataController` (17, não 18 — errata no #7766) |
| 12:2x | Catraca de scorecard reprovou o #7766 por scorecards novos no main — merge do main resolveu; defeito da catraca virou tarefa |
| 12:5x | CI do #7766: 15 testes com 403 — `actAsAdmin()` só dava permissão ao papel `Admin#<biz>`, que o seed do CI não cria. Conserto no `FinanceiroTestCase` |
| 12:59 / 13:00 / 13:23 | #7766, #7771, #7772 mergeados (auto-merge autorizado por [W]) |
| 13:3x | Smoke em prod no deploy `be796fc82`: 8 rotas sem login `302 → /login`; 10 telas logadas `200` com componente certo e `business_id` 1 |
| 13:4x | Pedido "mapeia as categorias": medido que o biz=1 usa plano legado (`1.x`/`2.x`), 216 títulos de ago/set todos em `1.2.1`. [W] escolheu de-para por conta + proposta revisada |
| 14:21 | #7789 mergeado (de-para no `DreService`, sem dado) |
| 14:1x | Sessão "Migração layout em ondas" avisou: required Financeiro vermelho no main (`PlanoContaControllerTest` 403). Assumido |
| 14:26 | #7793 mergeado (teste concede `financeiro.dashboard.view` ao ator) |

## Estado atual dos artefatos

### PRs (todos mergeados em `main`)

| PR | Conteúdo |
|---|---|
| [#7766](https://github.com/wagnerra23/oimpresso.com/pull/7766) | Gate de permissão: Categorias (ver `dashboard.view`; escrever `lancamentos.create`, confirmado por [W]), Plano de contas, Conciliação, Contas bancárias, Extrato, Contas a pagar/receber. `PermissaoRotasContratoTest` (tenant 98). `FinanceiroTestCase` dá as permissões ao usuário quando não há papel Admin |
| [#7771](https://github.com/wagnerra23/oimpresso.com/pull/7771) | `SetSessionData` no grupo operacional; o bloco aninhado da conciliação saiu. `RotasSessaoContratoTest` |
| [#7772](https://github.com/wagnerra23/oimpresso.com/pull/7772) | `financeiro:install` lê `DataController::user_permissions()` (13 → 17) |
| [#7789](https://github.com/wagnerra23/oimpresso.com/pull/7789) | Migration `dre_linha` (nullable) + `DreService::rowPertenceALinha()`; NULL = regra de prefixo antiga. `DreLinhaDeParaContratoTest` (dois caminhos de valor) |
| [#7793](https://github.com/wagnerra23/oimpresso.com/pull/7793) | `PlanoContaControllerTest` concede a permissão que a rota passou a exigir |

### Fora do git (de propósito)

- `DRE-de-para-WR2-proposta.csv` — 130 contas do biz=1 com títulos, sugestão de linha, motivo e coluna "revisar" (50 marcadas). Só contagens, **nenhum valor em R$**. Entregue a [W], que repassou a [E].

## Decisões desta sessão

- **De-para por conta, sem reclassificar títulos** ([W], AskUserQuestion). Quando `dre_linha` está preenchido, é a única regra (tipo e prefixo ignorados); `fora` tira a conta da DRE mas conta como "mapeada".
- **Categorias escrevem com `lancamentos.create`** ([W]): não existe permission própria de categoria.
- **`visual-regression` vermelho aceito no #7771** ([W]): o baseline de `/financeiro/contas-bancarias` foi gravado com a sessão vazia (lista vazia); não regravado (ADR 0409).

## Riscos e lacunas declarados

- **Sem despesa lançada no biz=1 depois de abril/2026.** Mesmo com o de-para, a DRE de setembro vai mostrar só receita — falta de lançamento, não de mapeamento.
- **Contas legadas com tipo trocado ou títulos misturados** (ex.: `2.4.6 REFEIÇÕES` com 77 títulos a receber): com `despesas`, esses recebimentos passam a ser subtraídos. Está na planilha como "revisar".
- **A DRE não tem linha de resultado financeiro nem de impostos sobre o lucro**: juros/tarifas foram sugeridos em despesas; IR, empréstimo e parcelamentos, `fora`.
- **Efeitos colaterais no staging do CT 100** (banco compartilhado `oimpresso_staging`): a coluna `dre_linha` já existe lá (migrate rodado para o teste); o 1º usuário do tenant 98 ganhou permissões do Financeiro (os testes concedem). Tenant fictício; nada no biz=1.
- **403 em produção não foi testado** (não há usuário comum para isso); a prova é o `PermissaoRotasContratoTest` no CT 100 e no CI.
- **Por que o `PlanoContaControllerTest` passou no PR do #7766 e em `5c0c238a4`** e só caiu em `81c65b576`: não medido (provável ordem aleatória). O #7793 remove a dependência de ordem.

## Estado MCP no momento do fechamento

**Não consultado — o servidor MCP `oimpresso` estava fora** às 14:40Z: `cycles-active` e `my-work` devolveram `ECONNREFUSED`; `sessions-recent` e `decisions-search` devolveram "MCP server oimpresso is not connected", também depois de esperar a reconexão. Nenhum dado de cycle/tasks foi inventado aqui.

Fallback pelo git (`how-trabalhar.md` §Fallback), medido às 14:40Z:
- PRs da sessão, todos `MERGED`: #7766 `476c1993d` · #7771 `5c0c238a4` · #7772 `81c65b576` · #7789 `120340ba2` · #7793 `c6ff470fb`.
- Deploy com sucesso contendo #7766/#7771: `be796fc82`; contendo #7772: `81c65b576`. #7789/#7793 não mudam comportamento de produção sem dado.
- ADRs novas em `memory/decisions/` hoje: nenhuma (`git log --diff-filter=A --since=2026-09-23`).
- Handoff anterior: `2026-09-23-0729-maquinas-governanca-microsservicos-8-prs.md`.
