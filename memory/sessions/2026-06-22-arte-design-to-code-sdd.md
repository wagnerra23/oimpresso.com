---
slug: 2026-06-22-arte-design-to-code-sdd
title: "Estado-da-arte: fluxo design→código + spec-driven dev × método oimpresso (aplicar-prototipo)"
type: session
status: live
authority: reference
module: _DesignSystem
created: 2026-06-22
agent: estado-da-arte
related_adrs: [0114, 0107, 0104, 0273, 0282, 0293, 0297, 0093]
related_docs:
  - prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md
  - .claude/skills/aplicar-prototipo/SKILL.md
  - memory/requisitos/_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md
  - scripts/governance/anchor-lint.mjs
---

# Estado-da-arte — design→código + spec-driven dev × método oimpresso

Escopo: método `aplicar-prototipo` (orquestração 6 fases: detectar → mapear paralelo read-only → consolidar → registrar → aplicar sessão-limpa por tela → fechar) + mecânica por-tela (RUNBOOK-replicar F0–F7). Stack Inertia/React + Laravel. Comparado contra os métodos consagrados de 2026 em 4 eixos.

---

## 1. PESQUISA (limpa — sem contaminação oimpresso)

| Player / método | Quem é | Mecanismo concreto | Por que é referência |
|---|---|---|---|
| **Figma Dev Mode + Code Connect** | Ponte design↔código da Figma (CLI + UI + MCP server) | `*.figma.tsx` mapeia cada componente Figma → componente de código real. Dev Mode passa a mostrar **o snippet de produção**, não código autogerado. Alimenta o MCP server pra agentes consumirem o design system com propriedades reais (variants, tokens, auto-layout) em vez de pixels | Padrão de facto do handoff 2025-26; elimina o "agente adivinha do screenshot" |
| **W3C DTCG + Style Dictionary v4** | Spec de design tokens (estável 2025.10) + transformador | `.tokens.json` vendor-neutral (cor/espaço/tipo/composite) → Style Dictionary v4 transforma pra CSS/TS/Tailwind/iOS. Fonte única de verdade da camada Fundações | Spec estável W3C, suportada por Figma/Tokens Studio/Terrazzo; resolve drift de token cross-tool |
| **Storybook + Chromatic/Percy** | Workbench de componente isolado + visual regression hospedado | Cada `story` vira asserção repetível. Chromatic tira screenshot pixel-perfect por estado, compara com baseline aprovado, **gateia o PR** (TurboSnap só roda o que mudou). Percy faz VRT a nível-página | VRT de componente isolado pega regressão **antes** de compor a página; baseline versionado por branch |
| **GitHub Spec Kit + spec-anchored (arXiv 2602.00180)** | Toolkit SDD (set/2025) + taxonomia acadêmica | 4 fases (specify→plan→tasks→implement), 7 slash-commands. Paper define 3 níveis: spec-first (drift), **spec-anchored** (spec+código co-evoluem, testes BDD/contract forçam sync a cada commit), spec-as-source. Specs particionam trabalho → **agentes paralelos em tarefas não-sobrepostas** | SDD vira mainstream; estudos mostram **−50% erro** com código gerado de spec refinada por humano |
| **Anthropic orchestrator-worker** | Arquitetura multi-agente do Research | Lead decompõe → spawna 3-5 subagentes paralelos, cada um com **objetivo + formato de saída + fronteira de tarefa + ferramentas**. Context isolado por subagente (janela própria, devolve só o relevante = fan-in). Heurística de escala (1 agente p/ fato simples, 10+ p/ pesquisa complexa). Checkpoint: salva o plano em memória externa cedo | −90% tempo em query complexa; +90,2% vs single-agent. Fontes documentam falhas: trabalho duplicado por fronteira vaga, overhead de coordenação |

Fontes no rodapé.

---

## 2. COMPARA — método consagrado × oimpresso (aderência %)

