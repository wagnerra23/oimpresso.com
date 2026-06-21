---
paths:
  - "resources/js/Components/**/*.tsx"
  - "resources/js/Components/**/*.ts"
  - "resources/js/Lib/**/*.ts"
  - "resources/js/Hooks/**/*.ts"
  - "Modules/**/Services/**/*.php"
  - "Modules/**/Entities/**/*.php"
  - "Modules/**/Models/**/*.php"
---

# Rule path-scoped — antes de CRIAR símbolo novo, consulte o índice

> Carrega ao tocar a camada onde nascem componentes/utils/hooks/Models/Services. Fecha o problema #5 do [MANUAL-CSS-JS](../../memory/requisitos/_DesignSystem/MANUAL-CSS-JS.md) ("colisões de símbolo / copy-paste"). O caso real: `Cobranca/_components/atoms.tsx` re-implementou `PageHeader`/`KpiCard`/`StatusBadge`/`Field` que já existem em `@/Components/shared`.

## Regra (reusa > recria)

**Antes de escrever um `function`/`const`/`class`/componente novo, rode:**

```bash
npm run reuse:check "<nome ou o que faz>"
```

- `✅ JÁ EXISTE — REUSA` → importe o canônico (arquivo:linha na saída), **não recrie**.
- `❌ NÃO existe` → pode criar (confirme o kind + onde mora).
- Ex: `reuse:check "BaixaService"` → *"não existe; reais `TituloBaixa`/`BaixaRepository`"*.

## Onde mora o canônico (não hand-rolar)

- Primitivos UI: `@/Components/ui` (shadcn+Radix+CVA) · compartilhados: `@/Components/shared` — ver [REGISTRY_DS_COMPONENTES](../../prototipo-ui/REGISTRY_DS_COMPONENTES.md).
- Utils JS: `resources/js/Lib/` (`cn`, `format-br`, `numberPtBR`, `utils`).
- Models/Services PHP: namespaced por módulo — `reuse:check` acha o real.

## Enforcement

O gate `reuse-gate.yml` (CI) falha o PR em **duplicata NOVA** (ratchet, baseline em `scripts/reuse-duplicates-baseline.json`). Se a duplicata for legítima: `npm run reuse:baseline:write` consciente + justificativa no PR.

Refs: `scripts/reuse-index.mjs` · MANUAL-CSS-JS #5 · [ADR 0240](../../memory/decisions/0240-task-ledger-git-native-cowork-code.md) (derivado>escrito).
