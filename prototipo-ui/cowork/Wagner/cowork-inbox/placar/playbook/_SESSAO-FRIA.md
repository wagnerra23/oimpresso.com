# Sessão fria — Placar

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-09-23 a partir do bloco json do `00-INDICE.md` (sha 701f40c6ec66). Se o índice mudar, **o índice manda**, não esta folha.

## Prompt de abertura — cole no chip novo, trocando só o NN

```
/onda placar --thread NN
```
> O diretório é **`placar`** (literal). Não use o nome do módulo (`Placar`): o `/onda` só passa o argumento pra minúsculo.

> ⚠️ Nenhuma thread de [CL] livre hoje neste módulo (todas bloqueadas, com `_saida`, de outro dono ou com dependência). Rode o `placar` antes de abrir chip.

Leia só isto, nesta ordem:
1. a saída do `placar` que o `/onda` imprime: se não for `proximo`, **pare** e diga por quê.
2. o `NN-*.md` da thread, inteiro.
3. o `prefixo` e o `nao_toca` dessa thread no `00-INDICE.md` (só o objeto dela no json).
4. o que a thread listar como "lido no turno", **relido no `main`**.

**Não leia:** as outras threads · o resto do índice · o histórico do chat do Cowork.
**Termine:** `_saida-NN.md` nesta pasta e PARE. Não edite `00-INDICE.md` nem esta folha.

## Threads

| # | o que faz | dono | ficha | depende de | estado escrito |
|---|---|---|---|---|---|
| **01** | Estado `sem recibo`: provas verdes sem _saida | CL | `01-entregue-sem-recibo.md` | — | tem _saida |
| **02** | /onda modo thread: caminho quebrado ($1/$NN) e índice ausente vira PARAR | CL | `02-onda-modo-thread.md` | — | tem _saida |
| **03** | COWORK-ESTRUTURA-E-TELAS.md: 3 regras mortas saem da ROTINA | CL | `03-rotina-cowork-desatualizada.md` | — | tem _saida |

> "Estado escrito" é só o que dá pra ver em disco (`_saida` / `bloqueio`). O estado real é **derivado** pelo `placar`; na dúvida, ele vence.
> Dono **W** = decisão/merge do Wagner, não abre chip de [CL]. Dono **CC** = volta pro Cowork.