| Eixo / dimensão | Estado-da-arte (2026) | Estado oimpresso hoje | Aderência | Distância |
|---|---|---|---|---|
| **Orquestração agêntica** | Lead decompõe, fan-out paralelo, context isolado, fan-in, checkpoint | Mapear = 1 agente read-only/tela paralelo; aplicar = sessão-limpa/tela em worktree isolada; GAP-SPEC = task auto-contida (objetivo+formato). Separa análise barata (1x) de aplicação cara | **90%** | curta |
| **Spec-anchored (sync spec↔código)** | spec+código co-evoluem, lint a cada commit, anchor verificável | `anchor-lint.mjs` (ADR 0273/0297): classifica cada US em `anchored_ok/dead/zombie/pendente/parcial`; `_pendente_`/`_parcial_` são estados de 1ª classe; **`zombie`** (path existe mas Page desligada) vai **além** do paper | **95%** | curta — **bate/supera o mercado** |
| **Decomposição de tarefa (fronteira)** | "objetivo + output format + fronteira + ferramentas" por subagente (anti-duplicação) | GAP-SPEC divide tela em PARTES (header/KPIs/filtros/lista/drawer), por parte: o quê+porquê+esforço+risco+ordem | **85%** | curta |
| **Change mgmt (ADR + changelog + commits)** | conventional commits → changelog auto; ADR linkado a PR; AgDR (decisão de agente) | ADRs Nygard append-only; conventional commits + `Refs:`; CHANGELOG por módulo; ADR 0293 = decision-register por tela | **80%** | curta-média |
| **Design tokens (fundação)** | DTCG `.tokens.json` + Style Dictionary → fonte única transformada | Tokens Tailwind nativos + mapeamento manual Cowork-CSS→Tailwind feito **na cabeça do agente** (tabela no RUNBOOK F2: `oklch(...)`→`bg-rose-500`). Sem `.tokens.json`, sem transformador | **35%** | **longa** |
| **Ponte design↔código (component map)** | Code Connect: `*.figma.tsx` 1:1 componente↔código, consumido por MCP | Mapeamento é **prosa** (tabela "Cowork CSS → Tailwind" + "termo canon → vocabulário vertical") re-derivada por tela. Sem map persistente máquina-legível; protótipo é HTML/JSX Cowork, não Figma | **30%** | **longa** |
| **Visual regression (gate)** | Chromatic/Percy: baseline aprovado por branch, gateia PR, TurboSnap | `visual-regression.yml` (Playwright próprio) + `contrato-de-tela.mjs` (zero-diff telas "ouro") + `pr-ui-judge`. Required-readiness mas parte ainda `continue-on-error`; **portão real = Wagner aprova o SCREENSHOT** (humano, não baseline automático) | **60%** | média |
| **Component workbench isolado** | Storybook: estados isolados, catálogo, base do VRT | **Inexistente.** Zero `.stories`, zero `.storybook`. Validação é a tela viva inteira em prod (smoke interativo Chrome MCP) | **10%** | **longa** |
| **Verificação/critics (loop spec→code)** | spec2code: LLM + critic/verifier iterativo (backprompting) | Pest estrutural + anti-regressão (regex useMemo/memo) + smoke interativo + Wagner. Critic é humano no fim, não verifier automático no loop | **55%** | média |

**Aderência média ponderada ~58%.** O método é **estado-da-arte no que é caro de copiar** (orquestração agêntica, spec-anchored com anchor-lint) e **fica pra trás no que é commodity comprável** (tokens, component map, Storybook, VRT por baseline).

### Onde o oimpresso JÁ supera o mercado
- **`anchor-lint` com estado `zombie`**: a literatura spec-anchored para em "anchor existe vs não existe". O oimpresso detecta "path existe mas a Page está **desligada**" (controller não-roteado / atrás de redirect 301). Isso é mais fino que o que o paper arXiv 2602.00180 descreve. Manter e divulgar.
- **Separação custo análise×aplicação + portão humano por screenshot**: a regra "não aprova por tabela, aprova por screenshot" é mais rigorosa que o gate Chromatic típico (que aprova diff). Não perder isso ao adotar VRT automático — VRT vira **pré-filtro**, screenshot-Wagner continua sendo o gate final.

---

## 3. AVALIA — o que falta (rankeado por impacto × esforço)

