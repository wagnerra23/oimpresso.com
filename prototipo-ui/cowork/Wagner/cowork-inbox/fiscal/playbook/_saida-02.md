---
sessao: "_saida-02"
thread: "02 · Paginação .fx-pager no Cockpit"
dono: "[CL]"
data: 2026-09-23
prefixo_tocado: nenhum — só este recibo
base_lida: wagnerra23/oimpresso.com@main 1061dbf2e
natureza: RECIBO RETROATIVO — a thread foi entregue ANTES de o playbook existir
---
# _saida-02

## 0 · Por que não houve execução

A thread foi entregue pelo [PR #6711](https://github.com/wagnerra23/oimpresso.com/pull/6711)
(*"Onda 3 — o cockpit serve uma PÁGINA da lista, e filtrar volta à 1ª"*), mergeado em
**2026-09-04T01:17:50Z** — **4 dias antes** do playbook (`criado: 2026-09-08`). O índice §0 a
marcou "DE PÉ" porque a prova buscava a string `Pagination`, e a paginação do Cockpit usa nomes em
PT-BR (`pagina` / `porPagina` / `paginas`). O `_saida-01` já tinha registrado isso em 2026-09-14
(§2: *"a prova do playbook era falso-negativo por buscar a string `Pagination`"*). Nenhum código
foi escrito nesta sessão.

## 1 · Onde está (medido em `1061dbf2e`)

- [`Cockpit.tsx:241-242`](../../../../../../resources/js/Pages/Fiscal/Cockpit.tsx) — estado `pagina` / `porPagina` (default 8).
- `Cockpit.tsx:296-316` — `filtrados.slice(...)` e o `useEffect` que devolve à página 1 ao mudar tipo, status, busca, cliente ou tamanho.
- `Cockpit.tsx:721-755` — `.fx-pager` com `data-contract="paginacao-notas"`: meta `a–b de N carregadas`, `Select` 8/25/50 do DS, Anterior/Próxima (`Button` do DS, desabilitados nos extremos), `p / total`.
- Posição: o rodapé é o último filho dentro do `FxShell`, **depois** do `.fx-table` (`:573`). Só renderiza com `filtrados.length > 0` — lista vazia não mostra pager fantasma.
- Contrato: `UC-FCKP-09` em [`Cockpit.casos.md:57`](../../../../../../resources/js/Pages/Fiscal/Cockpit.casos.md) e `:154`, teste `tests/js/fiscal-cockpit-paginacao.test.tsx`, lane `fiscal-cockpit-paginacao-gate.yml` (advisory).

## 2 · Gate rodado nesta sessão

`gh workflow run fiscal-cockpit-paginacao-gate.yml --ref main` → run
[`35910709794`](https://github.com/wagnerra23/oimpresso.com/actions/runs/35910709794), SHA
`1061dbf2e`: **success, `Tests 4 passed (4)`** — as 4 asserções do `UC-FCKP-09` executaram (não é
verde por não-execução). O `UC-FCKP-11` (teclado) segue com `onKeyDown` presente em `:605`; a
última run de `fiscal-teclado-gate.yml` em `main` é **success** (2026-09-11).

## 3 · Onde a entrega diverge do texto da thread — e por quê

| a thread pedia | o que existe | razão |
|---|---|---|
| `Pagination` do DS | pager local com `Select` + `Button` do DS | **não existe** componente `Pagination` em `resources/js/Components/ui/` (medido). Só há `function Pagination` local em `Cliente/Index.tsx:2261` e `OficinaAuto/Vehicles/Index.tsx:585`. A thread apontava para um componente inexistente. |
| corte server-side se a lista vier inteira | corte **client-side** sobre ≤50 linhas | `NotasUnifiedService::LIMITE = 50` já corta **no servidor** (`:32`, `->limit()` em `:86` e `:126`, `->take()` em `:42`). A tela nunca recebe a base do negócio — a preocupação do bloco C da thread não se realiza. |
| meta `de T` (total) | `de N carregadas` | não há total do negócio honesto: os contadores contam a mesma lista truncada. Trocar para server-side com total real exige UNION NF-e + NFS-e e muda o cockpit de *resumo* para *lista* — decisão [W] registrada no charter pelo #6711. |

**Tier 0 (ADR 0093):** as duas fontes são escopadas — `NfeEmissao` por `HasBusinessScope`, `NfseEmissao`
por `NfseBusinessScope` (trait próprio do módulo NFSe). A paginação recorta a lista já escopada; não
abre consulta nova. ⚠️ O docblock do `NotasUnifiedService` diz que *ambos* usam `HasBusinessScope` —
impreciso para a NFS-e (o escopo existe, com outro nome). Não corrigido: fora do prefixo.

## 4 · Provas do §5 do índice

| prova | estado |
|---|---|
| `Cockpit.tsx` contém `fx-pager` | ✅ `:721` — a prova era `Pagination` (falso-negativo: a tela usa `pagina`/`porPagina`); trocada no índice neste mesmo PR, com `nota` datada no §5 |
| guarda: `Cockpit.tsx` contém `onKeyDown` | ✅ `:605` |
| guarda: `Cockpit.casos.md` existe | ✅ |

## 5 · Não verificado

Screenshot de produção dark 1280 e T7 — não medidos nesta sessão.
