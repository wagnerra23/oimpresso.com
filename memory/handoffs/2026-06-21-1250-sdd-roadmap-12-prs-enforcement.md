---
date: 2026-06-21
time: "1250 BRT"
slug: "sdd-roadmap-12-prs-enforcement"
tldr: "Avaliação adversarial do SDD deu composto 60/100 (mede honesto, não governa — 0 dos ~18 required são SDD). Gerados roadmap + 13 planos verificados (#3135) e 12 PRs armando o Trilho A + lever do floor + 1º dente. Verificação adversarial: os 7 engines de gate MORDEM (rodados em sandbox); 5 são advisory-por-design (parede 14d/promoção), 2 mordem hard hoje (Pfr foundation-ratchet, P01 commit-back). 3 reds de CI eram mecânicos (opentelemetry, censo de gates, flake APP_KEY) — todos consertados. Resto é parede de relógio/humano: merges + secret + prod + 7 nightlies + janela 14d."
decided_by: [W]
cycle: "CYCLE-08"
prs: [3135, 3138, 3140, 3142, 3143, 3144, 3145, 3149, 3150, 3155, 3157, 3158]
next_steps:
  - "Mergear os 12 na ordem: P08 #3140 → P05 #3144 (rebase gate-selftest), depois P01/P03/P09/P07/P12/P11/DOCS/P14/P15; Pfr #3143 fica DRAFT até ~2026-07-05"
  - "Flip foundation-ratchet a required (gh api branch protection) após 7 nightlies verdes + 14d (~2026-07-05) — 1º dente SDD em L3"
  - "Follow-up P03 tier-S: r.tier===TIER_FILTER || r.tier==='S' em sqlite-test-corruptors.mjs:~363 antes de promover P03"
  - "Roadmap-v2: dobrar correção P01/P02 (HEAD=not_yet_measured, congelar 295 não 274) + entries P14/P15/P16, após #3135 mergear"
  - "Mão do Wagner: secret OPENAI_API_KEY (P12 real), migrate prod mcp_sdd_scorecard_history (P06), fila ambíguos (P10), rebuild imagem CT100 com pcov (P07)"
---

# Handoff — SDD: 60/100 → 12 PRs de enforcement armados (2026-06-21)

## Origem
Wagner: *"qual seria a régua correta para avaliar o processo?"* → a régua é o **teste contrafactual** (se um funcionário tentar quebrar uma decisão já tomada, o processo barra sozinho?). Régua de maturidade L0–L4; L3 = gate `required` + counterfactual. Rodado `/sdd-avaliar` (7 skeptics adversariais) → **composto 60/100**: detecção honesta (L2), governança vazia (0/18 required são SDD). Vetor #2848 já entrou verde. Scorecard completo: [session log](../sessions/2026-06-21-sdd-avaliacao-adversarial.md) (PR #3135).

## Os 12 PRs (roadmap memory/requisitos/_Governanca/roadmap/)

| PR | Item | Engine morde? | Observação |
|---|---|---|---|
| #3143 | **Pfr** foundation-ratchet (1º dente) | ✅ hard (sem continue-on-error) | **DRAFT até ~05/jul** — não mergear antes do flip (protection-drift fica 🔴) |
| #3142 | **P01** commit-back do floor | ✅ hard (escreve 295 vivo, idempotente) | corrigido censo de gates + flake APP_KEY |
| #3144 | **P05** grandfather #2848 | ✅ (bad→exit1, isolated→exit0) | advisory; cobre os 4 baselines |
| #3140 | **P08** métricas GT + 6ª catraca | ✅ (3 claims reproduzidos) | advisory; base do gate-selftest 12/12 |
| #3145 | **P03** corruptores (lever) | ⚠️ advisory **+ furo tier-S** | 18→11 real; furo é follow-up |
| #3149 | **P09** sanear anchors | ✅ (path falso→exit1) | advisory; dead/placeholder reduzidos |
| #3150 | **P07** pcov no CI | — | medição real aguarda rebuild CT100 |
| #3138 | **P12** recall-eval mock | ✅ (slug morto→exit1) | corrigido ext opentelemetry |
| #3155 | **P11** KL E2 detector×corretor | — | reconcilia ghost adr/ + cron distiller |
| #3135 | **DOCS** roadmap + 13 planos | — | anti-ghost advisory esperado (cita ghosts) |
| #3157 | **P14** charters related_us | ✅ (charter sem related_us→exit1) | fecha re-scope sem ADR; 3 legacy migrados |
| #3158 | **P15** tripwire nightly-diff | ✅ (classe inflada→detecta) | advisory, alerta OFF até P03 estabilizar |

## Veredito (verificação adversarial — 7 skeptics rodaram cada gate em sandbox)
**Implementação honesta:** nenhum gate é teatro — todos mordem quando rodados (não há fixture-tautológica, código morto nem métrica-de-forma). **O gap é puramente governança:** 5 dos 7 são advisory/não-required por design (gates nascem advisory; viram required pelo calendário ADR 0275 / janela 14d). Isso É o 60/100 — "mede mas não governa".

## A parede (o que nenhum agente comprime — ADR 0106 relógio real)
Merges (revisão humana) · secret OPENAI_API_KEY · migrate prod · fila ambíguos · **7 noites de nightly verde** (P04) · **janela 14d** do 1º dente.

## Follow-ups precisos (não bloqueiam merge)
1. **P03 tier-S:** o gate roda `--tier=A` e não pega `Schema::drop` de tabela CORE (tier S, pior caso). Fix: `r.tier === TIER_FILTER || r.tier === 'S'` em `sqlite-test-corruptors.mjs:~363`, validado pelo meta-teste vitest. Hoje há 0 tier-S → não urge; fazer antes de promover P03 a required.
2. **Roadmap-v2:** correção P01/P02 (o HEAD commitado é `not_yet_measured`; o "274" é working-tree sujo do #3020 — congelar o **295 vivo**, não 274) + entries P14/P15/P16-on-hold no `_ROADMAP.md`, após #3135 mergear.

## Lições da sessão (mecânicas)
- **Rate-limit:** >~10 worktree-agents simultâneos estrangulam a API ("not your usage limit"); bater em lotes ≤4 (2 ondas perdidas, recuperadas com batching 4+3).
- **Staleness:** `main` avança durante a sessão; `gh pr update-branch` resolve falso-positivo append-only (PRs cortados antes do #3141 viam os ADRs ratificados como "modificados").
- **Check [G]:** todo workflow novo exige entry em `gates-registry.json` no MESMO PR (travou o #3142).
