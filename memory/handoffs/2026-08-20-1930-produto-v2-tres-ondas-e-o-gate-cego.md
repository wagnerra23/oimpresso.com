---
date: "2026-08-20"
time: "1930 BRT"
slug: "produto-v2-tres-ondas-e-o-gate-cego"
tldr: "Pacote V2 da Consulta de Produtos aplicado inteiro em 3 ondas mergeadas (#5995/#5996/#5997). O achado que sobrevive à sessão: o gate de pixel dessa tela captura a TABELA VAZIA — a onda 3, que é a que mais muda o que o operador vê, passou sem aprovação humana porque o gate não a enxerga. Falta o smoke em produção com dados reais."
decided_by: [W]
cycle: null
prs: [5995, 5996, 5997, 6001]
us: ["US-PROD-023"]
next_steps:
  - "Smoke em produção de /products/unificado com produtos reais — é o único caminho que exercita avatar, saldo por local, observação e as seções do drawer; o gate de pixel não alcança"
  - "Decidir a proposta de faixa de preço por quantidade (memory/decisions/proposals/2026-08-19-faixa-de-preco-por-quantidade.md) — a 3ª pergunta (quem aplica no PDV) separa 'tabela nova' de 'mexer no cálculo de venda'"
  - "Avaliar se o contrato visreg desta tela deve capturar um estado COM linhas — hoje o gate é estruturalmente cego à linha do produto"
related_adrs: ["0093-multi-tenant-isolation-tier-0", "0104-processo-mwart-canonico-unico-caminho", "0130-handoff-append-only-mcp-first", "0314-poda-gates-onda-2-lei-fusoes"]
---

# Handoff 2026-08-20 19:30 BRT — Três ondas dentro, e um gate que não vê o que a última mudou

## TL;DR

O pacote **"PROTÓTIPO OFICIAL — PRODUTO UNIFICADO V2"** (2026-08-19) foi aplicado por inteiro na
`/products/unificado`, em três ondas mergeadas hoje. O que vale carregar pra próxima sessão não
é o código: é que **o gate de regressão visual dessa tela captura a tela com a TABELA VAZIA**, e
por isso a onda 3 — a que mais muda o que o operador vê — passou **sem aprovação humana**, não
porque foi conferida, mas porque o gate é cego a ela.

## O que entrou

