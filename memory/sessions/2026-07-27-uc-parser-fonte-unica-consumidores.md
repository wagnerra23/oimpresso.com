---
date: "2026-07-27"
topic: "Migrar os consumidores de ucHeadRe pra fonte única — a premissa caiu na medição (4 dos 5 usos querem o BLOCO, não o id) e o DoD por grep provou-se incompleto por construção"
authors: [C, W]
type: session
module: governance
pii: false
related_adrs:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
---

# Parser de UC — fonte única fecha o ciclo (4 PRs)

## O pedido

Migrar os consumidores restantes de `ucHeadRe()` pra `ucsDeclaredInCasos()` em
[`scripts/lib/uc-regex.mjs`](../../scripts/lib/uc-regex.mjs). Contexto: o [PR #4843](https://github.com/wagnerra23/oimpresso.com/pull/4843)
consertou a sentinela Tier-0 que aplicava `ucHeadRe()` na **linha crua** (o heading canônico
é `## UC-CEDI-01 · …`, começa em `##`, então o regex ancorado em `^UC-` **nunca casava**) —
`hasCasosCoverage()` dava false pra toda tela e o piso media 4 telas cobertas onde havia 29.

A raiz é estrutural: a lib matou "4 regex que deviam ser iguais e drifaram", mas o **uso**
dela (o `split(/^##\s+/m).slice(1)` obrigatório antes do match) seguiu copiado e drifou pela
mesma porta.

## O que a MEDIÇÃO mudou no plano (antes de escrever código)

O prompt listava 5 consumidores. Medindo uso por uso, **4 dos 5 não consomem o id — consomem
o BLOCO**:

| Uso | Consome | Migrável p/ `ucsDeclaredInCasos`? |
|---|---|---|
| `casos-coverage-guard:157` (G-2) | só o id | ✅ |
| `casos-coverage-guard:233` (G-5) | bloco → `/Status:/` | ❌ |
| `casos-coverage-guard:342` (G-7) | bloco → `declaredStatus` | ❌ |
| `screen-grade-report:90` | bloco → `firstStatusGlyph` | ❌ |
| `uc-derive:68` | bloco → glyph + teste intencionado | ❌ |

A fonte única, como estava, **não era migrável por eles** — e a cópia do split ficaria de pé,
que é a porta exata pela qual o parser drifou. Daí `ucBlocksInCasos()` (gerador `{uc, block}`),
com `ucsDeclaredInCasos` derivando dela (uma travessia só).

Refinamento da contagem: `criar-tela.mjs` **nunca importou a lib** — só mencionava `ucHeadRe`
num comentário. Consumidores de código eram 3, não 5.

## Equivalência — diff literal, nada regravado

Regra dura do pedido: provar byte a byte, não "parece igual".

| Superfície | Resultado |
|---|---|
| `uc-derive` × 15 módulos × 2 modos (`--propose`) | 30 arquivos, 74.308 B, **zero** |
| guard `--report` / default / `--check-baseline-shrink` / `--json` | idênticos |
| guard `--write-baseline` | 17.121 B, **220 violações item a item** (só `generated_at` difere) |
| `screen-grade:report` | 3.983 B, idêntico |

O baseline commitado **nunca foi regravado**: gerado pelas duas versões só pra comparar e
restaurado do git (`git status` limpo depois).

**Igualdade de saída não prova que o gate ainda morde** — os meta-testes provam:
`vitest tests/casosGuard.spec.ts + casosResultsCollect.spec.ts` = **43/43** (46 em main após
o #4882), incluindo as sensibilidades G-1 (tela nova sem trio falha), G-2 (UC novo sem teste
vira órfão), G-6 (`.tsx` mais novo que `last_run` vira stale), G-7 (lie nova bloqueia).

Selftest da lib: **9 → 13**. Casos novos: bloco sem o `## `; bloco **para no próximo `## `**
(se vazasse, UC sem Status herdaria o do vizinho e o G-5 ficaria cego); equivalência
`ids(ucBlocksInCasos) === ucsDeclaredInCasos`; gerador fresco.

## Duas falhas de CI — mesma causa, nenhuma do código

`baseline-tamper-guard` e `--check-baseline-shrink` acusaram 5 UCs da Forja
(`UC-FORJA-04/06/11/12/13`) como "violação nova grandfatherada". Meu PR não toca o baseline.

**Os gates estavam certos.** O [PR #4879](https://github.com/wagnerra23/oimpresso.com/pull/4879)
mergeou durante a sessão e regravou `casos-coverage-baseline.json` (181→178 UCs, 28→23 órfãos).
Minhas branches carregavam a versão anterior, então **pareciam reintroduzir** violações que o
main já tinha zerado. Confirmado por medição: o commit `c15221f634` não é ancestral das branches.

Correção: `git merge origin/main` (não rebase — force-push é barrado pelo hook
`block-destructive`, corretamente). **Não regravei o baseline** — seria o atalho óbvio e teria
desfeito o #4879 em silêncio.

E como o corpus mudou, **re-provei a equivalência com os dados novos**: as 4 superfícies do
guard seguem byte-idênticas.

## O achado que fica aberto — o DoD por grep é incompleto POR CONSTRUÇÃO

`git grep ucHeadRe` **só acha quem já usa a lib**. Quem tem regex próprio escapa. Varrendo o
padrão `split(/^##`, achei duas cópias invisíveis a esse grep — e uma diverge de fato:

`scripts/qa/screen-coverage-map.mjs::ucsFromCasos` usa `/^(UC-[A-Z0-9]{0,8}-?\d{1,3})\b/i` —
**sem o `[a-zA-Z]?` de sufixo** da lib. Medido no corpus (44 arquivos):

```
total UC — lib=181  screen-coverage-map=178  (3 perdidos)
diverge em: resources/js/Pages/governance/DsRollout.casos.md
```

Os 3 invisíveis são reais e têm heading canônico (L92/99/109): `UC-DSR-08b`, `UC-DSR-01b`,
`UC-DSR-04b`. Sem o `[a-zA-Z]?`, o `\b` falha diante do `b` e o UC **some inteiro** (não é
truncado). `npm run screen:files governance/DsRollout` lista 10; a lib enxerga 13.

São **dois gates required discordando sobre o mesmo fato**: `casos-gate` cobra Status+teste
desses 3; `screen-coverage-gate` não os vê. Passou porque o selftest do próprio script
(`screen-coverage-map.mjs:342`) cobre `~~UC~~` tachado mas **não** sufixo letra.

Também `scripts/qa/prototipo-readiness.mjs::contaUCs` usa `/^UC-/i` (permissivo: conta
`## UC- solto` que a lib rejeita). No corpus real bate por acaso (181).

**Não corrigido de propósito:** muda comportamento (178→181) num gate required — a instrução
mandava manter mudança de comportamento fora da migração mecânica, e mexer no número é decisão
[W]. Registrado como task separada, iniciada pelo [W] em sessão paralela.

## Nota operacional (não vira lápide — nada quebrou)

- `gh pr create --base <branch>` **não pegou**: os 4 PRs nasceram com base `main`, então os
  "empilhados" carregavam 2 commits. Depois do squash do #4871 o SHA mudou e o commit ficou
  órfão dentro deles. Refeito por **branches novas** (`-v2`), já que rebase exigia force-push.
- Estourei o rate limit de **GraphQL** com `gh pr checks --watch` em loop; migrei pra REST
  (`/commits/{sha}/check-runs`), que tem cota separada.
- Fila do repo chegou a **819 runs enfileirados** contra 13 executando — os PRs ficaram ~40min
  em `queued` sem nada de errado neles.

## Pointers

- Lib: [`scripts/lib/uc-regex.mjs`](../../scripts/lib/uc-regex.mjs) · selftest [`uc-regex.test.mjs`](../../scripts/lib/uc-regex.test.mjs)
- Guard (dono do formato): [`scripts/casos-coverage-guard.mjs`](../../scripts/casos-coverage-guard.mjs)
- Handoff: [`2026-07-27-2056-uc-parser-fonte-unica.md`](../handoffs/2026-07-27-2056-uc-parser-fonte-unica.md)
