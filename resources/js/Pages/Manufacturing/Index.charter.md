---
id: resources-js-pages-manufacturing-index-charter
page: /manufacturing/production
component: resources/js/Pages/Manufacturing/Index.tsx
# 2026-09-22 [W]: fonte = handoff_fabricacao do Felipe. Caminho COMPLETO de proposito - bundle_source resolve por BASENAME (ancora.mjs:512) e ha 3 copias de manufacturing-page.jsx no staging.
# 2026-09-22 [F+C]: o caminho abaixo é a RAIZ de cowork/Felipe/ — o que o shell (oimpresso.com.html) carrega e o protótipo validado. A cópia em cowork/Felipe/handoff_fabricacao/design/ é DUPLICATA (usada só pela página-guia do pacote), não a fonte.
# 2026-09-25 [W]: fonte unica = cowork/Wagner ("e tudo do wagner, vai ser usado so esse agora" - D-MFG-FONTE, playbook manufacturing thread 02). As 2 linhas acima viram historico.
related_prototype: prototipo-ui/cowork/Wagner/manufacturing-producao.jsx
bundle_source: manufacturing-page.jsx  # 2026-09-09 [C]: porte REVERSO — `manufacturing-page.jsx:2` declara "Espelho de Modules/Manufacturing"; o gap-spec de 06/09 mede o prototipo ATRAS do vivo nos KPIs. Fonte de bundle, NAO design aprovado (§5 2026-08-28).
page_id: manufacturing-index
status: draft
owner: maiara
created: 2026-05-16
wave: J
runbook: memory/requisitos/Manufacturing/RUNBOOK-producao.md
casos: resources/js/Pages/Manufacturing/Index.casos.md
related_us: [US-MANU-004, US-MANU-008, US-MANU-009]
---

# Charter — Manufacturing/Index.tsx

## Mission
Listar ordens de produção (production_purchase) do business ativo em UX Inertia/React, no endereço
canônico `/manufacturing/production` desde o cutover de 2026-09-04 (`?legacy=1` devolve o Blade
antigo).

> **D1 (ADR 0448, aceita pelo [W] em 2026-10-13):** Fabricação é cadastro; a ordem nova nasce na
> Produção e esta aba vira consulta. Até a etapa de unificação da ADR, o formulário Blade da ordem
> (criar, editar rascunho, excluir) continua sendo o daqui, consertado em valor e estoque pela
> decisão B1 do [W] (2026-11-09, lote 1 da auditoria). Os botões "Nova produção" e "Editar ordem"
> da tela são do lote 5.

## Goals
- G1: PageHeader + summary cards (total / final / pendente / valor). **Desde 2026-10-07 (UC-OP-10,
  decisão [W] 2026-10-06) os 4 cartões seguem o filtro de local e período** (prop `kpis`); o
  contador da barra de abas segue com todas as ordens (prop `summary`). "Só finalizadas" não
  recorta os cartões.
- ~~G2: Tabela enxuta com 5 colunas (ref, data, local, total, status)~~ → **G2 (US-MANU-004,
  2026-09-04): 8 colunas do §4.5** — Data · Referência · Local · Produto (com
  `N ingredientes · quem lançou`) · Qtd · Custo total (sufixo `fix` na finalizada) ·
  Custo unit. · Situação
- G3: EmptyState honesto quando sem dados
- G4: Multi-tenant Tier 0 — todas queries via ProductionService scoped por business_id
- G5 (US-MANU-004): filtros de **leitura** — local, intervalo de data e o checkbox
  "Só finalizadas", que governa o mesmo filtro do KPI clicável. São controles que only
  **filtram a listagem**; nenhum deles escreve. O Non-Goal de CRUD abaixo segue intacto:
  create/edit/destroy continuam no Blade legado.
- G6 (US-MANU-004): situação vem do `StatusBadge` compartilhado (domínio `producao`) — o
  `StatusPill` local foi removido
- G7 (UC-OP-07, 2026-10-05): clicar numa ordem abre o painel lateral do protótipo
  (`MfgProducaoDrawer`) com os ingredientes consumidos e o custo. Só leitura: as contas são as do
  detalhe da tela antiga, prontas do servidor (`ProductionService::detalheOrdem`). "Editar ordem"
  aparece só em rascunho e leva ao formulário antigo — o Non-Goal de CRUD segue intacto.

