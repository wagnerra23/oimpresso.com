# Sessão fria — Sidebar

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-09-23 a partir do bloco json do `00-INDICE.md` (sha af09f7c3a0fd). Se o índice mudar, **o índice manda**, não esta folha.

## Prompt de abertura — cole no chip novo, trocando só o NN

```
/onda sidebar --thread 06
```
> O diretório é **`sidebar`** (literal). Não use o nome do módulo (`Sidebar`): o `/onda` só passa o argumento pra minúsculo.

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
| **01** | Seção CORPO: nav + a11y A1–A12 + aposentar Tabs/Chat/ConvRow | CC | `01-corpo-a11y.md` | — | tem _saida |
| **02** | Seção MODOS: auto-rail UI-0030 + persistir só escolha manual | CC | `02-modos-auto-rail.md` | — | tem _saida |
| **03** | Seção TOPO: paridade CompanyPicker + slot de alerta | CC | `03-topo-picker.md` | thread 01 | tem _saida |
| **04** | Modo hidden + SidebarReopenHandle → promover pro vivo | CL | `04-hidden-reopen.md` | thread 01 | — |
| **05** | Ghosts × ADR 0180 — emenda ou reversão | W | `05-ghosts-adr-0180.md` | — | — |
| **06** | Contrato de tela do shell + gates | CL | `06-contrato-e-gates.md` | — | — |

> "Estado escrito" é só o que dá pra ver em disco (`_saida` / `bloqueio`). O estado real é **derivado** pelo `placar`; na dúvida, ele vence.
> Dono **W** = decisão/merge do Wagner, não abre chip de [CL]. Dono **CC** = volta pro Cowork.
