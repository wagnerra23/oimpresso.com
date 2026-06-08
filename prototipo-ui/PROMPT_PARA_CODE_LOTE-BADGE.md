# LOTE-BADGE — 410 `STATUS_STYLE` → `<Badge variant>` (`ds/no-adhoc-status-text` Tipo 2) · FASE D

> **Origem:** Cowork [CC] · 2026-05-30 · **Para:** Claude Code [CL]. **PR alvo:** `feat/ds-lote-badge` (ou por módulo).
> ⚠️ **PRÉ-REQUISITO:** a **Fase C (Onda G — Badge +5 variants)** precisa estar **mergeada na main**. Sem as variants `success/warning/danger/info/neutral`, os 410 não têm destino limpo. Confira `badge.tsx` antes de começar.
> Fecha o **Tipo 2** do `ds/no-adhoc-status-text` (o grosso: ~64% do drift). O **Tipo 1** já foi na Fase A.

## O que migra (Tipo 2 — só estado)
`STATUS_STYLE`-like: objeto/mapa que pinta **rótulo de estado** com `bg-*-50 text-*-700 border-*-200` (autorizada/pendente/atrasada/cancelada). **NÃO** é erro de form (esse foi Tipo 1, Fase A).

## Mapa 1:1 (da Onda G — espelha o STATUS_STYLE existente)
| Estado | → variant |
|---|---|
| `late` · `rejeitada` · `denegada` · com atraso | `danger` |
| `active` · `autorizada` · `paid` · `Pago` | `success` |
| `pendente` · `processando` | `warning` |
| `sky` · em aberto · informativo | `info` |
| `idle` · `cancelada` · `inutilizada` · n/a | `neutral` |

> `destructive` (sólido) é **ação** (botão Excluir) — **não** confundir com `danger` (estado soft). Não toca em `destructive`.
> Erros/sucesso em `<div>` rose/emerald (ex. `FiscalSection` l.110,121) → `<Alert variant>` (não Badge).

## Método (por módulo — 410 é grande, não faça num commit gigante)
1. `npx eslint resources/js/Pages/<Mod> 2>&1 | grep 'ds/no-adhoc-status-text' || true` (sobra só Tipo 2 pós-Fase A).
2. Onde há `STATUS_STYLE` map → troca por `<Badge variant={mapaAcima[status]}>`. Remove o objeto de estilo cru.
3. `npm run build` por módulo → `lint:baseline:write` → `check` verde.
4. Âncoras conhecidas: `Cliente/Index.tsx` (STATUS_STYLE l.258 + KPI danger), `Sells/_components/FiscalSection.tsx` (l.36–41; divs erro/sucesso → `<Alert>`).

## Aceite
- `ds/no-adhoc-status-text` → **0** (Tipo 1 já zerou na A; aqui zera o Tipo 2).
- Badges visualmente idênticos (variant soft = mesmo tom do STATUS_STYLE). **Screenshot [W2]** por módulo (estados lado a lado).

---

## Cola no Claude Code (sem editar) — troca `<Mod>`/`<MOD>`

````bash
# 0. CONFIRME que a Onda G mergeou (badge.tsx tem success/warning/danger/info/neutral)
grep -E "success|warning|danger|info|neutral" resources/js/Components/ui/badge.tsx || echo "FALTA ONDA G — pare e rode a Fase C primeiro"

git checkout main && git pull origin main
git checkout -b feat/ds-lote-badge-<MOD>

npx eslint resources/js/Pages/<Mod> 2>&1 | grep 'ds/no-adhoc-status-text' || true

# trocar STATUS_STYLE/pill cru -> <Badge variant> pelo mapa. div erro/sucesso -> <Alert>.
# NAO tocar destructive (acao). migrar por arquivo, build no meio.

npm run build
npm run lint:baseline:write
npm run lint:baseline:check

git add resources/js/Pages/<Mod> config/eslint-baseline.json
git commit -m "refactor(ds): lote-badge <MOD> -> <Badge variant> (ds/no-adhoc-status-text Tipo 2)

STATUS_STYLE bg-*-50/text-*-700 -> Badge variant soft (success/warning/danger/
info/neutral) pelo mapa da Onda G. div erro/sucesso -> Alert. destructive (acao)
intacto. Baseline cai. Badges visualmente identicos."

git push -u origin feat/ds-lote-badge-<MOD>
gh pr create --title "refactor(ds): lote-badge <MOD> -> Badge variant" \
  --body "Migra STATUS_STYLE hardcoded do <MOD> pros Badge variants da Onda G (mapa 1:1). Tipo 1 ja foi na Fase A. destructive intacto. Baseline cai. Ver DS-ROADMAP-ATE-ZERO.md + ONDA_G_BADGE_VARIANTS.md." \
  --base main --head feat/ds-lote-badge-<MOD>
# >>> 2o+ PR: git fetch origin main && git rebase origin/main && npm run lint:baseline:write && git add config/eslint-baseline.json && git commit --amend --no-edit && git push -f
# NÃO --admin. [W2] confere os estados.
````

> Módulos na mesma ordem da fila A (Sells/Cliente/Financeiro concentram os badges fiscais). Pode consolidar módulos leves num PR só se a contagem for baixa.
