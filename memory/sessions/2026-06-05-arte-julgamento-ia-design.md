---
slug: arte-julgamento-ia-design
title: "Estado da arte — julgamento de DESIGN/UI por IA (LLM/VLM-as-judge)"
type: estado-da-arte
date: 2026-06-05
agent: estado-da-arte
escopo: "Como a IA AVALIA e JULGA design (telas, UX, hierarquia, a11y) — não como gera"
status: research
---

# Estado da arte — julgamento de DESIGN/UI por IA

> Pedido Wagner (refinado p/ DESIGN): "Quais julgamentos por IA existem, técnicas dos
> melhores, compare e avalie os mais recentes e os melhores". Foco: **avaliar/gradear**
> interface, não gerar.

## Resumo executivo (4 linhas)

O estado-da-arte 2025-2026 convergiu em **VLM-as-judge sobre screenshot + rubrica
estruturada (rubric tree) + rationale antes da nota + painel/jury de juízes com
mitigação de viés posicional/verbosidade**. Benchmarks recentes (MLLM-as-UI-Judge,
WebDevJudge, GEBench) mostram que VLMs alinham **moderadamente** com humanos em
percepção de UI — úteis pra triagem precoce, **não** pra veredito final sozinhos.
O oimpresso já tem 80% das **rubricas** (framework 15/16-dim + Nielsen + WCAG + persona)
e um juiz LLM real (`ui:judge-pr`), mas o juiz é **single-shot, texto-só (diff, não
screenshot), gpt-4o-mini, sem anti-viés e desligado** — exatamente onde o mercado evoluiu.

---

## 1. PESQUISA — como os melhores fazem em 2026

### 1.1 LLM/VLM-as-judge — o consenso técnico

| Técnica | O que é (mecanismo) | Maturidade | Fonte |
|---|---|---|---|
| **Rubric tree (rubrica em árvore)** | Juiz recebe critérios hierárquicos, pontua por folha e agrega. WebDevJudge usa nós `Intention / Static Quality / Dynamic Behavior`; GEBench usa 5 rubricas → `GE-Score`. | Validado 2025 | WebDevJudge, GEBench |
| **CoT-rationale ANTES da nota (G-Eval)** | Modelo escreve crítica estruturada por dimensão, *depois* emite score. Reduz nota "no chute". | Padrão consolidado | G-Eval / DeepEval |
| **Pairwise vs pointwise** | Pairwise (A vs B) é mais sensível porém **mais enviesado**: preferência troca ~35% por posição vs ~9% no pointwise. Pra *nota absoluta* preferir pointwise+rubrica; pra *ranquear 2 designs* usar pairwise com swap. | Validado 2024-2026 | arXiv 2406.07791, 2406.12319 |
| **Jury / panel of judges** | N juízes (modelos diferentes/baratos) votam → estabiliza veredito ruidoso. "Weak judges, strong panel". | Validado | orq.ai, papers jury |
| **Mitigação viés posicional** | Swap-and-average (rodar A/B e B/A), randomização de ordem. | Validado | arXiv 2406.07791 |
| **Mitigação viés de verbosidade** | Length-controlled scoring (AlpacaEval 2 LC) — penaliza preferir resposta longa. | Validado | AlpacaEval 2 LC |
| **Reference anchoring** | Comparar contra exemplo-ouro ("essa tela parece feita pela Linear?") em vez de julgar no vácuo. | Prática consolidada | design-critique skills |

### 1.2 VLM-as-judge sobre SCREENSHOT (o salto de 2025-2026)

- **MLLM-as-a-UI-Judge** (arXiv 2510.08783, out/2025): testa Claude 3.5 Sonnet, GPT-4o,
  Llama-3.2-11B-Vision a julgar UIs vs percepção humana. Achado honesto: **moderadamente
  bom**, útil pra **suplementar** pesquisa de UX em estágio inicial — mas com vieses,
  alucinação e inconsistência mesmo em modelos top. **Não** substitui humano.
