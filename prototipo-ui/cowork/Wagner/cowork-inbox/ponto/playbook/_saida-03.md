---
sessao: "_saida-03"
thread: "03 · a11y — sinal não-cor na divergência + mobile-fit"
dono: "[CL]"
data: 2026-09-13
prefixo_tocado: NENHUM — thread não executada como código (ver §1)
base_lida: wagnerra23/oimpresso.com@main (PR #7248)
natureza: RECIBO DE RECONCILIAÇÃO de thread PARADA POR MEDIÇÃO — ver §0 e §1
---
# _saida-03

## 0 · Por que este recibo não foi escrito pela sessão executora

Mesma razão das irmãs: em 2026-09-13 o `cowork-inbox/` não existia no repo. O executor registrou em
[`memory/sessions/2026-09-13-ponto-thread03-a11y-ja-feita.md`](../../../../../../memory/sessions/2026-09-13-ponto-thread03-a11y-ja-feita.md)
e no [PR #7248](https://github.com/wagnerra23/oimpresso.com/pull/7248). **Nenhuma afirmação aqui é nova.**

## 1 · NÃO executada — e isso é o desfecho correto

> **A thread 03 já estava feita quando o playbook foi escrito.** O trabalho saiu em
> [#6407](https://github.com/wagnerra23/oimpresso.com/pull/6407) (2026-08-28) — cujo título é
> literalmente o escopo da thread — e foi complementado por
> [#6777](https://github.com/wagnerra23/oimpresso.com/pull/6777). O playbook é de **2026-09-06**,
> 9 dias depois do primeiro. Nenhum arquivo de tela foi tocado nesta sessão.

Recibo do axe citado pelo executor: run `33927949960` — **28 passed (82 assertions), 0 violação
CRITICAL** nas 12 telas declaradas.

## 2 · A prova do playbook mede a implementação, não o predicado

O §7 exige que `MonthHeatmap.tsx` **contenha a string `sr-only`**. O alvo real — *sinal não-cor
na divergência* — está implementado por **glifo** (`AlertTriangle`/`StateIcon`) + `aria-label`.
A prova procura um mecanismo específico em vez do comportamento, então ela **falha sobre trabalho
feito**. É a mesma família do `Pagination` na thread 02 do Fiscal.

⚠️ Por isso esta thread **permanece "em curso" no placar mesmo com este recibo**, e está certo:
a prova de fato não passa. Consertá-la é de quem emite o índice — **não** se conserta editando o
código para conter uma string.

## 3 · Estado medido (2026-09-14, `origin/main`)

`resources/js/Pages/Ponto/_components/MonthHeatmap.tsx` — `sr-only`: **0** · `aria-label`/`aria-hidden`: **presentes**.
