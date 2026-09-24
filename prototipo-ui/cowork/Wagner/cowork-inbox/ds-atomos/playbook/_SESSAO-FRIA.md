# Sessão fria — ds-atomos

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-09-23 a partir do bloco json do `00-INDICE.md` (sha 2b4a3ec3b48a). Se o índice mudar, **o índice manda**, não esta folha.

## Prompt de abertura — cole no chip novo, trocando só o NN

```
/onda ds-atomos --thread 01
```
> O diretório é **`ds-atomos`** (literal). Não use o nome do módulo (`ds-atomos`): o `/onda` só passa o argumento pra minúsculo.

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
| **01** | ui/card.tsx — badge · note · flush | CL | `01-card-anatomia.md` | — | — |
| **02** | shared/KpiCard.tsx — variant=filter | CL | `02-kpicard-filter.md` | — | — |
| **03** | shared/Toolbar.tsx — CRIAR (3 zonas) | CL | `03-toolbar-criar.md` | — | — |
| **04** | shared/DataTable.tsx — density="dense" aditivo (D-GRADE: servidor) | CL | `04-tabela-densa.md` | — | — |
| **05** | shared/StatusBadge.tsx — kinds sla · frescor · atendimento + rel/tone | CL | `05-statusbadge-kinds.md` | — | — |
| **08** | StatusBadge: tirar o fill sólido (AP7) — decisão [W] 2026-09-01 | CL | `08-statusbadge-ap7.md` | — | — |

> "Estado escrito" é só o que dá pra ver em disco (`_saida` / `bloqueio`). O estado real é **derivado** pelo `placar`; na dúvida, ele vence.
> Dono **W** = decisão/merge do Wagner, não abre chip de [CL]. Dono **CC** = volta pro Cowork.
