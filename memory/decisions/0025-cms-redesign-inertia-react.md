# ADR 0025 — Redesign da landing pública (`Modules/Cms`) em Inertia/React

**Status:** ✅ Aceita
**Data decisão:** 2026-04-25
**Início execução:** 2026-04-25 (PR1 commitado em `aabe142d`)
**Escopo:** Frontend público (`/`, `/pricing`, futuras páginas estáticas)
**Branch:** `claude/cms-react-redesign` (saída de `6.7-bootstrap`)

## Histórico

- **2026-04-25** — Wagner pediu redesign do site público. Crítica de design feita em [`oimpresso.com/`](https://oimpresso.com/) e [`oimpresso.com/pricing`](https://oimpresso.com/pricing) identificou template UltimatePOS em inglês ("Automate your business management at very-Low cost"), erros gramaticais ("What They Says About Us"), pricing roxo "estilo 2010" travando em loading. Decisão: substituir por Inertia/React seguindo o stack já em produção (ADR 0023).

## Contexto

`Modules/Cms` na produção entrega `/` (homepage), `/c/page/{slug}` (páginas estáticas), `/c/blogs`, `/c/blog/{slug}-{id}`, `/c/contact-us`. `Modules/Superadmin` entrega `/pricing`. Tudo Blade puro, template UltimatePOS sem customização: copy em inglês, ilustrações genéricas tipo Storyset, sem identidade visual, sem logo, com erros de tradução, e segmentos americanos (Liquor, Pharmacy) que não casam com o ICP brasileiro.

Inertia.js v3 + `@inertiajs/react ^3.0.3` já estão rodando no app autenticado desde ADR 0023 (2026-04-25). Existem 41+ páginas Inertia em `resources/js/Pages/`. O pipeline Vite (`vite.inertia.config.mjs`) e o entry `resources/js/app.tsx` resolvem páginas via convention `./Pages/{Name}.tsx`. Tailwind v4 com tokens CSS em `resources/css/inertia.css` (shadcn new-york slate). Componentes shadcn em `@/Components/ui/`.

## Decisão

1. **Site público (Cms + landing)** migra de Blade pra Inertia/React, **mesmo stack** do app autenticado — não Next.js standalone.
2. **Reusa pipeline existente** (`vite.inertia.config.mjs`, `app.tsx`, `Layouts/`, `Components/ui/`). Nenhum build novo.
3. **Convenção de namespace** pras páginas públicas: `resources/js/Pages/Site/*.tsx` (paralelo a `Financeiro/`, `MemCofre/`, `Ponto/`).
4. **Layout dedicado** `Layouts/SiteLayout.tsx` — público, sem `AppShell`. Header próprio com logo + nav PT-BR + Entrar + CTA. Footer próprio com 4 colunas.
5. **Componentes em `@/Components/Site/`**: `SiteHeader`, `SiteFooter`, `Hero`, `DashboardMockup`, `SocialProof`, `FeatureGrid`, `PricingTiers`, `PricingFaq`.
6. **Versão Blade legada preservada** atrás de sufixo `/old` (`/old`, `/pricing/old`) durante a transição. Remover após validação em produção.
7. **Copy em PT-BR**, posicionamento "ERP completo pra sua empresa", segmentos verdadeiros (Comunicação Visual, Varejo, Serviços), CTAs duplos (Começar grátis + Ver recursos / Falar com o time).
8. **Conteúdo dinâmico (cms_pages)** fica para PR2 — PR1 entrega copy hardcoded em PT-BR pra acelerar entrega.
9. **Framer Motion / animações ricas** ficam para PR2 — PR1 usa só Tailwind transitions/animate.
10. **Pest tests** cobrem contrato Inertia: `GET /` e `GET /pricing` retornam componente `Site/Home` e `Site/Pricing`, props esperadas presentes, `/old` e `/pricing/old` continuam servindo Blade.

## Alternativas consideradas e rejeitadas

| Caminho | Por que rejeitado |
|---|---|
| **A) Manter Blade + caprichar com Tailwind/Alpine/GSAP** | 90% do "uau" de React em 30% do esforço, mas Wagner explicitamente quis "site incrível em React". Ok ergonomicamente pra um operador solo, mas perde acoplamento com o app autenticado (que já é React). |
| **C) Next.js standalone hospedado fora (Vercel/Cloudflare)** | Máxima qualidade visual + SEO, mas duplica stack: Node deploy, repo separado, API pública no Laravel, autenticação cross-domain. Caro pra operador solo. Faria sentido só se o site marketing fosse separado da operação. |
| **Refatorar URLs do app pra `/app/*` e abrir `/` pra landing** | Risco enorme — centenas de URLs hardcoded em controllers/blades/JS do UltimatePOS. PR pequeno se torna PR gigante. Memória registra regressões caras com mudanças sistêmicas (Carbon, Form bool attrs). |

