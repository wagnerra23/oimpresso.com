# Sessão fria — Patrimonio

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-09-23 a partir do bloco json do `00-INDICE.md` (sha cb475c0ca2f4). Se o índice mudar, **o índice manda**, não esta folha.

## Prompt de abertura — cole no chip novo, trocando só o NN

```
/onda patrimonio --thread 01
```
> O diretório é **`patrimonio`** (literal). Não use o nome do módulo (`Patrimonio`): o `/onda` só passa o argumento pra minúsculo.

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
| **01** | Tenant na subconsulta de revoke (vazamento Tier 0) | CL | `01-tenant-subquery-revoke.md` | — | — |
| **02** | Trava de saldo na alocacao | CL | `02-trava-de-saldo.md` | — | — |
| **03** | Guarda asset.view no indice | CL | `03-guarda-asset-view.md` | — | — |
| **04** | Remedir D1/D5 e os nao-lidos (frente 0) | CL | `04-remedir-frente-0.md` | — | — |
| **05** | Job de retencao LGPD (nasce com enabled=false) | CL | `05-retencao-lgpd.md` | — | — |
| **06** | A UI inteira — 46 arquivos | W | `06-ui-bloqueada.md` | decisão D-ENDERECO | BLOQUEADA |

> "Estado escrito" é só o que dá pra ver em disco (`_saida` / `bloqueio`). O estado real é **derivado** pelo `placar`; na dúvida, ele vence.
> Dono **W** = decisão/merge do Wagner, não abre chip de [CL]. Dono **CC** = volta pro Cowork.
