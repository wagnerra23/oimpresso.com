---
sessao: "_saida-06"
thread: "06 · CONN-O5 · apagar o legado Blade (depois do screenshot [W2])"
dono: "[CL]"
data: 2026-10-05
base_lida: wagnerra23/oimpresso.com@main 77af1c9d3c
---
# _saida-06

## O portão [W2]
- Ao abrir a thread não havia registro do [W2] (charter `pendente [W2]`, nenhum `_DECISOES-W` do Connector). A sessão parou e mostrou a tela em produção.
- Medido a 1280px: as células da tabela tinham padding 0 ("Tokens 24 h" e "Criado" colavam no mesmo pixel; a linha do WR Server lia "14517/10/2024") e a lupa da busca ficava sobre o texto. [W] pediu o conserto antes de aprovar: #8677 (mergeado e com deploy em 2026-10-05).
- Remedido em produção depois do deploy: 24px entre as duas colunas, lupa termina em x=52 e o texto começa em x=58.
- **[W] aprovou o screenshot em 2026-10-05** ("aprovado, pode seguir a thread 06"). Charter vira `status: live`.

## Entregue
- Removidos:
  - `Resources/views/clients/index.blade.php` — sem rota que a renderizasse desde a thread 04 (`ClientController::index` responde só `Inertia::render('Api/Index')`, sem flag nem fallback). Ainda chamava `action([ClientController::class, 'regenerate'])`, que não existe desde a thread 05.
  - `Resources/views/layouts/master.blade.php` — nenhum `@extends` o usava (`git grep` em `Modules/Connector`: 0).
  - `Resources/assets/sass/app.scss` — arquivo vazio, sem consumidor.
  - `Resources/assets/js/app.js` — 0 byte, sem consumidor.
  - Em `Resources/lang/*/lang.php` (16 idiomas, 49 linhas): as chaves `create_client`, `client_secret`, `documentation` e `regenerate_doc`, que só a Blade lia (`git grep` sem acesso dinâmico a `connector::lang.`). Apontado pela sessão "Fechar o placar da Connector 09".
  - `Http/Controllers/ConnectorController.php` — devolvia `view('connector::index|create|show|edit')`, views que nunca existiram.
  - `Routes/web.php`: `GET /connector/api` (a única rota do `ConnectorController`). Logado, em produção, ela respondia **500** antes deste PR.
  - `phpstan-baseline.neon`: as 8 entradas cujo `path` era o `ConnectorController`.
- Mantidos de propósito (no prefixo, mas vivos):
  - `Resources/lang/**` com as chaves `connector_module`, `connector` e `clients` — o `DataController` as usa no menu.
  - `Resources/assets/.gitkeep` e `Resources/views/.gitkeep`.
- Docs: charter `live` + R8 apontando `Index.tsx` (`is_demo`); `RUNBOOK-connector-index.md` §12 registra a F5; `SUPERFICIE.md` regenerado (`module-surface.mjs Connector --write`).

## Quem ainda usava (varredura)
- `ConnectorController`: 1 uso fora de docs/baselines — a rota `/api` deste PR. Saiu também do `SCOPE.md` (`contains[]`), e o `catalog.json` foi regenerado (−1 nó, −1 aresta); `catalog-graph --check` rc=0. Na 1ª leitura eu tinha concluído que o catálogo não mudava — errado, o CI pegou.
- `clients/index.blade.php` / `connector::clients.index`: 0 renderizações; só citações em docblock (`ClientController`, `ClientControllerBaselineTest`) e no RUNBOOK.
- `GET /connector/api` exato: nenhum link no menu (`DataController` aponta `/connector/client` desde a thread 05). As rotas `connector/api/*` são do `Routes/api.php` (Bearer) e não mudam. O teste de ≥20 rotas no prefixo continua valendo; o KPI "Endpoints publicados" segue 78: o `ClientController` só conta `connector/api/` com a barra final, então a rota web `connector/api` nunca entrava na conta. _Errata 2026-10-05: a 1ª versão deste recibo dizia que o KPI perdia 1 — não medido; a medição em produção depois do deploy deu 78._

## Provas do json, medidas no branch
1. `Modules/Connector/Resources/views/clients/index.blade.php` ausente — ✅
2. `Modules/Connector/Http/Controllers/ConnectorController.php` ausente — ✅

## Divergência com a ficha (`06-legado.md`)
A ficha pede também `grep -r "connector::" Modules/` vazio. Isso não fecha sem sair do escopo:
- `DataController` (menu) usa `connector::lang.*` — legítimo;
- `Http/Controllers/Api/BusinessController.php` tem 4 métodos sem rota que devolvem `view('connector::*')` inexistentes, mas `Api/` está em `nao_toca` desta thread;
- os demais são comentários. As provas do bloco json (as duas acima) estão verdes.

## Entregue 1 de 1 (Connector/06).

## Medido em produção depois do deploy (2026-10-05, merge `dd525fd6d8b9`)
- `GET /connector/api` logado: 500 → **404**; sem sessão: 302 → 404.
- `GET /connector/client` logado: 200, componente `Api/Index`, 6 clients; sem sessão: 302 → `/login`.
- `GET /connector/install` sem sessão: 302 → `/login`.
- `/connector/api/business-location` JSON sem token: 401 (API do Delphi intacta).

## NÃO MEDI
Pest local (regra: só CI/CT 100); o veredito é o CI do #8696.

## PR
(no corpo do PR)
