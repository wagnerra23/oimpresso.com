---
register: /crm · window.CrmPage
irmao_charter: Crm.charter.md
tecnica: Decision Register (ADR 0293 D-B · anéis Avaliar/Testar/Adotar/Descartar)
owner: wagner
last_update: 2026-06-22
schema: ADR-0293-D-B
related_adr: 0293-governanca-decisao-design-responsavel-registro-veredito
pii: false
---

# Decision Register — /crm (Funil comercial / kanban)

> **Materialização do par charter↔decisoes que o `integrity-check` IT2 exige** (ADR 0293 D-B).
> Decisões inferidas do charter [`charter.md`](./charter.md) (identidade) + casos [`decisoes.md`](./decisoes.md) — nenhuma fabricada.
> Schema mínimo `D-NN` da ADR 0293: responsável · detecção · padrão · opções · status.

## D-01 · Cor / identidade (Tier-0) — accent escopado azul 220 + hues semânticos por estágio
- responsável: [W] (Tier-0 · ADR 0293 D-A regra-mestre)
- detecção: charter (proposta Cowork 2026-06-02) — accent escopado `.crm-scope` + estágios com hues próprios
- padrão: accent ESCOPADO `.crm-scope{ --accent: oklch(0.45 0.11 220) }` (ADR 0200 D-02, proposta) · roxo canon (ADR 0235) intacto fora do escopo
- opções: (a) redeclarar token global (proibido); (b) accent escopado + hues semânticos por estágio (lead 250 · qual 270 · prop 70 · negoc 30 · ganho 145)
- status: PENDENTE [W] (proposta — accent escopado + hues de estágio aguardam ratificação; tokenizar hues é ajuste travado no charter)

## D-02 · Migração Blade legado → Inertia (a tela do repo ainda é Blade)
- responsável: [W] ratifica · [CC] migra (MWART)
- detecção: charter + CODE_NOTES (L-26) — `/crm` HOJE é Blade legado (UltimatePOS, sem Inertia page)
- padrão: processo MWART (ADR 0104) · charter/casos capturam o estado vivo pra a migração ser fiel
- opções: (a) seguir Blade; (b) migrar pra Inertia espelhando `crm-page.jsx` (direção do trio Cowork)
- status: PENDENTE [W] (migração F3 não iniciada; `live ⬜` nos casos até lá)

## D-03 · Emoji → lucide nos estágios (ajuste travado)
- responsável: [W]
- detecção: nota de evolução dos casos (2026-06-02) — ajustes travados no charter
- padrão: ícones lucide (consistência DS) · sem emoji cru
- opções: (a) emoji; (b) lucide (escolhida na direção, pendente aplicação na migração)
- status: PENDENTE [W] (aplicar na migração F3 junto com tokenizar hues de estágio)

---

## Graduados (saíram daqui → viraram ✅ no charter)
- _(nenhum — tela é proposta Cowork, ainda não migrada)_

## Devolvidos ([Design/Cowork] · ledger governance/design-requests/)
- _(nenhum)_

## Trilha do tempo
- 2026-06-22 · [CC] materializou o par `Crm.decisoes.md` no schema ADR 0293 D-B (IT2 deixa de passar no vácuo). Decisões inferidas do charter + casos — nenhuma fabricada.
