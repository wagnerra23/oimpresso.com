---
page: /manufacturing/v2/report
component: resources/js/Pages/Manufacturing/Report.tsx
owner: wagner
status: draft
parent_module: Manufacturing
related_prototype: prototipo-ui/cowork/manufacturing-producao.jsx
related_us: [US-MANU-002]
runbook: memory/requisitos/Manufacturing/RUNBOOK-report.md
casos: resources/js/Pages/Manufacturing/Report.casos.md
alcance:
  rota: /manufacturing/report
  rota_nome: manufacturing.report.index   # canônico desde o cutover 2026-09-04; /manufacturing/v2/report virou 301
  permission: null # sem permissão Spatie granular própria — mesmo gate do Blade legado (só pacote)
  menu_hook: null  # não está na sidebar; alcançada pela aba "Relatório" das telas v2
  pacote: manufacturing_module
tier: B
charter_version: 1
---

# Page Charter — Manufacturing/Report (DRAFT · PT-01 Relatório)

> Segunda das 7 telas do handoff **"PROTÓTIPO OFICIAL - FABRICAÇÃO V1"** — decisão [M]
> 2026-09-02 de fazer as ondas em ordem de custo crescente, começando pela mais barata.
> Fonte visual: `prototipo-ui/cowork/manufacturing-producao.jsx::MfgRelatorio`, dentro do
> mesmo bundle já aplicado inteiro pela Onda 1 (Recipes) — **nenhuma classe CSS nova**.
>
> **Rota aditiva, sem decisão [W] sobre o endereço** — segue o padrão da Onda 1 de Ordens de
> produção (Wave J `indexV2`): `/manufacturing/v2/report` novo, `/manufacturing/report`
> (Blade) intocado. Se [W] preferir tomar o endereço legado (como fez em Recipes), é troca de
> rota + `?legacy=1`, não reescrita da tela.

## Mission

Responder "pra onde foi o dinheiro de produção no período", agrupado por produto — sem exigir
que Wagner abra planilha ou some ordem por ordem.

## Goals — Features (faz)

- Filtra por período (De/Até) + "Só finalizadas" (default **ligado**) — recarrega do servidor
- Agrupa por produto: ordens · quantidade · custo total · custo médio · % do período com barra
  proporcional
- Ordena por custo desc (maior gasto primeiro — é a pergunta que a tela responde)
- Rodapé com o total do período e o aviso de que o valor entra no Financeiro
- Mesma aba do módulo que as outras telas v2 (Receitas · Ordens de produção · Relatório ·
  Configurações)

## Non-Goals — Features (NÃO faz)

- ❌ **Não escreve nada.** 100% leitura, igual à tela de Receitas.
- ❌ **Não filtra por local (`location_id`).** O protótipo normativo (`MfgRelatorio`) não tem
  esse filtro; o Blade legado tem, mas replicá-lo aqui seria inventar requisito que a fonte de
  design não pede.
- ❌ **Não mostra custo congelado.** `transactions` não tem `custoSnap` hoje (só chega em
  US-MANU-007). Todo número aqui é recalculado na leitura — igual a Receitas.
- ❌ **Não exporta CSV/Excel/PDF.** O Blade legado tem; o protótipo normativo não pede pra
  este porte.

## Automation Anti-hooks (o que agente nenhum pode fazer aqui)

- ❌ Reimplementar a fórmula de custo. O custo de cada ordem é
  `RecipeBomService::calculateUnitCost($recipe) × quantidade_produzida_na_ordem` — REUSA o
  método já testado em Receitas (UC-RECIPE-03/04). Prova algébrica de que isso reproduz
  `consumoOP()` do protótipo em `RUNBOOK-report.md §1`.
- ❌ Agrupar sem a defesa de tenant `products.business_id` na cadeia até a receita — ver
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- ❌ Deixar `NaN`/`Infinity` chegar à tela — custo médio e `% do período` são `0` quando o
  denominador é zero.
- ❌ Calcular o agrupamento no cliente. O servidor agrupa e soma; o cliente só formata (mesmo
  princípio §9 do handoff que rege Receitas).

## UX Targets

- Cabe em 1280px sem scroll horizontal fora do `.mfg-tablewrap`
- Linha de tabela ≥ 44px
- Filtro de data recarrega via Inertia partial reload (`only: ['relatorio','filters']`) — sem
  reload de página inteira

## Refs

- Handoff normativo *PROTÓTIPO OFICIAL - FABRICAÇÃO V1* — §4.6
- RUNBOOK (F1 PLAN): `memory/requisitos/Manufacturing/RUNBOOK-report.md`
- Padrão de Tela: PT-01 · Constituição UI v2: UI-0013
- Charter irmão (Onda 1): `resources/js/Pages/Manufacturing/Recipes.charter.md`
