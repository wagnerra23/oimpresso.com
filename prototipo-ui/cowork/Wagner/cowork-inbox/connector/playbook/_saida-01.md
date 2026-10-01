---
sessao: "_saida-01"
thread: "01 · CONN-O1 · prova mínima: teste + charter + casos (nasce vermelho)"
dono: "[CL]"
data: 2026-09-30
base_lida: wagnerra23/oimpresso.com@main 4bfd227f4
---
# _saida-01

## Entregue
Cópia de `cowork-inbox/connector/` para o módulo, como a ficha manda (o contrato **não** veio — é da 04):

- `Modules/Connector/Tests/Feature/ApiClientsPanelTest.php`
- `Modules/Connector/Resources/js/Pages/Api/Index.charter.md`
- `Modules/Connector/Resources/js/Pages/Api/Index.casos.md`

Nada em `Modules/Connector/Http/` (o `nao_toca`). Nenhum código de produção mudou.

## Provas do json, medidas no branch
1. `Modules/Connector/Tests/Feature/ApiClientsPanelTest.php` existe — ✅
2. `Modules/Connector/Resources/js/Pages/Api/Index.charter.md` existe — ✅

## Adaptações na cópia (o que não é byte a byte, e por quê)
- **Teste:** o repo não tem `Business::factory()` (só `UserFactory`), então o `setUp` original
  estourava em todos os casos antes de chegar a qualquer UC — vermelho sem nome. Troquei por
  tenant fictício 98 (`seededTenant()`) e adversário 99 (`seededSupportClientTenant()`), ADR 0358,
  com `username` único e as permissões `superadmin`/`connector.access` criadas por `firstOrCreate`.
  Acrescentei o mesmo guard de driver/schema do `ClientControllerBaselineTest`. As **asserções
  ficaram como o Cowork escreveu.**
- **Charter:** `related_prototype` não passava no schema (`^(n/a…|prototipo-ui/…)$`); virou
  declaração `n/a (herda PT-01 + PT-04 …)`. Não promovi o blueprint a âncora (§5 2026-09-09:
  promoção é decisão [W]). `blueprint_cowork` apontava `prototipo-ui/cowork/connector/`, que não
  existe; corrigido para `prototipo-ui/cowork/Wagner/connector-page.jsx`.
- **Casos:** acrescentei `owner: wagner` + `last_run: "2026-09-30"` (G-5 do casos-gate exigia).

## Como a thread "nasce vermelha" sem travar o merge
A ficha: *"se a lane do módulo for required, marcar os vermelhos como skip"*. A lane do Connector
é `Modules Pest` (matrix, SQLite `:memory:` sem migrate) e **não** está nos contexts required
do baseline. Lá o arquivo inteiro faz **skip** pelo guard de driver (como todo teste de DB do
módulo). Os vermelhos nomeados (UC-CONN-12, 14, 15 + catálogo `connector.access`) só aparecem
num run MySQL.

## Pendente / NÃO MEDI
- **NÃO MEDI** os vermelhos reais: Pest MySQL só no CT 100, que esta sessão não pode tocar.
  Quais casos caem de fato fica para o primeiro run MySQL.
- **Errata pro Cowork (não editei o playbook):** o teste usa `/connector/api` como lista de
  clients, mas no `main` `/connector/api` é `ConnectorController::index`; a lista é
  `/connector/client` (`ClientController::index`, o que o baseline testa). Os UC-CONN-01/02/03/09/16
  podem cair por isso, não pelo achado que nomeiam — decidir na 04 (tradução) qual rota é a tela.
- O formato dos UCs no `casos.md` é `**UC-CONN-NN — …**` (negrito), não `## UC-…`; o casos-gate
  não os enxerga como UC, então G-2/G-5 não cobram citação nem `Status:` por caso.
- `Index.tsx` ainda não existe (vem na 04): a tela fica fora do denominador até lá.

## PR
(preenchido no corpo do PR)