## Consequências

### Positivas
- **Um stack só**: React/Inertia/Tailwind v4/shadcn, mesmo pipeline pro público e pro autenticado.
- **SEO mantido**: Inertia v3 com SSR continua server-rendering as páginas de marketing.
- **Reversibilidade**: Blade legado em `/old` permite reverter rota em segundos se algo quebrar.
- **DX**: Wagner já está acostumado com a stack — adiciona zero cognitive load.
- **Endereça as 5 priority recommendations** da crítica de design (idioma, mockup, header, CTAs, segmentos).

### Negativas / riscos
- **JS obrigatório pra renderizar a homepage** (mas Inertia SSR mitiga). Crawlers velhos podem ter visão degradada.
- **Conteúdo hardcoded no JSX** em PR1: Wagner não consegue editar pelo `/cms/cms-page` ainda. Tem que aguardar PR2 (hidratar componentes a partir de `cms_pages`).
- **Pricing ainda hardcoded**: tiers e preços vivem no `PricingTiers.tsx`, não no DB de packages. PR2 precisa hidratar.
- **`PricingController` continua dependendo de `superadmin_package`** (acoplamento cross-módulo Cms↔Superadmin). Pra remover, viraria refator maior.

## Roadmap de PRs

- ✅ **PR1** — `aabe142d` — Esqueleto: SiteLayout + Home + Pricing + 7 componentes + Pest tests + fallback Blade `/old`.
- 🔜 **PR2** — Polimento + dinâmico:
  - Hidratar `Site/Home` com dados de `cms_pages` (Wagner edita pelo `/cms/cms-page` superadmin).
  - Hidratar `Site/Pricing` com `Package::listPackages()` real.
  - Substituir `DashboardMockup` por screenshot real do app rodando em produção (precisa credenciais demo).
  - Adicionar `framer-motion`: stagger no hero, scroll-reveal nas features, hover lift nos cards.
  - Migrar `/c/page/{slug}` (CmsPage) e `/c/blogs` pra Inertia.
  - Banda de logos clientes reais (Wagner envia).
- 🔜 **PR3** — Consolidar `/ajuda/` (WP 6.1.10 patcheado em `reference_wp_ajuda_fix.md`) no `Modules/Knowledgebase` Inertia. Importer Artisan `cms:import-wp-help` lê DB do WP e popula `kb_articles`. Redirects 301 das URLs antigas. Aposenta o WP.
- 🔜 **PR4** — Officeimpresso.com.br (WP separado, tema Betheme, 40+ posts, páginas pitch "O ERP", "Você tem interesse em reduzir custos") — importer `cms:import-wp-officeimpresso` migra pro `cms_blogs`. Redirect 301 do domínio inteiro pra oimpresso.com.

## Métricas de sucesso

- Lighthouse mobile ≥ 90 (Performance + Accessibility + Best Practices + SEO).
- TTFB < 600ms (Hostinger LiteSpeed deve aguentar).
- Conversão `/` → `/login` rastreada (PR2 adiciona evento).
- Zero regressão no app autenticado (rotas existentes continuam intocadas).

## Referências

- Crítica de design completa: sessão `claude-code 2026-04-25` (chapter "Diagnóstico landing oimpresso.com")
- Stack de Inertia v3: ADR 0023
- Convenção de namespace de páginas Inertia: ADR 0024
- Identidade do produto: nome **oimpresso** (não "Office Impresso") — confirmado por Wagner 2026-04-25
- Memória relacionada: `reference_modules_cms_landing.md`, `reference_wp_ajuda_fix.md`, `project_inertia_v3_upgrade.md`
