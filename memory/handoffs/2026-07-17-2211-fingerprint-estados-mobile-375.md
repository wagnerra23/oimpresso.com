---
date: "2026-07-17"
time: "22:11 BRT"
slug: fingerprint-estados-mobile-375
tldr: "Chip C-F2 mergeado (PR #4489, squash b62dfc82d4): o harness de fidelidade de protótipo passou a cobrir os eixos que o vetor default não media — pseudo-estados hover/focus/active (nova passada compararEstados + captura Playwright real via --estados) e mobile 375 (dimensão de primeira classe, altura de telefone). LOCAL/dispatch, nunca render pareado em CI (ADR 0290)."
prs: [4489]
decided_by: [W]
related_adrs: [0290-fidelity-lock-v0-recusado]
next_steps: ["Corrida LIVE proto×prod com --estados (browser real) numa tela ancorada — o --selftest só prova orquestração, não captura", "Se útil: --estados no loop 0→6 do RUNBOOK-fidelidade quando bater fidelidade de afordância"]
---

## Estado MCP no momento do fechamento

MCP server **indisponível** no fechamento (`cycles-active` timeout −32001) → fallback brief (#373, gerado há ~125min):
- **Cycle:** — (nenhum ativo). Off-cycle.
- **HITL Wagner:** 2 (FIN-004 cobrança ROTA LIVRE; runbook on-prem pós-Gold).
- **ADRs 24h:** 0340 (tema colapso oposto), 0341 (memory-schema charter/spec required), 0342 (slug legacy), 0343 (promove adr-gate required).
- **Flags:** todas 🟢.

## O que aconteceu

Execução do **chip C-F2** (grade design→código 2026-07-17, nota ~7,7 do eixo construir). GAP: o `style-fingerprint` media só o estado DEFAULT; hover/focus/active e o viewport 375 estavam **fora**. Isto é a **Onda 3a.2** — o TODO já anunciado no cabeçalho do próprio `fingerprint-harness.mjs` (não um doc/roadmap paralelo).

- **`compararEstados`** (nova passada em `style-fingerprint.mjs`): casa elemento interativo por texto+tag e diffa o **CONJUNTO de propriedades que reagem** em cada estado (a afordância). **Cego ao valor base da cor** (o default pass já é dono disso) → pega o gap real (hover que não faz nada / anel de foco faltando) sem falso-positivo por cor-base diferente. **NÃO é escore** (respeita a lápide "razão de fidelidade" §5 2026-07-17 e ADR 0290): veredito por elemento×estado, vocabulário DIVERGE/IDENTICO.
- **`--estados`** (opt-in) no harness: força hover/focus/active por elemento via **Playwright real** (hover/focus/mouse-down) e anexa a afordância pro comparador. `--root` escopa à região; altura **viewport-aware** (mobile <600 = 812) torna **375 de primeira classe**.
- **CI:** wireei o `fingerprint-harness --selftest` (hermético) no `design-memory-gate.yml`, ao lado do style-fingerprint — a orquestração/estados vira gate guardado (não mecanismo que nada invoca). Captura live segue LOCAL (ADR 0290).
- **RUNBOOK-fidelidade-fingerprint.md:** registra que hover/focus/active são mecanizados; estados de DADOS (vazio/loading/erro/drawer) seguem manuais.

**Merge:** [W] mergeou #4489 (squash `b62dfc82d4`, 21:36 UTC). CI: 82 pass, 0 falha em required (design-memory gate + ESLint ratchet + Vite build verdes); único vermelho = `module-grades-gate` **advisory não-relacionado** (métrica composta de módulos PHP; meu diff = zero PHP) — não mexi (fora de escopo, 1-PR-1-intent).

## Artefatos gerados

- `prototipo-ui/style-fingerprint.mjs` (+76): `compararEstados`/`diffEstadosPar` + wire no `comparar()` + família 'estado' no `veredictoNL` + 5 asserts herméticos.
- `prototipo-ui/fingerprint-harness.mjs` (+146): `capturarEstados` (Playwright hover/focus/active) + `--estados`/`--root` + altura viewport-aware + selftest matriz 3vp (incl. 375) × 2 temas com regressão de estado só-mobile.
- `.github/workflows/design-memory-gate.yml` (+8): step harness `--selftest`.
- `prototipo-ui/RUNBOOK-fidelidade-fingerprint.md` (+6): estados mecanizados.

## Persistência

- **git:** PR #4489 MERGED em `origin/main` (`b62dfc82d4`). Este handoff via PR próprio (branch `claude/handoff-fingerprint-estados-375`).
- **MCP:** webhook GitHub→MCP propaga o handoff em ~2min (MCP estava down no fechamento; verificar depois).

## Próximos passos pra retomar

Corrida **LIVE** proto×prod com `--estados` numa tela ancorada (browser real) — é a evidência que falta: o `--selftest` prova **orquestração**, não captura (mesma disciplina honesta da Onda 3a; o PR não alegou end-to-end).

```
node prototipo-ui/fingerprint-harness.mjs --proto <url> --prod <url> --estados --viewports 375,1280,1440 --root "<css>"
```

## Lições catalogadas

- **Incidente evitado (recuperado):** meus 1ºs Edits caíram no **checkout MAIN serving** (`D:\oimpresso.com\prototipo-ui\...`) porque meu cwd do Bash tinha driftado pra lá (`cd /d/oimpresso.com` no começo). Peguei via grep (worktree pristine × main sujo), confirmei que o diff era **só** meu (nenhuma sessão paralela em risco), copiei pro worktree e restaurei o main com `git checkout --`. Trabalho de feature **sempre** no worktree; conferir cwd antes de Edit por path absoluto. Pareia com R8/ADR 0233.
- **Falso-trigger R12/design-hooks:** os hooks de fechamento (`encerrar`) e comparação-design dispararam na **minha própria** palavra de controle de loop ("encerrar o loop", "fingerprint") — classifiquei como falso-positivo e não executei cerimônia à toa. O fechamento REAL foi este ("salve tudo, merge já foi").

## Pointers detalhados

- Roadmap/origem: `memory/sessions/2026-07-08-arte-fingerprint-vs-sota.md` (Onda 3a/3a.2).
- Comparador: `prototipo-ui/style-fingerprint.mjs` · Driver: `prototipo-ui/fingerprint-harness.mjs` · Processo: `prototipo-ui/RUNBOOK-fidelidade-fingerprint.md`.
- Fronteira: [ADR 0290](../decisions/0290-fidelity-lock-v0-recusado.md) (sem render pareado em CI).
