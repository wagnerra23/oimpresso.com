# Sessão fria — financeiro

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-09-23 a partir do bloco json do `00-INDICE.md` (sha 2c115a5ca250, revisado 2026-09-25). Se o índice mudar, **o índice manda**, não esta folha.

## Prompt de abertura — cole no chip novo, trocando só o NN

```
/onda financeiro --thread NN
```
> O diretório é **`financeiro`** (literal). Não use o nome do módulo (`financeiro`): o `/onda` só passa o argumento pra minúsculo.


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
| **00** | ALVO `financeiro--unificado` (seção drawer) — **entregue, `_saida-00.md`** | CL | `07-Unificado.drawer.md` + `_PATCH-INDICE-2026-09-25.md` §00 | — | — |
| **07** | Drawer do lançamento — acabamento + aba IA — **em execução** | CL | `07-Unificado.drawer.md` + `_PATCH-INDICE-2026-09-25.md` §07 | 00 | — |
| **08** | Drawer segue o tema | CL | `_PATCH-INDICE-2026-09-25.md` §08 | 07 · D-FIN-DW-TEMA | — |
| **09** | ALVO `financeiro--dre--index` (read-only) | CL | `09-dre-conta-mono.md` §09 | — | — |
| **10** | DRE — coluna Conta em mono (1 arquivo) | CL | `09-dre-conta-mono.md` §10 | 09 | — |

> "Estado escrito" é só o que dá pra ver em disco (`_saida` / `bloqueio`). O estado real é **derivado** pelo `placar`; na dúvida, ele vence.
> Dono **W** = decisão/merge do Wagner, não abre chip de [CL]. Dono **CC** = volta pro Cowork.
