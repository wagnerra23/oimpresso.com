<!-- SESSÃO FRIA · abra esta thread sozinha. Read-order mínimo e prompt de abertura: `_SESSAO-FRIA.md` (linha "Banco de Horas · gap").
     Os ids de decisão (D-*) só existem em `ATA-DECISOES-2026-09-14.md` — leia a ata antes, ou as siglas ficam órfãs.
     Não leia as outras threads: cada uma é 1 PR e o contexto delas não é pré-requisito desta. -->

# 21 · gap.md de Banco de Horas — onda 3 (2 telas, 1 símbolo)

> **Entrega:** propostas de `memory/requisitos/Ponto/banco-horas-{index,show}-gap.md`.
> **Lido no turno:** `BancoHoras/Index.charter.md` (2.745 B) · `BancoHoras/Show.charter.md` (2.817 B) · protótipo `ponto-telas.jsx :289-394` (símbolo `BancoHoras`, inteiro). **NÃO lido:** `Index.tsx` (7.641 B) e `Show.tsx` (9.471 B) ⇒ lado vivo **TODO**.
> **1 símbolo, 2 telas:** `BancoHoras :: 289-394` faz master-detail **no mesmo componente** — o `if (sel)` (`:305`) é o `Show`. Por isso o map precisa de **2 arquivos apontando para o mesmo símbolo com faixas diferentes**: `:378-394` (Index) e `:305-377` (Show).

---

## A · `banco-horas-index-gap.md` — 4 regiões

| # | região | protótipo | `data-contract` | vivo | status |
|---|---|---|---|---|---|
| 1 | **Faixa de KPI** — Crédito total · Débito total · Colaboradores no banco · Multiplicadores | `:379-384` | *falta* (`.pt-kpis` não é Card) | TODO | ⚠️ **divergência de conjunto** (abaixo) |
| 2 | **Saldos por colaborador** — 6 colunas: Colaborador (nome+cargo), Matrícula (mono), Escala, Saldo (num, cor por sinal), Última movimentação (mono), Ação | `:385-393` | ✅ `bancohoras-saldos-por-colaborador` | TODO | a medir |
| 3 | **Paginação** | `:393` | — | TODO | **corrigido no build: 15 → 30/pág** |
| 4 | **Rodapé legal** — append-only (Portaria MTP 671/2021) | `:394` | — | TODO | a medir |

### Divergências

1. **Conjunto de KPI: 2 de 4 batem.** O charter pede **crédito total · débito total · nº com crédito · nº com débito**. Eu tenho crédito e débito como tiles, e **nº com crédito / nº com débito estão na sub-linha desses dois** (`ln`), não como tiles próprios — mais **2 tiles que o charter não pede** (`Colaboradores no banco`, `Multiplicadores`, este vindo de `CONFIG.banco_horas`). **A informação está toda presente**; a forma difere. Proposta: manter 4 tiles com sub-linha (densidade de ERP) e **emendar o charter**, não apagar tile. Decisão: `D-BH-KPI`.
2. **Ordenação por saldo desc** — Goal do charter, e o meu render não ordenava. **Defeito meu, corrigido no build.**
3. **"Movimentos" é rota no charter, estado interno no protótipo.** O charter manda link para `/ponto/banco-horas/{colaborador}`; eu troco `useState(sel)` e renderizo o detalhe no mesmo componente. Mesma família do `D-INTERC-DRAWER`. Decisão: `D-BH-ROTA`.
4. **Rótulo da ação:** charter diz **"Movimentos"**, meu botão diz **"Detalhes"**. Copy literal é contrato — **o charter manda**; troco quando `D-BH-ROTA` fechar (se virar rota, o rótulo vem com ela).

---

## B · `banco-horas-show-gap.md` — 6 regiões