- **WebDevJudge** (out/2025): 654 pares avaliados por especialistas com rubric tree —
  benchmark robusto p/ medir o quão bom é um VLM-juiz de web.
- **GEBench / GE-Score** (2026): métrica multidimensional VLM-guiada (intent fulfillment,
  interaction logic, UI consistency, structural integrity, visual fidelity).
- **Visual Prompting + Iterative Refinement for Design Critique** (arXiv 2412.16829):
  VLM gera crítica de UI, refina em loop — detecta violações de princípio de design
  **semântica E espacialmente** e explica de forma acionável p/ designer.

### 1.3 Ferramentas/produtos reais (2025-2026)

| Produto | Como julga design com IA |
|---|---|
| **Figma AI design review assistant** | Crítica, layouts alternativos, microcopy dentro do Figma. |
| **Attention Insight / Neurons / UX Pilot** | Heatmap **preditivo** (eye-tracking treinado) — onde o olho vai antes de testar com humano. Plugin Figma. |
| **Impeccable** (design linter IA) | Pontua contra **Nielsen 10** + cognitive load (Miller) + persona lens + detector de **25 anti-padrões** + "AI slop". |
| **Heurilens** | Mede legibilidade, contraste, comprehension vs **WCAG**. |
| **Claude Design (Anthropic Labs, abr/2026)** | Lê codebase + design files, constrói design system, aplica crítica/handoff. Ainda **research preview** — não substitui Figma como source of truth nem design review em escala. |
| **Google Stitch / Vercel v0 / Uizard** | Geram UI + dão crítica/iteração via slider. |

### 1.4 Rubricas canônicas que viram score

A indústria converge em: **Nielsen 10 heuristics** (sanity check) + **WCAG 2.1/2.2 AA**
(contraste 4.5:1, focus, ARIA, teclado) + **visual hierarchy / densidade / cognitive load
(Miller)** + **microcopy**. O truque de automatização: transformar cada heurística numa
**folha de rubrica com 0-10 + evidência textual obrigatória** (não nota nua).

### 1.5 Hype vs validado (honesto)

- **Validado:** rubric tree, CoT-antes-da-nota, jury, swap-and-average, heatmap preditivo
  (Attention Insight tem base de eye-tracking real), Nielsen+WCAG como rubrica.
- **Promissor mas com ressalva:** VLM julgando screenshot — alinhamento só *moderado*,
  vieses reais. Bom p/ triagem, ruim como juiz final solo.
- **Hype:** "AI substitui design review", "score estético objetivo". Gosto estético e
  fit-com-cliente-real (Larissa) seguem humanos — o próprio AUTOMATION-ROADMAP já admite
  "10-20% sempre humano".

