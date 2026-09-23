---
sessao: "_saida-01"
thread: "01 · Rede: 2 specs E2E (cockpit + NF-e)"
dono: "[CL]"
data: 2026-09-13
prefixo_tocado: e2e/fiscal-cockpit.spec.ts · e2e/fiscal-nfe.spec.ts
base_lida: wagnerra23/oimpresso.com@main (PR #7257)
natureza: RECIBO DE RECONCILIAÇÃO — ver §0
---
# _saida-01

## 0 · Por que este recibo não foi escrito pela sessão executora

A thread rodou quando o `cowork-inbox/` não existia no repo (apagado pela #7224; a `R3` recusava
`.md` aninhado). O executor registrou tudo em
[`memory/sessions/2026-09-13-fiscal-e2e-rede-thread01.md`](../../../../../../memory/sessions/2026-09-13-fiscal-e2e-rede-thread01.md)
e no [PR #7257](https://github.com/wagnerra23/oimpresso.com/pull/7257). Este arquivo transporta
aquele recibo para o lugar canônico que a ADR 0398 devolveu. Nada aqui é afirmação nova.

## 1 · Feito (palavras do executor)

> `e2e/fiscal-cockpit.spec.ts` e `e2e/fiscal-nfe.spec.ts` criados: **6 casos executáveis + 3
> `fixme` declarados**.

## 2 · O achado que vale mais que a entrega

> Thread **02** (paginação) confirmada **FEITA** — a prova do playbook era **falso-negativo por
> buscar a string `Pagination`**.

Confirmado por medição independente em 2026-09-14: [`Fiscal/Cockpit.tsx:296-307`](../../../../../../resources/js/Pages/Fiscal/Cockpit.tsx)
pagina client-side com nomes em PT-BR (`pagina`/`porPagina`/`paginas`). **A thread 02 não é
trabalho pendente — é prova desatualizada**, e o conserto é do lado que emite o índice.

Reportado e **não consertado** pelo executor: o Cockpit anuncia 4 atalhos e tem 1 handler de tecla.

## 3 · Provas

As **4 provas de arquivo** do §7 passam (medidas contra `origin/main`, 2026-09-14).
