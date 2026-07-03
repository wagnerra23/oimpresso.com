---
date: "2026-07-03"
time: "21:00 BRT"
slug: fiscal-onda6-ibscbs-us021
tldr: "Onda 6 Fiscal completa (4 PRs) + US-FISCAL-021 IBS/CBS PR-A/B/C mergeados (pin dev-master + cálculo + flag gated OFF) + US-FISCAL-022 mergeado; resta só PR-D serialização (chip). Tudo inerte/legacy byte-idêntico = zero efeito biz=1 live."
prs: [3738, 3753, 3761, 3764, 3771, 3772, 3774, 3775]
decided_by: [W]
related_adrs: [0321-pin-sped-nfe-dev-master-ibs-cbs]
next_steps:
  - "PR-D (chip): serialização grupo UB tagIBSCBS gated + XSD PL_010_V1 + REGRA MESTRE antes de mergear"
  - "US-FISCAL-018 canary Fiscal biz=4 Larissa (pré-existente)"
---

# Handoff — 2026-07-03 21:00 · Onda 6 Fiscal + US-FISCAL-021 IBS/CBS

## Estado MCP no momento do fechamento
- **cycles-active:** nenhum cycle ativo em COPI.
- **my-work:** bloqueado por outage da plataforma no fechamento (retomado no resume). US-FISCAL-021 = `doing` (owner wagner); US-FISCAL-022 = feito (#3775).
- **PRs desta sessão:** #3738/#3753/#3761/#3764 (Onda 6) + #3771/#3772/#3774 (IBS/CBS A/B/C) + #3775 (US-FISCAL-022) — **todos MERGEADOS**.

## O que aconteceu
Duas frentes numa sessão épica. **Onda 6 Fiscal COMPLETA** (programa de ondas, 4 passos): ficha nota 75, inventário+2 US, régua 7 telas, catraca. **US-FISCAL-021 IBS/CBS destravada ~90%**: descoberta que o XML grupo UB estava hard-blocked pela lib (`sped-nfe` v5.2.5 sem release; issue #1274) → [W] escolheu pinar dev-master full → ADR 0321 → back-compat provado verde no CI (main-atual+dev-master; o susto "+4" era ruído de staging defasada) → PR-A (pin) + PR-B (cálculo motor, inerte) + PR-C (feature flag `reforma_tributaria_modo` + seleção schema, legacy=byte-idêntico) mergeados. US-FISCAL-022 (health-check cert) também mergeado via chip. Falta só PR-D (serialização gated, Tier-0 REGRA MESTRE) — em chip próprio.

## Artefatos gerados (canon, já na main)
- ADR 0321 · CAPTERRA-FICHA + INVENTARIO Fiscal · 7 casos.md + 7 scorecards · PLANO-MESTRE Onda 6.
- NfeBrasil: TributoCalculado +7 campos IBS/CBS · MotorTributarioService popula · flag reforma_tributaria_modo · schemaReforma · composer pin dev-master.
- Session log: `memory/sessions/2026-07-03-fiscal-onda6-e-ibscbs-us021.md`.
- Plano: `~/.claude/plans/async-dazzling-bumblebee.md`.

## Persistência
- **git:** 8 PRs mergeados. **MCP:** US-FISCAL-021 com comentários duráveis (evidência back-compat + correções). **Chips:** PR-D rodando.

## Próximos passos pra retomar
- PR-D serialização (chip): `tagIBSCBS`/`tagIBSCBSTot` no NfeService gated por schemaReforma; XSD PL_010_V1; **apresentar antes→depois (flag OFF = 0 mudança) ao [W] antes de mergear** (REGRA MESTRE valor Tier-0).

## Lições catalogadas
- Back-compat de lib = rodar na **main-atual via CI**, NÃO na staging CT100 defasada (52 falhas pré-existentes contaminam o sinal).
- Pin dev-master: `"dev-master"` (branch) no composer.json + SHA no lock (`#sha` trava composer validate --strict).
- Feature flag = **string** (não enum) pra não quebrar domain-dict-guard G-4.
- Transparência: derrubei 2 hipóteses de alarme próprias com evidência antes de escalar.

## Pointers detalhados
- Session log 2026-07-03-fiscal-onda6 · ADR 0321 · US-FISCAL-021/022 (tasks-detail) · issue nfephp-org/sped-nfe#1274.