| # | Gap | O que roubar | Impacto | Esforço (IA-pair, ADR 0106) | Pré-req? |
|---|---|---|---|---|---|
| 1 | **Mapa design↔código volátil** (re-derivado por tela, em prosa) | Code-Connect-like: arquivo de map persistente `<Tela>.map.md`/`.json` por componente (Cowork-classe→componente React→token), versionado, consumido pela sessão de aplicação. Anti-duplicação à la Anthropic (fronteira explícita) | **alto** | ~3h | não |
| 2 | **Tokens manuais** (oklch→Tailwind na cabeça do agente) | DTCG `.tokens.json` da camada Fundações + script de transformação (não precisa Style Dictionary inteiro; um `tokens.mjs` resolve). Tira o "aprox" do RUNBOOK F2 | **alto** | ~4h | desbloqueia #3 |
| 3 | **Sem workbench isolado** (valida só tela-inteira em prod) | Storybook leve só pra `_components/` recém-nascidos (KpiCard, MercosulPlate, badges). Estado isolado = pega regressão antes de compor | **médio** | ~6h | melhor após #2 |
| 4 | **VRT é screenshot-humano + Playwright parcial** | Promover `visual-regression.yml` a baseline-gateado de fato (remover `continue-on-error` residual); usar como pré-filtro do gate-Wagner, não substituto | **médio** | ~2h | não (infra existe) |
| 5 | **Critic é só humano no fim** | Step de critic automático na FASE 4: após aplicar, um agente read-only confere GAP-SPEC×diff (achievement check) antes do screenshot. Espelha spec2code backprompting + LLM-as-judge da Anthropic | **médio** | ~2h | depende de #1 (precisa do map pra conferir) |

### Recomendação concreta

**Comece pelo #1 — mapa design↔código persistente.** Alto-impacto, baixo-esforço (~3h IA-pair), sem pré-req bloqueante, e é o gap de **maior distância** (aderência 30%) que está na **rota crítica** do método (a FASE 1 já produz a informação — falta só persistir num formato máquina-legível em vez de prosa re-derivada). Desbloqueia o critic automático (#5) e dá ao oimpresso uma versão "Code Connect sem Figma" adequada ao seu protótipo Cowork-HTML.

**Próxima ação hoje:** adicionar ao RUNBOOK-aplicar (FASE 1, saída) que cada agente de mapeamento, além do `<tela>-gap.md`, emita um `<tela>.map.json` — por PARTE: `{ cowork_source, componente_react, tokens_usados, fronteira }`. A FASE 4 (aplicação) carrega o `.map.json` junto do `-gap.md`; a FASE 5 (anchor-lint) ganha um check de que todo componente do map existe no disco (reaproveita o motor `anchored_ok/dead` que já está escrito). Custo de escrita: a estrutura do GAP-SPEC já tem 80% disso — é formalizar a coluna "Parte→Ação" num JSON.

---

## Fontes

- Figma Code Connect / Dev Mode / MCP: developers.figma.com/docs/code-connect, figma.com/blog/introducing-figma-mcp-server, figma.com/blog/design-systems-ai-mcp
- W3C DTCG (estável 2025.10): w3.org/community/design-tokens/2025/10/28/..., designtokens.org/tr/drafts/format; Style Dictionary v4: styledictionary.com/info/dtcg
- Storybook visual testing + Chromatic/Percy: storybook.js.org/docs/writing-tests/visual-testing, chromatic.com/storybook
- GitHub Spec Kit: github.com/github/spec-kit, github.blog/.../spec-driven-development-with-ai
- Spec-anchored: arxiv.org/html/2602.00180v1 (spec-first/anchored/as-source, −50% erro); spec2code: arxiv.org/pdf/2411.13269
- Anthropic multi-agent: anthropic.com/engineering/multi-agent-research-system (orchestrator-worker, fan-out/fan-in, context isolation, falhas)
- Amazon Working Backwards PR/FAQ: workingbackwards.com/concepts/working-backwards-pr-faq-process
- Change mgmt: conventional commits → changelog auto; AgDR (agent decision records): github.com/me2resh/agent-decision-record
