---
date: "2026-07-07"
time: "17:46 BRT"
slug: financeiro-fidelidade-dark-mecanismos-comparacao
tldr: "Dogfood do protocolo aplicar-prototipo no Financeiro/Unificado: inventário por região (199 comparações ancoradas), fila de 10 fixes aplicada + pills/valor-saída iguais ao protótipo, dark-mode corrigido (KPIs, botão nu, linha) e retune WARM dos tokens dark (ADR UI-0020). Nasceram 2 mecanismos: style-fingerprint.mjs (comparação exaustiva proto×prod) e modo UPDATE do VRT. 12 PRs mergeados (#3923-3934)."
prs: [3923, 3924, 3925, 3926, 3927, 3928, 3930, 3931, 3932, 3933, 3934, 3935]
decided_by: [W]
related_adrs: [0019-sidebar-light-definitivo-supersede-0009-0014, 0020-dark-warm-ds-v6-tokens]
next_steps:
  - "Verificar deploy cumulativo publicou o chunk (botão + KPIs + warm) via manifest no servidor"
  - "Mergear #3935 (baselines dark regeneradas pelo modo UPDATE) — fecha dívida anti-#3297"
  - "Estrear style-fingerprint em prod nos 2 temas — pendente: como navegar localhost:8765 até a view Financeiro"
---

# Handoff — Financeiro/Unificado: fidelidade ao protótipo + dark + mecanismos de comparação

## Estado MCP no momento
MCP tools deferred/desconectados nesta sessão — snapshot via `gh`:
- **PRs desta sessão:** #3923→#3934 MERGED (12), #3929 CLOSED (dup de base errada), **#3935 OPEN** (baselines VRT regeneradas pelo robô do modo UPDATE).
- **Deploy "Deploy to Hostinger":** in_progress (rerun cumulativo — fila estava congestionada por run preso, cancelado + re-disparado).
- Branch de trabalho da sessão: worktree `financeiro-proto-test` (fresca de origin/main; a worktree-mãe `adoring-lovelace` estava −4875 stale, nunca usada — guard funcionou).

## O que aconteceu
Wagner pediu "testar se o protocolo `aplicar-prototipo` é bão" usando o Financeiro. Virou dogfood completo do protocolo + correção da tela:

1. **Máquina Fase 0.5 (`detectar-telas.mjs`)** rodada → pegou bug real (parser `memcofreModule` só lia `module:`, não `module=` → falso AMBIGUO no Caixa derrubava o gate). Fix + selftest (#3923, já em prod).
2. **Inventário por região** (#3924): 6 regiões × **199 comparações** componente-a-componente, âncora dupla (arquivo:linha proto E prod) + medição DOM ao vivo. Placar 64 IDENTICO · 70 DIVERGE · 32 PROD_A_FRENTE · 18 SO_PROTOTIPO · 15 SO_PRODUCAO. Método formalizado no RUNBOOK Fase 1.
3. **P0 achado:** copy da lente Conciliação renderizava `±R$ [redacted]` (artefato do git filter-repo em string de UI) → #3925.
4. **ADR UI-0019** (#3927): sidebar light DEFINITIVO, supersede UI-0009+UI-0014 ([W] "revogue as anteriores").
5. **Fila de 10 fixes** (#3928): toggles com estado visual, KPIs (anel/hover/dots), ClienteCombobox ligado (US-FIN-024 fechada, charter v18), rejeição inline, forma pré-selecionada, sparkline em tokens, drag&drop anexos. Depois pills+valor-saída "iguais ao protótipo" ([W] "pode fazer igual").
6. **Smoke ao vivo** (browser MCP + Windows-MCP) pegou 2 bugs que CI não vê: anel/toggle não rendiam (bespoke fora de `@layer` vence utility) → #3930/#3931 (outline + `!`). E o dark inteiro "não bateu".
7. **Dark WARM** (#3932, **ADR UI-0020**): retune de 37 tokens dark azulados (hue 258/265) → warm DS-v6 (282, chroma 4-5× menor). Causa-raiz do "cor da lista/fundo/linha diferente". [W] "autorizo tudo".
8. **2 mecanismos novos** ([W] "tem método pra isso?"): `style-fingerprint.mjs` (#3934 — vetor exaustivo por elemento nos 2 temas, selftest 7/7) e **modo UPDATE do VRT** (#3933 — dispatch regenera baselines no runner canônico e abre PR, anti-#3297).

## Artefatos gerados
- **Código Financeiro:** `Index.tsx` + `_components/{FinBaixaSheet,TituloCreateSheet,FinAnexosPanel}.tsx` (fila + dark).
- **Tokens:** `semantic.tokens.json` (37 dark) + gerados `_generated-{inertia,cockpit}-dark.css` + `PALETA.html`.
- **Mecanismos:** `prototipo-ui/style-fingerprint.mjs` (~230 ln, selftest 7/7); `.github/workflows/visual-regression.yml` (modo UPDATE).
- **Docs canon:** ADR UI-0019, ADR UI-0020, `financeiro-unificado-visual-comparison.md` (Round 2026-07-07 + fila 1-10 ✅), RUNBOOK-aplicar-prototipo Fase 1 (regras 5/6/7 + mecanismo fingerprint), charter Unificado v18.

## Persistência
- **git:** 12 PRs mergeados em main; #3935 aberto; este handoff em PR próprio.
- **MCP:** webhook GitHub→MCP propaga docs canon (~2min pós-push).
- **BRIEFING:** Financeiro tocado — `brief-update` aplicável na retomada.

## Próximos passos pra retomar
`/continuar` → depois: (1) confirmar chunk cumulativo no ar (grep manifest no servidor); (2) mergear #3935; (3) rodar `node prototipo-ui/style-fingerprint.mjs --snippet` nos 2 lados (falta descobrir navegação do protótipo localhost:8765 → view Financeiro).

## Lições catalogadas
- **Sonda por amostragem tem viés do que o agente lembra de medir** — 3 lacunas escaparam no mesmo dia (dark não sondado, geometria/botão-3-linhas, componente nu). Resposta mecânica: `style-fingerprint` (vetor completo, 2 temas). O olho do Wagner foi a 2ª linha de defesa porque a 1ª não existia.
- **Bespoke fora de `@layer` vence utility Tailwind v4** independente de especificidade → `outline`/`!` pra fixes locais.
- **SHA no servidor ≠ bundle servido:** git pull chega antes do build Vite publicar; provar deploy pelo CHUNK do manifest, não só SHA (RUNBOOK regra 6).
- **PR cortado de branch já-squashada arrasta commits fantasma** → conflito; cherry-pick sobre origin/main fresco (recorrente hoje: #3929→#3930, #3931→v2).

## Pointers detalhados
- Inventário completo: [`financeiro-unificado-visual-comparison.md`](../requisitos/Financeiro/financeiro-unificado-visual-comparison.md) §Round 2026-07-07 (199 linhas ancoradas).
- Método: [`RUNBOOK-aplicar-prototipo-orquestracao.md`](../../prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md) Fase 1 regras 4-7.
- Decisões: [ADR UI-0019](../requisitos/_DesignSystem/adr/ui/0019-sidebar-light-definitivo-supersede-0009-0014.md) · [ADR UI-0020](../requisitos/_DesignSystem/adr/ui/0020-dark-warm-ds-v6-tokens.md).
