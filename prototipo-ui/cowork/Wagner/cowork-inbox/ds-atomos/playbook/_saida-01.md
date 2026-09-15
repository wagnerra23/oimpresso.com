---
sessao: "_saida-01"
thread: "01 · ui/card.tsx — badge · note · flush"
dono: "[CL]"
data: 2026-09-13
prefixo_tocado: resources/js/Components/ui/card.tsx · tests/js/card-anatomia.test.tsx · .github/workflows/card-anatomia-gate.yml
base_lida: wagnerra23/oimpresso.com@main (PR #7253)
natureza: RECIBO DE RECONCILIAÇÃO — ver §0
---
# _saida-01

## 0 · Por que este recibo não foi escrito pela sessão executora

A thread rodou em 2026-09-13, quando o `cowork-inbox/` **não existia no repo** (apagado pela #7224;
a `R3` do `cowork-ssot-guard` ainda recusava `.md` aninhado sob `cowork/<dono>/`). Não havia onde
gravar. O executor registrou em [`memory/sessions/2026-09-13-ds-atomos-01-card-anatomia.md`](../../../../../../memory/sessions/2026-09-13-ds-atomos-01-card-anatomia.md)
e no [PR #7253](https://github.com/wagnerra23/oimpresso.com/pull/7253). A ADR 0398 devolveu a pasta;
este arquivo transporta aquele recibo. **Nenhuma afirmação aqui é nova.**

## 1 · Feito (palavras do executor)

> `ui/card.tsx`: **3 props opcionais** (`badge` · `note` · `flush`); **default byte-idêntico** a
> `733033864088`.
> `tests/js/card-anatomia.test.tsx`: guarda dos **7 defaults** + controle negativo do comparador
> (**13 asserts**).
> `card-anatomia-gate.yml` (advisory, `promote_by 2026-09-27`) + entry no `gates-registry` —
> **spec sem lane nunca executa**.

A lei desta pasta era **ADITIVO OU NADA**, porque `Card` é consumido fora do Ponto. O
"default byte-idêntico" é o que prova que a lei foi respeitada — não é enfeite de redação.

## 2 · Provas

As **2 provas de arquivo** do §7 passam (medidas contra `origin/main` em 2026-09-14).
