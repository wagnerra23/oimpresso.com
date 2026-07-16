---
slug: 2026-07-07-arte-handoff-design-code-grade
tldr: "Estado-da-arte do handoff Design→Code agêntico 2026 COM GRADE ponderada (pesos Capterra P0/P1/P2). oimpresso lidera (82/100) porque a régua pesa governança (gate/SSOT/anti-regressão/auditoria = P0, 16 de 26 de peso) — onde somos best-in-class. Mas em FLUIDEZ pura de handoff (formato da ida + tradução estilo→código) o Claude Design nos supera claramente. Fato novo: tool DesignSync sondada empiricamente hoje (leitura+escrita) destrava o 'canal de volta' no nível de tooling → gap #1 (subir canon DS na origem) virou baixo-esforço/sem-pré-req-bloqueante."
date: "2026-07-07"
topic: "Grade com nota (0-100 ponderada) do protocolo de handoff Design→Code agêntico 2026 — oimpresso vs Claude Design+DesignSync, Figma DevMode MCP+Code Connect, Builder.io, Locofy, v0, W3C DTCG"
tipo: arte
autor: estado-da-arte (Claude Code)
tema: protocolo handoff design→código agêntico — grade comparativa
supersedes_scope: 2026-06-06-arte-claude-design-handoff (aquele = só Claude Design; este = landscape completo com grade ponderada)
---

# Estado-da-arte — handoff Design→Code agêntico 2026 · GRADE COM NOTA

> Fase 1 pesquisa limpa (sem ler memória) → Fase 2 compara com o loop oimpresso → Fase 3 grade ponderada + gaps.
> Ceticismo aplicado: **capability comprovada ≠ claim de marketing**. Notas justificadas, sem inflar a nossa.
> Amplia o dossiê [2026-06-06-arte-claude-design-handoff](2026-06-06-arte-claude-design-handoff.md) (aquele era só Claude Design; este é o landscape inteiro com **nota em grade**, pedido explícito do Felipe: *"comparou com os melhores em grade? com nota?"*).

---

## Fase 1 — PESQUISA (os melhores, mecânica real)

7 players de referência. Mecanismo concreto, não buzzword:

| Player | Quem é | Mecanismo real do handoff | Por que é referência |
|---|---|---|---|
| **Claude Design + DesignSync** (Anthropic) | design-agent do mesmo lab que o Claude Code | **bundle estruturado machine-readable** (component structure + design tokens do canvas + layout + assets) que o Code lê **sem inferir de pixels** (mesma família de modelo). Overhaul jun/2026: **import de DS** (GitHub repo/arquivos/uploads), **`/design-sync` bidirecional** (pull DS→canvas / push code→canvas), auto-correção contra o DS antes de mostrar. | "the handoff is the feature": zero translation-loss quando quem projeta e quem codifica são o mesmo modelo. |
| **Figma Dev Mode MCP + Code Connect** | padrão de mercado design→dev | **MCP server** streama variables/tokens/components/auto-layout como dados ao agente (Cursor/Claude Code); **Code Connect** liga o componente Figma ao seu `<Button/>` real (proveniência de componente). `generate_figma_design` (fev/2026) faz code→Figma frames. | maior adoção, DTCG-aware, proveniência via Code Connect. |
| **Builder.io Visual Copilot** | plugin Figma→code | pipeline de 3 passos: modelo (2M data-points) achata design→hierarquia, compilador open-source **Mitosis** gera código, LLM final adapta ao seu framework; **component mapping** aponta pros seus componentes. | melhor conversão estruturada multi-framework. |
| **Locofy Lightning** | Figma/Penpot→code | **Large Design Model (LDM)** one-click; converte Figma Styles/Variables → CSS custom properties (`:root`); Agent Mode refina por prompt sobre o código pixel-perfect. | alta fidelidade de produção em layouts complexos. |
| **v0 (Vercel)** | gerador UI agêntico | sistema **agêntico** (planeja/raciocina/executa multi-step, busca web, inspeciona sites, debuga sozinho); editor de código + diff view interno (fev/2026). Prompt/screenshot→code, não bundle de design. | workflow agêntico mais maduro (autonomia real). |
| **W3C DTCG** (`.tokens.json`) | padrão vendor-neutral | **formato de troca** de tokens em JSON (1ª versão estável out/2025): color spaces, groups/aliases, resolvers. Validação via JSON schema + Style Dictionary gera CSS/TS/Swift/Kotlin. | ~74% dos DS maduros entregam tokens; interop entre 10+ tools. **É formato, não pipeline.** |
| **oimpresso (nosso loop)** | ERP multi-tenant | PROTOCOL v2 (§10): IDA por bundle/HTML → **gate §10.4** (ancora `origin/main` fresco, append-only, anti-stale) → F3 traduz em Inertia/React → **3 canais git de volta §10.2** → CI (visual-regression + UI Judge). git=SSOT (ADR 0239). | governança formal + anti-regressão por catraca + multi-tenant Tier 0. |

