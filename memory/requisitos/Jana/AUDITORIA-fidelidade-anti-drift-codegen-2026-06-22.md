# AUDITORIA — Fidelidade & Anti-Drift no codegen AI→produção (SOTA Ângulo 3)

> **Tema:** como medir/garantir fidelidade visual e travar drift entre o que a IA gera (protótipo Claude Design) e o que vai pra produção (`resources/js/Pages/<Mod>/<Tela>.tsx`).
> **Data:** 2026-06-22 · **Autor:** audit-research-expert (Fase 1) · **Escopo código:** `prototipo-ui/`, `scripts/`, `.github/workflows/`, `tests/Browser/`
> **Pergunta do Wagner:** "qual o melhor transporte design→code, como estruturar melhor, e meu pipeline está em quantos % de maturidade?"
> **Auditorias irmãs (não duplicar):** [AUDITORIA-design-as-code-token-driven](AUDITORIA-design-as-code-token-driven-2026-06-22.md) (camada de *geração* token-driven). Esta foca na camada de **verificação/medição de aceitação**.

---

## 1. TL;DR

**Maturidade global ponderada: 78% — "Advanced", recomendação CONSOLIDAR (não EVOLUIR).**

O oimpresso já está **acima da média do mercado** na *arquitetura* de anti-drift: tem pixel-diff determinístico (`pest-plugin-browser`, pixelmatch, maxDiffPixelRatio 1%), grade de identidade visual com **σ=0** (regex, não LLM-judge — cura documentada da alucinação 91→71), ratchet append-only, e um `EVAL_PROTOCOL` que separa "quem gera de quem avalia" — algo que 90% dos times de vibe-coding nem nomeiam. O que falta é **fechar 3 loops já especificados mas não operacionais**: (1) `outcome-metrics.js` (custo/retrabalho/lead-time por tela) não existe; (2) nenhum `critique-score.json` foi emitido nos protótipos — o gate F1.5 é manual; (3) **não há diff automático protótipo↔produção** — a fidelidade do *transporte* (o pulo F1→F3) é medida por olho humano, não por máquina.

**Top 3 gaps:** P0 diff protótipo↔produção automatizado · P0 `outcome-metrics.js` (a taxa de aceitação real é invisível hoje) · P1 PR UI Judge sair de advisory→calibrado.

---

## 2. Concorrentes (3 categorias, 11 sistemas)

### A. Visual-regression / fidelidade visual (a "máquina que pega regressão")
| Sistema | Modelo | OSS/Cloud | Diferencial 2026 |
|---|---|---|---|
| **Chromatic** | Storybook-first, por-componente | Cloud | Baseline por estado de componente + aprovação humana; **zero camada AI** (determinístico puro) |
| **Percy (BrowserStack)** | DOM-snapshot → render em browser real | Cloud | **Visual Review Agent** (2026) filtra ruído pixel-diff automaticamente |
| **Applitools** | Visual AI semântico | Cloud | IA visual mais madura (maior runway de treino), entende intenção não-pixel |
| **Playwright `toHaveScreenshot`** | pixelmatch nativo, CI | OSS | Grátis, determinístico, threshold/maxDiffPixelRatio configurável |
| **Happo** | screenshot cross-browser real | Cloud | Fidelidade cross-browser (browser real, não headless) |

### B. Geração design→code (onde o drift NASCE)
| Sistema | Fidelidade | Mecanismo |
|---|---|---|
| **Builder.io** | **Alta** | Liga a design system existente → gera com componentes/tokens de produção (Code Connect-like) |
| **Figma Make / Replit / Bolt** | Média-alta | **Acesso a metadata do Figma** ("cheat code" de fidelidade) |
| **v0 / Lovable** | Média-baixa | **Screenshot-based** → "lê uma imagem" → versões genéricas, gap layout/estilo |

### C. Verificação agêntica / juiz (auto-review do código gerado)
| Sistema | Papel |
|---|---|
| **SonarQube Agentic Analysis** | Verifica código da IA *enquanto* escreve, dentro do loop, antes do PR |
| **Playwright MCP + Claude Code** | Loop de feedback visual: agente vê screenshot e auto-corrige (accessibility-tree, não pixel-grid) |
| **LLM-as-judge (WebDevJudge, DeepEval)** | Juiz LLM separado avalia UI; concorda ~85% com humano em 2026 |

---

## 3. Matriz de capacidades (18 dimensões)

Legenda: ✅ pleno · 🟡 parcial · ❌ ausente · — n/a

