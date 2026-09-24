# Sessão fria — jana

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-09-23 a partir do bloco json do `00-INDICE.md` (sha 19ff53c88491). Se o índice mudar, **o índice manda**, não esta folha.

## Prompt de abertura — cole no chip novo, trocando só o NN

```
/onda jana --thread 01
```
> O diretório é **`jana`** (literal). Não use o nome do módulo (`jana`): o `/onda` só passa o argumento pra minúsculo.

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
| **01** | Painel: o tier Pro governa brief, análises e ações | CL | `01-painel.gating-pro.md` | — | — |
| **02** | Painel sem histórico mostra um estado de página, não 6 caixas vazias | CL | `02-painel.estado-vazio.md` | — | — |
| **03** | Jana/Pro: tirar os style={{}} inline (cores → tokens/classes) | CL | `03-pro.sem-inline.md` | — | — |
| **04** | Permissão da Jana provada por teste (6 Pest do emenda de casos) | CL | `04-permissao.testes.md` | — | — |

> "Estado escrito" é só o que dá pra ver em disco (`_saida` / `bloqueio`). O estado real é **derivado** pelo `placar`; na dúvida, ele vence.
> Dono **W** = decisão/merge do Wagner, não abre chip de [CL]. Dono **CC** = volta pro Cowork.
