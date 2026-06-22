---
date: "2026-06-21"
time: "2112 BRT"
slug: "sdd-2-dentes-l3-flip-leva"
tldr: "Sessão fechou os 2 primeiros dentes SDD em L3: foundation-ratchet (1º) + scorecard-ratchet (2º) agora required no main — 0/18 → 2/19 gates SDD governando. Arco: avaliação adversarial 60/100 → roadmap + 13 planos → leva de 14 PRs mergeada (com a união cuidadosa do tamper-guard, gate-selftest 14/14) → 2 flips. Automação: sweep semanal de roadmap (auto-captura + dedup por raiz) ligado. Follow-up: auto-sync do scorecard mata a staleness flaky e destrava o GT-G3 inteiro."
authors: [W, C]
cycle: "CYCLE-08"
prs: [3135, 3138, 3140, 3142, 3143, 3144, 3145, 3149, 3150, 3155, 3157, 3158, 3168, 3169, 3171, 3181]
next_steps:
  - "Resto do roadmap sobe 1 dente por vez (backlog + sweep semanal); próximos candidatos: anchor-lint required (SA-A10, depende coverage 100%), R1 full-suite (depende burn-down floor verde)"
  - "Follow-up principal: auto-sync do scorecard (estende o commit-back do floor/P01) → mata a staleness flaky, destrava o GT-G3 completo e faz brief/dashboard pararem de mentir"
  - "Backlog capturado nesta sessão: US-GOV-034..042 (tier-S fix, isentar roadmap/, corruptores lotes 2-3, backfill related_us, tripwire, TDAD on-hold, roadmap-v2, limpar scorecard sujo, MemCofre/SRS) + US-FIN-060 (boleto-OCR 403 prod) + US-WA-318 (DNS mídia 48k)"
  - "PRs abertos a triar: #3176 (isenção roadmap no detector — fecha o anti-ghost advisory; GitHub flaky no update-branch), #3162 (ADS Tier-0), #2441 (fix filial, parado desde 08/jun)"
---

# Handoff — SDD: 2 primeiros dentes em L3 + leva de 14 PRs mergeada (2026-06-21)

## O que mudou (o concreto)
- **0/18 → 2/19 gates SDD `required` no `main`.** O programa cruzou de "mede" pra "governa".
  - 🦷 **1º dente:** `Foundation ratchet (quarentena/RefreshDatabase/Business::first)` — bloqueia PR que suba `n_quarantine` ou meta `@group legacy-quarantine` sem razão. (PR prep #3143, flip via `gh api`.)
  - 🦷 **2º dente:** `SDD scorecard ratchet (métrica armada não regride · GT-G3)` — bloqueia PR que regrida uma métrica armada vs `sdd-scorecard-baseline.json`. (PR prep #3181, flip via `gh api`.)

## O arco da sessão
Wagner: *"qual seria a régua correta pra avaliar o processo?"* → régua = teste contrafactual (se um funcionário tentar quebrar uma decisão já tomada, o processo barra sozinho?). Rodado `/sdd-avaliar` (7 skeptics) → **60/100** (mede honesto, governança vazia, 0/18 required SDD). Daí: roadmap + 13 planos verificados (#3135) → 14 PRs armando o Trilho A → verificação adversarial (os 7 engines mordem) → leva mergeada → **2 flips**.

## Decisões de método que importam (anti-teatro)
- **2º dente = ratchet-ONLY, não o GT-G3 inteiro.** Teste no main limpo mostrou: determinismo ✓, ratchet ✓, mas **staleness 🔴** (o scorecard commitado defasa a cada mudança do repo). Promover o job inteiro = gate-que-grita-lobo. Isolei o ratchet num workflow próprio always-run + hard; a staleness fica advisory (o lugar dela é auto-sync, não bloquear PR humano).
- **P05 grandfather (#3144):** conflito real — outra sessão reconstruiu o `baseline-tamper-guard` em direção diferente. Resolvido por UNIÃO dos 2 (8 baselines guardados: 3 SDD + memory-health + 5 ratchets de UI), **gate-selftest 14/14**, os dois caminhos provados em sandbox.
- **Flip é Wagner-only (ADR 0275 §5)** — respeitado; o `gh api` foi rodado sob autorização explícita ("pode fazer").

## Automação ligada (pra não "ficar pedindo")
- `roadmap-auto-sweep-semanal` (seg 08:00 BRT) — varre handoffs/sessions/PR-bodies novos, deduplica **por raiz+arquivo** (não título), cria os LIMPOS no backlog e reporta os SUSPEITOS. Testado ao vivo: pegou as 2 pegadinhas de duplicata (#2765 ≡ floor commit-back; audit mcp_* ≡ US-ADS-001).
- `sdd-foundation-ratchet-flip-check` — **desativado** (flip já feito).

## Lições mecânicas
- Branch deletado no merge → re-push re-cria órfão; checar `state:MERGED` antes de adicionar a um PR (aconteceu 2×: handoff + #3168).
- `gh pr list` default cap 30 ≠ volume real (~800/mês) — sempre `--limit` alto.
- Backticks em `--body` do bash viram command-substitution — usar `--body-file -` com heredoc `'EOF'`.
- Promover gate com `paths:` filter = deadlock "expected, waiting"; required precisa always-run.
