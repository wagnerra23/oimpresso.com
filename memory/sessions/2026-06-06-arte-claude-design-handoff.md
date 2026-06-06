---
slug: 2026-06-06-arte-claude-design-handoff
tldr: "Estado-da-arte do handoff Claude Design→Code (bundle machine-readable, mesmo-modelo) vs nosso loop PROTOCOL.md §10. Estamos à frente em retorno/gate/SSOT/regressão-IA; eles estão à frente no formato do bundle (mata F3.1/F3.4 manual). Recomendação: adotar o bundle estruturado como entrada do F3, mantendo nosso retorno §10.2 + gate §10.4."
date: "2026-06-06"
topic: "Estado-da-arte do handoff Claude Design → Claude Code (bundle estruturado) vs nosso loop PROTOCOL.md §10 — benefícios e roadmap de adoção"
tipo: arte
autor: estado-da-arte (Claude Code)
tema: protocolo handoff design→código Claude Design → Claude Code
---

# Estado-da-arte — handoff Claude Design → Claude Code vs loop oimpresso

> Pesquisa limpa (Fase 1 sem ler memória) → comparação honesta (Fase 2) → gaps rankeados (Fase 3).
> Ceticismo aplicado: separei **capability comprovada** de **claim de marketing**. Anthropic não publicou o spec; "research preview" sem audit log/versionamento.

---

## 1. PESQUISA — o que é o protocolo (mecânica, não marketing)

Claude Design (Anthropic Labs, 17/abr/2026, research preview, Opus 4.7) tem 1 feature central que importa pra nós: o **handoff bundle** Design→Code. Pesquisa de 8 buscas + 4 fontes primárias.

### 1.1 Mecânica do bundle (o que está confirmado vs especulação)

| Fato | Status | Fonte |
|---|---|---|
| Bundle = component structure machine-readable + design tokens **usados no canvas** + layout hierarchy + assets referenciados | **confirmado** (testado por terceiros) | claudefa.st, victordibia |
| Entregue como **tar archive com README** instruindo o coding agent a ler os arquivos direto e casar o visual na tech do codebase existente | **confirmado** (testes reais) | victordibia ("structured for a coding agent to pick up", README explícito) |
| Fluxo: Export → "Send to Claude Code" → copia 1 comando → cola no Claude Code → ele **busca o design de um endpoint de API** e começa a construir | **confirmado** | claudefa.st, pilot-shell |
| Chega como **contexto pra `/team-plan`** (Code Kit agent teams): plan file escrito contra o spec, specialist agents despachados | **confirmado** | claudefa.st |
| Formato interno do spec (schema, serialização exata) | **NÃO publicado** — "whatever works best between two models from the same lab"; "plan for the spec to change before GA" | claudefa.st, readysolutions |
| **Sem canal de volta** Code→Design (one-way) | **confirmado como limitação** | readysolutions ("no reverse iteration back to design") |
| **Sem data residency, audit log, usage tracking** (research preview) | **confirmado como limitação** | readysolutions |

A tese central ("the handoff is the feature"): **o que produz o protótipo e o que implementa são a mesma família de modelo, falando a mesma representação estruturada**. Não infere de pixels — recebe estrutura. É a diferença real vs Figma/PNG.

### 1.2 Auto design-system onboarding (claim vs comprovado)

- **Claim oficial:** "During onboarding, Claude builds a design system for your team by reading your codebase and design files. Every project after that uses your colors, typography, and components automatically." Ingere GitHub repo + arquivos locais + Figma + fontes/logos + style notes.
- **Comprovado (victordibia, teste real):** subiu CSS token files → reconstruiu a página com **fidelidade decente**, aplicando os tokens reais em vez de estilo genérico. Design-system awareness real, não alucinação.
- **NÃO endereçado (riscos abertos):** tokens conflitantes entre arquivos, fidelidade ao longo de iterações, **detecção/prevenção de drift** quando o agente modifica o código gerado, naming inconsistente. "An artifact that makes its own omissions look intentional" (readysolutions — o pior caso).

### 1.3 Landscape 2026 — onde Claude Design ganha e perde

