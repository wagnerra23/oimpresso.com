# Sessão fria — AppLojas

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-10-06 a partir do bloco json do `00-INDICE.md` (sha 2fe69ddc0280). Se o índice mudar, **o índice manda**, não esta folha.

## Prompt de abertura — cole no chip novo, trocando só o NN

```
/onda app-lojas --thread NN
```
> O diretório é **`app-lojas`** (literal). Não use o nome do módulo (`AppLojas`): o `/onda` só passa o argumento pra minúsculo.

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
| **01** | App das lojas v1 — Ponto dentro do Mobile (desenho) | Design | `01-mobile-com-ponto.md` | — | — |

> "Estado escrito" é só o que dá pra ver em disco (`_saida` / `bloqueio`). O estado real é **derivado** pelo `placar`; na dúvida, ele vence.
> Dono **W** = decisão/merge do Wagner, não abre chip de [CL]. Dono **CC** = volta pro Cowork. Dono **Design** = outro projeto Claude Design.
