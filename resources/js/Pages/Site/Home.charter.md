---
page: /
component: resources/js/Pages/Site/Home.tsx
related_prototype: n/a (landing pública bespoke de marketing — não segue um dos 5 Padrões de Tela do ERP)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Cms
related_adrs: [114, 101, 94, 91]
tier: B
charter_version: 1
---

# Page Charter — / (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Cms/Http/Controllers/CmsController@index` (rota pública `GET /`, sem auth — página de marketing). Landing page do oimpresso, primeira impressão pra visitante/prospect. Renderiza via `SiteLayout` (sem AppShellV2/sidebar do ERP).

---

## Mission
É a porta de entrada pública do produto. O visitante (prospect não-logado) chega aqui pra entender o que o oimpresso faz e ser conduzido a `Começar grátis` (`/login`) ou `Falar com o time` (`/c/contact-us`). Conteúdo (Hero, estatísticas, depoimentos, FAQs) vem do CMS (`CmsPage`/`CmsSiteDetail`) mas cada seção tem fallback hardcoded pra pintar imediato — SEO + conversão são o objetivo, não gestão de dados.

---

## Goals — Features (faz)
- Renderiza Hero, prova social (estatísticas), grade de features, depoimentos e FAQs a partir do CMS, com copy fallback hardcoded quando a prop não hidratou.
- Cada seção CMS vem via `Inertia::defer` (per-key: `page`/`testimonials`/`faqs`/`statistics`) e hidrata em segundo turno dentro de `<Deferred>` com fallback shell.
- CTA final fixo (não-CMS): `Começar grátis` → `/login` e `Falar com o time` → `/c/contact-us`.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não exige login nem toca dados de um `business_id` específico (conteúdo institucional global, não multi-tenant).
- ❌ Não edita conteúdo CMS aqui — edição vive no admin (`cms-page`), esta é só leitura pública.
- ❌ Não usa AppShellV2/sidebar do ERP (é `SiteLayout` público).
- ❌ Não é uma listagem/dashboard operacional — inferência pendente de Wagner.

---

## UX targets
- p95 < 800ms (página pública hot-path SEO/conversão) ; cabe em 1280px (ROTA LIVRE) ; `SiteLayout` (sem AppShellV2).

---

## Automation hooks (faz)
- 4 props `Inertia::defer` (closures só executam quando o front pede) → shell imediato + hidratação diferida.
- `OtelHelper::spanBiz('cms.home.render', …)` instrumenta o render da home no payload deferido.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling nem revalida conteúdo sozinha após o load.
- ❌ Não muta nada em `GET` (render puramente read-only).
- ❌ Não coleta/envia dado do visitante sem ação explícita (CTA leva pra formulário de contato dedicado).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar copy fallback vs conteúdo CMS real em produção
