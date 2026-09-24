---
sessao: "_saida-02"
thread: "02 · shared/KpiCard.tsx — variant=filter"
dono: "[CL]"
data: 2026-09-13
prefixo_tocado: resources/js/Components/shared/KpiCard.tsx
base_lida: wagnerra23/oimpresso.com@main (PR #7251)
natureza: RECIBO DE RECONCILIAÇÃO — ver §0
---
# _saida-02

## 0 · Por que este recibo não foi escrito pela sessão executora

A thread rodou em 2026-09-13, quando o `cowork-inbox/` **não existia no repo** (apagado pela #7224;
a `R3` do `cowork-ssot-guard` ainda recusava `.md` aninhado sob `cowork/<dono>/`). Não havia onde
gravar. O executor registrou em [`memory/sessions/2026-09-13-ds-atomos-02-kpicard-filter.md`](../../../../../../memory/sessions/2026-09-13-ds-atomos-02-kpicard-filter.md)
e no [PR #7251](https://github.com/wagnerra23/oimpresso.com/pull/7251). A ADR 0398 devolveu a pasta;
este arquivo transporta aquele recibo. **Nenhuma afirmação aqui é nova.**

## 1 · Feito (palavras do executor)

> ds-atomos thread 02 — **KpiCard `variant=filter` aditivo**, e o `D-KPI-LABEL` que **não
> sobreviveu à medição**.

Duas coisas, e a segunda importa tanto quanto a primeira: além da variante, o executor derrubou
por medição uma das decisões que o índice listava como pendente. Decisão que cai medida não volta
como "pendente" — quem reemitir o `00-INDICE.md` precisa ler o session log antes.

## 2 · Provas

As **2 provas de arquivo** do §7 passam (medidas contra `origin/main` em 2026-09-14).