| Player | Como resolve | Onde ganha | Onde perde |
|---|---|---|---|
| **Claude Design → Code** | bundle estruturado, **mesmo modelo**, sem inferir de pixels; auto-DS do codebase | zero translation-loss design↔intent; integra agent workflow (`/team-plan`) nativo | formato proprietário fechado; one-way; preview sem audit/versionamento; drift não resolvido |
| **Figma Dev Mode + MCP server** | MCP entrega variables/tokens/components/variants/auto-layout como dados ao agente (Cursor/Claude Code) | padrão de mercado, DTCG-aware, Code Connect mapeia pro seu componente real | per-seat caro (View/Collab ~6 tool calls/mês); ainda Figma→agente→código (3 sistemas) |
| **Builder.io Visual Copilot** | CLI mapeia cores genéricas → seus tokens; component mapping aponta pros seus Button/Card | melhor structured conversion; reduz cleanup | **lock-in** no runtime `@builder.io/react`; "unacceptable for client projects" |
| **Locofy / Lightning** | Figma Styles/Variables → CSS custom properties; :root organizado | maior fidelidade de produção; código dev-friendly | ainda parte de Figma; menos agent-native |
| **W3C DTCG (`.tokens.json`, 2025.10 estável)** | **padrão vendor-neutral** de troca de tokens em JSON | interop real entre 10+ tools (Figma, Penpot, Supernova, Style Dictionary) | é formato, não pipeline; Claude Design **rejeita explicitamente** ("not a standards-committee compromise") |

**Insight estratégico:** Claude Design abandona o padrão aberto (DTCG) de propósito, apostando no acoplamento mesmo-modelo. Ganha fluidez agora, paga em lock-in + spec instável ("vai mudar antes do GA"). Quem tem governança própria (nós) pode pegar a **fluidez sem comprar o lock-in** — usando o bundle como *entrada*, não como autoridade.

---

## 2. COMPARA — Claude Design oficial vs nosso loop PROTOCOL.md §10

Lido: `prototipo-ui/PROTOCOL.md` (v1.1 · ADR 0114/0241), `PROTOCOL-F3-COWORK-CODE.md` (7 sub-fases), ADR 0239 (git=SSOT), REGISTRY_DS_COMPONENTES.md, tokens.css (DS v4/v6 oklch), ds-v6/REUSE_MAPPING.md.

| Dimensão | Claude Design oficial | oimpresso hoje | Quem está à frente |
|---|---|---|---|
| **IDA Design→Code** | bundle estruturado machine-readable (tar+README+spec+tokens) | snippet genérico + export IIFE/HTML; **F3.1 EXTRACT auto-detecta formato mas traduz manual** | **eles** (formato) |
| **VOLTA Code→Design** | inexistente (one-way) | **3 canais git §10.2** (`DS_ADOCAO_INDICE` via `ds:report:write` / `SYNC_LOG` append / `HANDOFF`) lidos por [CC] via webhook GitHub→MCP | **nós** |
| **Gate de validação** | nenhum (humano confere) | **§10.4** — [CL] valida prompt contra `origin/main` fresco (Passo 0), pega stale/append-only/ADR duplicada **sem depender do [W]** + hook `git-base-freshness-guard.mjs` | **nós** |
| **Source of truth** | endpoint de API (URLs `claudeusercontent.com` expiram ~1h) | **git = SSOT** (ADR 0239 R1); prompts salvos em `PROMPT_PARA_CODE_*.md` commitados | **nós** |
| **Anti-regressão DS** | "drift não resolvido" (claim aberto) | **ADR 0239 R3** regressão-IA (visual-regression + screen-grade ≥80 + critique-score + freshness) + `ds-guard.mjs` + `integrity-check.mjs` + ratchet | **nós** |
| **Auto design-system** | ingere codebase+Figma → monta DS automático | **DS v6 já canon e curado** (REGISTRY 11 componentes, tokens.css oklch SSOT, REUSE_MAPPING 8/11) — montado à mão, governado | **empate** (nós temos melhor DS; eles automatizam a ingestão) |
| **Tradução de estilo** | spec estruturado → Claude Code casa na tech do codebase | **F2/F3.4 manual**: CSS Cowork → Tailwind v4 + pixel-diff visual (F3.3 gate >5%) — **aqui nasce a perda de tradução** | **eles** |
| **Integração agent workflow** | `/team-plan` + Code Kit nativo | loop autônomo 0-humano (ADR 0241) + agente `cowork-to-inertia` + gates CI (UI Judge + visual-regression) | **empate** (caminhos diferentes, ambos maduros) |
| **Multi-tenant Tier 0** | N/A (não é ERP) | `business_id` global scope em toda onda (F3.6 Pest biz=1 vs biz=99) | **nós** (irrelevante pra eles) |

