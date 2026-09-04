---
casos: Manufacturing/Report — relatório de produção do período
irmaos: Report.charter.md (lei) · memory/requisitos/Manufacturing/RUNBOOK-report.md (F1)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
fonte: handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" §4.6 — os UC abaixo DERIVAM dele
owner: wagner
last_run: "2026-09-03"
---

# Casos de Uso & Aceite — Manufacturing/Report

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> Os casos abaixo **não foram derivados do `.tsx`** (§5 tautológico das proibições). Cada um
> aponta o requisito do handoff normativo (`§4.6`) de onde saiu.

---

## UC-REPORT-00 · Chego na tela pela aba "Relatório" das telas v2
- **Persona:** Wagner — está em Receitas ou Ordens de produção e clica "Relatório".
- **Aceite:** Dado a aba "Relatório" em `Recipes.tsx` · Quando o usuário clica · Então navega
  pra `/manufacturing/v2/report` (não mais pro Blade legado).
- **Regressão que defende:** a aba continuar apontando pro Blade em silêncio depois que a
  tela nova existir.
- **Teste:** `Wave30ReportInertiaTest.php`
- **Status: 🧪**

---

## UC-REPORT-01 · A rota `/manufacturing/v2/report` serve a tela nova
- **Persona:** Wagner — a rota aditiva existe e é do `ProductionController`.
- **Aceite:** Dado o app carregado · Quando se pergunta ao **registry de rotas** (não ao
  arquivo) quem serve `GET manufacturing/v2/report` · Então existe uma rota, e a ação dela é
  o `ProductionController`.
- **Fonte:** §4.6 do handoff (a tela) + decisão de rota aditiva registrada em
  `RUNBOOK-report.md` (sem decisão [W] explícita sobre o endereço).
- **Teste:** `Wave30ReportInertiaTest.php`
- **Regressão que defende:** alguém remover a rota achando que é redundante com o Blade.
- **Status: 🧪**

---

## UC-REPORT-02 · O relatório NÃO vaza produção de outro business, e período vazio devolve 0
- **Persona:** qualquer tenant — Tier 0.
- **Aceite:** Dado um tenant fictício sem nenhuma ordem de produção · Quando `reportByProduct`
  roda pra esse tenant · Então devolve `linhas: []` e `total: 0.0` — nunca erro, nunca `NaN`.
- **Fonte:** [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) +
  §7.3 do handoff (divisão por zero).
- **Teste:** `Wave30ReportInertiaTest.php`
- **Regressão que defende:** a cadeia de tenant (`transactions.business_id` → recipe via
  `products.business_id`) vazar produção de outro business, e o `array_sum`/divisão por zero
  quebrar quando não há produção no período.
- **Status: 🧪**

---

## UC-REPORT-03 · O custo de cada ordem reproduz a fórmula do protótipo — dupla prova
- **Persona:** Wagner — o número que a tela mostra tem que bater com o que o protótipo
  prometeu, não uma fórmula inventada no porte.
- **Aceite:** Dado uma receita com ingredientes conhecidos e uma ordem que produziu uma
  fração da quantidade canônica da receita · Quando o custo da ordem é calculado ·
  Então `RecipeBomService::calculateUnitCost($recipe) × quantidade_produzida` bate,
  **algebricamente e numericamente**, com `consumoOP()` do protótipo
  (`manufacturing-data.jsx:149`) — nas três fórmulas de `production_cost_type`.
- **Fonte:** §7 do handoff (modelo de custo) + `RUNBOOK-report.md §1` (a prova algébrica
  completa, com as 3 fórmulas lado a lado).
- **Teste:** `Wave30ReportInertiaTest.php` — REGRA MESTRE de VALOR (proibicoes.md): dupla
  prova com números concretos, dois caminhos de cálculo independentes.
- **Regressão que defende:** reimplementar a fórmula do zero em vez de reusar
  `calculateUnitCost` (já testado em UC-RECIPE-03/04), divergindo em silêncio.
- **Status: 🧪**

---

## UC-REPORT-04 · "Só finalizadas" vem LIGADO por padrão
- **Persona:** Wagner — abre o relatório sem mexer em nada e já vê só produção fechada.
- **Aceite:** Dado a URL sem o parâmetro `is_final` · Quando o controller monta os filtros ·
  Então `is_final` resolve pra `true` (ausência de param ≠ false).
- **Fonte:** SPEC.md DoD "Só finalizadas com default ligado".
- **Teste:** `Wave30ReportInertiaTest.php` — assert estrutural sobre o código do controller
  (`! request()->has('is_final') || request()->boolean('is_final')`).
- ⚠️ **O que este teste NÃO prova:** que uma requisição HTTP real, sem seed de ordem de
  produção no ambiente, retorna o payload com o filtro já aplicado. Isso é smoke
  (`RUNBOOK-report.md §3`), não Pest.
- **Regressão que defende:** trocar pra `request()->boolean('is_final')` sozinho, que resolve
  `false` quando o param está ausente — o oposto do DoD.
- **Status: 🧪** — do que ele mede: a expressão de default existe no código.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Custo **congelado** por ordem finalizada (`custoSnap`) — não existe ainda;
  entra com US-MANU-007.
- **[BACKLOG]** Exportar CSV/Excel/PDF do relatório — o Blade legado tem, o protótipo
  normativo não pede; decisão de escopo, não esquecimento.
- **[BACKLOG]** Filtro por local (`location_id`) — mesmo caso acima.

## Trilha do tempo
- 2026-09-03 · [F+C] US-MANU-002 (a mais barata da fila, decisão [M] 2026-09-02). 4 UC com
  teste Pest. Refs: UI-0013 · ADR 0264 G-1/G-2 · ADR 0104 · proibicoes.md §REGRA MESTRE.
- 2026-09-04 · [F+C] Barra de abas corrigida: "Configurações" apontava pra rota Blade legada
  (âncora crua, saía do SPA) e a aba "Insumos" não existia. [F] reportou clicando na aba e
  caindo na tela antiga. **Segunda ocorrência do mesmo defeito** — em 2026-09-03 a aba
  "Relatório" foi corrigida do mesmo jeito e a "Configurações" ficou pra trás na mesma leva,
  porque **nenhum UC cobre a barra de navegação** e nada guardava isso. A guarda agora existe:
  `Modules/Manufacturing/Tests/Feature/AbasTelasV2Test.php` (4 asserts; 3 provados por bite
  test contra cópia adulterada, o 4º usa o registry de rotas em runtime). Nenhum UC acima
  mudou de comportamento. ⚠️ O cutover da rota legada segue PENDENTE e é decisão [W].
