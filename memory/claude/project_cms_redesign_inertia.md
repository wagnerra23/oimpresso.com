---
name: Cms redesign Inertia/React em andamento
description: Modules/Cms (landing pública oimpresso.com) e /pricing migrando de Blade pra Inertia/React. PR1 commitado 2026-04-25 (aabe142d) na branch claude/cms-react-redesign saindo de 6.7-bootstrap; precisa npm install + composer install + dev:inertia + Herd pra validar local. Versão Blade legada preservada em /old e /pricing/old. ADR 0025 grava decisão completa.
type: project
originSessionId: 78bc6849-f503-4b7f-93a1-4c2a439cc019
---
**Branch:** `claude/cms-react-redesign` (saída de `6.7-bootstrap`)
**Worktree:** `D:\oimpresso.com\.claude\worktrees\cms-redesign`
**Commit:** `aabe142d feat(cms): redesign landing publica em Inertia/React (PT-BR)` + commit do Pricing (próximo)

**Why:** Landing pública em produção é template UltimatePOS em inglês ("Automate your business management at very-Low cost", "What They Says About Us"); /pricing roxo "estilo 2010" travando em loading. Crítica de design feita 2026-04-25 elencou 5 priority recommendations.

**How to apply:**
- Iterar dentro deste worktree, não no `sleepy-haslett-f10c55` (que é antigo, sem Modules/Cms).
- Pages novas em `resources/js/Pages/Site/*.tsx` (paralelo a Financeiro/Ponto/MemCofre).
- Layout dedicado: `Layouts/SiteLayout.tsx` (público, sem AppShell). Componentes em `@/Components/Site/`.
- Antes de validar, rodar `composer install && npm install && npm run dev:inertia` no worktree.
- Reverter rápido: trocar `Inertia::render(...)` de volta pra `view(...)` no controller (Blade legado preservado).

**PR1 (mergeado 2026-04-25):**
- `Site/Home` + `Site/Pricing` + 9 componentes (`SiteHeader`, `SiteFooter`, `Hero`, `DashboardMockup`, `SocialProof`, `FeatureGrid`, `PricingTiers`, `PricingFaq`)
- 5 testes Pest cobrindo contrato Inertia + fallback Blade
- Versão Blade legada em `/old` e `/pricing/old` durante transição

**Pendências PR2:**
- ~~Hidratar Site/Home com dados de `cms_pages` (Wagner edita pelo `/cms/cms-page` superadmin)~~ — **TENTADO E REVERTIDO** em 2026-04-26 commit `039a810d`. Seed UltimatePOS em `cms_pages` row id=3 traz copy inglês ("Automate your business management at very-Low cost") que vazava no Hero PT-BR. Decisão final: copy do Hero é **hardcoded** no `Hero.tsx`, ignora `cms_pages`. Pra editar texto do Hero, mudar o componente — não a tabela.
- Hidratar Site/Pricing com `Package::listPackages()` real (preços hardcoded são placeholder)
- Substituir `DashboardMockup` por screenshot real do app (precisa credenciais demo)
- Adicionar `framer-motion` (animações stagger/scroll-reveal)
- Migrar `/c/page/{slug}` e `/c/blogs` pra Inertia
- Logos clientes reais na banda de prova social

**Pendências longo prazo:**
- PR3: consolidar `/ajuda/` (WP) no `Modules/Knowledgebase` Inertia + redirects 301
- PR4: importer WP officeimpresso.com.br → `cms_blogs` + redirect 301 do domínio inteiro

**Decisões fixadas:** [ADR 0025](memory/decisions/0025-cms-redesign-inertia-react.md) (no repo)
