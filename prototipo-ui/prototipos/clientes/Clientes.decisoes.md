---
register: /clientes · window.ClientesPage
irmao_charter: Clientes.charter.md
tecnica: Decision Register (ADR 0293 D-B · anéis Avaliar/Testar/Adotar/Descartar)
owner: wagner
last_update: 2026-06-22
schema: ADR-0293-D-B
related_adr: 0293-governanca-decisao-design-responsavel-registro-veredito
pii: false
---

# Decision Register — /clientes

> **Materialização do par charter↔decisoes que o `integrity-check` IT2 exige** (ADR 0293 D-B).
> A tela tem **mockup Cowork pronto** (`HANDOFF_CLIENTES.md`, 9.4) mas **ainda não passou por uma decisão de
> design de governança registrada** (nenhum gate barrou, nenhum veredito [W] de cor/identidade pendente).
> **Sem decisões registradas ainda** além das abaixo, inferidas do handoff — nada fabricado.
> Schema mínimo `D-NN` da ADR 0293: responsável · detecção · padrão · opções · status.

## D-01 · Validação BR inline (CPF/CNPJ mod 11, e-mail, CEP) — registrada no handoff
- responsável: [W] ratifica · [CC] migra
- detecção: HANDOFF_CLIENTES.md — drawer 760px com validação BR inline + máscaras automáticas
- padrão: validação client-side espelhada server-side na migração (Inertia/MWART ADR 0104)
- opções: n/a — requisito do mockup; vira contrato na migração
- status: PENDENTE [W] (mockup aprovado 9.4; migração pra Inertia não iniciada)

## D-02 · Cor / identidade (Tier-0) — pendente decisão de accent
- responsável: [W] (Tier-0 · ADR 0293 D-A regra-mestre)
- detecção: sem accent escopado declarado no handoff (vs Vendas/CRM que já têm) — decisão de identidade ainda em aberto
- padrão: roxo canônico do DS (ADR 0235) por default · accent escopado só se [W] decidir (como Vendas/CRM)
- opções: (a) usar o accent canônico (default, como Compras); (b) accent escopado próprio (exige decisão [W])
- status: PENDENTE [W] (sem decisão de cor registrada — usa o canônico até [W] decidir o contrário)

---

## Graduados (saíram daqui → viraram ✅ no charter)
- _(nenhum — tela ainda é mockup, sem migração)_

## Devolvidos ([Design/Cowork] · ledger governance/design-requests/)
- _(nenhum)_

## Trilha do tempo
- 2026-06-22 · [CC] materializou o par `Clientes.decisoes.md` no schema ADR 0293 D-B (IT2 deixa de passar no vácuo). Esqueleto mínimo + decisões inferidas do handoff — nada fabricado; itens sem histórico marcados PENDENTE [W].
