# Sessão 2026-06-04 — QA-de-tela sustentável (ADR 0250 + enforcement)

**Origem:** Wagner pediu "Tester e QA como implementar" → garantir cobertura de 100% das telas com especialista Full Tester+QA, comparado aos melhores, com notas, **que sobreviva no tempo**.

## O que foi entregue (5 PRs)

| PR | Entrega | Estado |
|---|---|---|
| #2218 | hotfix FK NFSe (`nfse_emissao_id` INT casa com `increments`) — destravou suíte MySQL (erro 3780) | ✅ merged |
| #2215 | fundação: agente-autor `screen-qa-specialist` + `screen-coverage-map.mjs` + **ADR 0250 aceita** | ✅ merged |
| #2223 | enforcement: `screen-grade-seed.mjs` (222 scorecards) + `screen-grades-ratchet.mjs` + `screen-grades-gate.yml` | ✅ merged |
| #2225 | Onda 2: `screen-coverage-gate.yml` (catraca de cobertura) + baseline scorecard 0→222 | 🟡 aberto |

## A trajetória (o valor foi a reavaliação, não a 1ª ideia)

O caminho mudou 3× conforme estudei as rotinas internas — registrado na ADR 0250:
1. "implementar QA do zero" → **80% já existia** (método `SCREEN-GRADE-METODO.md` + baseline 222 telas de 30/mai).
2. "`screen:grade` determinístico espelhando `module:grade`" → a nota é **LLM-as-judge** (16 dims subjetivas vs Stripe/Linear/Bling); computar seria **inventar heurística** (anti-padrão Tier 0).
3. Virou **"ligar o enforcement determinístico"** — seed + catraca, espelhando o `module-grades-gate` (ADR 0155) que o time já confia.

**Princípio canonizado: CI vigia (determinístico) · agente julga (LLM).**

## Avaliação por etapa (nota /10) — entregue ao Wagner

QA-de-tela geral **40/100** (Developing). Padrão: etapas "de governança" (contrato 6, sentinela 6, catraca-módulo) fortes; etapas "de execução na tela" (nota 3, E2E 2, visual 3, a11y 2, self-healing 2) fracas. Backend QA é 80/100. Diagnóstico: "painel de instrumentos excelente, motor desligado".

## Diagnóstico de cobertura (factual)

275 telas · charter 132 (48%) · **E2E 3 (1,1%)** · **a11y 0** · scorecard **0→222** (pós-seed).

## Estado dos 4 anéis de sobrevivência (ADR 0250)

1. Catraca de nota → ✅ no ar (`screen-grades-gate`, #2223)
2. Catraca de cobertura → ✅ #2225 (aberto)
3. Sentinela freshness → parcial (`charter:health` cron 06:30 já roda)
4. Self-healing → a ligar

## Próximos (Onda 2b/3+)

- Dim-16 mecânica (charter? `@/ui`? tokens v4? zero inline-style?) como piso grep por tela do diff.
- Sentinela "TELAS SEM RE-SMOKE" no Daily Brief.
- Agente-autor rola por módulos P0 (Sells → Financeiro → NfeBrasil → Ponto).

## Notas operacionais

- Push de workflow exigiu token `gh` (workflow scope) — credential Windows não tinha. Padrão: `git push https://x-access-token:$(gh auth token)@...`.
- Workflow novo em PR não roda na própria branch (precisa existir no default branch) — `screen-grades-gate` ativa a partir do próximo PR pós-merge.
