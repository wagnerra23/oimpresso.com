# Sessão fria — ModulosFaltantes

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-10-06 a partir do bloco json do `00-INDICE.md` (sha 2fe69ddc0280). Se o índice mudar, **o índice manda**, não esta folha.

## Prompt de abertura — cole no chip novo, trocando só o NN

```
/onda modulos-faltantes --thread NN
```
> O diretório é **`modulos-faltantes`** (literal). Não use o nome do módulo (`ModulosFaltantes`): o `/onda` só passa o argumento pra minúsculo.

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
| **01** | Contratos de tela das 3 telas que já existem (CV, Suporte, Vestuário) | CL | `01-contratos.md` | — | — |
| **02** | Suporte — tela Log (nova) | CL | `02-suporte-log.md` | — | — |
| **03** | Voz do Cliente — Caixa em Inertia (blade → trio) | CL | `03-voz-do-cliente.md` | PERM [W] | — |
| **04** | Catálogo QR — gerar QR em Inertia (blade → trio) | CL | `04-catalogo-qr.md` | PERM [W] | — |

> "Estado escrito" é só o que dá pra ver em disco (`_saida` / `bloqueio`). O estado real é **derivado** pelo `placar`; na dúvida, ele vence.
> Dono **W** = decisão/merge do Wagner, não abre chip de [CL]. Dono **CC** = volta pro Cowork. Dono **Design** = outro projeto Claude Design.
