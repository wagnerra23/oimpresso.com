---
sessao: "_saida-13"
thread: "13 · Projects: exigir permissão do módulo + tirar decisões do charter (D10 · D11)"
dono: "[CL]"
data: 2026-10-09
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main b7715dda
---
# _saida-13

## Entregue
Cumpre as decisões [W] de 2026-10-07: D10 *"exigir permissão do módulo Forja"* e D11 *"tirar do charter"*.

- **D10:** `Admin\ProjectsController` passou a exigir `can:jana.mcp.usage.all` no construtor, além do
  `auth`. Vale para as 4 ações: lista, criação, detalhe e decompose. É a mesma permissão de
  Aprovações, Trabalho, Roadmap, Team e Tasks, porque o `DataController` declara que as telas da
  Forja usam `jana.mcp.usage.all`. Sem ela, a resposta é 403. Antes bastava estar logado.
- **D11:** o `ProjectShow.charter.md` não cita mais as decisões ligadas ao project (Mission, Goals e o
  link para `/ads/admin/decisoes/{id}`). Um Non-Goal novo registra que a fonte morreu com a ADR 0363.
  O `Projects.charter.md` também perdeu o "agrupa decisões + ADRs" da Mission.
- Os dois charters ganharam a linha **Acesso** e o Non-Goal "não abre para quem só está logado".

## Provas
Teste novo `Modules/Forja/Tests/Feature/ForjaProjectsAcessoTest.php`, no tenant 98:

| UC | o que prova |
|---|---|
| UC-ADPJ-05 | sem a permissão, a lista e o POST de criação dão 403 e nada é gravado. Com ela, a lista dá 200 (controle positivo) |
| UC-ADPS-04 | sem a permissão, o detalhe e o decompose dão 403. A part continua uma e o `updated_at` do project não muda. Com ela, o detalhe dá 200 |
| UC-ADPS-05 | o charter do detalhe não cita `/ads/admin/decisoes` nem promete decisões |

O teste está na allowlist do `forja-pest.yml` (lane `PHP / Pest (Forja · MySQL)`).
`test-lane-coverage.mjs --json` não o lista entre os arquivos fora de lane.

Os 3 testes antigos que passam pela rota (`AdsAdminProjectsContratoTest`,
`AdsAdminProjectShowContratoTest`, `ProjectDecomposeTenantTest`) logavam com usuário **sem
permissão nenhuma**. Com a trava, cairiam em 403. Passaram a conceder `jana.mcp.usage.all`, e o
cache do Spatie é limpo antes, pelo motivo da FK 1452 registrado na `_saida-11`.

**Não rodei Pest:** é proibido fora do CT 100 e do CI. O veredito é o run da lane neste PR.
Rodei só `php -l` nos 5 PHP tocados (sem erro) e o `casos-coverage-guard` (sem violação nova).

## Fora do prefixo, declarado
O prefixo da thread cobre os 2 charters e o teste. Para cumprir a D10 foi preciso também mudar
`ProjectsController.php`, os 3 testes antigos acima, os 2 `.casos.md` (UC novos, e os dois
`[BACKLOG]` respondidos pelo [W] riscados) e o `forja-pest.yml`. A thread 11 seguiu o mesmo caminho.

## O que não está provado ou ficou de fora
- **`ProjectShow.tsx`** ainda tem o bloco "Decisões geradas neste project". Ele nunca renderiza,
  porque `decisions` chega sempre vazio. Não toquei porque a tela não está no prefixo. Tirar o bloco,
  e a chave `decisions` do `ProjectService::findDetail`, fica para a próxima mexida na tela.
- **Admin da empresa:** o papel `Admin#{business_id}` passa pelo `Gate::before`, igual à `_saida-11`.
  O dono da empresa vê Projects sem marcar a permissão.
- **Menu:** não conferi se a entrada de Projects no menu já esconde para quem não tem a permissão.
  Se não esconder, o usuário vê o link e recebe 403.

## Espelho Cowork
Este arquivo ainda não subiu para o Claude Design: escrever lá exige opt-in do [W] (ADR 0315). Como
arquivo novo, nasce "nunca verificado" e não trava o required do espelho.
