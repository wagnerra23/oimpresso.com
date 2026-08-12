---
paths:
  - "resources/js/Components/**"
  - "resources/js/Pages/**/_components/**"
---

# Árvore canônica de componentes (UI-0013 camadas → pastas)

**A pergunta que decide onde o arquivo nasce é uma só: quem consome?**

| O que é | Onde nasce |
|---|---|
| Domínio de **1 módulo** só | `Pages/<Mod>/_components/` (COM underscore) |
| Composto **cross-módulo** (≥2 módulos consomem) | `Components/shared/` — PascalCase, **flat** |
| Primitivo visual (input-like, overlay, badge…) | `Components/ui/` — kebab-case + registrar em [REGISTRY_DS_COMPONENTES.md](../../prototipo-ui/REGISTRY_DS_COMPONENTES.md) |
| Primitivo de layout | `Components/layout/` — **só via ADR** ([ADR 0253](../../memory/decisions/0253-primitivos-layout.md)) |

**Pasta de topo em `Components/` além dessas três** (Shell, superfície pública, domínio fiscal de
dono ≠ consumidor…) é **permitida e cara de propósito**: exige adicionar a entrada — com a
justificativa — ao `ALLOWED_DIRS` de [`scripts/components-tree-guard.mjs`](../../scripts/components-tree-guard.mjs)
**no mesmo PR**, pra aparecer no diff e alguém perguntar "por quê".

> **A lista viva mora no guard, e esta regra não a repete.** Doc que duplica o que outro sistema
> sabe melhor drifta — é lápide do projeto (§5 2026-07-17). Pra ver o autorizado hoje:
> `node scripts/components-tree-guard.mjs`, ou leia o `ALLOWED_DIRS` direto.

**Antes de criar:** `npm run reuse:check` (anti-duplicação) + consultar o REGISTRY — *se está lá, não hand-rola*.

**PageHeader:** tela nova usa o canon `@/Components/PageHeader` ([ADR 0189](../../memory/decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md) · [0190](../../memory/decisions/0190-primary-button-roxo-universal-295.md)). O `shared/PageHeader` está congelado em migração F4 — ratchet que **só desce**; tela tocada migra no mesmo PR com aprovação visual. O contador vivo é `config/pageheader-shared-baseline.json` — **não decore o número**: esta regra carregou `104` enquanto o baseline dizia `97` e a árvore, `90`.

**BR inputs:** moeda/decimal → `ui/numeric-input-ptbr` · CPF/CNPJ → `ui/document-input` · telefone → `ui/phone-input`. Não hand-wirar `br-mask` em form novo.

## Catracas deste path — e o que elas de fato fazem

`pageheader:guard` · `layout:check` · `reuse:gate` · `lint:baseline:check` (regras `ds/*` em `Pages/**`) · `a11y:check` · `components:check`.

> ⚠️ **Rodar ≠ bloquear merge — e isso muda sem avisar esta regra.** O dono único de "o que é
> required" é [`governance/required-checks-baseline.json`](../../governance/required-checks-baseline.json);
> consulte-o antes de assumir que uma catraca acima derruba o PR. Várias ficam **vermelhas sem
> bloquear**, por política deliberada ([ADR 0314](../../memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md):
> required = Tier-0 + exceção por emenda). Baselines são path-keyed — ao MOVER arquivo, re-keye a
> entry no mesmo PR, não regenere tudo.

## O que esta regra NÃO cobre (estado honesto, decisão em aberto)

O guard valida o **topo** de `Components/` e a convenção `_components`. Ele **não** valida:

- **os demais prefixos que já existem em `Pages/`** — `_lib`, `_show`, `_drawer`, `_shared`, `_form` —
  nascidos sem regra e convivendo (`Cliente/` tem três deles);
- **import atravessando módulo** (`Pages/A` consumindo `Pages/B/_components/`), que hoje acontece e
  inclui ao menos um caminho Tier-0 (valor/estoque).

Se você está decidindo onde pôr algo e nenhuma linha acima responde: **pergunte, não invente
prefixo.** Convenção nova aqui é decisão [W], não faxina.

Refs: [ADR UI-0013](../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) · [MANUAL-CSS-JS §5](../../memory/requisitos/_DesignSystem/MANUAL-CSS-JS.md) · [PRE-MERGE-UI](../../memory/requisitos/_DesignSystem/PRE-MERGE-UI.md)
