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

---

> **Adendo MV batch 2026-07-06 (piloto Módulo Vivo — screen-qa Financeiro/Impostos/Index).**
> Auditoria do contrato revelou que UC-IMP-01/04/05 tinham só cobertura de *shape* ou eram
> manuais. Os UCs abaixo fecham o gap com asserção **backend** derivada do charter/US-FIN-062,
> não de shape. Âncoras: charter Goals (KPIs · costura NF · valor recalculado server-side) +
> US-FIN-062 (SPEC.md:1803) + ADR 0093 (Tier 0). Não redesenham a tela — blindam o entregue.

## UC-IMP-08 — KPI "A recolher" = soma das guias abertas (não só shape)
Status: 🧪 (ImpostosContractTest C1 — valor = soma das abertas)
Refina UC-IMP-01: além da chave existir (I1), o **valor** de `kpis.a_recolher.valor` tem de ser
exatamente a soma da coluna Valor das guias com `status != paga`, e `kpis.a_recolher.qtd` = a
contagem dessas linhas. Âncora: charter Goals "A recolher (soma das guias abertas)".
**Pronto quando:** `kpis.a_recolher.valor` == `sum(props.calendario[].valor)` e `qtd` ==
`count(props.calendario)`, com receita seedada > 0 (DAS estimado presente na soma).

## UC-IMP-09 — Valor recalculado server-side (anti tampering)
Status: 🧪 (ImpostosContractTest C2 — client não injeta valor)
Anti-pattern do charter ("não confiar em valor vindo do client"): o POST `/lancar` só aceita
`competencia`; qualquer `valor`/`vencimento`/`status` enviado pelo cliente é **ignorado**. O
título criado grava o valor recalculado (6% × receita recebida server-side), nunca o do payload.
**Pronto quando:** POST com `valor: 999999.99` extra cria título cujo `valor_total` = 6% da
receita recebida (não 999999.99), e vencimento = dia 20 do mês seguinte (não o do payload).

## UC-IMP-10 — Costura NF↔título (backend, não só manual)
Status: 🧪 (ImpostosContractTest C3 — sem_nf e pct_com_nf derivados)
Refina UC-IMP-05: recebível do mês SEM `metadata.nfe_numero`/`nfe_chave` entra em `props.sem_nf`
(até 5) e derruba `kpis.pct_com_nf`; recebível COM NF vinculada NÃO entra e conta como consistente.
Sem recebíveis no mês → `pct_com_nf = 100`. Âncora: charter Goals "Painel NF↔título".
**Pronto quando:** seed de 1 recebível sem NF + 1 com NF no mês → `sem_nf` contém só o sem-NF e
`pct_com_nf` reflete a proporção (com 1/2 = 50).

## UC-IMP-11 — Guia paga sai do "A recolher" e do calendário (backend)
Status: 🧪 (ImpostosContractTest C4 — status quitado excluído dos abertos)
Refina UC-IMP-04 pra asserção backend: título de guia (DAS/FGTS/INSS) com `status = quitado`
aparece em `guias` com `status = paga`, mas NÃO entra em `props.calendario` nem soma no
`kpis.a_recolher`. Âncora: charter Goals "Status: a vencer / paga / atrasada" + KPI só-abertas.
**Pronto quando:** guia quitada seedada → presente em `guias` (status paga) e ausente de
`calendario`; `a_recolher` não a inclui.
