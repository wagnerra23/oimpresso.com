# Sessão fria — Compras

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-09-23 a partir do bloco json do `00-INDICE.md` (sha 9101f86af501). Se o índice mudar, **o índice manda**, não esta folha.

## Prompt de abertura — cole no chip novo, trocando só o NN

```
/onda compras --thread 01
```
> O diretório é **`compras`** (literal). Não use o nome do módulo (`Compras`): o `/onda` só passa o argumento pra minúsculo.

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
| **01** | Rede: 2 specs E2E do modulo | CL | `01-rede-e2e.md` | — | — |
| **02** | Build daqui: coluna Margem sem fonte no drawer do prototipo | CC | `02-margem-sem-fonte.md` | — | — |
| **03** | Fornecedores — aba sem receptor | W | `03-fornecedores-bloqueada.md` | decisão D-FORN | BLOQUEADA |
| **04** | Ghost /compras/create — conflito de canon | W | `04-ghost-create-bloqueada.md` | decisão D-GHOST | BLOQUEADA |
| **05** | Smoke/canary da grade tam x cor (US-COM-005) | W | `05-grade-smoke-bloqueada.md` | decisão D-GRADE | BLOQUEADA |

> "Estado escrito" é só o que dá pra ver em disco (`_saida` / `bloqueio`). O estado real é **derivado** pelo `placar`; na dúvida, ele vence.
> Dono **W** = decisão/merge do Wagner, não abre chip de [CL]. Dono **CC** = volta pro Cowork.