**Insight de mercado (separando comprovado de marketing):**
- **Token tax / rate limits** são reais e documentados no Figma MCP: cap de Starter (6 calls/mês) atado ao *team dono do arquivo*, respostas >25k tokens estouram o context, agente **hardcoda hex/px e ignora as variables do DS** (reclamação recorrente 2026). "Hidden token tax destroying API budgets."
- **Não-determinismo** (mesmo prompt → resultado diferente) e **drift de token** (agente edita template copiado em vez de package versionado, 1 mudança propaga em tudo) são o consenso do porquê **visual-regression virou gate obrigatório** — Applitools Eyes 10.22 + Figma plugin comparam app vivo × design (fecham o loop intenção→produção).
- Claude Design **rejeita de propósito** o padrão aberto (DTCG) — "not a standards-committee compromise" — apostando no acoplamento mesmo-modelo. Ganha fluidez, paga em lock-in + spec instável ("muda antes do GA").

---

## Fase 2 — COMPARA (grade ponderada)

Lido do interno: `PROTOCOL.md` v1.1 (+§10.6 de hoje), `PROTOCOL-F3-COWORK-CODE.md` (7 sub-fases), `AUTOMACAO-LOOP-AUTONOMO.md` (estado vivo de merge), ADRs 0114/0241/0239/0282/0283/0271/0314, dossiê 2026-06-06.

