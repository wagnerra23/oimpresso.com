---
slug: 0165-design-system-breakpoints-mobile-first-responsive
number: 165
title: "Design System — breakpoints canon + regra mobile-first em todas as Pages Inertia"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-17"
accepted_at: null
review_at: 2026-11-17
module: Governance
quarter: 2026-Q2
tags: [design-system, responsive, mobile-first, breakpoints, tailwind, charter, mwart, larissa, acessibilidade, touch-targets]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0104-processo-mwart-canonico-unico-caminho, 0107-emendation-0104-visual-comparison-gate-f3, 0110-cockpit-pattern-v2-canon-list-detail, 0114-prototipo-ui-cowork-loop-formalizado, 0094-constituicao-v2-7-camadas-8-principios, 0093-multi-tenant-isolation-tier-0, 0066-format-date-shift-3h-preservado-legacy-clientes]
pii: false
review_triggers:
  - Quando ≥5 telas falharem viewport smoke 2x consecutivo → revisar breakpoints canon (sinal que escolha está mal calibrada vs parque real)
  - Quando parque cliente migrar massa pra ≥1920 (telemetria > 60% acima de 1920) → relaxar lg (1024) pra status "legacy support" e elevar xl como target primário
  - Quando time MCP entrante (Felipe/Maiara/Eliana/Luiz) reportar friction repetido pra aplicar a regra → revisar tooling (skill auto-trigger, CI Playwright, charter linter)
  - Quando ROTA LIVRE (Larissa biz=4) trocar de monitor pra ≥1920 → reavaliar se xl ainda é "target Larissa" ou se passa a ser caso comum (não-exceção)
  - Se aparecer pedido recorrente de breakpoint custom (ex 1100px, 1440px) ≥3x em 1 mês → reavaliar escolha de breakpoints Tailwind nativos vs custom
---

# ADR 0165 — Design System: breakpoints canon + mobile-first em todas as Pages Inertia

## Status

**Proposed** — aguarda aprovação Wagner antes de virar `accepted` + carga no MCP.

## Contexto

Sessão 2026-05-17 dossier estado-da-arte [tela-venda-arte responsivo](../sessions/2026-05-17-tela-venda-arte-responsivo.md) detectou que `/sells/create` (e por extensão muitas das 200+ Pages Inertia) está desenhada **xl-only** (target 1280px da Larissa/ROTA LIVRE), produzindo quebra/scroll-horizontal em ~30-45% do parque cliente brasileiro:

| Bucket viewport | % parque BR estimado | Status oimpresso atual |
|---|---|---|
| ≥1920px (2xl) | ~30% | ✅ funciona |
| 1366–1599 (xl) | ~25% | ✅ funciona |
| 1024–1365 (lg) | **~25-30%** | ❌ scroll horizontal, comprime mal |
| 768–1023 (md) | ~5% | ❌ quebra layout |
| <768px (sm/mobile) | ~15% | ❌ inutilizável |

Larissa é caso piloto VESTUÁRIO em prod (99% volume vendas atual), mas representa só ~25% da forma de tela do mercado-alvo total. Para escalar pra Modules/ComunicacaoVisual (candidatos: 6 OfficeImpresso legacy) + OficinaAuto (Martinho) + mobile (vendedor campo, dono no celular), precisa universalizar responsividade.

Hoje **não existe ADR de design system com breakpoints canônicos** — cada PR escolhe o que quer. Resultado: 200+ telas inconsistentes, retrabalho previsível quando entrarem mais clientes.

ADR 0110 (Cockpit Pattern V2) define padrões visuais de header/footer/KPIs mas é **agnóstico de breakpoint**. ADR 0107 (Visual Gate F1.5) requer aprovação de screenshot Wagner mas o screenshot canon é geralmente 1440 (xl) — não cobre lg/md/sm. Falta a camada de **viewport contract**.

## Decisão

### 1. Breakpoints canon = **Tailwind 4 nativos** (sem custom)

