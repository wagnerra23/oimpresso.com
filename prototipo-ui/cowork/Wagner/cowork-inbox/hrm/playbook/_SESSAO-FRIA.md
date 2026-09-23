# Sessão fria — Hrm

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-09-23 a partir do bloco json do `00-INDICE.md` (sha 45e63465d2e4). Se o índice mudar, **o índice manda**, não esta folha.

## Prompt de abertura — cole no chip novo, trocando só o NN

```
/onda hrm --thread 02
```
> O diretório é **`hrm`** (literal). Não use o nome do módulo (`Hrm`): o `/onda` só passa o argumento pra minúsculo.

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
| **01** | Build: TABS do HRM (−Presença · +Departamentos/Cargos) | CC | `01-build-tabs-hrm.md` | — | tem _saida |
| **02** | Licenças — Page | CL | `02-licencas.md` | — | — |
| **03** | Tipos de licença — Page | CL | `03-tipos-licenca.md` | — | — |
| **04** | Metas — PUXAR (produção à frente, #6869) | CC | `04-metas-venda.md` | decisão RESIDUO-5 | — |
| **05** | Turnos — Page | CL | `05-turnos.md` | thread 09 · decisão RESIDUO-3 | — |
| **06** | Painel — Page | CL | `06-painel.md` | thread 09 | — |
| **07** | Configurações — PUXAR (12 campos × 10 chaves) | CC->CL | `07-configuracoes-puxar.md` | — | — |
| **08** | Feriados — PUXAR (ler Holidays/Index.tsx) | CC | `08-feriados-puxar.md` | — | — |
| **09** | Presença SAI do HRM → Ponto dono da jornada | W+CL | `09-presenca-sai.md` | — | — |
| **10** | Folha — BLOQUEADA (D2 → projeto com ADR própria) | W | `10-folha-bloqueada.md` | — | BLOQUEADA |
| **11** | Fim do topnav Blade + limpeza O8 | CL | `11-topnav-legado.md` | thread 02 · thread 03 · thread 05 · thread 06 | — |

> "Estado escrito" é só o que dá pra ver em disco (`_saida` / `bloqueio`). O estado real é **derivado** pelo `placar`; na dúvida, ele vence.
> Dono **W** = decisão/merge do Wagner, não abre chip de [CL]. Dono **CC** = volta pro Cowork.
