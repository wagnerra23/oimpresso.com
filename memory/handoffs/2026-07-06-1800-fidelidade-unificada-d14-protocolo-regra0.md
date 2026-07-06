---
date: "2026-07-06"
time: "18:00 BRT"
slug: fidelidade-unificada-d14-protocolo-regra0
tldr: "Maratona 11 PRs: sentinela frescor v1 morta por adversário → v2 identidade canônica + dispatch/ledger/SLA (ADR 0324 proposto); [W] aprovou direção do Financeiro; diff prod×proto achou primary ghost/magenta + full-reload D-14 — consertados e provados em prod (X-Inertia-Partial-Data); deploy flaky morto (OPcache pré-gate); PROTOCOLO-COMPARACAO-RUNTIME criado com Regra 0 (pós-deploy sempre Chrome+comparação sem [W] pedir)."
decided_by: [W, C]
cycle: null
prs: [3880, 3881, 3882, 3883, 3884, 3885, 3886, 3887, 3888, 3889, 3890]
next_steps:
  - "Integrar pixel-gate buildado (PixelBaselineTest) ao protocolo + pacote promoção a required (chip)"
  - "Sweep D-14 repo-wide: router.get sem only (chip)"
  - "Ratificar ADR 0324 + 1ª rodada completa do dispatch de frescor (chip)"
  - "US-FIN-031 bulk actions — verificar âncora Implementado-em antes (chip)"
related_adrs:
  - 0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma
  - 0315-design-sync-claude-design-vs-cowork-charter
  - 0190-primary-button-roxo-universal-295
---

# Handoff — Fidelidade da Unificada + D-14 morto + Protocolo de comparação (Regra 0)

