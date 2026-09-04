---
page: /manufacturing/v2/insumos
component: resources/js/Pages/Manufacturing/Insumos.tsx
owner: wagner
status: draft
parent_module: Manufacturing
related_prototype: prototipo-ui/cowork/manufacturing-insumos.jsx
related_us: [US-MANU-005]
runbook: memory/requisitos/Manufacturing/RUNBOOK-insumos.md
casos: resources/js/Pages/Manufacturing/Insumos.casos.md
alcance:
  rota: /manufacturing/insumos
  rota_nome: manufacturing.insumos.index   # canônico desde o cutover 2026-09-04; /manufacturing/v2/insumos virou 301
  permission: manufacturing.access_recipe
  menu_hook: null # alcançada pela aba "Insumos" das telas v2
  pacote: manufacturing_module
tier: B
charter_version: 1
---

# Page Charter — Manufacturing/Insumos (DRAFT · PT-01 Lista + drawer)

> Quinta onda da família Fabricação e a **única que nasce com backend novo** — o §18.3 do
> handoff declarava que sem `usosDoInsumo()` a aba não saía. Fonte visual:
> `prototipo-ui/cowork/manufacturing-insumos.jsx::MfgInsumosView`.

## Mission

Responder, antes de o fornecedor mandar a tabela nova: **se este insumo subir X%, quais receitas
sofrem, e quanto sobra de margem depois.**

## Goals — Features (faz)

- Lista os insumos do business com custo, estoque, nº de receitas e o **maior peso** que o
  insumo tem no custo de alguma receita
- Busca por nome e SKU
- Drawer por insumo: simulador de −30% a +60% (passo 5, default +10%) e a tabela de receitas
  afetadas com consumo, custo/un atual, custo/un simulado e margem resultante
- Ordena por mais usados; empate desempata pelo maior peso
- Fecha com `Esc`, com o ✕ e com clique no scrim

## Non-Goals — Features (NÃO faz)

- ❌ **Não escreve nada.** Simular não altera preço nenhum — quem aplica a variação de verdade
  é uma nota em Compras, e a tela diz isso ao usuário.
- ❌ **Não cria "insumo" como entidade.** A lista é derivada dos ingredientes das receitas; o
  app não tem flag de matéria-prima (RUNBOOK §2).
- ❌ **Não edita receita** — quem faz isso é o editor de ingredientes (US-MANU-006).
- ❌ **Não simula no cliente.** O slider dispara partial reload; a conta é do servidor.

## Automation Anti-hooks (o que agente nenhum pode fazer aqui)

- ❌ Reimplementar o custo. A simulação troca o preço na cópia em memória e rechama
  `RecipeBomService::calculateCost()` — a mesma fórmula das outras telas.
- ❌ Copiar o atalho aditivo do protótipo (`total + qtd × preço × pct`): ele **subestima** o
  custo quando `production_cost_type` é `percentage`. Desvio declarado no RUNBOOK §1 e travado
  por UC-INS-02.
- ❌ Consultar receita sem o JOIN `mfg_recipes → variations → products.business_id` — sem
  global scope no módulo, é a única barreira de tenant ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- ❌ Confiar no `variacao_pct` que o cliente manda: o controller **clampa** em −30..60.
- ❌ Deixar `NaN`/`Infinity` chegar à tela — denominador zero devolve 0.

## UX Targets

- Cabe em 1280px sem scroll horizontal fora do `.mfg-tablewrap`
- Linha de tabela clicável por teclado (`role="button"` + Enter/Espaço), não só por mouse
- O drawer é `role="dialog"` rotulado pelo nome do insumo

## Refs

- Handoff normativo *PROTÓTIPO OFICIAL - FABRICAÇÃO V1* — §4.4 · §18.3
- RUNBOOK (F1 PLAN): `memory/requisitos/Manufacturing/RUNBOOK-insumos.md`
- Charters irmãos: `Recipes.charter.md` · `Report.charter.md` · `Settings.charter.md`