Fontes: arXiv [2510.08783](https://arxiv.org/html/2510.08783v1), [2412.16829](https://arxiv.org/pdf/2412.16829),
[2406.07791](https://arxiv.org/html/2406.07791v9), [2406.12319](https://arxiv.org/pdf/2406.12319);
[orq.ai jury](https://orq.ai/blog/llm-juries-in-practice); [DeepEval G-Eval](https://deepeval.com/guides/guides-llm-as-a-judge);
[Impeccable](https://impeccable.style/docs/critique/); [Attention Insight via Klay](https://www.theklaystudio.com/top-7-ai-design-review-tools-for-mid-to-large-creative-teams-in-2026/);
[Claude Design](https://www.anthropic.com/news/claude-design-anthropic-labs); [NN/g heuristics](https://www.nngroup.com/articles/ten-usability-heuristics/).

---

## 2. COMPARA — o que o oimpresso JÁ TEM (mapa honesto peça-a-peça)

| Peça oimpresso | Onde | O que faz | = estado-da-arte? |
|---|---|---|---|
| **Framework 15/16-dim** | `framework-15-dimensoes.md` | Rubrica de design ponderada por persona (Larissa/Daniela/Jair/Kamila) | ✅ no nível de **rubrica** — é exatamente "rubric + persona weighting". Forte. |
| **Nielsen 10 + JTBD Forces** | mesmo doc | Sanity check + decisão adoção/churn | ✅ Nielsen ✅ JTBD (vai além do mercado, que raramente usa JTBD em juiz) |
| **`screen-grade` (skill)** | `.claude/skills/screen-grade` | Pré-flight resolver + nota /100 por tela, 16-dim, score-as-code YAML, níveis Beginner→Champion | ✅ rubrica madura — porém **execução manual** (command `screen:grade` não existe ainda) |
| **`pr-ui-judge` (juiz LLM REAL)** | `Modules/Jana/Ai/Agents/PrUiJudgeAgent.php` + `ui:judge-pr` | LLM avalia PR contra Constituição UI v2, 9-dim, JSON score+verdict | 🟡 **é o juiz, mas defasado** — ver §3 |
| **`mwart-comparative` V4** | skill Tier B | Gate visual F1.5 + F3, orquestra plugin Anthropic (design-critique/a11y/ux-copy), Wagner aprova **screenshot** | ✅ alinha com "human-in-the-loop + reference anchoring + critique estruturada". Muito forte. |
| **Plugin Anthropic `design:*`** | integrado (ADR 0109/0114) | critique 5-cat + WCAG + ux-copy + handoff | ✅ é literalmente o "VLM critique" do mercado |
| **`design-arte` (agent)** | `.claude/agents/design-arte.md` | Benchmark estratégico vs Linear/Shopify/Bling + nota /100 + CAPTERRA-DESIGN-FICHA | ✅ ZOOM-OUT estratégico — mercado não tem equivalente formalizado |
| **Visual regression** | `.github/workflows/visual-regression.yml` (ADR 0108) | Pest 4 Browser + Playwright pixel-diff como **ground truth** | 🟡 existe mas **INFRA-ONLY** (bloqueado por migration order) |
| **`ui:lint` + AP1-AP8** | PRE-MERGE-UI / Onda 1-2 | Lint sintático (cor crua, FontAwesome, emoji, slots PT-01) | ✅ camada determinística — corretamente separada do juiz semântico |

**Veredito honesto:** o oimpresso está **à frente da média do mercado** em RUBRICA
(15/16-dim + persona + Nielsen + JTBD) e em PROCESSO (gate visual com Wagner aprovando
screenshot — human-in-the-loop que muita ferramenta não tem). Está **atrás** na
ENGENHARIA DO JUIZ automatizado: o juiz que existe é texto-só, single-shot, modelo fraco,
sem anti-viés, e desligado.

---

## 3. AVALIA — gaps rankeados (impacto × esforço)

> Esforço em IA-pair (ADR 0106: ~10x humano + margem 2x).

| # | Gap | Impacto | Esforço IA-pair | Pré-req? | Recência vs validação |
|---|---|---|---|---|---|
| G1 | **Juiz é texto-só (lê diff, não screenshot)** — não vê o que Wagner vê. Trocar p/ **VLM-as-judge sobre screenshot** (já capturamos via Chrome MCP/Playwright) | **alto** | ~3-4h | screenshot pipeline (já existe Chrome MCP + visual-regression) | validado (MLLM-UI-Judge) |
| G2 | **CoT-rationale antes da nota** — forçar o juiz a escrever crítica por dimensão *antes* do score (G-Eval). Hoje o agent pede score+dim juntos | **alto** | ~1-2h (prompt) | nenhum | validado (G-Eval) |
| G3 | **Modelo fraco** — `gpt-4o-mini` single-shot. Upgrade já documentado no próprio arquivo (trocar p/ Claude Sonnet, prompt caching → ~$0.005/PR) | **alto** | ~30min | `ANTHROPIC_API_KEY` (já no .env local) | validado |
| G4 | **Sem mitigação de viés** posicional/verbosidade no `pr-ui-judge` e no `screen-grade` quando comparar 2 telas (A/B) → swap-and-average | médio | ~1-2h | só quando houver modo pairwise | validado |
| G5 | **`screen:grade` command não existe** — rubrica 16-dim roda 100% manual. Materializar o command (espelho de `module:grade`) persiste YAML + vira gate CI | médio | ~3-4h | framework-15-dim (existe) | n/a (engenharia) |
| G6 | **Visual regression é ground truth real mas está INFRA-ONLY** — destravar dá ao juiz uma âncora objetiva (pixel-diff) p/ separar "mudou de propósito" de "regrediu" | médio | ~6-8h (migration order legacy) | escopo ADR 0108 separado | validado (screenshot diff = ground truth) |
| G7 | **Sem jury/panel** — 1 juiz só. Adicionar 2º juiz barato (modelo diferente) e medir agreement antes de confiar em CI auto-merge | baixo | ~2-3h | G1+G3 antes | validado, mas só vale quando o juiz solo já for bom |
| G8 | **Heatmap preditivo** (attention/eye-tracking IA) ausente — Attention Insight-like p/ prever onde olho da Larissa vai | baixo | alto (integração externa) | sinal qualificado Larissa | promissor, não-essencial |

### Separação pedida: "mais recentes" vs "comprovadamente melhores"

- **Comprovadamente melhores (faça agora):** G2 (CoT-antes-da-nota), G3 (modelo melhor),
  G1 (screenshot). São validados, baratos, e o oimpresso já tem a infra.
- **Mais recentes (2025-2026, faça depois com sinal):** G7 (jury), G8 (heatmap preditivo),
  GE-Score multidimensional. Promissores mas com ressalva de alinhamento moderado — não
  apostar CI auto-merge neles ainda.

---

## RECOMENDAÇÃO — top 5 ações priorizadas

1. **G2+G3 juntos (alto-impacto-baixo-esforço, sem pré-req bloqueante):** no
   `PrUiJudgeAgent.php`, (a) trocar `gpt-4o-mini` → Claude Sonnet (upgrade já documentado
   no próprio header do arquivo, linhas 43-47) e (b) reestruturar o prompt p/ **rationale
   por dimensão ANTES do score** (padrão G-Eval). ~2h IA-pair. Próxima ação hoje:
   editar `instructions()` + `#[Provider]/#[Model]` e rodar `ui:judge-pr` num PR de UI real.
2. **G1 — alimentar o juiz com screenshot** (Chrome MCP/Playwright já captura): passar a
   imagem renderizada da tela junto do diff. Vira VLM-as-judge de verdade. ~3-4h.
3. **G5 — `php artisan screen:grade <Mod>/<Tela>`**: materializar o command que persiste o
   YAML 16-dim, transformando `screen-grade` de manual em automatizável/gate.
4. **G4 — swap-and-average** quando o juiz comparar 2 variantes (A/B), eliminando viés
   posicional (35%→~9%). Barato, ativa só no modo pairwise.
5. **G6 — destravar visual-regression (INFRA-ONLY→real)** p/ dar ao juiz uma âncora
   pixel-diff objetiva. Escopo maior (ADR 0108), faça quando telas-âncora estabilizarem.

**Não faça ainda:** jury/panel (G7) e heatmap preditivo (G8) — só depois do juiz solo
estar bom, e G8 só com sinal qualificado da Larissa (ADR 0105). Evitar apostar CI
auto-merge em VLM-judge: o estado-da-arte diz alinhamento *moderado* — mantenha Wagner
aprovando screenshot no gate final (R2/R7), que já é a prática certa.

---

## Restrições respeitadas
- Multi-tenant Tier 0: nenhum gap aqui vaza tenant (juiz lê diff/screenshot, não dados de
  negócio). Score-as-code YAML não carrega PII.
- Sem PII em queries (pesquisa foi sobre técnica, não cliente).
- Não editei código nem commitei — só este doc em `memory/sessions/`.
