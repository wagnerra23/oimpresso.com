# Sessão fria — Governanca

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-09-23 a partir do bloco json do `00-INDICE.md` (sha 0d159eb84a10). Se o índice mudar, **o índice manda**, não esta folha.

## Prompt de abertura — cole no chip novo, trocando só o NN

```
/onda governance --thread 01
```
> O diretório é **`governance`** (literal). Não use o nome do módulo (`Governanca`): o `/onda` só passa o argumento pra minúsculo, e `governanca` não é esta pasta.

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
| **01** | Descer o contrato de governanca pra contrato-cowork (estagio; cobre 5 de 9, declarado) | CL | `01-contrato-desce.md` | — | — |
| **02** | Rede: 2 specs E2E (dashboard + policies toggle) | CL | `02-rede-e2e.md` | — | — |
| **03a** | casos.md de Policies (a menor tela) — abre a frente do trio | CL | `03a-casos-policies.md` | — | — |
| **04** | Meu build esta 4 telas atras da producao | CC | `04-build-atras.md` | — | — |
| **05** | Gate::before deixa admin passar por qualquer can: | W | — | decisão D-GATE | BLOQUEADA |
| **06** | PLANO-MESTRE: Trilha D com o ciclo completo — ratificado [W] 2026-08-06 | CL | `06-trilha-d-ciclo.md` | — | — |

> "Estado escrito" é só o que dá pra ver em disco (`_saida` / `bloqueio`). O estado real é **derivado** pelo `placar`; na dúvida, ele vence.
> Dono **W** = decisão/merge do Wagner, não abre chip de [CL]. Dono **CC** = volta pro Cowork.
