# SWEEP — form-section hand-rolled → `<FormSection>` (`ds/no-handrolled-form-section`) · FASE E

> **Origem:** Cowork [CC] · 2026-05-30 · **Para:** Claude Code [CL]. **PR alvo:** `feat/ds-formsection-<MOD>`.
> ⚠️ **PRÉ-REQUISITO:** o componente **`FormSection`** (Onda F) precisa existir em `@/Components/ui/form-section` (vem do PR-A). Confirme antes.
> Fecha `ds/no-handrolled-form-section`. **Mais arriscado** que as outras filas — mexe em **estrutura** de form, não só className. Faça **por módulo**, build no meio, e só depois da Fase A do módulo (mesmo terreno).

## O que a regra pega
`<section>`/`<div>` com className casando `rounded-lg … border …` **E** (`p-4`|`p-5`) = card de seção de form feito à mão (header + grid embutidos na unha). Hoje duplicado (ex.: Create `p-4` vs DadosFiscaisBR `p-5`).

## Transform
```
<section className="rounded-lg border p-4">
  <h3>Título</h3>
  <div className="grid grid-cols-2 gap-4">…campos…</div>
</section>
```
→
```
<FormSection title="Título">
  <FormGrid>…campos…</FormGrid>
</FormSection>
```
- `title` (e `icon`/`count` se houver) vão pras props do `<FormSection>`.
- O grid 2-col → `<FormGrid>` (já faz 2→1 col em container ≤520px).
- **Não** mude os campos dentro — só a casca. Confirma que o submit/layout ficam idênticos.

## Método (por módulo)
1. `npx eslint resources/js/Pages/<Mod> 2>&1 | grep 'ds/no-handrolled-form-section' || true`
2. Troca a casca `<section rounded-lg border p-4|p-5>` → `<FormSection>`/`<FormGrid>`. Mantém os filhos.
3. `npm run build` (estrutura mudou — confirma que renderiza) → `lint:baseline:write` → `check` verde.
4. Concentração: telas **Create/Edit** (Cliente `_form/`, Sells, Purchase, OficinaAuto, RecurringBilling/Planos).

## Aceite
- `ds/no-handrolled-form-section` → 0 no módulo.
- Forms renderizam e enviam **idêntico** — **screenshot [W2]** de cada Create/Edit que mudou (estrutura é sensível).

---

## Cola no Claude Code (sem editar) — troca `<Mod>`/`<MOD>`

````bash
# 0. confirma o componente
test -f resources/js/Components/ui/form-section.tsx || echo "FALTA FormSection (Onda F / PR-A) — pare e crie primeiro"

git checkout main && git pull origin main
git checkout -b feat/ds-formsection-<MOD>

npx eslint resources/js/Pages/<Mod> 2>&1 | grep 'ds/no-handrolled-form-section' || true

# trocar casca <section rounded-lg border p-4|p-5> -> <FormSection title><FormGrid>.
# manter os campos filhos intactos. migrar por arquivo, build no meio.

npm run build
npm run lint:baseline:write
npm run lint:baseline:check

git add resources/js/Pages/<Mod> config/eslint-baseline.json
git commit -m "refactor(ds): form-section hand-rolled <MOD> -> <FormSection> (ds/no-handrolled-form-section)

Casca <section rounded-lg border p-4|p-5> -> <FormSection>/<FormGrid> (@/ui).
Campos filhos intactos. Resolve a duplicacao Create p-4 vs DadosFiscais p-5.
Baseline cai. Submit/layout identicos."

git push -u origin feat/ds-formsection-<MOD>
gh pr create --title "refactor(ds): form-section -> FormSection (<MOD>)" \
  --body "Migra a casca de secao de form hand-rolled do <MOD> pro <FormSection>/<FormGrid>. So a casca; campos intactos. Baseline cai. Ver DS-ROADMAP-ATE-ZERO.md." \
  --base main --head feat/ds-formsection-<MOD>
# >>> 2o+ PR: git fetch origin main && git rebase origin/main && npm run lint:baseline:write && git add config/eslint-baseline.json && git commit --amend --no-edit && git push -f
# NÃO --admin. [W2] confere cada Create/Edit (estrutura sensivel).
````