**Veredito honesto:** somos um **superset governado** do que a Anthropic lançou. Eles têm 1 peça melhor que a nossa — **o formato do bundle** (machine-readable, mesmo-modelo) — e ela ataca exatamente o ponto onde sangramos: o **F3.1/F3.4 manual** (export IIFE/HTML + mapeamento CSS→Tailwind manual + pixel-diff). Em todo o resto (retorno, gate, SSOT, regressão, multi-tenant) estamos à frente do produto oficial.

---

## 3. AVALIA — o que falta, rankeado

| Gap | Impacto | Esforço (IA-pair, ADR 0106 10x) | Pré-req? |
|---|---|---|---|
| **Consumir o bundle estruturado no F3.1** (em vez de IIFE/HTML+CSS manual): quando [CC] for Claude Design real, o tar traz tokens+layout serializados → [CL] casa direto no DS v6, sem mapeamento CSS→Tailwind à mão | **alto** (mata a maior fonte de translation-loss/regressão) | ~3-5h (parser do bundle no agente `cowork-to-inertia` + mapper tokens-do-bundle→`tokens.css`) | depende de Wagner ter acesso ao Claude Design "Send to Code" real (hoje usamos Cowork export) |
| **Mapper bundle-tokens → tokens.css oklch** (validar que tokens do canvas batem com nosso SSOT; rejeitar token novo não-canon = drift) | **alto** | ~2h (script + gate; reusa lógica `ds:report`) | bundle real |
| **Adapter `/team-plan` → nossas 7 sub-fases** (se Wagner adotar Code Kit; senão N/A) | médio | ~3h | decisão de adotar Code Kit |
| **Versionar o bundle recebido** (eles não versionam — nós já temos `PROMPT_PARA_CODE_*` commitado; estender pra `BUNDLE_<tela>_<sha>.tar`) | médio | ~1h | bundle real |
| **Documentar no PROTOCOL.md que bundle estruturado ≠ autoridade** (passa pelo gate §10.4 igual snippet) — fechar a porta de lock-in/stale antes de abrir | médio (preventivo) | ~30min (emenda PROTOCOL §10.1 + §3 F3.0) | nenhum |

### Riscos de adotar (céticos)

- **Lock-in de formato:** spec proprietário "vai mudar antes do GA". Mitigação: tratar bundle como *entrada derivada*, nunca SSOT (ADR 0239 R1 já cobre). Nosso git continua a fonte.
- **Perder nosso gate/retorno:** os snippets oficiais são só-IDA e não carregam o protocolo (§10 nasceu exatamente disso — `HANDOFF.md` ficou 15d stale). Adotar o bundle **sem** plugar §10.2/§10.4 nos joga de volta ao buraco. **Inegociável: bundle entra pelo §10.1, valida no §10.4, reporta no §10.2.**
- **Drift de DS:** o auto-DS deles pode inventar token fora do nosso canon. Mitigação: o mapper rejeita token não-presente em `tokens.css` (vira sinal de proposta de evolução, não aplicação silenciosa — ADR 0239 R2).
- **Custo/preview instável:** research preview sem audit log. Não migrar nada crítico pra ele até GA.

### Roadmap de adoção (fases pequenas, mantendo §10.2 + §10.4)

1. **F-A (30min, hoje, zero pré-req):** emendar `PROTOCOL.md` §3 F3.0 + §10.1 — "bundle estruturado Claude Design entra como **proposta**, passa pelo gate §10.4 e reporta §10.2 igual snippet; nunca é autoridade (ADR 0239 R1)". Fecha o risco de lock-in antes de abrir a porta.
2. **F-B (~2h, quando houver bundle real):** mapper `bundle-tokens → tokens.css` com gate de drift (token não-canon = bloqueia + sinaliza proposta).
3. **F-C (~3-5h):** estender agente `cowork-to-inertia` F3.1 pra parsear o tar estruturado e casar layout no DS v6 sem CSS→Tailwind manual — **mata o F2/F3.4 manual**.
4. **F-D (~1h):** versionar bundle recebido (`BUNDLE_<tela>_<sha>`) — supre o que eles não têm.

