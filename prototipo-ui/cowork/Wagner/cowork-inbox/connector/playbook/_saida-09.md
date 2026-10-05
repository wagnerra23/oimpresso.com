---
sessao: "_saida-09"
thread: "09 · Aba Documentação (PR-b da 04)"
dono: "[CL]"
data: 2026-10-05
base_lida: wagnerra23/oimpresso.com@main cb1fe1d6f4
---
# _saida-09

## Resultado
A aba Documentação **já está no `main`**: entrou pelo PR #8379 (`09e7f298fd`, "abas Documentação,
Saúde e Módulo do painel (thread 04 PR-b)", mergeado em 2026-10-01). A ficha 09 foi aberta para
fechar a pendência do `_saida-05` ("a aba Documentação ainda não existe; o menu não tem link") e o
#8379 fechou as duas coisas. Nenhum arquivo de código foi alterado nesta thread — este recibo é a
entrega inteira.

## O que está no main (lido em `cb1fe1d6f4`)
- **Aba:** `Modules/Connector/Resources/js/Pages/Api/Index.tsx` abre a aba por `?aba=docs`
  (`PageHeaderTabs`); o conteúdo é `Api/_components/ConnectorAbas.tsx`.
- **Catálogo:** `ClientController::index` entrega as props `endpoints` e `endpoints_count`, lidas das
  rotas registradas com prefixo `connector/api/`. Nenhuma linha escrita à mão.
- **Menu:** `Modules/Connector/Http/Controllers/DataController.php`, ghost
  `['key' => 'docs', 'label' => 'Documentação', 'href' => '/connector/client?aba=docs']`.
- **Caso:** UC-CONN-19 em `Api/Index.casos.md` ("o catálogo da aba Documentação é esse mesmo
  conjunto — toda linha existe nas rotas"), citado por dois testes em
  `Modules/Connector/Tests/Feature/ApiClientsPanelTest.php`:
  `test_api_registra_pelo_menos_20_rotas_no_prefixo_connector_api` e
  `test_catalogo_da_documentacao_sao_as_rotas_registradas`.

## Prova de execução — lane MySQL
`connector-pest.yml` ("Connector · Pest (MySQL)"), run
[36868265003](https://github.com/wagnerra23/oimpresso.com/actions/runs/36868265003), `push` em
`main`, `32f86c84b0` (2026-10-01). O #8379 é ancestral desse commit
(`git merge-base --is-ancestor 09e7f298fd 32f86c84b0` → sim). Trecho do log:

```
PASS  Modules\Connector\Tests\Feature\ApiClientsPanelTest
  ✓ catalogo da documentacao sao as rotas registradas                    0.43s
  ✓ api registra pelo menos 20 rotas no prefixo connector api            0.20s
Tests:    194 passed (725 assertions)
```

Sumário JUnit do mesmo run, `ApiClientsPanelTest`: 228 assertions. O step do Pest executou (os dois
testes aparecem nominalmente com ✓); não é skip-as-pass.

**O run vale para o `main` de hoje:** `git log 32f86c84b0..origin/main -- Modules/Connector
tests/Feature/Connector .github/workflows/connector-pest.yml` devolve vazio (medido em `cb1fe1d6f4`).

## Quem enxerga o item do menu (medido no código)

| | antes do `_saida-05` (`75addd7832^`) | hoje (`cb1fe1d6f4`) |
|---|---|---|
| Item | "Documentação" → `/docs` | "Documentação" → `/connector/client?aba=docs` |
| Superadmin | via, se o módulo estivesse instalado | vê, se o módulo estiver instalado |
| Não-superadmin com `connector_module` na assinatura | **via** o dropdown, só com esse item | **não vê** (o `modifyAdminMenu` retorna antes de montar o menu) |
| Abrir a URL direto, sem superadmin | — | `ClientController::index` responde **403** |

Ou seja: a documentação voltou ao menu, mas **só para superadmin**. O não-superadmin de um negócio
com o pacote, que antes tinha o link `/docs`, continua sem link e, se digitar a URL da aba, recebe
403. É a consequência da decisão [W] D1/D5 aplicada no `_saida-05` (painel, menu e documentação são
de superadmin); esta thread não muda isso. O teto de negócios afetados medido no `_saida-05` foi
**1 e 164** (assinatura vigente citando `connector_module`, sem medir se o valor está ligado).

## Placar
`09 [feito]` — entregue 1 de 1, pela prova do json ("aba Documentação no menu do Connector, UC no
recibo"): menu com o ghost Documentação, UC-CONN-19 verde na lane MySQL, run citado acima.
O `00-INDICE.md` não foi editado.

## NÃO MEDI
- Render da aba em produção (screenshot). A prova aqui é o teste de contrato das props, não o DOM.
- O que a URL antiga `/docs` serve hoje.
- A lane `connector-pest` é **advisory**: isto prova que o teste passa no MySQL, não que bloqueia merge.
