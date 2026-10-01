---
sessao: "_saida-01"
thread: "01 · CONN-O1 · prova mínima: teste + charter + casos (nasce vermelho)"
dono: "[CL]"
data: 2026-09-30
base_lida: wagnerra23/oimpresso.com@main 4bfd227f4
---
# _saida-01

## Entregue
- `Modules/Connector/Tests/Feature/ApiClientsPanelTest.php` (cópia de `cowork-inbox/connector/`, adaptada — abaixo)

**Não entregue nesta thread, por decisão técnica da sessão-mãe:** `Index.charter.md` e
`Index.casos.md` seguem só em `cowork-inbox/connector/` como fonte e entram na **04**, junto com
o `Index.tsx`. Motivo na errata (1). O contrato também é da 04, como a ficha já dizia.

Nada em `Modules/Connector/Http/` (o `nao_toca`). Nenhum código de produção mudou.

## Provas do json, medidas no branch
1. `Modules/Connector/Tests/Feature/ApiClientsPanelTest.php` existe — ✅
2. `Modules/Connector/Resources/js/Pages/Api/Index.charter.md` existe — ❌ **de propósito** (errata 1).
   Por isso o placar NÃO marca a thread como `feito`; ela fica `em curso` até o Cowork mover a prova.

## Errata pro Cowork (não editei o playbook)
1. **A prova "charter em `Pages/Api`" da thread 01 é incompatível com a IT2.**
   `scripts/design/integrity-check.mjs` IT2/IT2b (duro desde 2026-07-09) reprova charter sem
   `.tsx` irmão e `component:` apontando pra arquivo inexistente. Com o charter na 01, o check
   `governance script tests` ficava vermelho no main para todos os PRs até a 04 (medido no 1º
   commit do #8336). Um `.tsx` stub seria burla. **Pedido:** mover charter + casos (e a prova
   `${MPAGES}/Api/Index.charter.md`) para as provas da thread 04.
2. **Rota.** O teste usa `/connector/api` como a lista de clients, mas no `main` essa rota é
   `ConnectorController::index`; a lista é `/connector/client` (`ClientController::index`, o que
   o `ClientControllerBaselineTest` testa). UC-CONN-01/02/03/09/16 podem cair por isso, e não pelo
   achado que nomeiam — decidir na 04 qual rota é a tela.
3. **Formato dos UCs.** O `casos.md` usa `**UC-CONN-NN — …**` (negrito), não `## UC-…`; o
   casos-gate não os enxerga como UC (G-2/G-5 não cobram citação nem `Status:`). Converter antes
   da 04. Ajustes que a cópia precisou e a 04 vai precisar de novo: `related_prototype` em forma
   `n/a (…)` (o valor atual reprova no schema do charter), `blueprint_cowork` com o path real
   `prototipo-ui/cowork/Wagner/connector-page.jsx`, e `owner` + `last_run` no casos (G-5).

## Adaptação no teste (o que não é byte a byte)
O repo não tem `Business::factory()` (só `UserFactory`): o `setUp` original estourava antes de
qualquer UC. Troquei por tenant fictício 98 (`seededTenant()`) e adversário 99
(`seededSupportClientTenant()`), ADR 0358, com `username` único e as permissões
`superadmin`/`connector.access` por `firstOrCreate`. As asserções ficaram como o Cowork escreveu.

## Vermelho por desenho × required
Padrão do módulo, o mesmo do `ClientControllerBaselineTest`: guard no `setUp` que faz
`markTestSkipped` em SQLite e com schema incompleto. A lane do Connector é `Modules Pest`
(SQLite `:memory:`, sem migrate) e não está entre os contexts required; lá o arquivo inteiro faz
skip. Nenhum required roda este arquivo. Os vermelhos nomeados (UC-CONN-12, 14, 15 + catálogo
`connector.access`) só aparecem num run MySQL.

## NÃO MEDI
Os vermelhos reais: Pest MySQL só no CT 100, que esta sessão não toca.

## PR
[#8336](https://github.com/wagnerra23/oimpresso.com/pull/8336)
