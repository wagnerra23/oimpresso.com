---
page: /c/blogs
component: resources/js/Pages/Site/Blogs.tsx
related_prototype: n/a (herda PT-01 Lista; grid de cards de post — assinatura de lista presente, mas é lista pública fora do ERP)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Cms
related_adrs: [114, 101, 94]
tier: B
charter_version: 1
---

# Page Charter — /c/blogs (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Cms/Http/Controllers/CmsController@getBlogList` (rota pública `GET /c/blogs`, sem auth). Lista os posts de blog publicados. `SiteLayout` (sem AppShellV2). PT-01 Lista: grid de `<article>` cards com busca client-side.

---

## Mission
Índice público do blog (marketing de conteúdo/SEO). O visitante navega pelos artigos publicados num grid de cards, busca por título/descrição client-side, e clica pra ler cada post (`/c/blog/{slug}-{id}`). Backend entrega os posts (`CmsPage type=blog`, `is_enabled=1`, ordenados por `priority`).

---

## Goals — Features (faz)
- Grid responsivo de cards de post (`grid sm:grid-cols-2 lg:grid-cols-3`), cada `<article>` com capa lazy, título, descrição (`line-clamp-3`), data (`pt-BR`) e link "Ler mais →".
- Busca client-side (`useMemo`) sobre título + `meta_description`.
- Estados vazios: "Em breve: nossos primeiros posts" (sem posts) e "Nenhum artigo encontrado" (busca sem resultado).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não pagina server-side — traz todos os posts publicados e filtra no cliente (inferência pendente de Wagner; ok pra volume baixo).
- ❌ Não é a listagem administrativa PT-01 do ERP (sem AppShellV2, sem ações por linha, sem `business_id` scope — é vitrine pública).
- ❌ Não edita/publica posts (isso vive no admin `cms-page`).

---

## UX targets
- p95 < 800ms (página pública SEO) ; cabe em 1280px (ROTA LIVRE) ; `SiteLayout` (sem AppShellV2).

---

## Automation hooks (faz)
- `OtelHelper::spanBiz('cms.blog.list', …)` instrumenta a listagem no payload.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não muta nada em `GET`.
- ❌ Não faz polling nem live-refresh da lista.
- ❌ Não envia a query de busca ao servidor (busca 100% client-side, sem tracking).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Decidir se paginação server-side será necessária quando o volume de posts crescer
