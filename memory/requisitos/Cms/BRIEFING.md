# BRIEFING — Modules/Cms

> 1-pager executivo. Atualizado por PR (skill `brief-update` Tier B).
> **Última atualização:** 2026-05-16 (Wave Massive 12-agents — criação inicial).

## O que é

CMS embarcado do oimpresso que serve **2 papéis distintos**:

1. **Landing oimpresso.com** — home pública, pricing, blog, páginas estáticas (Termos, Política, Sobre). Anônimo, SEO-friendly.
2. **(Futuro)** Site público por tenant — cada business com landing/blog próprio (subdomain ou path), pra vender CMS como add-on ao núcleo.

Stack: Laravel + Inertia/React (migração MWART em curso); tabelas `cms_pages`, `cms_site_details`, `cms_page_metas`. Importer WordPress + autenticação social Socialite.

## Estado atual (2026-05-16)

- **Nota auditoria:** 30/100 — Crítico
- **Gap principal:** D1 (escopo features básicas) 5/30 · D3 (multi-tenant Tier 0) **0/15**
- **Bloqueador Tier 0:** tabelas SEM `business_id` — funcionam como globais, impedem evolução pra CMS multi-tenant produto vendável

## Capacidades vivas

| Capacidade | Status | Evidência |
|---|---|---|
| Home pública `/` Inertia | ✅ | `SiteHomeTest.php` (6 cases) |
| Pricing público `/pricing` | ✅ | `SiteHomeTest` |
| Páginas dinâmicas `/c/page/{slug}` | 🟡 (sem isolamento) | `SitePageTest.php` |
| Site home dinâmico per-config | 🟡 | `SiteHomeDinamicoTest.php` |
| Pricing dinâmico | 🟡 | `SitePricingDinamicoTest.php` |
| Auth social (Google/Facebook) | 🟡 | `AuthSocialTest.php` |
| Importer WordPress | 🟡 | `ImporterWpTest.php` |
| Multi-tenant isolation slug | ❌ **GAP P0** | `MultiTenantSlugIsolationTest.php` (skipped até US-CMS-002) |
| Multi-tenant site_details | ❌ **GAP P0** | `SiteHomeMultiTenantTest.php` (skipped até US-CMS-003) |
| CRUD admin React (MWART) | ❌ | Blade legacy ainda — US-CMS-004 |
| Page builder drag-drop | ❌ | US-CMS-010 backlog |

## Diferenciais vs concorrentes

- **Bling/Tiny:** sem CMS embarcado → oimpresso ganha
- **WordPress Multisite:** maduro mas operacionalmente complexo → oimpresso pode ganhar com simplicidade
- **Webflow/Squarespace:** standalone, sem ERP integration → oimpresso ganha em integração nativa (vendas, clientes, NFe)

## Risco principal (Tier 0)

`cms_pages` e `cms_site_details` SEM `business_id`. Se evoluirmos pra multi-tenant sem fix, slug biz=1 vaza em queries biz=99 → **violação ADR 0093 IRREVOGÁVEL**. Bloqueia entrada de qualquer business novo no CMS.

## Próximos passos sugeridos

1. **Sprint 1 (~10h):** US-CMS-002 + US-CMS-003 (adicionar `business_id` + global scope + backfill biz=1)
2. **Sprint 2 (~12h):** US-CMS-004 (CRUD admin MWART Blade→Inertia)
3. **Sprint 3 (~14h):** US-CMS-006 + US-CMS-007 (site home + pricing dinâmico per-tenant)

Detalhes em [SPEC.md](SPEC.md).

## Cliente piloto

Hoje: **oimpresso.com landing** (single tenant, business_id=1 implícito).
Futuro: módulos verticais com CMS próprio (ComVis serigrafia precisa portfolio público, Vestuario precisa lookbook).

## Tests existentes (NÃO mexer)

`SiteHomeTest.php`, `SitePageTest.php`, `SiteHomeDinamicoTest.php`, `SitePricingDinamicoTest.php`, `AuthSocialTest.php`, `ImporterWpTest.php` (6 arquivos).

## Tests novos (Wave Massive 2026-05-16)

`MultiTenantSlugIsolationTest.php` (5 cenários — D3) · `SiteHomeMultiTenantTest.php` (3 cenários — D1+D3).

Suítes `markTestSkipped` até US-CMS-002/003 entregarem `business_id` — viram alarme passivo automático: assim que a coluna existir, testes ativam sem reescrita.

## Referências

[SPEC.md](SPEC.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) · Pattern `Modules/ComunicacaoVisual`