> **TL;DR:** sessão-maratona (11 PRs mergeados). Arco: sentinela de frescor v1 → **morta por
> adversário** (2 bugs de fundamento) → estado-da-arte → **identidade canônica** (path completo +
> hash normalizado, #3882) → **modelo operacional de frescor** (dispatch+ledger+SLA, #3883, ADR
> 0324 proposto) → Wagner aprovou a direção do Financeiro vendo o protótipo renderizado →
> **diff prod×protótipo** achou primary ghost/magenta + **full-reload (D-14)** → tudo consertado
> e **provado em prod** (partial reload com `X-Inertia-Partial-Data` no header). Deploy flaky
> (5xx fantasma) morto com reset OPcache PRÉ-Boot-gate (#3890, Infra Contract cumprido no 1º run).
> **Regra 0 gravada no método** (Wagner: "isso deve ficar no método"): pós-deploy o agente SEMPRE
> abre no Chrome + compara com o protótipo, sem ser pedido.

## O que foi CRIADO (resposta à pergunta "o que foi criado eu quero saber")

| Artefato | Tipo | O que é |
|---|---|---|
| [`PROTOCOLO-COMPARACAO-RUNTIME.md`](../requisitos/_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md) | **PROTOCOLO/método** (não é runbook) | Comparação prod×protótipo em 7 dimensões (D1 comportamento/rede ⭐ · D2 layout/linhas · D3 ícones · D4 tipografia · D5 footer · D6 cor · D7 densidade) + **Regra 0** (pós-deploy sempre Chrome+comparação, sem Wagner pedir) + regra "divergência ≠ auto-bug" |
| `scripts/governance/cowork-mirror-freshness.mjs` v2 + `.test.mjs` (46 asserts) | máquina/sentinela | frescor do espelho Cowork: `sha256(normalizado)` por path completo; `--manifest`/`--compare`/`--sla`; ledger datado (`.cowork-freshness-ledger.json`, 1ª rodada real: 1 SYNC) |
| `anchorRelPath` em `anchor-content-check.mjs` | máquina | identidade de âncora por path completo (mata colisão de basename) |
| [ADR 0324 (proposto)](../decisions/proposals/0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma.md) | ADR | modelo operacional: dispatch logado + SLA 14d + limite de plataforma (DesignSync sem webhook/service-token) + PR-bot V2 como direção |
| [session arte](../sessions/2026-07-06-arte-design-code-sync-frescor.md) | estado-da-arte | como os líderes 2026 fazem design↔code sync (Tokens Studio/Figma/Chromatic); "viver só da API" ≠ padrão; identidade = hash normalizado por path |

**⚠️ CSS/React BUILD — onde ficou (gap declarado):** a comparação **buildada + pixel-a-pixel JÁ
EXISTIA** e **não foi criada hoje**: `tests/Browser/CoreScreens/PixelBaselineTest.php` (Pest 4
Browser + Playwright, builda Vite React/CSS, pixelmatch com double-threshold e baseline da
`financeiro_unificado` commitada) rodando **advisory** em `visual-regression.yml`. O protocolo
novo ainda **NÃO aponta** pra ela — integrar o pixel-gate ao protocolo + decidir promoção a
required (tirar `continue-on-error`, ADR 0271 já prevê) ficou de **chip** pra próxima sessão.

## O que foi CONSERTADO em produto (tudo provado em prod pós-deploy, Regra 0)

- **Financeiro/Unificado**: primary "Novo título" ghost/magenta → **roxo 295 fixo** · filtro de
  data `<select>` → **segmentado** · barra de filtro em **2 linhas** (ordem do proto) · setas
  ‹› texto → **SVG lucide** · **"✓ Recebi/Paguei"** · **D-14 MORTO** (`aplicar()` com `only:` +
  4 props em closures no controller — provado com `X-Inertia-Partial-Data:
  kpis,lancamentos,pagination,filters,periodLabel` + marker JS vivo).
- **Deploy flaky morto**: 2 deploys falhavam com 5xx fantasma porque o Boot gate rodava ANTES do
  reset de OPcache → step "Reset OPcache PRÉ-GATE" (degradante; o obrigatório ADR 0269 intacto).
  1º run real: `OPCACHE_RESET_OK` → gate OK.
- **Espelho Cowork**: 42 relatórios meta deletados (zero âncora quebrada, adversário validou).
- **Tasks stale fechadas**: US-FIN-027/028 estavam DONE no código desde Onda 22 (verificado
  file:line) — fechadas no MCP com evidência.
- **§0.2 INDEX**: contradição "byte-idêntico…CRLF" corrigida; pendência "antigo?" **RESOLVIDA**
  ([W] aprovou a direção: "esse mesmo, gostei"); accent do browser do Wagner voltou a 295
  (localStorage accentHue estava em 330 magenta — era parte do "bem diferente").

## Lições da sessão (pro próximo agente NÃO repetir)

1. **Comparar contra o espelho sem provar SYNC = comparar contra design velho.** Fonte = Cowork
   vivo (ou espelho hash-provado). Erro cometido e corrigido no protocolo.
2. **Presença ≠ fidelidade**: medir comportamento/rede (D1) ANTES de pixel. O full-reload não
   aparece em screenshot.
3. **Divergência ≠ bug**: pills da prod eram refino #3391 aprovado DEPOIS do proto (prod-à-frente).
   Não reverter decisão do Wagner no automático.
4. **Regra 0**: nunca declarar "aplicado" sem deploy success + Chrome + comparação + prova.
   Wagner teve que pedir 2× — agora é método.
5. Sessões paralelas do Wagner mergeiam no meio do trabalho (aconteceu 2×: #3886 e o registro
   §0.2 do #3875) — commitar cedo, conferir `gh pr view --json state` antes de re-push.

## Estado MCP no momento do fechamento

`cycles-active`/`my-work` → **timeout/unavailable** às 18:03 BRT (registrado honesto). Último
estado conhecido na sessão: US-FIN-027 e US-FIN-028 transicionadas todo→doing→review→**done**
com comentário de evidência. Brief da manhã: cycle —, HITL 2 pendências (FIN-004, runbook on-prem).

## Próximos passos (viram chips)

1. Integrar **pixel-gate buildado** (PixelBaselineTest/visual-regression.yml) ao protocolo +
   pacote de promoção a required pra decisão [W].
2. **Sweep D-14 repo-wide**: outras telas com `router.get` sem `only:` ("não pode em tela nenhuma").
3. **ADR 0324**: ratificação [W] + 1ª rodada COMPLETA do dispatch de frescor (3/3 hasheados).
4. **US-FIN-031 bulk actions** (verificar âncora "Implementado em" antes — pode estar stale como 027/028).
