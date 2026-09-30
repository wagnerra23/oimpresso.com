# Sessão fria — Produto

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-09-30 a partir do bloco json do `00-INDICE.md` (sha 4fa39eb8f007). Se o índice mudar, **o índice manda**, não esta folha.

## Prompt de abertura — cole no chip novo, trocando só o NN

```
/onda produto --thread NN
```
> O diretório é **`produto`** (literal, minúsculo).

Leia só isto, nesta ordem:
1. a saída do `placar` que o `/onda` imprime: se não for `proximo`, **pare** e diga por quê.
2. o `NN-*.md` da thread, inteiro.
3. o `prefixo` e o `nao_toca` dessa thread no `00-INDICE.md` (só o objeto dela no json).
4. o que a thread listar como "lido no turno", **relido no `main`**.

**Não leia:** as outras threads · o resto do índice · o histórico do chat do Cowork · os dois `PEDIDO-*` absorvidos.
**Termine:** `_saida-NN.md` nesta pasta e PARE. Não edite `00-INDICE.md` nem esta folha.

## Threads

| # | o que faz | dono | ficha | depende de | estado escrito |
|---|---|---|---|---|---|
| **00** | PUXAR 8 Pages vivas → protótipo | CC | `01-puxar-vivo.md` | D7 | — |
| **A1** | ALVO produto--unificado--index | CL | `02-alvos.md` | 00 | — |
| **A2** | ALVO das 4 telas novas | CL | `02-alvos.md` | — | — |
| **01** | Permissões nos 3 controllers abertos | CL | `03-permissoes.md` | D1 · D2 | — |
| **07** | Contratos das 4 telas novas | CL | `04-contratos.md` | A2 | — |
| **02** | Cadastros: Unidades · Marcas · Categorias | CL | `05-cadastros.md` | 07 · D3 | — |
| **03** | Cadastros: Variações · Grupos · Garantias | CL | `05-cadastros.md` | 01 · 02 | — |
| **04** | Etiquetas | CL | `06-etiquetas.md` | 01 · 07 · D4 | — |
| **05** | Importação (2 PRs) | CL | `07-importacao.md` | 01 · 07 | — |
| **06** | Atualizar preço por planilha | CL | `08-atualizar-preco.md` | 01 · 07 | — |
| **08** | Menu filtrado por permissão | CL | `09-menu.md` | 02–06 | — |

> "Estado escrito" é só o que dá pra ver em disco (`_saida` / `bloqueio`). O estado real é **derivado** pelo `placar`; na dúvida, ele vence.
> Dono **W** = decisão/merge do Wagner. Dono **CC** = volta pro Cowork.
