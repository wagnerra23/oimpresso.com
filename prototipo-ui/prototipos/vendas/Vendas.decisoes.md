---
register: /vendas · window.VendasPage
irmao_charter: Vendas.charter.md
tecnica: Decision Register (ADR 0293 D-B · anéis Avaliar/Testar/Adotar/Descartar)
owner: wagner
last_update: 2026-06-22
schema: ADR-0293-D-B
related_adr: 0293-governanca-decisao-design-responsavel-registro-veredito
pii: false
---

# Decision Register — /vendas (Index / lista)

> **Materialização do par charter↔decisoes que o `integrity-check` IT2 exige** (ADR 0293 D-B).
> Decisões inferidas do charter canônico [`charter.md`](./charter.md) (identidade & anti-patterns) — nenhuma fabricada.
> Schema mínimo `D-NN` da ADR 0293: responsável · detecção · padrão · opções · status.

## D-01 · Cor / identidade (Tier-0) — accent escopado verde 155
- responsável: [W] (Tier-0 · ADR 0293 D-A regra-mestre)
- detecção: backfill do charter 2026-06-02 — piloto aprovado [W] ("pode fazer sim, nada estranho")
- padrão: accent ESCOPADO `.vendas-scope{ --accent: oklch(0.45 0.11 155) }` por cima do DS (ADR 0200 D-02) · roxo canon (ADR 0235) intacto fora do escopo
- opções: (a) redeclarar token global (proibido); (b) accent escopado herdando os componentes do `ds-v5` (escolhida)
- status: DECIDIDO (verde 155 escopado, NÃO redeclara token global — registrado no charter como piloto aprovado)

## D-02 · Lista (/vendas) ≠ cadastro (Sells/Create)
- responsável: [W]
- detecção: nota do charter — não confundir com a tela irmã de cadastro `Sells/Create.charter.md`
- padrão: golden = `Sells/Create` (10 regras GOLDEN-REFERENCE) · Cockpit V2 (ADR 0110)
- opções: n/a — duas telas distintas (lista vs documento)
- status: DECIDIDO (separação mantida; este protótipo é só a lista/Index)

## D-03 · Não regredir o piloto (anti-regressão)
- responsável: [W] (Tier-0) · gate visual F1.5 (automático)
- detecção: anti-pattern do charter — tela é piloto aprovado, regressão de layout é cara
- padrão: gate visual F1.5 (antes/depois) obrigatório · migração pro `ds-v5` aditiva, reuse-first
- opções: n/a — qualquer regressão de layout barra o merge
- status: DECIDIDO (gate visual F1.5 obrigatório antes de tocar a tela)

---

## Graduados (saíram daqui → viraram ✅ no charter)
- D-01…D-03 já refletidos no charter canônico (piloto 9.5). Mantidos aqui no schema ADR 0293 para o rastro de governança.

## Devolvidos ([Design/Cowork] · ledger governance/design-requests/)
- _(nenhum)_

## Trilha do tempo
- 2026-06-22 · [CC] materializou o par `Vendas.decisoes.md` no schema ADR 0293 D-B (IT2 deixa de passar no vácuo). Decisões inferidas do charter — nenhuma fabricada.
