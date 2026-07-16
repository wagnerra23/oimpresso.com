---
page: /c/blog/{slug}-{id}
component: resources/js/Pages/Site/BlogPost.tsx
related_prototype: n/a (detalhe de post de blog bespoke — não segue um dos 5 Padrões de Tela do ERP)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Cms
related_adrs: [114, 101, 94]
tier: B
charter_version: 1
---

# Page Charter — /c/blog/{slug}-{id} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Cms/Http/Controllers/CmsController@viewBlog` (rota pública `GET /c/blog/{slug}-{id}`, sem auth). Renderiza um post de blog individual. `SiteLayout` (sem AppShellV2).

---

## Mission
Página de leitura de um artigo do blog (marketing de conteúdo/SEO). O visitante lê o post — título, data, tempo estimado de leitura, imagem de capa, corpo HTML sanitizado e tags. O id é resolvido eager (`findIdFromGivenUrl` → `findOrFail`) pra o 404 acontecer antes de renderizar, e o HTML é sanitizado server-side (`SiteContentService::sanitizeHtml`).

---

## Goals — Features (faz)
- Renderiza título, data formatada (`pt-BR`), tempo de leitura calculado (~200 palavras/min, `useMemo`), capa lazy, corpo HTML sanitizado (`prose`) e chips de tags.
- Link "← Voltar pro blog" pra `/c/blogs`.
- Define `<Head title>` + `<meta name="description">` (SEO).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não é listagem — renderiza UM post (`<article>` de detalhe).
- ❌ Não tem comentários, curtidas ou compartilhamento — só leitura (inferência pendente de Wagner).
- ❌ Não edita o post (edição no admin `cms-page`).
- ❌ Não escopa por `business_id` (conteúdo público global) e não usa AppShellV2/sidebar.

---

## UX targets
- p95 < 800ms (página pública hot-path SEO) ; cabe em 1280px (ROTA LIVRE) ; `SiteLayout`.

---

## Automation hooks (faz)
- `OtelHelper::spanBiz('cms.blog.view', …)` instrumenta o render do post.
- Sanitização de HTML server-side antes de serializar (anti-XSS, front usa `dangerouslySetInnerHTML`).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não muta nada em `GET`.
- ❌ Não faz polling nem sugere posts relacionados dinamicamente.
- ❌ Não renderiza HTML não-sanitizado.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar 404 real em prod pra id inexistente