| # | Dimensão | Chromatic | Percy | Playwright | Builder.io | **oimpresso** |
|---|---|---|---|---|---|---|
| 1 | Pixel-diff determinístico | ✅ | 🟡 | ✅ | — | ✅ (pest-plugin-browser, thr 0.3, ratio 1%) |
| 2 | Baseline commitada + review | ✅ | ✅ | ✅ | — | ✅ (snapshot CI ubuntu, update consciente) |
| 3 | Render determinístico (fonte/anim zerada) | ✅ | ✅ | 🟡 | — | ✅ (Arial norm, anim 0, Carbon::setTestNow) |
| 4 | Cobertura por-componente (Storybook) | ✅ | 🟡 | 🟡 | 🟡 | ❌ (só 6 telas núcleo, page-level) |
| 5 | Token-linting / design-lint | ❌ | ❌ | ❌ | ✅ | ✅ (`design-identity-grade` σ=0, 8 dims) |
| 6 | Ratchet "só sobe" (anti-regressão) | 🟡 | 🟡 | ❌ | ❌ | ✅ (baseline append-only, ADR 0254/0209) |
| 7 | a11y gate (WCAG AA) | 🟡 | 🟡 | 🟡 | 🟡 | ✅ (axe-core, a11y-ratchet, F3.5) |
| 8 | LLM-judge calibrado vs humano | — | 🟡 | — | — | 🟡 (PR UI Judge advisory, sem calibração 4-sem) |
| 9 | Noise-filtering AI no diff | ❌ | ✅ | ❌ | — | ❌ (diff é binário, sem filtro semântico) |
| 10 | **Diff protótipo↔produção (transporte)** | ❌ | ❌ | ❌ | 🟡 | ❌ **(gap central — F1→F3 só olho [W])** |
| 11 | Acceptance-rate medida (% aceito s/ retrabalho) | — | — | — | — | ❌ (`outcome-metrics.js` não existe) |
| 12 | Rework/devolução por entrega rastreada | — | — | — | — | 🟡 (SYNC_LOG tem timestamp, sem agregação) |
| 13 | Replay/golden de comportamento do agente | — | — | — | — | ✅ (REPLAY_CASES RC-01..06, GOLDEN_SET) |
| 14 | Quem gera ≠ quem avalia (separação) | ✅ | ✅ | — | 🟡 | ✅ (EVAL_PROTOCOL regra-mãe explícita) |
| 15 | Agentic self-review pré-PR | ❌ | ❌ | — | 🟡 | 🟡 (auto-crítica F1.5 + auto-a11y, manual) |
| 16 | CLIP/SSIM/perceptual score | ❌ | 🟡 | ❌ | — | ❌ (só pixelmatch, sem métrica perceptual) |
| 17 | Componentes de produção na geração | — | — | — | ✅ | 🟡 (charter+PT guiam, sem Code-Connect map) |
| 18 | Multi-tenant safe no harness | — | — | — | — | ✅ (business_id, auth-bridge cross-process) |

**Leitura:** oimpresso ganha em 5/6/7/13/14/18 (governança/anti-regressão); perde em 4/9/10/11/16 (cobertura, filtro-de-ruído, **diff de transporte**, **acceptance-rate**, **perceptual**).

---

## 4. Score % por área (5 áreas ponderadas)

Fórmula global: `Σ(score_área × peso) / Σpeso`.

| Área | Peso | Score | Evidência |
|---|---|---|---|
| **A. Fidelidade visual (regression)** | 3 | **82%** | Pixel-diff núcleo-6 operacional (`PixelBaselineTest.php`, pixelmatch ratio 1%, render determinístico). Falta: cobertura por-componente (4) + métrica perceptual CLIP/SSIM (16). Mercado: Playwright/Chromatic dão isso "de graça" mas sem ratchet. |
| **B. Anti-drift / token-linting** | 3 | **90%** | `design-identity-grade.mjs` σ=0, 8 dims ponderadas (tipo×3, cor×2, layout×2...), ratchet só-sobe (ADR 0254). Supera ds-bridge/Design-Lint (Figma-side) por estar no *código de produção*. Mercado: só 40% dos times têm pipeline de token automatizado ([zeroheight 2026]). |
| **C. Verificação agêntica / self-review** | 2 | **70%** | F1.5 crítica + F3.5 a11y como auto-check de quem produz; PR UI Judge (Sonnet) existe. Falta: judge calibrado vs [W] (KPI delta especificado, não rodado 4 sem) + SonarQube-style inline. |
| **D. Medição de aceitação (% maturity)** | 2 | **55%** | KPIs especificados no EVAL_PROTOCOL (custo/retrabalho/lead-time/replay-pass) mas `outcome-metrics.js` **não existe**; nenhum `critique-score.json` emitido. A taxa de aceitação real do transporte é **invisível** hoje. |
| **E. Transporte protótipo→produção (fidelidade do pulo F1→F3)** | 3 | **72%** | PROTOCOL/MWART 5-fases sólido, gates de chegada (build OK, a11y, visual-reg). Mas o **diff direto protótipo↔produção é 100% humano** (gap 10). [W] ainda é o detector — anti-padrão L-38 reconhecido no próprio repo. |

