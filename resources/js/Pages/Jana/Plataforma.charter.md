---
id: resources-js-pages-jana-plataforma-charter
page: /ia/superadmin/metas
component: resources/js/Pages/Jana/Plataforma.tsx
owner: wagner
status: draft
last_validated: "2026-09-02"
parent_module: Jana
parent_adr: memory/decisions/0093-multi-tenant-isolation-tier-0.md
related_prototype: prototipo-ui/cowork/jana-telas-novas.jsx
related_adrs: [52, 93, 94, 104, 180, 182]
related_charters:
  - resources/js/Pages/Jana/Index.charter.md
related_us: [US-COPI-148]
runbook: memory/requisitos/Jana/RUNBOOK-plataforma.md
related_casos:
  - resources/js/Pages/Jana/Plataforma.casos.md
alcance:
  rota: /ia/superadmin/metas
  rota_nome: jana.superadmin.metas
  permission: jana.superadmin
  menu_hook: Modules/Jana/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: jana_module
tier: A
charter_version: 1
permissao: jana.superadmin
---

# Page Charter — `/ia/superadmin/metas` (aba Plataforma da área Jana)

> **Status:** `draft` — nasceu em 2026-09-02 como a 6ª aba da paridade com a âncora
> (`jana-merge.jsx` §`JmTabs`, só com `jana.superadmin`). Vira `live` com o screenshot pós-merge
> aprovado por [W] (R1). **Tier A** porque é a única tela da área que lê cross-tenant.

## Mission

A visão de **plataforma** da Jana: metas com `business_id NULL` e as metas de **todos** os clientes,
listadas cruas, mais o bloco de instalação do módulo. Substitui o Blade AdminLTE
(`superadmin/metas.blade.php`) sem mudar o que ele mostrava — muda só que agora a tela **diz** o que
não existe (a agregação) e o gate do menu passou a concordar com o da rota.

## Goals

- **Aba na barra ÚNICA da área** — ghost `plataforma` (label `Plataforma`, `/ia/superadmin/metas`),
  6ª posição, **só** para quem passa em `DataController::podeVerPlataforma()` — o MESMO gate real de
  `SuperadminController::metas` (`hasPermissionTo('jana.superadmin')` ou `user_type` superadmin, P0
  #6421). `JanaSubNav` sobe `maxVisible` para 6: a âncora mostra as seis inline.
- **Metas da plataforma** (`business_id NULL`): Meta (nome + slug) · Unidade · Origem
  (`manual` → *cadastro manual*, copy da âncora; outros valores do enum ficam crus).
- **Metas de clientes** (cross-business, `withoutGlobalScope` deliberado — o caso legítimo do ADR
  0093): Business (`#id` + nome da empresa) · Meta · Unidade · Período atual (`dd/mm–dd/mm`) ·
  Última apuração (`nunca apurada` + linha `archived` quando não há). Subtítulo
  `cross-business · N metas em M empresas` derivado do payload.
- **Instalação do módulo**: contagens de migrations · seeders · permissões **derivadas do
  disco/registry** no servidor (a âncora traz `21 · 4 · 24` fixos — e 24 já estava errado: são 22),
  situação por `System::getProperty('jana_version')`, e os botões *Rodar atualização* /
  *Desinstalar módulo* (confirmação antes) **só** com `can('superadmin')` real, que é o gate de
  `BaseModuleInstallController` — mais estreito que `jana.superadmin`.
- Copy literal da âncora pinada em `prototipo-ui/contrato/jana-plataforma.contract.json`.

## Non-Goals

- ⛔ Agregar (somar/contar/agrupar) metas de clientes — não existe no controller; somar na tela
  seria inventar total de plataforma no cliente. A nota de rodapé declara a pendência.
- ⛔ Editar/criar meta da plataforma — Blade `/ia/metas/*` (MetasController).
- ⛔ Instalar (`/ia/install` POST) — o fluxo canônico segue `/manage-modules`.

## UX targets

- 1280px sem scroll horizontal; duas `DataTable` shared (PT-01) com paginador de uma página.
- Dark mode por token; cards de contagem em `bg-card` + `border-border`.

## Anti-hooks

- ⛔ **Gate do menu diferente do gate da rota.** Até 2026-09-02 o dropdown usava
  `can('jana.superadmin')`, que o `Gate::before` devolve `true` para todo `Admin#{biz}` — o dono
  via o link e tomava 403. Aba e dropdown usam `podeVerPlataforma()`, espelho do controller.
- ⛔ **Repetir o alerta da âncora** (*"Gate desta tela não separa dono de empresa de superadmin"*).
  Era verdade em 27/08 e **deixou de ser em 28/08** (#6421). Copy literal que afirma bug fechado é
  mentira com selo de autoridade — fica registrada no contrato como caducada, não na tela.
- ⛔ **Bullets do modal da âncora** (*"Roda como job no servidor"*, *"Ambiente atual: CT 100
  (Proxmox)"*): as rotas de `/ia/install` rodam **síncronas em GET** e o app web vive no
  **Hostinger** (ADR 0062). Ficam fora; o parágrafo do modal (o que o rollback apaga) fica.
- ⛔ **Contagem digitada** no bloco de instalação — vem do servidor; número escrito à mão apodrece
  (§5 2026-07-17).
- ⛔ `<Link>` do Inertia para `/ia/install/*` — são GET que rodam migrations e redirecionam.

## Skills relevantes

`multi-tenant-patterns` (Tier A) · `mwart-process` · `comparar-design-prod`

## Charter version log

- v1 (2026-09-02) — Tela nasce da paridade das abas (handoff 2026-08-31 §Paridade Painel; fecha
  *"abas: protótipo 6 × prod 3"*). `SuperadminController@metas` → Inertia (gate intacto);
  `DataController::podeVerPlataforma()`; `JanaSubNav maxVisible 6`; Blade apagado. Contrato:
  UC-PLAT-00..04 em `Plataforma.casos.md`.
