---
sessao: "01"
saida: 2026-09-09
base_lida: 39c9233d424c (remedido — o índice nasceu em 752041ac450d)
dono: "[CL]"
veredito: FEITA
---
# `_saida-01` — a perna do bundle resolve no LUGAR_FIXO

## 1 · O defeito, reproduzido antes de tocar em código
```
$ node prototipo-ui/ancora.mjs Repair/Dashboard/Index      # sem --staging
  âncora:  ⚠️ charter sem related_prototype nem -page.jsx — registre o protótipo
```
com `prototipo-ui/cowork/repair-page.jsx` = **46.532 B** no lugar fixo. Confirmados os
5 arquivos da tabela do pedido: `repair-page.jsx` 46.532 · `governance-page.jsx` 20.212 ·
`oficina-page.jsx` 72.534 · `oficina-os-page.jsx` 18.896 · `produtos-page.jsx` 44.275.

## 2 · O que mudou
Uma perna nova em `resolveAncora`, depois do bloco `if (stagingDir)`. Reusa `LUGAR_FIXO`,
`mockupJsx` e `ehArquivo` — **zero constante nova de lugar, zero mudança de assinatura**.
`raiz` = o REPO (o arquivo está no git), não staging — o defeito de 2026-08-25.

## 3 · Um achado que o pedido não previa: DEDUP
A 1ª versão fazia `Sells/Index` imprimir **duas** âncoras pro mesmo `vendas-page.jsx` (ela
declara `related_prototype` E `bundle_source`). O pedido a nomeia como controle positivo —
"tem que continuar resolvendo igual". Quem pegou foi o controle, não a revisão.

Conserto: dedup por **arquivo resolvido** (via `caminhoDaAncora`), não por tipo de perna —
comparar `tipo` não pegaria, porque as pernas são de tipos diferentes por construção.
Charter com `n/a` + `bundle_source` segue imprimindo **as duas coisas**: `n/a` não resolve
em arquivo nenhum, logo nunca colide no dedup.

## 4 · Validação (os 8 itens do pedido)
| # | item | resultado |
|---|---|---|
| 1 | as 14 resolvem sem `--staging`, dizendo de qual campo vieram | ✓ `bundle_source` e `visual_source` atribuídos corretamente |
| 2 | `--staging` continua vencendo, com `raiz` = staging | ✓ `[-page.jsx (bundle · bundle_source)]` |
| 3 | `n/a` + `bundle_source` imprime as duas coisas | ✓ `Repair/Index` imprime `sem âncora: n/a (…)` **e** a perna |
| 4 | ausência real continua visível | ✓ `Suporte/Visao` (charter sem os 3 campos) segue no ⚠️ |
| 5 | selftest com BITE + 2 controles | ✓ 5 asserções novas |
| 6 | BITEs de 2026-08-25 seguem verdes | ✓ |
| 7 | `--selftest` verde | ✓ rc=0 · **67 PASS · 0 FAIL** |
| 8 | PLACAR no PR | ✓ |

## 5 · Bite-test (o BITE morde?)
Mutação `if (!ancoras.some(…))` → `if (false)`: os 2 BITEs ficam **[FAIL]**, `--selftest` rc=1.
Restaurado, rc=0. Os controles seguem verdes na mutação — corretamente: eles provam que
NÃO se inventa perna, e isso continua verdadeiro com a feature desligada.

## 6 · Guardas
`.claude/hooks/post-merge-ui-smoke-required.mjs` segue importando `caminhoDaAncora`
(5 ocorrências) e seu teste passa (rc=0). Só `prototipo-ui/ancora.mjs` foi tocado.
