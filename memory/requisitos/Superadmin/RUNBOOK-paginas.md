---
id: requisitos-superadmin-runbook-paginas
title: "RUNBOOK — /superadmin/frontend-pages (Páginas do site · Inertia, cutover 2026-10-27)"
module: Superadmin
tela: superadmin/Paginas/Index
owner: W
status: ativo
last_validated: "2026-10-09"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
spec_ref: memory/requisitos/Superadmin/SPEC.md
---

# RUNBOOK — `/superadmin/frontend-pages` (Páginas do site, Inertia/React)

F1 do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) da
thread **Superadmin/08**, decisão [W] D-PAG de 2026-10-07 (*migrar para React*, seguindo separado
do Cms, D1). A lista era `superadmin::pages.index` (cards com o HTML cru) e o formulário eram duas
páginas Blade (`pages.create`/`pages.edit`).

- **Fonte de design:** não há `sa-paginas` no protótipo (`superadmin-page.jsx` só desenha negócios,
  assinaturas, pacotes, comunicador e config). A tela herda o **PT-01 Lista** + drawer **PT-02**.
- **Page:** `Modules/Superadmin/Resources/js/Pages/superadmin/Paginas/Index.tsx`.
- **Rotas (não mudam):** `Route::resource('/superadmin/frontend-pages')`. A página pública
  `/page/{slug}` (`showPage`, layout do site) **não** entra nesta thread.

## 1. Chave de entrada

Até 2026-10-09 a tela nova respondia só com `?tela=nova`. **Cutover (F5) em 2026-10-27**, pedido pelo
[W] na migração das áreas: `index()` sempre devolve a tela React, as Blades `pages.index`,
`pages.create` e `pages.edit` foram apagadas e o resource passou a `->except(['create','edit','show'])`
(`show` nunca teve método — GET `/frontend-pages/{id}` dava 500). A `pages/show.blade.php` fica: é da
página pública `/page/{slug}`, outra migração.

## 2. O que mudou no backend

- `index()` → `Inertia::render('superadmin/Paginas/Index')` com a prop `paginas`
  em `Inertia::defer` (id, título, slug, ordem, visível, resumo sem tag, conteúdo para edição).
- `store`/`update`/`destroy`: quando a requisição é Inertia, slug repetido vira erro de validação
  no campo `slug` e o sucesso volta para a lista. Fora do Inertia, `store`/`update` redirecionam
  para a lista e `destroy` devolve JSON, como antes.

## 3. Tier 0 — invariantes

- `superadmin_frontend_pages` é conteúdo **global** do site, sem `business_id` (ADR 0093 §exceções
  Superadmin). A trava é o middleware `superadmin` + `can('superadmin')`.
- A tela **não** renderiza o HTML da página: mostra resumo sem tag. O HTML só é impresso na página
  pública, que é conteúdo do próprio superadmin.

## 4. Smoke prod (R1)

Após o deploy: abrir `https://oimpresso.com/superadmin/frontend-pages` como superadmin, ver a lista
carregar e o drawer abrir em "Nova página"; `/superadmin/frontend-pages/create` deve dar 404.
**Não** salvar alteração em página real (Termos/Privacidade têm efeito legal).
