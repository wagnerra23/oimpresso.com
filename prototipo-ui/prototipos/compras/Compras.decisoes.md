---
register: /compras · window.ComprasPage
irmao_charter: Compras.charter.md
tecnica: Decision Register (ADR 0293 D-B · anéis Avaliar/Testar/Adotar/Descartar)
owner: wagner
last_update: 2026-06-22
schema: ADR-0293-D-B
related_adr: 0293-governanca-decisao-design-responsavel-registro-veredito
pii: false
---

# Decision Register — /compras

> **Materialização do par charter↔decisoes que o `integrity-check` IT2 exige** (ADR 0293 D-B).
> Decisões inferidas do charter canônico [`charter.md`](./charter.md) (identidade & anti-patterns) — nenhuma fabricada.
> Schema mínimo `D-NN` da ADR 0293: responsável · detecção · padrão · opções · status.

## D-01 · Cor / identidade (Tier-0) — roxo canônico do DS, sem accent escopado próprio
- responsável: [W] (Tier-0 · ADR 0293 D-A regra-mestre)
- detecção: backfill do charter 2026-06-02 — Compras foi a 1ª tela a provar a migração completa (navy/cream → tokens DS, 0 hex)
- padrão: roxo canônico do DS (ADR 0235) · DS é piso (ADR 0200) · `var(--accent)` canon direto
- opções: (a) accent escopado próprio `.compras-scope`; (b) usar o accent canônico do DS sem escopo (escolhida)
- status: DECIDIDO (usa o accent canônico do DS, sem escopo próprio — registrado no charter)

## D-02 · Não reintroduzir hex cru / `--cmp-*` bespoke (anti-regressão)
- responsável: [W] (Tier-0 cor) · gate `ds-guard` (automático)
- detecção: anti-pattern do charter — a tela já está em 0 hex; regressão pra cor crua desfaz o trabalho (L-02/L-23)
- padrão: cor só via token do DS · `ds-guard` barra paleta bespoke
- opções: n/a — regressão é REPROVADA por padrão
- status: DECIDIDO (proibido `--cmp-*` residual; confirmar antes de migrar pro `ds-v5` · gate visual F1.5)

## D-03 · Ação condicional ao `stage` (não mostrar todos os botões a toda hora)
- responsável: [W]
- detecção: anti-pattern do charter (UC-K05) — mostrar tudo quebra a "ação certa da etapa"
- padrão: Cockpit V2 (ADR 0110) · drawer FSM · ação derivada do `stage`
- opções: (a) todos os botões sempre visíveis; (b) ação condicional ao estágio (escolhida)
- status: DECIDIDO (ação condicional — registrado no charter como anti-pattern de "mostrar tudo")

## D-04 · Vocabulário fiscal: entrada (compras) ≠ saída (vendas)
- responsável: [W]
- detecção: anti-pattern do charter — confundir entrada com saída no vocabulário fiscal
- padrão: domínio fiscal correto (entrada de nota = Eliana [E]) · persona financeiro
- opções: n/a — não confundir os dois fluxos
- status: DECIDIDO (vocabulário de entrada mantido distinto de vendas)

---

## Graduados (saíram daqui → viraram ✅ no charter)
- D-01…D-04 já estão refletidos como ✅/anti-pattern no charter canônico (tela 9.4 migrada). Mantidos aqui no schema ADR 0293 para o rastro de governança (quem/o quê/por quê).

## Devolvidos ([Design/Cowork] · ledger governance/design-requests/)
- _(nenhum)_

## Trilha do tempo
- 2026-06-22 · [CC] materializou o par `Compras.decisoes.md` no schema ADR 0293 D-B (IT2 deixa de passar no vácuo). Decisões inferidas do charter — nenhuma fabricada.
