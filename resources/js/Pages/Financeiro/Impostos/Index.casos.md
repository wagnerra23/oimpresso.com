---
casos: Impostos & obrigações · /financeiro/impostos
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-06-10"
---

# Casos de uso — /financeiro/impostos (F2 PR-2)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Derivados do protótipo Cowork `TelaImpostos` + charter v1. Persona principal Eliana [E].

## UC-IMP-01 — "Quanto vou recolher este mês?"
Status: 🧪 (ImpostosGuardTest I1 — shape kpis)
Eliana abre Impostos & obrigações. KPI **A recolher** soma as guias abertas (DAS estimado +
títulos de guia lançados não-quitados). Hint mostra a quantidade de guias.
**Pronto quando:** valor bate com a soma da coluna Valor das linhas não-pagas.

## UC-IMP-02 — DAS estimado segue o regime caixa
Status: 🧪 (ImpostosGuardTest I2 — valor recalculado server-side)
Receita RECEBIDA no mês (baixas, sem estorno, título receber não-cancelado, valor real
juros+multa−desconto) × ≈6% = linha "DAS · Simples Nacional · estimado". Mês sem recebimento →
DAS não aparece como lançável (nada a lançar).
**Pronto quando:** valor da linha = 6% do total exibido no disclaimer.

## UC-IMP-03 — Lançar a pagar (costura com o Unificado)
Status: 🧪 (ImpostosGuardTest I2+I3 — cria P-NNNNN + idempotente)
Clicar **Lançar a pagar** na guia DAS cria título payable `P-NNNNN` no caixa unificado com
vencimento dia 20 do mês seguinte. A linha passa a mostrar `a pagar · P-NNNNN`. Clicar de novo
NÃO duplica (idempotente por `metadata.guia`). Valor é recalculado no servidor.
**Pronto quando:** título aparece no Unificado (lente A pagar) e re-POST devolve o mesmo numero.

## UC-IMP-04 — Guia paga vira histórico
Status: ⬜ (manual — quitar guia no Unificado e conferir status paga)
Título de guia quitado no Unificado aparece na tabela com status **paga** e sai do KPI
A recolher e do calendário.

## UC-IMP-05 — Costura NF↔título (aviso pré-fechamento)
Status: ⬜ (manual — depende de títulos sem NF no ambiente)
Recebíveis do mês sem `metadata.nfe_numero/nfe_chave` listam no painel NF↔título (até 5) com o
aviso "sem NF a base do DAS sai distorcida". 100% com NF → check verde "base consistente".

## UC-IMP-06 — Tier 0
Status: 🧪 (ImpostosGuardTest I4 — cross-tenant)
Guias, KPIs, calendário e painel NF nunca misturam business (ADR 0093). Coberto por
`ImpostosGuardTest` (I4).

## UC-IMP-07 — Disclaimer sempre visível
Status: ⬜ (manual/visual — disclaimer fixo no rodapé)
Rodapé fixo: "Estimativa visual … apuração oficial … módulo Fiscal". Anti-pattern do charter:
nunca apresentar a estimativa como apuração.
