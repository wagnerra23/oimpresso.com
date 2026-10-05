---
sessao: "_saida-06"
thread: "06 · CONN-O5 · apagar o legado Blade (depois do screenshot [W2])"
dono: "[CL]"
data: 2026-10-05
base_lida: wagnerra23/oimpresso.com@main 0f90289704
---
# _saida-06

## Pré-condição
- Thread 04 em produção: `/connector/client` responde Inertia `Api/Index` desde o #8379.
- Screenshot aprovado por [W2]: tirado em produção em 2026-10-05 (aba "API clients", 1280×800 e
  800×600, login superadmin) e aprovado por [W] no chat no mesmo dia.

## Apagado (lista do §CONN-O5 do pedido absorvido)
- `Resources/views/clients/index.blade.php` — não era renderizada desde a thread 04
  (`ClientController::index` é Inertia). Nenhum `view('connector::clients.index')` no código.
- `Resources/views/layouts/master.blade.php` — só servia a view acima; nenhuma referência.
- `Http/Controllers/ConnectorController.php` (classe inteira) — devolvia `connector::index`,
  `create`, `show`, `edit`, views que nunca existiram. A única rota que apontava para ela era
  `GET /connector/api`, que dava 500.
- `Resources/assets/js/app.js` e `Resources/assets/sass/app.scss` — 0 byte, sem referência em
  build nenhum.
- Chaves de idioma mortas nos 16 idiomas: `create_client`, `client_secret`, `documentation` e
  `regenerate_doc` (só no `en`). 49 linhas.

## O que fica, e por quê (diferenças em relação à lista do pedido)
- **`clients` fica no lang.** O pedido a marcava como morta, mas o `DataController` usa
  `connector::lang.clients` no item do menu. Ficam `connector_module`, `connector` e `clients`.
- **`documentation` sai.** O pedido a mantinha, mas desde o #8379 o menu usa o rótulo literal
  "Documentação" no ghost; nenhum código lê a chave.
- **`GET /connector/api` vira redirecionamento** para `/connector/client` (`Routes/web.php`). Era o
  endereço do ghost antigo "API Clients"; quem tiver o link salvo cai no painel em vez de 500.
  A API em `connector/api/*` (`Routes/api.php`) não muda: o redirect casa só o caminho exato.
- **Fora do prefixo desta thread, não tocados:** `ClientController::create/show/edit/update` (a
  thread 05 já fez create/show/edit redirecionarem ao painel; `update` segue um stub vazio) e o
  `Route::resource('/client', ...)` com os 7 verbos. Reduzir para `index/store/destroy` fica para
  quem pegar o `ClientController`.

## Arquivos derivados atualizados no mesmo PR
- `phpstan-baseline.neon`: 8 entradas do `ConnectorController` removidas (o arquivo não existe mais).
- `memory/requisitos/Connector/SCOPE.md`: `ConnectorController` saiu do `contains`.
- `memory/requisitos/Connector/SUPERFICIE.md`: regerado (`module-surface.mjs Connector --write`),
  126 → 121 arquivos; a seção "Views (Blade)" sumiu.
- `memory/governance/catalog.json`: regerado (`catalog-graph.mjs --write`); `--check` rc=0.

## Provas do json, medidas no branch
1. `Modules/Connector/Resources/views/clients/index.blade.php` ausente — ✅
2. `Modules/Connector/Http/Controllers/ConnectorController.php` ausente — ✅

## Provas da ficha e do pedido
- `blade-migration-census.mjs`: o Connector não aparece mais na tabela (0 Blade).
- `grep -r "connector::" Modules/` **não fica vazio**, e não vai ficar nesta thread:
  - `DataController` usa `__('connector::lang.*')` (tradução do menu, não view);
  - `Api/BusinessController` ainda tem 4 `view('connector::...')` em métodos sem rota (só
    `saveBusiness` é roteado em `Routes/api.php`). `Http/Controllers/Api/` está no `nao_toca`
    desta thread;
  - o resto são comentários (`ClientController`, testes, charter).
- `route:list --path=connector` **não rodado**: PHP não roda localmente (proibicoes §Ambiente).

## Corte (cutover)
Não é cutover: nenhuma das Blades apagadas era servida. O único endereço que mudou de resposta
foi `GET /connector/api`, de 500 para redirecionamento.

## Pendente
- Charter `Api/Index.charter.md` ainda diz que a Blade "sai na thread 06" e está `status: draft`.
  Com o screenshot aprovado, vira `live` (passo do checklist pós-merge do pedido). Fora do prefixo.
- `RUNBOOK-connector-index.md` cita o `ConnectorController` como estado da época; fato datado,
  não reescrito.

## NÃO MEDI
Pest e PHPStan (CT 100/CI). Nenhum teste do módulo chama `/connector/api` web, a Blade ou as
chaves de idioma removidas (`git grep` no repo).

## Placar
`06` pelas duas provas `ausente` do json. O `00-INDICE.md` não foi editado.