**Global ponderado = (82×3 + 90×3 + 70×2 + 55×2 + 72×3) / 13 = 1011/13 ≈ 78%.**

---

## 5. Top 10 gaps priorizados

| # | Gap | Sistema-ref | Esforço | ROI | Prio |
|---|---|---|---|---|---|
| 1 | **Diff automático protótipo↔produção** (renderizar `prototipos/<tela>/page.tsx` e `Pages/<Mod>/<Tela>.tsx` lado-a-lado, pixelmatch + score) — fecha o transporte | Builder.io / Playwright | 2-3 dd | 🔥🔥🔥 | **P0** |
| 2 | **`outcome-metrics.js`** (EVAL-002): custo/tela, retrabalho (devoluções [W]), lead-time F0→F4 do SYNC_LOG → primeira % de aceitação real | DORA rework-rate / Faros | 1-2 dd | 🔥🔥🔥 | **P0** |
| 3 | Emitir `critique-score.json` automático no F1.5 (hoje gate é manual, 0 arquivos) | LLM-judge / DeepEval | 1 dd | 🔥🔥 | **P1** |
| 4 | PR UI Judge advisory→calibrado: 4 sem de delta judge-vs-[W] antes de poder bloquear | WebDevJudge / Percy Agent | 2 dd (relógio: 4 sem) | 🔥🔥 | **P1** |
| 5 | Métrica perceptual (CLIP/CW-SSIM) além do pixelmatch — pega "genérico mas diferente" | Design2Code bench | 2 dd | 🔥 | P2 |
| 6 | Cobertura por-componente (não só 6 telas page-level) | Chromatic/Storybook | 3-5 dd | 🔥🔥 | P2 |
| 7 | Filtro de ruído no diff (anti-flakiness antialiasing/dynamic) | Percy Visual Review Agent | 1-2 dd | 🔥 | P2 |
| 8 | Code-Connect map charter↔componente de produção (geração já nasce com componente real) | Builder.io | 3 dd | 🔥 | P2 |
| 9 | visual-regression sair de skip-as-pass→blocking real em mudança de pixel | DORA elite | 1 dd (relógio: ratificação [W]) | 🔥 | P2 |
| 10 | Red-team mensal (EVAL-003): injetar cor crua/prompt stale, contar gates que seguram | chaos-eng | 1 dd/mês | 🟡 | P3 |

---

## 6. Decisão estratégica: **CONSOLIDAR**

