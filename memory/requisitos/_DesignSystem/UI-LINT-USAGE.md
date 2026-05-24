---
doc: UI-LINT-USAGE
camada: meta-protocolo
status: ativo
created: 2026-05-24
parent_adr: UI-0013
related: [AUTOMATION-ROADMAP Onda 1.2]
---

# `php artisan ui:lint` · uso

> **Onda 1 Item 1.2** do [AUTOMATION-ROADMAP](AUTOMATION-ROADMAP.md). Detecta anti-padrões UI da [Constituição UI v2 · UI-0013](adr/ui/0013-constituicao-ui-v2-camadas.md) em `resources/js/Pages/` e `resources/js/Components/`.

## Regras ativas

| ID | Detecta | Como |
|---|---|---|
| **R1** | Cor crua — `#hex` literal (exceto `#fff`/`#000`) ou `bg-COR-NNN` Tailwind literal | regex em arquivos `.tsx`/`.jsx` |
| **R2** | FontAwesome (lucide-only · [UI-0003](adr/ui/0003-lucide-react-como-unica-iconografia.md)) | grep `@fortawesome`, `FontAwesomeIcon` |
| **R3** | Emoji em UI de produto · use lucide icon | Unicode ranges `1F300-1F9FF`, `1F600-1F64F`, `1F1E6-1F1FF`, `1FA70-1FAFF` (exclui ✓ ✗ ⚠ text-style) |

## Comandos típicos

```bash
# Scan rápido (warning, exit 0)
php artisan ui:lint

# Detalhe completo (path:linha:match pra cada hit)
php artisan ui:lint --detail

# CI mode (exit 1 se hit > 0) — sem baseline, vai falhar SEMPRE em projeto atual
php artisan ui:lint --strict

# Modo ratchet (CI realista): só falha se regressão vs baseline
php artisan ui:lint --baseline=config/ui-lint-baseline.json --strict

# Pre-commit hook (só arquivos modificados vs origin/main)
php artisan ui:lint --changed-only --baseline=config/ui-lint-baseline.json --strict

# Regra específica
php artisan ui:lint --rule=R1
php artisan ui:lint --rule=R2,R3

# Path específico
php artisan ui:lint --path=resources/js/Pages/Cliente

# Atualizar baseline (depois de aceitar estado novo — ex: refator que reduziu hits)
php artisan ui:lint --write-baseline=config/ui-lint-baseline.json
```

## Baseline (2026-05-24)

Estado inicial registrado em `config/ui-lint-baseline.json`:
- **R1** (cor crua): ~6859 hits em ~309 arquivos
- **R2** (FontAwesome): 0 hits ✓
- **R3** (emoji): ~119 hits em ~37 arquivos
- **Total:** ~7280 violações · 317 arquivos

> Esse não é um "fail" — é o estado atual aceito. Modo ratchet (`--baseline=...`) **só falha em regressão**. Pra reduzir baseline, refatorar arquivo + rodar `--write-baseline=...` pra registrar novo estado aceito.

## Workflow CI sugerido (Onda 2)

```yaml
# .github/workflows/ui-lint.yml (a criar em Onda 2.1)
on: pull_request
jobs:
  ui-lint:
    steps:
      - uses: actions/checkout@v4
      - run: composer install --no-dev
      - run: php artisan ui:lint --changed-only --baseline=config/ui-lint-baseline.json --strict
```

## Workflow pre-commit (Onda 2.2)

```bash
# .git/hooks/pre-commit (a criar em Onda 2.2)
#!/bin/bash
php artisan ui:lint --changed-only --baseline=config/ui-lint-baseline.json --strict
exit $?
```

## Limites conhecidos (Onda 1.2)

- ❌ Não detecta `<style>` inline com cor crua (raro em React, mas existe)
- ❌ Não detecta `style={{ background: '#fff' }}` quando arquivo tem só essa única ocorrência (regex pega apenas color literals em strings)
- ❌ Não detecta AP2 (componente reinventado) · AP5 (gradient decorativo) · AP7 (status badge bg-fill) · AP8 (copy não-PT-BR) — futuras regras Onda 2/3
- ✅ Detecta R1 cor crua em Tailwind classes string
- ✅ Detecta R2 FontAwesome import
- ✅ Detecta R3 emoji decorativo (exclui ✓ ✗ ⚠ text-style)

## Refs

- **ADR-mãe:** [UI-0013 Constituição UI v2](adr/ui/0013-constituicao-ui-v2-camadas.md)
- **PRE-MERGE-UI camada 4 (AP1-AP8):** [PRE-MERGE-UI.md](PRE-MERGE-UI.md)
- **Roadmap:** [AUTOMATION-ROADMAP.md Onda 1.2](AUTOMATION-ROADMAP.md)
- **Command:** [`app/Console/Commands/UiLintCommand.php`](../../../app/Console/Commands/UiLintCommand.php)
- **Skill correlata Tier A:** [`constituicao-ui-aware`](../../../.claude/skills/constituicao-ui-aware/SKILL.md) (gancho de atenção, carrega Constituição antes de codar)

---

**Última revisão:** 2026-05-24 · v1.0 · Onda 1 entregue.