| Token | Mín. width | Target oimpresso |
|---|---|---|
| `default` | 0px | mobile-first base (vendedor campo + dono celular) |
| `sm:` | 640px | smartphone landscape, phablet |
| `md:` | 768px | tablet (iPad balcão, notebook 11") |
| `lg:` | **1024px** | **TARGET PRIMÁRIO** — notebook 14" Positivo/Acer + monitor 17" antigo 1280×1024 (parque BR maioritário invisível hoje) |
| `xl:` | 1280px | Larissa, notebook 15"+ moderno |
| `2xl:` | 1536px | escritórios novos, monitor 1920+ |

Rationale "sem custom": (a) qualquer dev Tailwind/React entra sem documento extra; (b) AI-pair (Claude) escreve nativamente; (c) zero risco de drift do tema; (d) Tailwind 4 ainda mantém defaults idênticos em 2026.

### 2. Regra mobile-first em **todas** as Pages Inertia (Tier 0 de design)

- Layout **default** (sem prefixo) DEVE funcionar em **375px** (iPhone SE — viewport mínimo canon)
- Adições via `sm:`/`md:`/`lg:`/`xl:`/`2xl:` apenas pra **enriquecer**, nunca pra "concertar" desktop
- Inverter o anti-pattern catalogado: layouts desenhados xl-only com `hidden md:block`/`@media (max-width)` espremidos pra baixo

### 3. Touch targets mínimos (Apple HIG 44pt + Material 48dp)

- Inputs/Selects/Buttons em **<md (768px)**: `h-11` (44px) — não negociável
- Inputs/Selects/Buttons em **md+**: `h-9` ou `h-8` permitido (mouse precision)
- Padrão Tailwind canon: `h-11 md:h-9` (rampa)
- Icon buttons mobile: `h-11 w-11` (delete, fechar, hambúrguer)

### 4. iOS safe-area obrigatória em sticky footer

Toda Page com `<div className="sticky bottom-0">` (Cockpit V2 footer) DEVE usar `pb-[env(safe-area-inset-bottom)]` ou helper Tailwind equivalente. Evita sobreposição com bottom-nav iOS Safari (catalogado dossier 2026-05-17).

### 5. Charter declara **viewport mínimo** + **viewports validados**

Toda `<Tela>.charter.md` precisa, em §UX Targets:

```markdown
- Viewport mínimo: 375px (iPhone SE)
- Viewports validados: 375, 768, 1024, 1280, 1440 (browser MCP screenshots)
- Touch targets ≥44px em <md
- iOS safe-area respeitada em sticky footer
```

Migração progressiva: charters existentes ficam OK até próxima Edit; ao tocar `.tsx` da tela, charter DEVE ser atualizado no mesmo PR (skill `charter-first` Tier A já força leitura — basta adicionar a regra).

### 6. CI Playwright valida 3 viewports (smoke pós-merge)

Skill `tela-smoke-pos-merge` (ADR 0164 fase C) hoje captura screenshot 1440 + 1280. Estender pra **3 viewports padrão**: **375 / 1024 / 1440** (cobre os 3 buckets críticos: mobile, lg target, Larissa atual). Se time MCP entrar e custo browser MCP virar pain (R$ [redacted Tier 0]/mês), reduzir pra 2 (mobile + lg).

### 7. Skill Tier B `responsive-mobile-first` (auto-trigger)

Criar skill `responsive-mobile-first` em `.claude/skills/responsive-mobile-first/SKILL.md` com description:

> Use ANTES de Edit/Write em qualquer `resources/js/Pages/**/*.tsx` ou componente `_components/*.tsx`. Carrega regra mobile-first + breakpoints canon (ADR 0165) + tabela de touch targets + iOS safe-area + checklist 5 dimensões responsivas.

Tier B (auto-trigger por description) — não custa nada se Page já está responsiva, força disciplina quando criando nova.

## Consequências

### Positivas

1. **~25-30% mais clientes utilizáveis** (lg/md monitors legacy parque BR) — habilita venda pra OfficeImpresso legacy migration sem retrabalho UI
2. **Mobile vira possível** — vendedor campo + dono celular ganham UI funcional (atinge 15% parque adicional)
3. **Onboarding time MCP simplifica** — Felipe/Maiara/Eliana/Luiz têm regra única + skill auto-trigger
4. **Reduz refactor de tela** — toda Page nova nasce responsiva; menos retrabalho por viewport
5. **Compatível com Cockpit V2** (ADR 0110) — não substitui, complementa (Cockpit define padrão visual; este ADR define como ele se adapta a viewport)
6. **CI Playwright 3 viewports** — catch regressão visual cedo (skill ADR 0164 estende, não nasce)
7. **Tailwind 4 nativo** — zero overhead de custom config, zero risco drift

### Negativas / custos

1. **Retrabalho gradual** das 200+ Pages existentes — não bloqueia; cada Edit dispara update no mesmo PR (gradual safe-by-default)
2. **CI Playwright 1.5x mais lento** (3 viewports vs 2) — custo estimado R$ [redacted Tier 0]-5/mês browser MCP adicional
3. **Charter linter precisa adaptar** pra exigir seção viewport — pequeno trabalho de governance
4. **Mobile-first é shift cultural** — devs acostumados com desktop-first vão precisar treino inicial (skill auto-trigger ajuda)
5. **Componentes shared (`Components/ui/*`)** podem precisar revisão pra garantir touch targets — auditoria leve por componente

### Riscos / não-objetivos

- ❌ NÃO substitui Cockpit V2 (ADR 0110) — coexistem
- ❌ NÃO redefine paleta/tipografia/spacing — só viewport behavior
- ❌ NÃO obriga refactor batch (gradual via skill + charter update no mesmo PR)
- ❌ NÃO cobre Blade legacy — só Pages Inertia (Blade tá em deprecation via MWART ADR 0104)
- ❌ NÃO define design pra impressão (cupom, etiqueta) — escopo separado

## Implementação

### Marco 1 — Sells/Create piloto (Wave 2 sessão 2026-05-17)

- Componentes responsive criados: `ProductLineCard.tsx` + `PaymentRow.tsx` refatorado
- Wave 2: integrar em `Create.tsx` + atualizar `Create.charter.md` viewport mínimo 375 + Pest viewport 3 sizes

### Marco 2 — Skill `responsive-mobile-first` Tier B

PR seguinte:
- Criar `.claude/skills/responsive-mobile-first/SKILL.md` com description matchando Edit/Write em `.tsx`
- Inclui checklist 5 dimensões: viewport mínimo, touch targets, safe-area, breakpoints, charter

### Marco 3 — CI Playwright 3 viewports

Estender [.github/workflows/screen-smoke-after-merge.yml](../../.github/workflows/screen-smoke-after-merge.yml) (skill `tela-smoke-pos-merge`) pra capturar **375 + 1024 + 1440** em vez de 1440 + 1280.

### Marco 4 — Charter linter

Adicionar regra ao [governance-gate.yml](../../.github/workflows/governance-gate.yml) Job: charter precisa ter seção "Viewports validados". Bloquear merge se Page nova sem essa seção.

### Marco 5 — Migração progressiva

- Toda Edit em `.tsx` existente DEVE atualizar charter + breakpoints no mesmo PR
- Backlog de telas com nota responsividade <60/100 (audit via dossier 2026-05-17 metodologia) — priorizado por volume de uso

## Pegadinhas conhecidas

- **PowerShell 5.1 BOM** ([memory/proibicoes.md §Ambiente](../proibicoes.md)) — ao criar `.tsx`/`.md` via `Set-Content`, usar `utf8NoBOM` ou Python — UTF-8 com BOM quebra PHP/JS
- **ROTA LIVRE Larissa biz=4 fallback Blade** ainda ativo ([SellController.php:800-808](../../app/Http/Controllers/SellController.php:800)) — Pages Inertia responsive só afeta clientes em v2 (feature flag); Larissa só vê após bugs v1 resolvidos
- **`format_date` shift +3h** (ADR 0066) — input `datetime-local` em mobile (iOS Safari ≠ Android Chrome) precisa testar comportamento real cross-platform
- **`<details>` colapsável em mobile** — comportamento iOS Safari ≠ Chrome Android (catalogado dossier 2026-05-17 PaymentRow agent finding)
- **localStorage draft cross-device** — não há sync; se Larissa começa no PC e termina no celular, não vê draft (aceitável MVP, documentar)

## Métricas de sucesso (revisão 2026-11-17)

- **% Pages com charter "Viewports validados"** ≥ 80% (linter mede)
- **% Pages com nota responsividade ≥75/100** ≥ 70% (audit dossier metodologia)
- **CI Playwright 3-viewport smoke flakiness** < 5% (sinal de viewport mal calibrado)
- **0 crash report cliente** "tela quebrada no celular/notebook antigo" (canal suporte)

Se < 50% das métricas batidas em 6 meses, revisar via review_triggers acima.

## Referências

- [Dossier 2026-05-17 estado-da-arte tela venda responsivo](../sessions/2026-05-17-tela-venda-arte-responsivo.md) (motivador)
- [ADR 0094 Constituição v2](0094-constituicao-v2-7-camadas-8-principios.md) (camadas + princípios duros)
- [ADR 0104 MWART canônico](0104-processo-mwart-canonico-unico-caminho.md) (F1.5 visual gate)
- [ADR 0107 Visual gate F3](0107-emendation-0104-visual-comparison-gate-f3.md) (screenshot aprovação)
- [ADR 0110 Cockpit Pattern V2](0110-cockpit-pattern-v2-canon-list-detail.md) (padrão visual, agnóstico viewport)
- [ADR 0114 Cowork loop formalizado](0114-prototipo-ui-cowork-loop-formalizado.md) (F1.5 protótipo)
- [ADR 0164 Screen Review PDCA pós-merge](0164-screen-review-pdca-tela-smoke-pos-merge.md) (skill `tela-smoke-pos-merge` que esta ADR estende pra 3 viewports)
- [Tailwind v4 Breakpoints](https://tailwindcss.com/docs/responsive-design)
- [Apple HIG — Hit Target Size](https://developer.apple.com/design/human-interface-guidelines/buttons)
- [Material Design 3 — Touch Targets](https://m3.material.io/foundations/accessible-design/accessibility-basics)
- [Shopify Polaris breakpoints](https://polaris-react.shopify.com/tokens/breakpoints) (referência líder)