| # | região | protótipo | `data-contract` | vivo | status |
|---|---|---|---|---|---|
| 1 | **Cabeçalho do colaborador** — Voltar + nome + matrícula · cargo · escala | `:334-337` | *falta* | TODO | a medir |
| 2 | **KPIs do extrato** — Saldo atual (cor por sinal, "atualizado em") · Lançamentos · **Teto do acordo** · **Prazo de compensação** | `:340-345` | *falta* | TODO | ⚠️ protótipo à frente (abaixo) |
| 3 | **Histórico de movimentos** — 5 colunas: Data (mono), Referência (mono), Origem (pill mono), Minutos (num, com sinal), Observação | `:346-360` | ✅ `bancohoras-historico-de-movimentos` | TODO | a medir |
| 4 | **Paginação do histórico (50/pág)** | **NÃO EXISTE** — renderizo a lista inteira | — | TODO | 🟠 **região a nascer** |
| 5 | **Ajuste manual** — Minutos (número, help "Ex.: 60 crédito, −30 débito") + Observação (500 chars) + `Registrar ajuste` + nota append-only | `:362-372` | ✅ `bancohoras-ajuste-manual` | TODO | **corrigido no build: observação ≥ 5 chars** |
| 6 | **Rodapé legal** | `:375` | — | TODO | a medir |

### Onde eu estou à frente — e responde uma pendência do charter

O charter `Index` lista como pendência: *"[ ] Confirmar regra de expiração de crédito exibida ao usuário"*. **O meu protótipo já exibe** dois pedaços disso na região 2 do Show: **Teto do acordo** (`saldo_maximo_horas` / piso `saldo_minimo_horas`) e **Prazo de compensação** (`prazo_compensacao_meses`, "acordo individual"). Não é gap — é **resposta ao item aberto**, e vale subir como proposta de emenda ao charter. **Não vira pedido de código.**

### O que o charter fecha e eu obedeço

- **Observação obrigatória, mín. 5 chars** — era só "não vazio" no protótipo. **Corrigido.** (*"Não faz ajuste sem observação — observação é auditada."*)
- **Append-only:** meu ajuste **acrescenta** movimento e recalcula o saldo somando; nada é editado nem apagado. ✓ E a nota `warn` diz isso na tela.
- **Non-Goal "não recalcula/reescreve o saldo":** ⚠️ atenção na implementação — no protótipo eu **somo no objeto de saldo** (`x.saldo_minutos + m`) porque não há backend; no vivo o saldo **deriva da soma dos movimentos**. Quem executar **não deve portar minha soma local** — é artefato de mock, declarado aqui para não virar regra.

---

## C · Decisões que esta onda abre

```json
[
  {
    "id": "D-BH-KPI",
    "pergunta": "A faixa de KPI do Banco de Horas segue o conjunto do charter (4 tiles: crédito, débito, nº com crédito, nº com débito) ou o do protótipo (crédito, débito, total de colaboradores, multiplicadores — com as contagens na sub-linha)?",
    "medido": "Informação idêntica; forma diferente. O protótipo acrescenta os multiplicadores de crédito/débito vindos de CONFIG.banco_horas, que o charter não menciona em nenhum lugar.",
    "dono": "[W]"
  },
  {
    "id": "D-BH-ROTA",
    "pergunta": "O extrato do colaborador é rota própria (/ponto/banco-horas/{colaborador}, como o charter e o Show.tsx dizem) ou master-detail no mesmo componente (como o protótipo faz)?",
    "consequencia": "se for rota, o meu if (sel) é pele paralela de uma página que existe (9.471 B); se for master-detail, o Show.tsx vira rota morta. Mesma pergunta do D-INTERC-DRAWER — vale UMA decisão para o módulo, não duas.",
    "dono": "[W]"
  }
]
```

---

## PARAR SE

- o `Show.tsx` vivo já tiver paginação de 50 ⇒ a região 4 **não é gap, é catch-up meu**;
- `D-BH-ROTA` seguir aberta ⇒ **nenhuma thread de layout** para o extrato;
- alguém tentar portar a soma local de saldo do protótipo ⇒ **parar**: viola o Non-Goal *"não recalcula/reescreve o saldo"*.
