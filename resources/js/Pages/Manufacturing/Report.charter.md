---
page: /manufacturing/report
component: resources/js/Pages/Manufacturing/Report.tsx
owner: maiara
status: draft
parent_module: Manufacturing
# 2026-09-22 [W]: fonte = handoff_fabricacao do Felipe. Caminho COMPLETO de proposito - bundle_source resolve por BASENAME (ancora.mjs:512) e ha 3 copias de manufacturing-page.jsx no staging.
# 2026-09-22 [F+C]: o caminho abaixo é a RAIZ de cowork/Felipe/ — o que o shell (oimpresso.com.html) carrega e o protótipo validado. A cópia em cowork/Felipe/handoff_fabricacao/design/ é DUPLICATA (usada só pela página-guia do pacote), não a fonte.
# 2026-09-25 [W]: fonte unica = cowork/Wagner ("e tudo do wagner, vai ser usado so esse agora" - D-MFG-FONTE, playbook manufacturing thread 02). As 2 linhas acima viram historico.
related_prototype: prototipo-ui/cowork/Wagner/manufacturing-producao.jsx
related_us: [US-MANU-002]
runbook: memory/requisitos/Manufacturing/RUNBOOK-report.md
casos: resources/js/Pages/Manufacturing/Report.casos.md
alcance:
  rota: /manufacturing/report
  rota_nome: manufacturing.report.index   # canônico desde o cutover 2026-09-04; /manufacturing/v2/report virou 301
  permission: manufacturing.access_production   # desde 2026-11-16 (lote 2.1, decisão B3 do [W] 2026-11-09); antes era só o pacote — UC-REPORT-06, tela + AJAX + ?legacy=1
  menu_hook: null  # sem item próprio: ghost "Relatório" do item Fabricação no menu lateral (DataController::modifyAdminMenu) e aba "Relatório" do módulo
  pacote: manufacturing_module
tier: B
charter_version: 1
---

# Page Charter — Manufacturing/Report (DRAFT · PT-01 Relatório)

> Segunda das 7 telas do handoff **"PROTÓTIPO OFICIAL - FABRICAÇÃO V1"** — decisão [M]
> 2026-09-02 de fazer as ondas em ordem de custo crescente, começando pela mais barata.
> Fonte visual: `prototipo-ui/cowork/Wagner/manufacturing-producao.jsx::MfgRelatorio`, dentro do
> mesmo bundle já aplicado inteiro pela Onda 1 (Recipes) — **nenhuma classe CSS nova**.
>
> **Endereço:** `/manufacturing/report` serve esta tela desde o cutover de 2026-09-04 (pedido
> [F]); `?legacy=1` devolve o Blade antigo no mesmo endereço. A tela nasceu como rota aditiva em
> `/manufacturing/v2/report`, que hoje é 301 para o canônico.

## Mission

Responder "pra onde foi o dinheiro de produção no período", agrupado por produto — sem exigir
que Wagner abra planilha ou some ordem por ordem.

## Goals — Features (faz)

- Filtra por período (De/Até) + "Só finalizadas" (default **ligado**) — recarrega do servidor
- Agrupa por produto: ordens · quantidade · custo total · custo médio · % do período com barra
  proporcional
- Ordena por custo desc (maior gasto primeiro — é a pergunta que a tela responde)
- Rodapé com o total do período e o aviso de que o valor entra no Financeiro
- Mesma barra de abas das outras telas do módulo (Receitas · Ordens de produção · Insumos ·
  Relatório · Configurações), filtrada pela permissão de cada aba (lote 2.4)

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
