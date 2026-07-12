---
page: /c/page/{page}
component: resources/js/Pages/Site/Page.tsx
related_prototype: n/a (página de conteúdo CMS bespoke — detalhe de artigo, não segue um dos 5 Padrões de Tela do ERP)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Cms
related_adrs: [114, 101, 94]
tier: B
charter_version: 1
---

# Page Charter — /c/page/{page} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Cms/Http/Controllers/CmsPageController@showPage` (rota pública `GET /c/page/{page}`, sem auth). Renderiza uma página CMS estática (termos, sobre, contato etc.) por título. `SiteLayout` (sem AppShellV2).

---

## Mission
Exibe uma página institucional/estática do CMS (ex.: termos de uso, política, sobre) resolvida pelo título da URL. O visitante lê conteúdo curado — o HTML já vem sanitizado server-side (`SiteContentService::sanitizeHtml`, HTMLPurifier) e é injetado via `dangerouslySetInnerHTML`. Se a página não existe, o controller faz `abort(404)`; a UI ainda tem fallback defensivo pra prop nula.

---

## Goals — Features (faz)
- Renderiza título, `feature_image_url` (lazy), e conteúdo HTML sanitizado dentro de um `<article>` tipografado (`prose`).
- Define `<Head title>` + `<meta name="description">` a partir de `meta_description` (SEO).
- Empty/erro: estado "Página não encontrada" com link de volta pra `/` quando `page` vem nula.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não é uma listagem — renderiza UMA página por vez (`<article>` de detalhe, não grid/tabela).
- ❌ Não edita conteúdo (edição vive no admin `cms-page`), aqui é leitura pública.
- ❌ Não escopa por `business_id` (conteúdo institucional global, não multi-tenant).
- ❌ Não usa AppShellV2/sidebar (é `SiteLayout` público).

---

## UX targets
- p95 < 800ms (página pública, latência sensível pra SEO) ; cabe em 1280px (ROTA LIVRE) ; `SiteLayout`.

---

## Automation hooks (faz)
- `OtelHelper::spanBiz('cms.page.render', …)` instrumenta o render no payload.
- Sanitização de HTML server-side antes de serializar pro Inertia (defesa XSS, o front usa `dangerouslySetInnerHTML`).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não muta nada em `GET`.
- ❌ Não faz polling nem revalida conteúdo após load.
- ❌ Não renderiza HTML não-sanitizado (a sanitização é obrigatória no backend — anti-XSS).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar 404 real em prod pra título inexistente
