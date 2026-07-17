---
date: "2026-07-17"
time: "13:00 BRT"
slug: memoria-conhecimento-c8-c12-eixo-temporal-ancora
tldr: "Chips C8 (eixo temporal da âncora — consome o verificado@sha que ninguém lia) e C12 (supersede detection ligado em staging, provado no banco) mergeados. Redestilação das portas foi descartada — sessão paralela já corrigira no main."
prs: [4429, 4431, 4432]
decided_by: [W]
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0295-bitemporal-event-time-memoria-jana
  - 0303-anchor-lint-wired-testado-sa-a2-bis
next_steps:
  - "Redistilar BRIEFING do OficinaAuto (distilled_at 2026-07-09 → tocar SPEC reprova ratchet GT-G3)"
  - "Emendar skill alinhar-tela/memory-schema-preflight: verificado@sha deve usar sha JÁ na main (git rev-parse origin/main), não HEAD da branch — 63% das âncoras não-medíveis por squash-merge"
  - "RAGAS semanal Jana sem scheduler no container staging (chokepoint fantasma) — casa com COPI-28 MEM-MET-4"
---

# memoria-conhecimento — C8 + C12 (eixo temporal da âncora)

Sessão de melhoria da dimensão **memoria-conhecimento** (nota 7,0/10, grade de réguas 2026-07-17). Tese da
grade: *"os ganhos aqui são ligar o que já existe"* — confirmada, zero mecanismo novo.

## Estado MCP no momento

- **Cycle:** nenhum ativo em COPI.
- **my-work @wagner:** 30 tasks (10 review · 8 blocked · 12 todo). Tocam esta sessão: `COPI-25` (MEM-EVAL-3
  backfill facts — mesmo comando que exercitei no C12) e `COPI-28` (MEM-MET-4 trend de qualidade/RAGAS —
  casa com o gap do RAGAS sem scheduler).
- **decisions-search:** ADR 0292 (errata 0291 — distiller_freshness determinístico) e 0327 (anchor-content
  required) são as vizinhas do território que toquei; nenhuma nova nesta sessão.

## O que aconteceu

Dois chips da grade, ambos "ligar o que já estava pago e mudo":

- **C8** ([#4429](https://github.com/wagnerra23/oimpresso.com/pull/4429), mergeado [W]): o `verificado@<sha7>`
  que a gramática ADR 0273 §1 **exige** era capturado em 2 arquivos/5 sites e usado só como *presença*
  (`SpecAnchorClassifier`→`deveFecharPorAncora`). Novo eixo `--stale` no `anchor-lint`: `git log <sha>..HEAD`
  → `anchor_stale` quando o código andou desde a verificação. **Opt-in** (caminho required segue fs-puro, ADR
  0303) + **guard anti-fabricação** (shallow/sha-ausente/sha-não-ancestral → `unknown`, nunca "fresco") +
  job advisory `anchor-stale` que o invoca (não vira chokepoint fantasma) + teste e2e contra repo git real
  (mutação verificada: 3 FAILs / 2 FAILs). US-GOV-055 registrada.
- **C12** ([#4431](https://github.com/wagnerra23/oimpresso.com/pull/4431), mergeado [W]): flag
  `JANA_SUPERSEDE_DETECTION_ENABLED` (ADR 0295 slice 3) ligada em staging. **Prova no banco** (não em arquivo):
  `event_valid_until` 0→1, `supersedes_id` 0→1 em biz=1, via `copiloto:backfill-fatos --business=1 --sync`.
  Append-only preservado (texto do fato antigo intacto). Só o `.env.staging.example` no diff.

**Reconciliação de sessão paralela:** o diagnóstico herdado mandava redistilar as portas Governance/Jana pra
desbloquear o C8. Redistilei — mas ao empurrar, o cherry-pick bateu num conflito: **uma sessão paralela já
tinha redistilado as duas no main**, com mais profundidade. Descartei minha versão (redundante + inferior); o
bloqueador `distiller_freshness` já estava 0 no main. Git como ponte pegou isso antes de eu sobrescrever
trabalho melhor.

## Achado maior que o chip

**277 de 442 âncoras (63%) estão carimbadas com sha que o squash-merge apagou** (`sha_fora_da_ancestralidade`):
o agente carimba o HEAD da própria branch, o squash come o commit, o sha some no CI. Só sobrevive quem carimba
sha **já na main**. Por isso o eixo hoje só mede ~40% das âncoras — os 60% `unknown` são, eles próprios, o
achado. Correção da convenção fica como next-step (emendar a skill que orienta o carimbo).

## Lições catalogadas

- **O instrumento mente antes do sistema.** 4× o meu próprio ferramental de auditoria saiu verde por não medir
  nada (heredoc do Bash colapsando `\\` 3×; `2>/dev/null` no `execSync` caindo em cmd.exe no Windows —
  pegadinha já catalogada). Todas pegas por **controle-negativo**. Corolário: todo contrafactual precisa de um
  controle que prove que o instrumento casa o alvo, senão mede o vazio e chama de evidência.
- **Mitigação adotada:** escrever script com `Write`, nunca heredoc (o colapso de `\\` é sistemático no Git Bash).

## Persistência

- **git:** 3 PRs mergeados no main (#4429/#4431/#4432) + branches limpas.
- **MCP:** webhook GitHub→MCP propaga o session log em ~2min. Sem task MCP nova (o C8 virou US-GOV-055 no SPEC).
- **BRIEFING:** Governance/Jana já redistilados no main pela sessão paralela — não toquei.

## Próximos passos pra retomar

Ler o session log (`memory/sessions/2026-07-17-memoria-conhecimento-c8-c12-portas.md`, no main) §Pendências.
Prioridade: redistilar OficinaAuto (senão o próximo PR que tocar OficinaAuto/SPEC.md reprova o ratchet GT-G3).

## Pointers detalhados

- Session log completo (4 achados + fio condutor + pendências): `memory/sessions/2026-07-17-memoria-conhecimento-c8-c12-portas.md`
- Grade de réguas origem: `memory/sessions/2026-07-17-reguas-grade-truncagem-silenciosa.md`
- Código C8: `scripts/governance/anchor-lint.mjs` (`--stale`), `scripts/governance/anchor-stale.test.mjs`, `.github/workflows/anchor-drift.yml` (job `anchor-stale`)
