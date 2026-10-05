---
sessao: "02"
titulo: Vendas — troca do Blade pelo React (cutover F5) e fechamento do playbook 01
autor: "[CL]"
criado: 2026-10-05
base: wagnerra23/oimpresso.com@main 1685efb6f5 (lida 2026-10-05)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/playbook/
---

# Vendas — playbook 02

> **Absorve, não duplica:** `../PEDIDO-CL-venda-menu-02.md` (#8615) e o `00-INDICE.md`, cujas
> threads 01–07 continuam valendo. Este arquivo acrescenta as threads C0–C6 do cutover e fixa
> o que falta do 01. Escrito pelo Code a pedido do [W] em 2026-10-05 ("use o que precisar para
> adiantar tudo"). Se o Design reescrever, **o índice manda**.

## 1 · Onde o 01 está (medido em `1685efb6f5`)

| thread | estado |
|---|---|
| 01 Lista de POS · 02 Remessas · 04 Descontos · 05 Importação · 06 Pedido de venda | entregues (#8488, #8490, #8491, #8493, #8514, #8517, #8516, #8520, #8542, #8486) |
| 03 Devolução | lista entregue (#8487); **tela de devolver (`SellReturn/Add`) em andamento** |
| 07 Caixa (movimentos + conferência física) | **em andamento** |
| 00 Puxar as telas vivas para o protótipo | não começou |

## 2 · O problema que o 02 resolve

Todas as Pages entregues só respondem com o cabeçalho `X-Inertia`; o GET comum (URL direta e
menu) entrega o Blade. Medido em produção em 2026-10-02 (biz=1): `/discount`, `/sales-order` e
`/sell-return` pela URL e Descontos pelo menu entregam o Blade.

## 3 · Mecanismo (já existe no projeto — não inventar)

`config/mwart.php`: uma entrada por tela com `enabled` + `business_ids` (env
`MWART_<TELA>` e `MWART_<TELA>_BIZ`). Vazio = todas as empresas; lista = só as listadas. É o
mesmo usado no Repair. Nasce **desligado**.

## 4 · Threads

| # | o que faz | depende | prova |
|---|---|---|---|
| **C0** | Chaves `mwart.vendas_*` + helper único em `app/` + condição "X-Inertia **ou** chave ligada" nos 6 controllers; teste por tela (desligada → Blade · ligada p/ 98 → React · ligada p/ 99 → 98 segue no Blade) | — | `VendasMwartCutoverTest` verde na lane Sells |
| **C1** | Liga para **biz=1** as telas de menor uso: Descontos, Importação, Pedido de venda | C0 merge + deploy | GET comum em produção (biz=1) devolve a Page React (`data-page` com o componente certo); smoke de cada tela |
| **C2** | Liga para **biz=1**: Lista de POS, Remessas, Devoluções | C1 sem incidente 48 h | idem |
| **C3** | Observação: 7 dias de canário no biz=1 (MWART F5) | C1 · C2 | nenhum incidente nas telas; log sem erro novo |
| **C4** | Aviso à ROTA LIVRE (biz=4) e liga para todas as empresas (`_BIZ` vazio) | C3 + **aviso feito pelo [W]** | GET comum em produção devolve React para biz=4 |
| **C5** | Segunda observação de 7 dias com todas as empresas | C4 | idem C3 |
| **C6** | Apaga as views Blade e o fallback dos 6 controllers (1 PR por tela) | C5 | `E10` (zero Blade) desce; rotas sem `view(...)` |

Fora do cutover, seguem do 01: **03-b** (`SellReturn/Add`), **07** (Caixa, 2 PRs) e **00**.

## 5 · Decisões — aplicadas pelo Code sob a autorização geral do [W] de 2026-10-05

| id | escolha | por quê | reverte com |
|---|---|---|---|
| D4 | Em **dois lotes** (C1 de menor uso, C2 de balcão) | limita o raio de um problema às telas menos usadas primeiro | ligar tela a tela no C1/C2 |
| D5 | **7 dias por lote**, não por tela | regra MWART F5 cumprida sem multiplicar o prazo por 6 | uma observação por tela |
| D6 | **Um aviso só** à ROTA LIVRE, antes do C4 | biz=4 só entra depois que as 6 já rodaram 7 dias no biz=1 | avisos por tela |

O aviso ao cliente (C4) é mensagem externa: **quem envia é o [W]**. Nenhuma thread liga o biz=4
antes disso.

## 6 · PARAR SE
- a tela React divergir do Blade em valor exibido (total, saldo, desconto) — regra mestre;
- o GET comum devolver 500 em qualquer empresa ligada;
- a observação registrar erro novo nas telas ligadas.