O oimpresso **não precisa de troca de paradigma** — precisa **ligar o que já desenhou**. A arquitetura de anti-drift (ratchet σ=0 + EVAL_PROTOCOL com separação gerador/avaliador + golden set congelado por [W]) é mais madura que a de qualquer ferramenta de vibe-coding comercial, que tipicamente para no pixel-diff sem ratchet e sem medir aceitação. EVOLUIR (ex: trocar pixelmatch por VLM-judge proprietário, ou Chromatic SaaS) adicionaria custo recorrente e dependência cloud sem cobrir o **gap real**, que é puramente de *medição* (áreas D e E). Os 3 itens P0/P1 são código já especificado nos próprios docs (`outcome-metrics.js` está nomeado no EVAL_PROTOCOL Onda 2). Consolidar = sair de 78%→~90% sem mudar a espinha. A única coisa "nova" justificável é o **diff protótipo↔produção** (gap #1), que não é paradigma — é uma extensão natural do harness Pest já existente.

---

## 7. Roadmap (3 ondas — caso de consolidação acelerada)

- **Onda 1 (P0, ~1 sem):** `outcome-metrics.js` lê SYNC_LOG → emite tabela semanal (custo/tela, retrabalho, lead-time). Primeira leitura da **taxa de aceitação**. + diff protótipo↔produção como job opt-in. → revela a % real.
- **Onda 2 (P1, ~4 sem relógio):** `critique-score.json` automático no F1.5 + PR UI Judge advisory rodando contra rubrica [W] (delta judge-vs-[W] como KPI). → tira [W] do caminho crítico de detecção.
- **Onda 3 (P2, ~2 sem):** métrica perceptual (CLIP/CW-SSIM) no diff + cobertura por-componente + visual-regression blocking. → fecha o ratchet visual.

**Métrica de saturação:** parar de subir quando (a) delta judge-vs-[W] < 5 pts por 4 semanas E (b) escapes pegos por [W] → 0 E (c) taxa de aceitação F1→F3 sem retrabalho ≥ 80% (benchmark: Cursor autocomplete 72%; design-to-code "limpo" é mais difícil, 80% page-level é elite). Acima disso o custo marginal de cobertura excede o risco residual.

---

## 8. Surpresa positiva & negativa

**🟢 Positiva (oimpresso > mercado):**
1. **σ=0 por design.** Enquanto o mercado celebra "LLM-judge concorda 85% com humano", o oimpresso *já diagnosticou e curou* a alucinação do LLM-judge (91→71 no mesmo PR) trocando por regex binário determinístico. Isso é uma lição que v0/Lovable/zeroheight ainda não internalizaram.
2. **Separação gerador≠avaliador formalizada** (EVAL_PROTOCOL "quem gera não escreve a própria régua" + golden set congelado por [W]). É o anti-padrão #1 de eval de IA — e o oimpresso tem regra-mãe escrita.
3. **Ratchet append-only "só-sobe"** (ADR 0254/0209) — anti-drift estrutural que Chromatic/Percy não têm nativamente (eles aprovam baseline, mas não impedem a nota *cair*).

**🔴 Negativa (mercado > oimpresso):**
1. **Acceptance-rate é invisível.** Cursor publica 72%, Copilot 38%; DORA tem rework-rate como 5ª métrica oficial (2025), AI-code survival 65% vs 92% humano. O oimpresso **não consegue dizer hoje** quantos % das telas passam F1→F3 sem retrabalho — exatamente a pergunta do Wagner. O dado existe no SYNC_LOG mas não é agregado.
2. **O transporte (F1→F3) é medido por olho.** Builder.io/Figma-Make têm acesso a metadata e geram com componente de produção; o loop Cowork é screenshot-based (mesma classe de fidelidade-média de v0/Lovable) e **sem diff de máquina** entre protótipo e tela final — [W] é o detector (L-38).

---

## Fontes

- [Percy vs Applitools vs Chromatic 2026 — Crosscheck](https://crosscheck.cloud/blogs/percy-vs-applitools-vs-chromatic-visual-regression-testing/)
- [20 Best Visual Testing Tools 2026 — Sauce Labs](https://saucelabs.com/resources/blog/comparing-the-20-best-visual-testing-tools-of-2026)
- [Design System Drift & detection — OverlayQA](https://overlayqa.com/blog/design-system-drift/) · [ds-bridge](https://ds-bridge.com/) · [zeroheight report via Magic Patterns](https://www.magicpatterns.com/blog/design-system-maintenance)
- [Design2Code Benchmark — EmergentMind](https://www.emergentmind.com/topics/design2code-benchmark) (CLIP, CW-SSIM, TreeBLEU) · [UI2Code^N arxiv](https://arxiv.org/html/2511.08195v1)
- [Best Vibe Coding Tools 2026 (v0/Lovable/Bolt/Replit/Figma Make) — EPAM](https://www.epam.com/insights/ai/blogs/best-vibe-coding-tools-v0-lovable-bolt-replit-and-figma-make)
- [AI Code Assistant Stats 2026 — Konabayev](https://konabayev.com/blog/ai-code-assistant-statistics-2026/) (Cursor 72% / Copilot 38% accept)
- [Rework Rate 5th DORA metric — Faros](https://www.faros.ai/blog/5th-dora-metric-rework-rate-track-it-now) · [DORA not enough 2026 — Oobeya](https://oobeya.io/blog/dora-metrics-not-enough-2026)
- [AI code quality vs human baselines — Exceeds](https://blog.exceeds.ai/industry-benchmarks-ai-code-productivity/) (survival 65% vs 92%, revert 8% vs 3%)
- [SonarQube Agentic Analysis](https://www.sonarsource.com/products/sonarqube/agentic-analysis/) · [Playwright MCP + Claude Code — Builder.io](https://www.builder.io/blog/playwright-mcp-server-claude-code)
- [LLM-as-a-Judge 2026 — DeepEval](https://deepeval.com/blog/llm-as-a-judge) · [WebDevJudge arxiv](https://arxiv.org/html/2510.18560)

**Refs internos:** `tests/Browser/CoreScreens/PixelBaselineTest.php` · `scripts/design-identity-grade.mjs` (ADR 0254) · `prototipo-ui/evals/EVAL_PROTOCOL.md` · `prototipo-ui/PROTOCOL.md` · `.github/workflows/visual-regression.yml` · `.github/workflows/design-identity-gate.yml`