### Fato novo desta sessão (evidência empírica 2026-07-07)
A tool **`DesignSync`** (pareada com skill `/design-sync`) foi sondada no harness: **leitura** (`list_projects`/`get_project`/`list_files`/`get_file` ≤256 KiB) **E escrita** (`create_project`/`finalize_plan`→`write_files`/`delete_files`, incremental, nunca wholesale) de projetos design-system do claude.ai/design. Funciona no **agente desktop com login claude.ai**; ambiente remoto exige `/design-login` interativo (indisponível → fallback "Send to Claude Code Web"). Isso **materializa** o que o dossiê 2026-06-06 tratou como "formato fantasma" e **fecha, no nível de tooling, a limitação nº1 do handoff oficial ("one-way / sem canal de volta")**. Documentado em `PROTOCOL.md §10.6` (PR #3913).

### Escala: célula = nota 0-10 (justificativa 1 linha ancorada em fonte)

| Dimensão (peso) | oimpresso | Claude Design+DesignSync | Figma DevMode MCP+CodeConnect | Builder.io Visual Copilot | Locofy Lightning | v0 (Vercel) | W3C DTCG* |
|---|---|---|---|---|---|---|---|
| **Formato da ida** (P1) | **6** — recebe IIFE/HTML + mapeia CSS→Tailwind à mão (F3.1); DesignSync só transporta | **9** — bundle machine-readable, mesmo-modelo, sem inferir pixel | **7** — MCP streama tokens/components, mas node JSON cru pesado | **8** — Mitosis + modelo 2M pts, conversão estruturada | **7** — LDM one-click, Styles/Variables→CSS vars | **5** — prompt/screenshot→code, sem bundle de design | **6** — só tokens, sem layout/componentes |
| **Canal de volta** (P1) | **9** — 3 canais git §10.2 + DesignSync write (sobe canon) | **7** — `/design-sync push` code→canvas (jun/2026), mas preview sem versão | **5** — `generate_figma_design` code→frames, rústico | **3** — quase só ida | **2** — one-way | **2** — design vive como código no v0, sem volta | **6** — JSON lido/escrito dos dois lados por natureza |
| **Gate validação / proveniência** (P0) | **9** — §10.4 ancora `origin/main` fresco + append-only + hook freshness, sem depender do humano | **4** — auto-corrige contra DS mas sem gate append-only/proveniência | **5** — Code Connect dá proveniência de componente; sem gate de drift | **4** — component mapping ~proveniência; sem governança | **3** — detecção de componente, limitado | **3** — sem gate design-específico | **6** — validação contra JSON schema + lint |
| **SSOT / versionamento** (P0) | **9** — git=SSOT (ADR 0239); prompts/bundles commitados | **3** — endpoint API, URLs expiram ~1h, preview sem versão | **5** — Figma é SoT do design (fora do git) | **4** — Figma source + runtime lock-in | **4** — Figma source | **4** — diff view interno, sem SoT de design | **8** — `tokens.json` no git, PR/Renovate |
| **Anti-regressão / drift** (P0) | **9** — visual-regression + screen-grade≥80 + critique-score + freshness + `foundation-guard`/`ds-guard` ratchet | **4** — "drift não resolvido" (claim aberto), não-determinístico | **3** — hardcoda hex/px, ignora variables (reclamação) | **4** — enforce DS mas sem catraca | **3** — nada notável | **3** — nenhum p/ design | **6** — snapshot + Style Dictionary quando usado |
| **Auto-DS / ingestão** (P1) | **6** — DS v6 curado à mão (REGISTRY + tokens oklch); DesignSync agora pode puxar/subir | **9** — ingere repo+Figma+arquivos → monta DS e auto-corrige | **6** — Code Connect manual + export DTCG | **7** — mapeia cores/tipo/spacing pros seus tokens | **6** — Styles/Variables→`:root` | **4** — genérico shadcn, fraco p/ DS próprio | **7** — é o próprio contrato de tokens |
| **Tradução estilo→código** (P1) | **6** — F3.3 pixel-diff >5% controla, mas CSS→Tailwind é manual (a sangria) | **9** — spec→código no mesmo modelo, zero inferência de pixel | **5** — infere de dados Figma, hardcoda, responsivo fraco | **8** — melhor conversão estruturada multi-framework | **7** — alta fidelidade, layouts complexos | **5** — gera UI de prompt, não de fidelidade de design | **6** — Style Dictionary, só tokens |
| **Auditabilidade** (P0) | **9** — `mcp_audit_log` + histórico git + SYNC_LOG append-only + cadeia charter | **2** — sem audit log/usage tracking/data residency (preview) | **6** — version history Figma + mapeamentos no repo | **3** — pipeline SaaS proprietário | **3** — SaaS token-based | **4** — SaaS + diff view | **6** — git, PRs assináveis |
| **Workflow agêntico** (P1) | **8** — loop autônomo ADR 0241 + agente `cowork-to-inertia` + gates CI; merge de `.tsx` fica humano (estrutural, ADR 0283) | **8** — `/team-plan` Code Kit, agent teams nativos | **7** — roda em Cursor/Claude Code via MCP; mas 3 sistemas | **6** — CLI + Cursor | **5** — Agent Mode sobre Lightning, dentro do Figma | **8** — agêntico pleno (plan/reason/execute/debug) | **3** — não é agêntico, alimenta agentes |

\* **W3C DTCG é camada de FORMATO, não pipeline ponta-a-ponta** — cobre só tokens (não layout/componentes/tradução agêntica). Entra na grade como referência da abordagem *open-standard*; a nota alta reflete alinhamento com governança, não completude de handoff.

### Nota ponderada (pesos Capterra: P0=4 · P1=2 · P2=1)

Classificação dos pesos: **P0 (crítico, Tier 0 governança)** = gate/proveniência, SSOT/versionamento, anti-regressão/drift, auditabilidade (4 dims × 4 = 16). **P1 (importante)** = formato ida, canal volta, auto-DS, tradução, workflow agêntico (5 dims × 2 = 10). Σpeso = 26 → denominador 260. Fórmula: `nota = Σ(célula × peso) / 2,6`.

| Player | Nota ponderada /100 | Perfil |
|---|---:|---|
| **oimpresso** | **82** | lidera na régua governança-pesada (gate/SSOT/anti-regressão/auditoria = best-in-class) |
| **W3C DTCG*** | **62** | forte nos P0 (SSOT/versão/gate) por ser padrão aberto git-based — mas só tokens, não pipeline |
| **Claude Design + DesignSync** | **52** | melhor formato/tradução/auto-DS; fraco em governança (audit 2, SSOT 3) |
| **Figma DevMode MCP + Code Connect** | **52** | equilibrado-moderado; proveniência via Code Connect, mas drift/hardcode |
| **Builder.io Visual Copilot** | **48** | melhor conversão estruturada; lock-in de runtime penaliza SSOT/audit |
| **Locofy Lightning** | **41** | boa fidelidade; quase só ida, sem governança |
| **v0 (Vercel)** | **40** | agêntico forte, mas não é pipeline de *design* (gera de prompt) |

**Honestidade sobre a régua (não inflar):** oimpresso lidera porque **nós escolhemos os pesos** e eles favorecem governança (16 de 26 de peso em P0, onde somos genuinamente best-in-class: git=SSOT, gate §10.4, catracas, `mcp_audit_log`). **Nas 2 dimensões de fluidez pura de handoff — formato da ida (6 vs 9) e tradução estilo→código (6 vs 9) — o Claude Design nos vence de forma clara.** É exatamente onde sangramos (mapeamento manual CSS→Tailwind). Se a régua pesasse fluidez em vez de governança, o pódio invertia nessas linhas.

---

## Fase 3 — AVALIA (gaps rankeados por impacto × esforço, 10x IA-pair ADR 0106)

| # | Gap | Impacto | Esforço (IA-pair) | Pré-req bloqueante? | Quem faz melhor / como |
|---|---|---|---|---|---|
| **1** | **Subir nosso canon DS na origem via DesignSync write** (componente já-aceito no `main` → `finalize_plan`→`write_files` incremental) — mata drift de token **na origem** em vez de só filtrar na chegada | **alto** | **~2h** (script uploader + skill wiring §10.6) | **NÃO** (DesignSync write testado hoje, funciona no desktop) | Claude Design auto-corrige contra o DS ingerido; nós subimos o canon governado pro projeto que o [CC] usa |
| **2** | **Consumir bundle estruturado no F3.1** (parser do tar tokens+layout → casa no DS v6, mata CSS→Tailwind manual) — a maior fonte de translation-loss/regressão | **alto** | ~3-5h (parser no agente `cowork-to-inertia` + mapper bundle-tokens→`tokens.css`) | **SIM** — depende do "Send to Code" real / bundle tar GA (DesignSync read entrega arquivo, mas p/ projetos design-system, não protótipo de tela clássico) | Claude Design: spec machine-readable, mesmo-modelo, zero inferência de pixel |
| **3** | **Wire do DesignSync read em F3.0/F3.1** p/ projetos design-system (puxar fresco direto, sem export→zip→commit; URLs `claudeusercontent.com` expiram ~1h) | médio | ~2h (integra `list_files`→`get_file` no fluxo, salva no git antes de agir §10.1) | parcial — só desktop (remoto bloqueado por `/design-login`) | Claude Design/DesignSync: API de leitura direta do estado atual |
| **4** | **Mapper bundle-tokens → `tokens.css` oklch com gate de drift** (token não-canon = bloqueia + sinaliza proposta, não aplica silencioso) | médio | ~2h (reusa `ds:report`/`foundation-guard`) | depende do #2 | — (nossa defesa; nenhum player tem gate append-only de token) |
| **5** | **Proveniência de componente estilo Code Connect** — hoje temos charter+REGISTRY mas não um mapping formal design-component↔code-component consultável pelo agente | baixo | ~3h | não | Figma Code Connect: mapeia 1:N design→código, compartilha com o MCP |

### Recomendação concreta
**Comece pelo #1 — alto-impacto, ~2h, sem pré-req bloqueante (DesignSync write foi sondado hoje e funciona no agente desktop).** É o único gap alto-impacto **destravado**: o #2 (o de maior impacto absoluto) depende do bundle tar real/GA. Subir o canon DS na origem via `finalize_plan`→`write_files` fecha o drift de token **antes** dele nascer, transformando o §10.6 de "documentado" em "operacional".

**Próxima ação hoje:** escrever o script uploader `canon → DesignSync` (só componente/token já-aceito no `main`; incremental via `finalize_plan` com lista explícita de paths; gated ao agente desktop) + skill/wiring que o dispara no retorno §10.2. Sobe **só o que já é canon** (subir ≠ decidir — append-only e soberania [W] preservadas). 1 PR ≤300 linhas.

---

## O que NÃO adotar (com fonte)

| Anti-adoção | Por quê | Fonte |
|---|---|---|
| **Runtime lock-in Builder.io** (`@builder.io/react`) | acopla o código gerado ao runtime deles — "unacceptable for client projects" | landscape sixtythirtyten 2026 |
| **Bundle proprietário Claude Design como SSOT** | spec não publicado, "muda antes do GA", preview sem versão/audit | claudefa.st, readysolutions |
| **Figma-as-Source-of-Truth** (design vive no Figma, não no git) | contradiz ADR 0239 (git=SSOT); + token tax/rate limits reais | ADR 0239 · Figma Forum 2026 |
| **Preço por-geração / token tax** (Locofy tokens, Figma MCP caps) | custo escala imprevisível; "hidden token tax destroying API budgets" | Design Systems Collective 2026 |
| **Auto-DS silencioso** (aplicar token que o auto-DS inventou) | drift não resolvido; "artifact that makes its own omissions look intentional" | readysolutions, victordibia |

**Regra Tier 0 que blinda tudo isso:** qualquer insumo externo (bundle, DesignSync read, snippet) entra como **proposta**, passa pelo **gate §10.4** (ancora `origin/main` fresco, append-only) e reporta pelos **3 canais §10.2** — **nunca é autoridade**. git segue SSOT (ADR 0239). DesignSync é **transporte, não autoridade**.

---

## Fontes

- [Figma — Introducing Dev Mode MCP server](https://www.figma.com/blog/introducing-figma-mcp-server/) · [Figma MCP developer docs](https://developers.figma.com/docs/figma-mcp-server/) · [rate limits & access](https://developers.figma.com/docs/figma-mcp-server/rate-limits-access/) · [Code Connect UI](https://developers.figma.com/docs/code-connect/code-connect-ui-setup/)
- [Figma MCP: CTO guide to design-to-code 2026 (Alex Bobes)](https://alexbobes.com/tech/figma-mcp-the-cto-guide-to-design-to-code-in-2026/)
- [Builder.io — Visual Copilot: better Figma-to-code](https://www.builder.io/blog/figma-to-code-visual-copilot) · [Visual Copilot CLI](https://www.builder.io/blog/visual-copilot-cli)
- [Locofy — Lightning Flow docs](https://www.locofy.ai/docs/lightning/) · [The New Stack — Locofy Large Design Model](https://thenewstack.io/locofy-launches-large-design-model-to-turn-designs-to-code/)
- [Anthropic — Introducing Claude Design](https://www.anthropic.com/news/claude-design-anthropic-labs) · [VentureBeat — Claude Design overhaul (DS imports + round-trips)](https://venturebeat.com/technology/anthropic-ships-major-claude-design-overhaul-with-design-system-imports-code-round-trips-and-a-fix-for-its-token-burning-problem) · [The New Stack — designer vs engineer disagree](https://thenewstack.io/anthropic-claude-design-overhaul/) · [Comece com Claude Design (oficial)](https://support.claude.com/pt/articles/14604416-comece-com-claude-design)
- [v0 by Vercel — docs](https://v0.app/docs) · [Vercel — Introducing the new v0](https://vercel.com/blog/introducing-the-new-v0) · [Subframe/Polymet/Onlook landscape (Roger Wong)](https://rogerwong.me/2026/03/multi-agent-design-pencil-demo)
- [W3C DTCG — spec 1ª versão estável (out/2025)](https://www.w3.org/community/design-tokens/2025/10/28/design-tokens-specification-reaches-first-stable-version/) · [zeroheight — what's new in the Design Tokens spec](https://zeroheight.com/blog/whats-new-in-the-design-tokens-spec/)
- [Design Systems Collective — hidden cost of Figma MCP roundtripping](https://www.designsystemscollective.com/the-hidden-cost-of-figma-mcp-roundtripping-and-the-sustainable-model-i-use-instead-142d8e782a51)
- [Augment Code — Visual Regression Testing for AI-generated UIs](https://www.augmentcode.com/guides/visual-regression-testing-ai-generated-uis) · [Percy — AI in Visual Testing 2026](https://percy.io/blog/ai-in-visual-testing) · [Chromatic — visual testing for Storybook](https://www.chromatic.com/storybook)
- [sixtythirtyten — Figma-to-Code AI 2026: Builder.io vs Locofy vs Anima](https://www.sixtythirtyten.co/blog/from-figma-to-code-ai-design-to-dev-workflows-in-2026)
- Interno: `prototipo-ui/PROTOCOL.md` (v1.1 +§10.6) · `PROTOCOL-F3-COWORK-CODE.md` · `AUTOMACAO-LOOP-AUTONOMO.md` · ADRs 0114/0241/0239/0282/0283/0271/0314 · dossiê [2026-06-06-arte-claude-design-handoff](2026-06-06-arte-claude-design-handoff.md)