## Non-Goals (Wave J)
- Não migrar CRUD completo (create/edit/destroy) — Blade legacy mantém
- Não fazer Kanban de produção — fica pra Wave futura
- Não migrar Recipes nem BOM (RecipeBomService) — escopo separado
- ~~Não tocar rota legacy `/manufacturing/production` — coexistência~~ — **SUPERADO pelo
  cutover de 2026-09-04.** O andaime de coexistência era da Wave J; o pedido [F] ("módulo
  inteiro em produção, com os links e vínculos reais, sem rotas alternativas"), sobre a
  aprovação [W] da família em produção, torna o endereço canônico a tela React. A rota
  **NÃO foi removida nem renomeada** e `?legacy=1` devolve o Blade no mesmo endereço.
  ⚠️ Non-Goal é território [W]: se ele discordar, o lugar de barrar é este PR.
  ⚠️ **"sem rotas alternativas" = sem ENDEREÇO paralelo (`/v2/*`), não "sem navegação".**
  O `pr-critic` leu a citação acima como se proibisse a barra de abas (PR #6771) — ela
  aponta pros endereços CANÔNICOS, que é exatamente o que a frase pede. Nota aqui porque
  a ambiguidade é da redação, não do leitor.

## Navegação
- A tela carrega a **barra de abas do módulo** (Receitas · Ordens de produção · Insumos ·
  Relatório · Configurações), igual às 4 irmãs. Nasceu SEM ela na Wave J — era a única tela
  React do módulo — e virou beco sem saída quando o cutover pôs o menu lateral apontando pra
  cá ([M] reportou 2026-09-04). Defendido por `CutoverRotasCanonicasTest`.

## UX targets
- Responsivo 1280px+ (cliente piloto ROTA LIVRE biz=4 monitor pequeno)
- PT-BR em todos labels
- Empty state convida a criar a primeira ordem pelo botão "Nova produção" (o link para `?legacy=1`
  saiu; a tela antiga segue acessível nesse parâmetro)

## Forma (alinhamento ao protótipo — 2026-09-08)

Três divergências de FORMA foram medidas contra o protótipo (`manufacturing-producao.jsx` ·
`manufacturing-page.jsx`) e corrigidas. A cadeia aqui é a do eixo FORMA — protótipo soberano
([UI-0029](../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)):

- **Rótulos LOCAL / DE / ATÉ** sobre os filtros. O protótipo põe cada controle num `<Campo label=…>`
  (`.mfg-fld > span`: 10px, caixa alta, tracking .07em, `--text-mute`); a tela só tinha `aria-label`.
  Cada `<label>` agora associa por `htmlFor`/`id` — o `aria-label` saiu, porque dois nomes fariam o
  leitor de tela anunciar coisa diferente do que está escrito.
- **Subtítulo conta receitas e ordens**, como o protótipo. ⚠️ A 3ª parte da copy dele
  (*"custo recalculado pelo preço atual dos ingredientes"*) fica **de fora de propósito**: aqui o
  custo é o `final_total` GRAVADO, nunca recalculado (US-MANU-004 · RUNBOOK-producao.md §1).
  Copiar a copy literal poria afirmação FALSA na tela. **Não "corrigir" isso de volta.**
- **"Rascunho" é âmbar, não cinza** — o protótipo usa `.mfg-pill.warn` (o `.ok` é a finalizada).
  Só ficou legível depois da [UI-0033](../../../../memory/requisitos/_DesignSystem/adr/ui/0033-foreground-de-success-e-warning-vira-cor-de-contraste.md):
  com o par `warning` quebrado o texto dava **1,25:1**; agora dá **7,72:1**. O domínio `producao`
  do `StatusBadge` é usado **só por esta tela** (medido: 1 de 1 sítio).

## Anti-hooks
- Não usar `withoutGlobalScopes` no Service
- Não permitir UPDATE direto em transactions (FSM trait Sells/Repair não cobre Manufacturing ainda)
- Não duplicar queries do ProductionController existente — Service centraliza
- Não promover charter pra `live` sem Wagner aprovar UX screenshot

## Data flow
```
Controller@indexV2  →  ProductionService::listProductions(biz, filters)   # + eager purchase_lines/location
                    →  ProductionService::enrichProductionRows(ordens, biz)  # produto · nº ingredientes ·
                    →  ProductionService::summary(biz)                       # quem lançou · qtd · custo unit.
                    →  ProductionService::summary(biz, local+período)        # kpis (UC-OP-10)
                    →  Inertia::render('Manufacturing/Index', {productions, summary, kpis})
```

> **Custo (US-MANU-004):** as colunas de dinheiro mostram `transactions.final_total` — o valor
> GRAVADO na criação da ordem —, nunca um recálculo. Recalcular aqui criaria uma segunda
> fórmula de custo na base (a primeira é `RecipeBomService`, usada pelo Relatório). O sufixo
> `fix` marca a ordem finalizada. Detalhe e a razão em `RUNBOOK-producao.md §1`.

## Rota
- `GET /manufacturing/production` — esta tela (`ProductionController@index` delega ao `indexV2`)
- `GET /manufacturing/production?legacy=1` — a tela Blade antiga, no mesmo endereço
- `GET /manufacturing/v2/production` — 301 para o canônico desde 2026-09-04
- Formulário da ordem: `production/create`, `production/{id}/edit`, `POST`/`PUT`/`DELETE` — Blade

## Próximos passos
- ~~Wire-up filtros (location, date range) com Inertia partial reload~~ — **feito** (o
  `applyFilter` faz partial reload com `only:[productions,summary,kpis,filters]` — `kpis` desde o UC-OP-10)
- ~~Charter MWART completo com RUNBOOK~~ — **feito em 2026-09-04**:
  `memory/requisitos/Manufacturing/RUNBOOK-producao.md` (o nome citado antes,
  `RUNBOOK-production-index.md`, nunca existiu)
- `Inertia::defer` segue **não aplicado de propósito** — `indexV2` documenta o rollback do
  Wave L/W7 (PR #963), em que defer quebrava o initial render desta tela
- ~~US-MANU-007 introduz o `custoSnap` gravado~~ — a US-MANU-007 foi cancelada (D1); o custo
  congelado passa a ser da ordem na Produção. Aqui, desde o lote 1.5 (UC-OP-19), o `final_total`
  é calculado no servidor