| Onda | Commit | Conteúdo |
|---|---|---|
| 1 | `ba37836ba` ([#5995](https://github.com/wagnerra23/oimpresso.com/pull/5995)) | Moldura: abas com badge de contagem, KPI "Itens listados" removido, filtros sem moldura em repouso, chips do recorte, avatar na coluna Produto, grid sem raio com cabeçalho sticky opaco |
| 2 | `62775ec6f` ([#5996](https://github.com/wagnerra23/oimpresso.com/pull/5996)) | Paginação server-side: rodapé 10/25/50/100, `page`/`per_page`/`ORDER BY` no servidor com lista branca, total autoritativo; teto de 500 linhas + rolagem interna aposentados |
| 3 | `36b746b77` ([#5997](https://github.com/wagnerra23/oimpresso.com/pull/5997)) | Revelação progressiva: saldo por local, observação do produto, terceira linha de variações, três seções novas no drawer |

## As decisões que sobrevivem

**As 5 cores cruas do pacote viraram token.** O pacote pedia RGB literal copiado da tela em
produção. Entraram como `--idx-*` em `cockpit.css` com par claro/escuro: o claro é byte-a-byte o
pedido (pixel não muda), o escuro é o par que faltava. É o encaminhamento que o LAUDO do pacote
recomenda e que a ADR 0401 dele registra como "impacto visual: nenhum".

**`[V0]` Ordenar por custo/margem é ignorado pra quem não tem `view_purchase_price`.** A coluna
não é montada pra esse perfil, mas ordenar por ela entregaria o número pela POSIÇÃO — "o
primeiro é o mais barato" é a estrutura de custo servida por ranking. Cai no padrão (nome) em vez
de dar erro, porque erro também informa. `UC-PUNI-11C`.

**`[T0]` O saldo por local é escopado por `business_locations.business_id`**, não pela lista de
ids de produto. A linha de saldo pendura num LOCAL — local de outro business com o mesmo
`product_id` (restore parcial, importação mal feita) entraria sem a cláusula, vazando o nome do
local do vizinho e inflando a soma. `UC-PUNI-12B`.

**O "A PARTIR DE" NÃO foi implementado, e é decisão de produto.** O pacote mostra faixa de preço
por quantidade; o UltimatePOS tem preço por **grupo de cliente** (`variation_group_prices`), sem
nenhuma coluna de quantidade — verificado no **baseline de schema**, não só na migration de 2018
(que nasceu com 3 colunas; a 4ª que entrou foi `price_type`). Mostrar aquilo faria o balcão
prometer desconto por volume que o sistema não aplica no lançamento. Proposta com as 3 perguntas
em [`proposals/2026-08-19-faixa-de-preco-por-quantidade.md`](../decisions/proposals/2026-08-19-faixa-de-preco-por-quantidade.md).

**Divergências declaradas do pacote:** resumo de variação sai `Cor (4) · Tamanho (3)`, não
`4 cores · 3 tamanhos` (o nome do atributo é texto livre do tenant; pluralizar daria "4 Cors") ·
`porPagina` padrão 25, não o 10 do protótipo (artefato do dataset de 14 itens dele) · badges
"Sob encomenda"/"Exige aprovação" não são servidos (não existem no cadastro; deduzi-los do texto
seria adivinhação exibida como fato).

## O achado que importa pra próxima sessão

**O contrato visreg desta tela captura a tela SEM PRODUTOS.** O tenant de teste não tem item no
recorte, e o que a baseline guarda é o estado "Nenhum item neste recorte".

Consequência medida, não inferida:

- **Onda 1** — diff real na zona cinza → pediu e recebeu aprovação [W] (label `visreg-gray-approved`).
- **Onda 2** — diff real (rodapé nasceu, cartão perdeu altura fixa) → pediu e recebeu aprovação.
- **Onda 3** — **zero diff, nenhum artifact `pixel-diff-views`, gate liberou com
  `VISREG_GRAY_APPROVED: 0`**. Avatar, terceira linha de variações, ícone de recado e as três
  seções do drawer simplesmente não renderizam com a tabela vazia.

Ou seja: a onda que mais mexe no que o operador vê foi a única que **não** passou pelo olho
humano — e o motivo é estrutural, não de processo. Está escrito no corpo do merge da #5997 pra
não virar precedente silencioso.

O que cobre a onda 3 são os testes, não a imagem: `UC-PUNI-12` (saldo por local só com 2+ locais
e soma que bate com a coluna), `UC-PUNI-12B` (cross-tenant), `UC-PUNI-13` (observação sem HTML) e
`UC-PUNI-14` (variação-fantasma do UltimatePOS não conta) — **85 passed, 306 assertions** na lane
Estoque · MySQL.

## Duas armadilhas de processo que custaram tempo hoje

**PR empilhada não dispara lane de Pest.** Os workflows declaram
`pull_request: branches: [main]`; PR que aponta pra outra branch recebe **55 checks**, não 126 —
e `PHP / Pest (Estoque · MySQL)` **não está entre eles**. Os 8 UCs novos estavam nas PRs sem
nunca terem executado. Medido abrindo a [#6001](https://github.com/wagnerra23/oimpresso.com/pull/6001),
uma PR temporária com a pilha inteira apontando pro `main`, só pra a lane rodar. Ela pagou-se
três vezes: pegou **2 testes meus quebrados**, uma **violação Tier 0 de valor monetário** (R$ em
doc canon + mensagem de commit + corpo de PR) e provou a execução por *assertions*, não por
"0 failed".

**Merge por squash quebra a pilha, previsivelmente.** Cada onda mergeada deixou a seguinte
conflitante, porque ela ainda carregava a versão não-espremida da anterior. O movimento é
`git rebase --onto origin/main <sha-antigo-da-anterior> <branch>` — replanta só o commit próprio.
Aconteceu 2×, nas duas transições.

## O erro que eu cometi, registrado pra não repetir

Diagnostiquei o `visual-regression` como **defeito de lane** e defendi isso por quatro turnos,
com tabela: "7 de 7 runs em escopo global falham, em 6 branches independentes". A correlação era
real e **confundida** — as runs que passavam tinham `VISREG_GRAY_APPROVED: 1`, ou seja, o label
aplicado. Esse dado estava na minha primeira medição e eu o li como ruído de ambiente.

A causa verdadeira estava no código, a uma leitura de distância:
`VisregThreshold::writeGrayZoneSummary` lança `RuntimeException` no `afterAll` quando há telas na
zona cinza sem o label — daí o exit 2 **depois** do resumo, com todos os testes verdes.

Lição operacional: **correlação medida não vira mecanismo sem abrir o código que decide.** Dois
chamados foram abertos em cima do diagnóstico errado e depois dispensados.

## Estado MCP no momento do fechamento

Consulta por filesystem (worktree sem MCP conectado — fallback legítimo por
[`how-trabalhar.md` §Fallback](../how-trabalhar.md)):

- **Handoffs recentes:** o último é `2026-08-20-1138-backup-ondas-mergeadas-f3-bloqueada-por-transporte.md` (backup, outro escopo — sem sobreposição com este).
- **PRs desta sessão:** #5995 `MERGED` · #5996 `MERGED` · #5997 `MERGED` · #6001 `CLOSED` (temporária, branch apagada).
- **`origin/main` no fechamento:** `45107cc48`.
- **ADRs novas:** nenhuma. Uma **proposta** criada (`proposals/2026-08-19-faixa-de-preco-por-quantidade.md`), aguardando decisão [W].
- **Sessão paralela:** a [#6032](https://github.com/wagnerra23/oimpresso.com/pull/6032) regenerou as 74 baselines de visreg entre a onda 2 e a 3 — resolveu, de outra sessão, a dívida de baseline defasada que este handoff ia registrar como pendente.

## Achados fora do escopo, deixados como chamado

| O quê | Estado |
|---|---|
| `catalog-graph` reprova `Superadmin>RecurringBilling` sem baseline declarada (veio da #5981, delegação deliberada). Vermelho no `main` desde 08-08, advisory. ⚠️ A saída avisa que `DB::table` no dado de outro módulo pula global scope — se o MRR lê `rb_subscriptions` assim, é achado Tier 0, não dívida de acoplamento | chamado aberto |
| Canário `memory-health cron vivo?` grita falso: afirma cron morto há 24 dias; o mesmo comando rodado na hora devolve a run **daquele dia**. Bate com a lápide §5 de 2026-08-13 (API de Actions servindo índice atrasado); remédio já registrado lá (`max()` sobre N amostras) | chamado aberto |

## Próxima ação

**Smoke em produção com dados reais.** É a única coisa devida do pacote e o único caminho que
exercita o que o gate de pixel não alcança: avatar na linha, saldo por local, observação,
variações e as seções do drawer. A tela está no ar desde hoje.
