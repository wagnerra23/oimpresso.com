---
date: "2026-06-06"
slug: determinizacao-anti-dup-design
tldr: "~23 PRs (#2343-2364) OFF-CYCLE, 3 frentes. ANTI-DUP: reuse-index (3434 símbolos JS+PHP) + gates reuse/no-mock/jscpd + deletou stubs CSS. DESIGN-COMM: Claude Design oficial = já somos superset governado (PROTOCOL §10.5). DETERMINIZAÇÃO LLM-judge→teste: ADR 0255 (charter+design-spec derivado) + a11y 3 fases axe + Onda 1 (PR UI Judge 6/9 dims→regex). 2 agentes thread travaram→assumi/finalizei. Fechei 2 pontas soltas minhas (#2364)."
hour: "22:51 BRT"
topic: "Onda de determinização (LLM-judge→teste) + anti-duplicação + comunicação com Claude Design — ~23 PRs"
duration: "~8h"
authors: [C, W]
---

## Estado MCP no momento

- **Cycle:** CYCLE-08 "Receita — Onda A" (25% decorrido, 21 dias). Esta sessão foi **OFF-CYCLE** (plataforma/segurança, não Receita) — Wagner dirigiu explicitamente ("projeto mais seguro").
- **ADR novo:** **0255** (aceito por Wagner) — contrato de view determinístico.
- **PRs:** #2343-2364 mergeados (21 confirmados no origin/main; alguns números são de outros devs/gaps).

## O que aconteceu (narrativa)

Wagner abriu com a dor "como não nascer componente errado/duplicado". Virou 3 frentes encadeadas:
1. **Anti-duplicação** — derivado+enforçado (ADR 0240/0239): reuse-index responde "reusa ou cria, em qual arquivo" (JS+PHP). 4 redes novas + deletou stubs mortos.
2. **Comunicação com design** — "saiu o Claude Design oficial?": pesquisa profunda mostrou que **já somos superset governado** (retorno + gate + git-SSOT); só falta o formato do bundle (reativo). PROTOCOL §10.5 blinda o canon.
3. **Determinização** — Wagner: "o que mais é LLM-judge que vira teste?". Auditoria: determinizar o objetivo (estrutura/a11y/anti-padrões), deixar o subjetivo no LLM (RAGAS/estética/voz). Enactado: ADR 0255 + a11y 3 fases + Onda 1.

**2 agentes em thread travaram** — Wagner mandou "confirmar bem / assumir": Fase 2 morreu no `npm install` de worktree fresco (resolvi com `--package-lock-only`, e descobri que vitest nem rodava no CI → criei o gate); Onda 1 travou na fiação do command (precisa Pest) — o scorer de 296 linhas estava sólido, fechei o merge 6+3→9 + 5 testes.

## Artefatos gerados (canon)

- `scripts/reuse-index.mjs` · `no-mock-in-prod.mjs` · `design-spec-gen.mjs` · `a11y-ratchet.mjs` · `scorer-sync-check.mjs` (5 gates Node)
- `Modules/Jana/Ai/UiDeterministicScorer.php` (porte regex) + `UiJudgePrCommand` fiado
- `tests/a11y-primitives.test.tsx` + `tests/Browser/CoreScreens/A11yAxeBrowserTest.php`
- `memory/decisions/0255-*.md` (aceito) + dossiês `memory/sessions/2026-06-06-arte-*.md` (4)
- `prototipo-ui/PROTOCOL.md` §10.5 · `REGISTRY_DS_COMPONENTES.md` (primitivos layout) · `Sells/Create.design-spec.json` (1ª)
- 7 workflows CI novos (reuse/no-mock/jscpd/design-spec/a11y/a11y-axe/scorer-sync)

## Persistência

- **git:** tudo em origin/main (#2343-2364). Webhook GitHub→MCP propaga ~2min.
- **MCP:** ADR 0255 sincroniza. ⚠️ tasks F0-F7 seedadas em `_DesignSystem/SPEC.md` mas o módulo `_DesignSystem` (underscore) NÃO é ingerido no task DB (server-side, flagueado).

## Próximos passos pra retomar

`brief-fetch` → o trabalho que sobra é **incremental/reativo por design** (não "faltou"): design-spec 1→146 telas (conforme telas tocadas) · a11y 294→0 (conforme correções) · scorecard consome design-spec · handoff F-C (reativo, formato Anthropic não publicado). **Voltar pro CYCLE-08 Receita** (esta foi off-cycle).

## Lições catalogadas

- **Agentes em thread travam no passo que não verificam local** (CT100/Pest/npm) — "confirmar bem + assumir" foi a jogada certa (pegou os 2). Thread é boa pro trabalho pesado, ruim pra fechar.
- **5ª/6ª vez que preflight pegou** quase-duplicação minha (manual overlap, PageHeader não-morto, conformance 4 guards, jscpd estrutural, primitivos-já-existem, regex-drift).
- **Mergeei #2350 sobre vermelho** (date sem aspas) por pressa — fix-forward. Depois travei "só mergeia se 0 fail".
- **Determinizar o subjetivo = falso determinismo** — RAGAS/estética/voz ficam LLM (a auditoria foi cética de propósito).

## Pointers detalhados (on-demand)

- Dossiês: `memory/sessions/2026-06-06-arte-{llm-judge-para-deterministico,view-contract-deterministico,claude-design-handoff}.md` + `plano-inventario-anti-duplicacao.md`
- ADR 0255 (contrato de view) · PROTOCOL §10.5 (bundle) · MANUAL-CSS-JS (problema #5 = reuse-index)
