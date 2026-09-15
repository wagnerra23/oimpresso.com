---
date: "2026-09-15"
topic: "Hook de schema mudo por dep não declarada, e o 5º eixo que pega required renomeado"
authors: [W, C]
prs: [7284, 7303, 7306, 7307, 7308, 7310]
outcomes:
  - "memory-schema-guard desmutado: as 3 deps do validador estavam ausentes dos 4 campos do package.json e o hook falhava aberto em 100% das invocações locais"
  - "5º eixo do required-always-run armado (advisory) — detecta promoção que renomeia job e órfana PRs abertos; FP 0 em 60 commits, bite-test pelo CLI"
  - "11 de 12 PRs abertos estavam travados por required que nunca nascia; 6 destravados com update-branch"
  - "Duas decisões [W]: manter o eixo advisory até ter mordida, e manter a comparação contra origin/main"
  - "Três conclusões minhas derrubadas por medição — todas por afirmar sobre premissa não medida"
---

## TL;DR

O pedido era desmutar um hook. O hook estava mudo por **dep não declarada**, não por lógica errada. Consertado isso, o CI expôs uma cadeia: dívida herdada num gate de handoff → sua promoção a required renomeando o job → **11 de 12 PRs abertos** sem o check exigido. Destravei 6 à mão e armei o eixo que detecta a classe. [W] decidiu mantê-lo advisory até haver mordida real.

## 1. O defeito era de declaração, não de lógica

`.claude/hooks/memory-schema-guard.mjs` é `deny`-by-default sobre `memory/**`, nascido do #4798. Medido: alimentado com frontmatter que o CI reprova, devolvia **exit 0 e zero bytes**.

`await import('ajv/dist/2020.js')` (`:105`) lançava `ERR_MODULE_NOT_FOUND` — o ajv hoistado era **6.15.0**, transitivo do `eslint@9`, e `dist/2020.js` só existe no ajv 8. Caía no `catch { process.exit(0) }` (`:110`), que é **fail-open correto** para ausência transitória. O que estava errado: as 3 deps não estavam em **nenhum** dos 4 campos do `package.json` — o CI as instala com `--no-save`. Mudez permanente.

O risco que justificou PR próprio (`ajv@8` no topo × `eslint@9`) foi medido: o npm aninhou `ajv@6.15.0` sob `eslint` e `@eslint/eslintrc` — confirmado pelo **resolvedor real** (`createRequire`), não por leitura de path — e os dois comandos da lane saíram com output **byte a byte idêntico**.

## 2. A cadeia que o CI expôs

| elo | o que era |
|---|---|
| `handoff-integrity` vermelho | herdado do #7224: `HANDOFF_DIR` no path velho → `files: 0` → 5 "refs mortas" que existiam, eixo órfão **cego por construção** |
| promoção a required (#7286) | renomeou o job na mesma leva (tirou `advisory ·`) |
| consequência | **11 de 12** PRs abertos emitindo o nome velho → `BLOCKED` com 0 falhas e 0 pendentes |

É a §5 2026-08-08, 2ª ocorrência. Agravante que fecha o argumento da ADR 0256: **a prescrição do conserto já existia em prosa** dentro do próprio `required-checks-baseline.json`, escrita pelo PR que causou o dano, e não foi cumprida.

## 3. O 5º eixo

Estendi o dono (`required-always-run.mjs`), não abri paralelo. Compara os contexts **emitidos** na base × no head. Nasce advisory, em job separado do `lint` (que é required).

- **FP medido antes de armar**: 60 commits de `main` → 1 acusa, e é o commit do incidente. Zero FP.
- **Bite-test pelo CLI de fora**, repo git de 2 commits: igual-à-base → 0 · rename → 1 · base inalcançável → **2 (NÃO MEDIDO)**, nunca 0.
- **Controle positivo ao vivo**: acusou o próprio PR que o promovia.

## 4. Onde eu errei (três vezes, mesma raiz)

1. Afirmei que o monitor me avisaria do CI — usava `jq`, **ausente neste shell**; sairia mudo (§5 2026-08-11).
2. Afirmei que um session log seguia fora do schema; já estava consertado.
3. Declarei um FP estrutural que **não existe no CI** — presumi head cru onde o checkout de `pull_request` entrega **merge ref**.

A raiz é uma só: afirmar sobre premissa não medida. O terceiro quase virou código — [W] pediu o PR que trocaria `origin/main` por `base.sha`, e medir antes mostrou que isso **introduziria** o FP da §5 2026-09-15 em vez de remover.

## 5. Decisões [W]

- **"deixa advisory, coleta mordida primeiro"** → #7310 fechado, branch protection intocada.
- **"deixa com esta"** → o predicado permanece contra `origin/main`.

## 6. Aberto

- DR-2 em **1/2** mordidas. O `design-gate-bites --scan` **não serve** para este eixo: ele detecta violação que persiste no `main`, e esta é transitória.
- O flip futuro precisa rodar `hooks-manifest-generate --write` no mesmo PR — o rename do job mexe no `_HOOKS-INDEX`.
- 5 PRs em conflito seguem sem o required do `handoff-integrity`; destravam com o dono.
