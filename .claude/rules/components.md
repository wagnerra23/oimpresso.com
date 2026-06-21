---
paths:
  - "resources/js/Components/**"
  - "resources/js/Pages/**/_components/**"
---

# Árvore canônica de componentes (UI-0013 camadas → pastas)

**Onde criar componente novo** (gate CI `components-tree-guard` valida):

| O que é | Onde nasce |
|---|---|
| Primitivo visual (input-like, overlay, badge…) | `Components/ui/` — kebab-case + registrar em [REGISTRY_DS_COMPONENTES.md](../../prototipo-ui/REGISTRY_DS_COMPONENTES.md) |
| Composto cross-módulo (≥2 módulos consomem) | `Components/shared/` — PascalCase |
| Primitivo de layout | `Components/layout/` — **só via ADR** (ADR 0253) |
| Domínio de 1 módulo só | `Pages/<Mod>/_components/` (COM underscore) |

**Antes de criar:** `npm run reuse:check` (anti-duplicação) + consultar o REGISTRY — *se está lá, não hand-rola*.

**PageHeader:** tela nova usa o canon `@/Components/PageHeader` (v3.8 · ADR 0189/0190). O `shared/PageHeader` está **congelado em migração F4** (`pageheader-gate` ratchet — contador 104 só desce; tela tocada migra o header no mesmo PR com aprovação visual).

**BR inputs:** moeda/decimal → `ui/numeric-input-ptbr` · CPF/CNPJ → `ui/document-input` · telefone → `ui/phone-input`. Não hand-wirar `br-mask` em form novo.

**Catracas ativas neste path:** `pageheader:guard` · `layout:check` (ADR 0253) · `reuse:gate` · `lint:baseline:check` (regras ds/* em Pages/**) · `a11y:check` · `components:check`. Baselines são path-keyed — ao MOVER arquivo, re-keye a entry no baseline no mesmo PR (não regenerar tudo).

Refs: [ADR UI-0013](../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) · [MANUAL-CSS-JS §5](../../memory/requisitos/_DesignSystem/MANUAL-CSS-JS.md) · [PRE-MERGE-UI](../../memory/requisitos/_DesignSystem/PRE-MERGE-UI.md)
