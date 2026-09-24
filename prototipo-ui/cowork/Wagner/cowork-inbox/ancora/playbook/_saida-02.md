---
sessao: "_saida-02"
thread: "02 · query ambígua deixa de sortear charter"
dono: "[C]"
data: 2026-09-24
tipo: recibo retroativo + marcadores
entregue_em: "#7131 + #7154 (2026-09-09)"
base_lida: wagnerra23/oimpresso.com@main 723d2b1e6
---
# _saida-02

## O que já estava entregue
A thread foi executada em 2026-09-09 e ficou sem `_saida`, então o placar a mostrava como `próximo`:

- **#7131** (mergeado 2026-09-09 18:57Z) — o `resolveAncora` coleta `candidatos` com uma força
  (4 rota · 3 caminho inteiro · 2 sufixo · 1 substring); empate na força máxima vira
  `r.ambiguidade`, e a CLI **recusa**: lista os candidatos (teto 10, com "e mais N"), diz como
  desambiguar e sai **2**. A API continua `ok:true` + `ambiguidade` por decisão [W] do mesmo dia,
  para não quebrar `design-diff-lote.mjs` e `render-proto-baseline.mjs` (o motivo está no
  comentário do `printResolve`).
- **#7154** (mergeado 2026-09-09 19:44Z) — o caso "1 match fraco" resolve em exit 0, mas a saída
  diz `match FRACO … SUBSTRING`. É o item D.2 do playbook.

O playbook manda parar se o loop já coleta candidatos. Não reimplementei nada.

## O que este PR muda
Os asserts já existiam, testavam a CLI de fora (`spawnSync` do próprio arquivo, lendo o
`status`) e mordiam. Só o **nome** não batia com a prova do índice. Troquei o rótulo de 3 deles,
sem mexer na lógica:

| antes | depois |
|---|---|
| `BITE CLI: query ambígua SAI 2 — não escolhe` | `BITE ambiguidade (CLI): …` |
| `CONTROLE CLI: match FORTE segue resolvendo, exit 0` | `CONTROLE ambiguidade (CLI): …` |
| `CONTROLE CLI: 1 match FRACO e único ainda resolve, exit 0` | `CONTROLE ambiguidade (CLI): …` |

## Provas medidas em 723d2b1e6 + este diff
1. `node scripts/design/ancora.mjs --selftest` → **rc=0**, `SELFTEST OK`, os 3 rótulos novos `[PASS]`.
2. **Mutação M1**: tirar o `return 2` da recusa → **rc=1**, 3 FAIL: `BITE ambiguidade (CLI): query
   ambígua SAI 2`, o do teto e o de "não imprime `âncora ✓` nem selo".
3. **Mutação M2**: fazer a recusa valer para toda query → **rc=1**, os 2 `CONTROLE ambiguidade`
   caem (match forte e fraco único deixam de sair 0).
4. Depois de cada mutação o arquivo foi restaurado de cópia e o sha256 conferido
   (`f9c894931d4aa989` antes = depois).
5. `candidatos` já estava no arquivo (#7100/#7131).

## Consumidores
Nenhum muda. `design-coverage`, `ancora-guard` e `integrity-check` leem `--list`, não a query. Os
chamadores programáticos (`post-merge-ui-smoke-required.mjs` importa só
`caminhoDaAncora`/`ehDeclaracaoNa`) não dependem dos rótulos do selftest. `git grep` pelos
rótulos antigos: só existiam dentro do próprio `ancora.mjs`.

## Residual
A thread 03 (`--list` prova o arquivo) é a próxima no mesmo arquivo. Meça o sha antes de escrever.
