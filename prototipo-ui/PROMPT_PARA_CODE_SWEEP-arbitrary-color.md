# SWEEP — cor crua → token semântico (`ds/no-arbitrary-color`) · FASE B

> **Origem:** Cowork [CC] · 2026-05-30 · **Para:** Claude Code [CL]. **PR alvo:** `feat/ds-arbitrary-color`.
> Fecha a regra `ds/no-arbitrary-color`. **Independente** das outras fases — toca className em telas, sem mexer em controle/estrutura. Pode rodar em paralelo com a Fase A.

## O que a regra pega
`(bg|text|border|ring|fill|stroke)-[#......]` — hex cru no className (e dentro de `BinaryExpression`/ternário também).

## Transform (precisa de CRITÉRIO — não é cego)
Troca cada hex cru pelo **token semântico** mais próximo. Mapa de decisão:

| Hex cru (intenção) | → token |
|---|---|
| branco / quase-branco de superfície | `bg-background` / `bg-card` |
| cinza claro de fundo | `bg-muted` |
| texto escuro principal | `text-foreground` |
| texto cinza secundário | `text-muted-foreground` |
| borda cinza | `border-border` |
| roxo da marca (`oklch(0.55 0.15 295)` / `#7c3aed`-ish) | `text-primary` / `bg-primary` / `border-primary` |
| vermelho de **ação** destrutiva | `text-destructive` / `bg-destructive` |
| cor de **estado** (verde/amber/rose de status) | **NÃO** vira token aqui — é badge (Fase C/D). Deixa. |
| anel de foco | `ring-ring` |

**Regra de ouro:** se não der pra mapear com confiança (cor decorativa única, gradiente, brand de terceiro), **NÃO inventa token** — deixa a linha, marca com `// TODO ds/no-arbitrary-color: revisar token` e segue. Melhor sobrar 3 hits do que achatar a cor errada.

## Método
1. `npx eslint resources/js/Pages resources/js/Modules 2>&1 | grep 'ds/no-arbitrary-color' || true`
2. Edita só as linhas apontadas, mapeando pelo quadro acima. Hex de **estado** fica pro badge.
3. `npm run build` (confirma que nenhum token quebrou visual) → `lint:baseline:write` → `check` verde.

## Aceite
- `ds/no-arbitrary-color` → 0 (menos os `// TODO` justificados, se houver).
- Zero mudança de cor perceptível (token = mesmo valor visual). **Screenshot [W2]** de 2–3 telas que mudaram.

---

## Cola no Claude Code (sem editar)

````bash
git checkout main && git pull origin main
git checkout -b feat/ds-arbitrary-color

npx eslint resources/js/Pages resources/js/Modules 2>&1 | grep 'ds/no-arbitrary-color' || true

# trocar hex cru -> token semantico (quadro do doc). Cor de ESTADO (verde/amber/rose) NAO -> fica pro badge.
# inseguro? deixa a linha + // TODO ds/no-arbitrary-color e segue.

npm run build
npm run lint:baseline:write
npm run lint:baseline:check

git add resources/js config/eslint-baseline.json
git commit -m "refactor(ds): cor crua -> token semantico (ds/no-arbitrary-color)

Troca bg/text/border-[#hex] por token (bg-background/card/muted, text-foreground/
muted-foreground, border-border, text-primary, text-destructive, ring-ring).
Cor de estado fica pro Badge (Fase C/D). Casos ambiguos marcados TODO. Baseline
cai. Sem mudanca visual."

git push -u origin feat/ds-arbitrary-color
gh pr create --title "refactor(ds): cor crua -> token semantico" \
  --body "Fecha ds/no-arbitrary-color: hex cru -> token semantico nas telas. Cor de estado fica pro badge (Fase D). Sem mudanca visual perceptivel. Ver DS-ROADMAP-ATE-ZERO.md." \
  --base main --head feat/ds-arbitrary-color
# >>> 2o+ PR: git fetch origin main && git rebase origin/main && npm run lint:baseline:write && git add config/eslint-baseline.json && git commit --amend --no-edit && git push -f
# NÃO --admin. [W2] confere cor.
````
