# Sessão fria — PageHeader

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-09-23 a partir do bloco json do `00-INDICE.md` (sha ebe1fc8be7e4). Se o índice mudar, **o índice manda**, não esta folha.

## Prompt de abertura — cole no chip novo, trocando só o NN

```
/onda pageheader --thread 02
```
> O diretório é **`pageheader`** (literal). Não use o nome do módulo (`PageHeader`): o `/onda` só passa o argumento pra minúsculo.

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
| **01** | ADR 0395 — merge + flip do required (ordem da própria ADR) | W | `01-adr-0395.md` | — | — |
| **02** | Arquivar pageheader-matriz-diferencas.md | CL | `02-arquivar-matriz.md` | — | — |
| **03** | DS: TabBar com setas (tablist) + PageHeader com role=banner | CL | `03-ds-a11y.md` | — | — |
| **04** | h1 do PageHeader: padrão 600 (titleWeight default semibold) | CL | `04-h1-600.md` | — | — |
| **05** | Abas do PageHeaderTabs com 36px | CL | `05-aba-36.md` | thread 04 | — |
| **06** | Título da Caixa Unificada 14px → 22px | CL | `06-caixa-h1.md` | thread 04 | — |

> "Estado escrito" é só o que dá pra ver em disco (`_saida` / `bloqueio`). O estado real é **derivado** pelo `placar`; na dúvida, ele vence.
> Dono **W** = decisão/merge do Wagner, não abre chip de [CL]. Dono **CC** = volta pro Cowork.