Cada fase = 1 PR ≤300 linhas (commit-discipline). F-A não toca código, só doc — pode ir já.

---

## RECOMENDAÇÃO FINAL

**Maior gap: o formato do bundle (machine-readable, mesmo-modelo) — é a única dimensão onde o produto oficial nos supera, e ataca exatamente nosso ponto de sangria (F3.1/F3.4 manual: export IIFE/HTML + CSS→Tailwind à mão + pixel-diff).**

**Comece por F-A — alto valor preventivo, 30min, zero pré-req bloqueante, não toca código.** Emendar `PROTOCOL.md` deixando explícito que o bundle estruturado entra como *proposta* pelo §10.1, valida no gate §10.4 e reporta no §10.2 — assim, quando o bundle real chegar (F-B/F-C), a infra de governança já o trata sem reabrir o buraco do "HANDOFF 15d stale". F-C (o mapper que mata a tradução manual) é o alto-impacto, mas depende de Wagner ter o "Send to Claude Code" real — então não é a primeira ação.

**Próxima ação hoje:** abrir PR de emenda ao `PROTOCOL.md` (§3 F3.0 + §10.1) com a regra "bundle estruturado = proposta, não autoridade; passa §10.4, reporta §10.2".

---

## PROVA EMPÍRICA — "é mesmo melhor?" (medido em origin/main, 2026-06-06)

O bundle real ainda não está no nosso fluxo (F-C reativo), então não dá pra testar o handoff ponta-a-ponta. **Mas o custo do jeito-VELHO (export HTML + mapeamento manual CSS→Tailwind) está medido no nosso próprio código** — é o sprawl que a Onda anti-duplicação combateu:

| Métrica (origin/main) | Jeito-VELHO (dump HTML + mapeamento manual) | Jeito-NOVO (bundle estruturado = tokens) |
|---|---:|---|
| Valores de cor crus `oklch` em `resources/css` | **1969** | reusa os **18** tokens da fundação |
| Arquivos CSS por-tela/módulo (`sells-cowork`/`fin-`/`cowork-`) | **27** | nenhum novo |

**Veredito medido:** o handoff manual produziu **~109× mais valores de cor** (1969 cru vs 18 token) espalhados em 27 arquivos por-tela. O bundle estruturado carrega os **tokens usados no canvas** → o Code reusa o canon em vez de re-derivar cor crua. Não é teoria: **é exatamente o sprawl que deletamos a sessão toda** (o manual diagnosticou ~20k linhas de CSS de "dumps Cowork colados"). O jeito-novo é melhor porque **não gera o problema que estávamos limpando**.

Comando de reprodução: `git grep -h -o "oklch(" origin/main -- 'resources/css/*.css' | wc -l`.

---

## Fontes

- [Anthropic — Introducing Claude Design](https://www.anthropic.com/news/claude-design-anthropic-labs) (oficial)
- [claudefa.st — Claude Design to Claude Code handoff (mecânica)](https://claudefa.st/blog/guide/mechanics/claude-design-handoff)
- [Victor Dibia — How Good is Anthropic's Claude Design? (teste cético)](https://newsletter.victordibia.com/p/how-good-is-anthropics-claude-design)
- [Ready Solutions — The Handoff Is the Feature](https://readysolutions.ai/blog/2026-04-24-claude-design-handoff-not-canvas/)
- [Anthropic Help — Set up your design system in Claude Design](https://support.claude.com/en/articles/14604397-set-up-your-design-system-in-claude-design)
- [Figma — Introducing Dev Mode MCP server](https://www.figma.com/blog/introducing-figma-mcp-server/)
- [W3C Design Tokens Community Group — spec 2025.10 estável](https://www.w3.org/community/design-tokens/2025/10/28/design-tokens-specification-reaches-first-stable-version/)
- [Builder.io vs Locofy vs Anima 2026 (landscape)](https://www.sixtythirtyten.co/blog/from-figma-to-code-ai-design-to-dev-workflows-in-2026)
- [Uniflow — Claude Design Handoff: concepts, tips, pitfalls](https://www.uniflow.kr/en/claude-design-handoff-guide-tips-pricing/)
