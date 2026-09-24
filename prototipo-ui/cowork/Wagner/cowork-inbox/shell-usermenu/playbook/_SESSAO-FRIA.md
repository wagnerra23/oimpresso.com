# Sessão fria — shell-usermenu

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-09-23 a partir do bloco json do `00-INDICE.md` (sha 6fc8b8fac31d). Se o índice mudar, **o índice manda**, não esta folha.

## Prompt de abertura — cole no chip novo, trocando só o NN

```
/onda shell-usermenu --thread 01
```
> O diretório é **`shell-usermenu`** (literal). Não use o nome do módulo (`shell-usermenu`): o `/onda` só passa o argumento pra minúsculo.

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
| **01** | Aparencia: a cascata de tema abre e escolhe (hoje o botao nao tem handler) | CL | `01-aparencia-tema.md` | — | — |
| **02** | Sair: confirmar antes de encerrar (hoje o item nao tem handler) | CL | `02-sair-confirma.md` | — | — |
| **03** | + Adicionar empresa: item com role=menuitem e sem acao nos dois dropdowns | CL | `03-adicionar-empresa.md` | — | — |

> "Estado escrito" é só o que dá pra ver em disco (`_saida` / `bloqueio`). O estado real é **derivado** pelo `placar`; na dúvida, ele vence.
> Dono **W** = decisão/merge do Wagner, não abre chip de [CL]. Dono **CC** = volta pro Cowork.
