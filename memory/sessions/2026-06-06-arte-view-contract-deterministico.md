---
date: '2026-06-06'
topic: "Contrato de view determinístico por-tela — charter (intenção) + design-spec DERIVADO (estrutura, machine-checkable). Consolidação dos 4 artefatos por-tela + mapa de conflitos + rascunho ADR."
type: session
authors: [W, C]
slug: arte-view-contract-deterministico
tldr: "Maior gap: nenhum dos 4 artefatos por-tela é determinístico-por-tela — todos prosa/LLM-judge. Recomendação: gerar <Tela>.design-spec.json DERIVADO da .tsx (reusa reuse-index + foundation-guard), começar pela tela-ouro Sells/Create. Charter fica (LLM-judge ok); visual-comparison/review/scorecard rebaixam a views advisory."
status: proposta
owner: W
related_adrs:
  - 0239-governanca-design-system-git-ssot-regressao-ia
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
---

# Contrato de view determinístico por-tela — dossiê estado-da-arte

> Pedido Wagner (2026-06-06): "pesquise para referenciar tudo, pesquise a integração, consolide e rebaixe todos os conflitos — isso é evolução determinística."
> Princípio Tier-0 que rege tudo abaixo: **derivado > escrito** (ADR 0239 git=SSOT; lição reuse-index.mjs). Artefato escrito à mão apodrece.

---

## Seção 1 — PESQUISA (estado-da-arte 2025-2026, fontes primárias)

Pesquisa feita **antes** de abrir memory/ (limpa, sem contaminar com "como nós fazemos").

| # | Referência | Mecanismo concreto | Por que é referência |
|---|---|---|---|
| 1 | **DTCG — Design Tokens Format Module 2025.10** (W3C Community Group) | JSON vendor-neutral (`application/design-tokens+json`, `.tokens.json`); theming, color spaces modernos, cross-tool. **Primeira versão ESTÁVEL** (28-out-2025). | Standard real, 20+ editores, 10+ ferramentas (Figma/Penpot/Sketch/zeroheight). Token = contrato interoperável, não convenção. |
| 2 | **Figma Code Connect** (Config 2025, GA) | "Describe how design *equals* code, não gera." Mapeia componente↔código 1:1, variants determinísticos, binding de estado. "Check designs linter": casa valor cru ↔ variável; lint custom força confirmar Code Connect em arquivo modificado. | Padrão de mercado pra ligar design-system-no-design ao código real. Anti-"código autogerado genérico". |
| 3 | **Fiberplane `drift`** (documentation linter, AST-anchored) | Frontmatter ancora spec.md a código: `path` + `#Symbol` opcional + `@<git-sha>` provenance. Hash de **AST normalizado** (node kinds + token text, sem whitespace/posição) via tree-sitter. `drift check` em CI: código mudou pós-provenance → exit≠0, força re-link. | **A peça anti-rot exata.** Símbolo-level = resto do arquivo muda livre. É o freshness-gate que o Wagner descreveu, já existindo lá fora. |
| 4 | **Spec-Driven Development w/ AI** (arXiv 2602.00180, 2026) | 3 níveis de maturidade: **spec-anchored** (specs evoluem c/ código, *testes forçam alinhamento*) → spec-as-source (código regenerado do spec, edição manual proibida). Rot resolvido por "se divergir, teste falha". | Formaliza o trade-off. Nível prático = spec-anchored (= o que propomos). Nível rigoroso = spec-as-source (= alvo do bundle Claude Design). |
| 5 | **Storybook v9 + Storybook MCP** (2025) | Stories como casos de teste de componente; MCP expõe metadata/props/stories machine-readable p/ agente reusar DS e auto-corrigir contra testes. | Story-como-contrato é o padrão dominante p/ contrato de componente. (No oimpresso não há Storybook — code-first; relevante como *alvo* futuro, não dependência.) |
| 6 | **LLM-as-judge vs deterministic gate** (Braintrust/Galileo, 2025-2026) | Híbrido é best-practice: gate determinístico p/ formato/estrutura/presença (<10ms, free, auditável, brittle); LLM-judge p/ qualidade subjetiva (tom, intenção) — probabilístico, flaky em single-call; mitiga com 3-5 juízes voto-maioria. | Justifica manter os DOIS: charter→LLM-judge (intenção), design-spec→gate (estrutura). Não é "trocar um pelo outro". |

**Convergência:** todos apontam pro mesmo eixo — **o código é o SSOT; o contrato testável é DERIVADO do código** (Code Connect, drift, spec-anchored, SSOT design-system). Escrever spec à mão = anti-padrão unânime. Tokens viram contrato JSON padronizado (DTCG). Híbrido determinístico+LLM é consenso, não escolha.

Fontes:
- [DTCG 2025.10 stable](https://www.w3.org/community/design-tokens/2025/10/28/design-tokens-specification-reaches-first-stable-version/) · [format module](https://www.designtokens.org/tr/2025.10/format/)
- [Figma Code Connect](https://github.com/figma/code-connect) · [Schema 2025 recap](https://www.figma.com/blog/schema-2025-design-systems-recap/)
- [Fiberplane drift](https://fiberplane.com/blog/drift-documentation-linter/)
- [Spec-Driven Development arXiv 2602.00180](https://arxiv.org/html/2602.00180v1)
- [Storybook MCP](https://storybook.js.org/blog/storybook-mcp-sneak-peek/) · [Storybook v9 InfoQ](https://www.infoq.com/news/2025/07/storybook-v9-released/)
- [Braintrust: LLM-as-judge vs deterministic](https://www.braintrust.dev/articles/what-is-llm-as-a-judge) · [Galileo](https://galileo.ai/blog/why-llm-as-a-judge-fails)

---

## Seção 2 — COMPARA (estado-da-arte × oimpresso hoje)

Levantado no main tree (worktrees excluídos): **143** `.charter.md` · **68** `<tela>-visual-comparison.md` · **157** `<Tela>.review.md` · **1** scorecard JSON (222 telas, screen-grade LLM-judge). Gates determinísticos já existem mas são **globais**, não por-tela: `reuse-index.mjs` (símbolos JS+PHP derivados — sabe o que uma .tsx importa), `foundation-guard.mjs` (def de token só na allowlist, ratchet só-desce), `conformance-gate.mjs`, `pageheader-gate`, `css-size-gate`, `jscpd`.

| Dimensão | Estado-da-arte | oimpresso hoje | Distância |
|---|---|---|---|
| Token como contrato testável | DTCG JSON estável, machine-checkable por-tela | foundation-guard trava DEF de token (global, ratchet); não há "quais tokens ESTA tela usa" como contrato | **média** — extração existe, falta projeção por-tela |
| Contrato de componente/composição por-tela | Code Connect 1:1, Storybook stories | reuse-index já extrai símbolos+imports da .tsx (derivado); não emite spec por-tela nem testa "bate o esperado" | **curta** — substrato pronto, falta o artefato+teste |
| Spec DERIVADO do código (anti-rot) | drift: AST-fingerprint + `@git-sha` provenance | reuse-index é derivado/regenerado (impossível apodrecer) MAS os 4 artefatos por-tela são **prosa escrita à mão** → apodrecem | **longa** nos 4 artefatos; **curta** na infra |
| Freshness gate (spec vs código) | `drift check` em CI, exit≠0 | foundation-guard tem ratchet só-desce (padrão espelhável); nenhum freshness por-tela | **média** |
| Determinístico vs LLM-judge | híbrido: gate p/ estrutura, judge p/ intenção | **invertido pro lado errado**: estrutura por-tela é julgada por LLM (screen-grade, review, visual-comparison). Só global é determinístico | **longa** — é o gap central |
| Intenção/qualidade subjetiva | LLM-judge calibrado, multi-juiz | charter + screen-grade fazem isso bem; Peso Real (ROI→R$) supera SOTA | oimpresso **à frente** |
| SSOT / anti-duplicação de docs | code repo canônico, docs derivadas | **4 artefatos sobrepostos por tela**, todos prosa, fontes independentes → exatamente o anti-padrão que SSOT condena | **longa** |

**Honesto:** o oimpresso **já bate o mercado** em ponderação por receita (Peso Real — nenhum dos 6 SOTA pondera ROI) e na infra de extração derivada (reuse-index/foundation-guard são genuinamente estado-da-arte, code-first sem Figma). O gap **não é conceitual nem de ferramenta** — é que os 4 contratos POR-TELA ficaram do lado prosa/LLM-judge quando metade deles é estrutura pura, derivável e determinizável. A peça que falta (design-spec derivado por-tela) é uma **projeção por-tela de máquinas que já rodam**, não algo novo.

### Mapa de conflitos dos 4 artefatos por-tela

| Artefato | O que cobre | Prosa ou derivável? | Overlap com | Veredito |
|---|---|---|---|---|
| **`.charter.md`** (143) | INTENÇÃO: Mission/Goals/Non-Goals/UX targets/Anti-hooks | Prosa — **e deve ser** (intenção não se deriva) | baixo overlap estrutural; é a única peça legitimamente subjetiva | **MANTÉM canônico.** LLM-judge ok. Vira 1 dos 2 pilares. |
| **`<tela>-visual-comparison.md`** (68) | 15 dimensões visuais (cor/espaço/tipo/layout vs golden) | ~70% derivável (tokens, espaçamento, componentes) · ~30% prosa (percepção) | alto c/ design-spec (estrutura) + c/ review.md | **VIRA VIEW DERIVADA** do design-spec p/ a parte estrutural; 30% subjetivo rebaixa a advisory dentro do screen-grade. Supersede como artefato escrito autônomo. |
| **`<Tela>.review.md`** (157) | design review por tela (gerador `design:review`) | ~60% derivável; é re-narração do que dá pra checar | alto c/ visual-comparison + scorecard | **REBAIXA a advisory/efêmero.** Não é fonte; é relatório gerado. Não deve ser 157 arquivos versionados que apodrecem. Regenerar on-demand. |
| **screen-grade scorecard** (222, LLM-judge) | nota QA 16-dim por tela, Peso Real | misto: ~half determinizável, ~half subjetivo | overlap c/ todos acima | **MANTÉM como camada de QUALIDADE** (LLM-judge legítimo p/ 16-dim), MAS as dims estruturais passam a CONSUMIR o design-spec determinístico (deixa de re-julgar o que a máquina já sabe). |

**Resolução de cada overlap (explícita):**
- visual-comparison ∩ design-spec → estrutura migra pro design-spec (fonte única, determinística); visual-comparison deixa de existir como .md autônomo.
- review.md ∩ tudo → review.md vira **saída gerada, não fonte**. Some do git como artefato permanente por-tela.
- scorecard ∩ design-spec → scorecard **importa** o veredito determinístico nas dims estruturais (ex: "usa só tokens allowlist?" vira pass/fail, não nota-de-LLM); LLM-judge fica só nas dims de gosto/intenção.
- charter ∩ resto → zero conflito; charter é o pilar intenção, design-spec é o pilar estrutura. Ortogonais por desenho.

---

## Seção 3 — AVALIA (o que falta, rankeado)

| Gap | Impacto | Esforço (IA-pair, ADR 0106 10x) | Pré-req bloqueante? |
|---|---|---|---|
| **`<Tela>.design-spec.json` derivado da .tsx** (tokens+componentes+layout) | **alto** — fecha o gap central, dá contrato determinístico por-tela | ~3-4h (reusa extração reuse-index + foundation-guard; é projeção por-tela) | **Não.** Substrato pronto. |
| **Teste por-tela "esta .tsx bate seu design-spec?"** | alto — torna o contrato executável (spec-anchored) | ~2h (espelha ratchet foundation-guard) | Depende do design-spec acima |
| **Freshness gate** (provenance `@git-sha` estilo drift) | médio — anti-rot se alguém editar spec à mão | ~1-2h (drift é só-leitura git+AST; reusa walker reuse-index) | Depende do design-spec |
| **Rebaixar review.md (157) a gerado on-demand** | médio — mata 157 arquivos que apodrecem | ~1h (deprecar gerador como fonte) | Não, mas faz depois do design-spec provar valor |
| **scorecard consome design-spec nas dims estruturais** | médio — para de re-julgar com LLM o que a máquina sabe | ~2h | Depende do design-spec |
| **Adotar formato DTCG p/ a parte token do spec** | baixo (futuro) — interop se um dia entrar Figma/Storybook | ~1h | Não bloqueante; nice-to-have |

**Risco multi-tenant (Tier 0, ADR 0093):** o design-spec descreve ESTRUTURA de UI (tokens/componentes/layout) — não toca `business_id`, não carrega dado de tenant. **Sem superfície de vazamento.** Se algum dia o spec capturar dado de exemplo/fixture, PII real é proibida (anonimizar). Por ora: P-zero de risco de tenant.

**Riscos do próprio modelo:**
- **Rot** → mitigado por desenho: spec é DERIVADO (regenerado da .tsx), nunca escrito. Freshness gate é cinto-e-suspensório.
- **Fragmentação** → o objetivo É des-fragmentar (4→2). Risco invertido: o perigo é parar no meio e ter 6 artefatos. Mitigação: rebaixar review/visual-comparison no MESMO PR que prova o design-spec.
- **Custo de migrar 143 telas** → **não migra à mão.** Gerador roda em batch sobre as .tsx existentes. Migração ≈ rodar o script. Esse é o ponto do "derivado".

---

## Recomendação final

**Comece pelo `<Tela>.design-spec.json` derivado — alto-impacto, baixo-esforço, sem pré-req bloqueante.** O substrato (reuse-index extrai símbolos+imports da .tsx; foundation-guard sabe tokens válidos) já roda em CI. O design-spec é a **projeção por-tela** dessas máquinas — não código novo de fundação.

**Próxima ação hoje:** prototipar o gerador numa única tela — a **tela-ouro `Sells/Create`** (A+ 9,75, já é golden-reference). Emitir `Sells/Create.design-spec.json` com 3 chaves: `tokens` (cores/espaços que a .tsx consome, cruzados c/ foundation-guard allowlist), `components` (símbolos DS importados, via reuse-index) e `layout` (primitivos `Components/layout` ADR 0253 usados). Validar que o JSON bate a tela manualmente UMA vez. Isso prova o modelo antes de escalar pras 143.

**Rascunho de ADR (PROPOSTA — Tier-0, Wagner aprova):**

> **ADR XXXX — Contrato de view determinístico por-tela: charter (intenção) + design-spec derivado (estrutura)**
> **Contexto:** 4 artefatos por-tela (charter 143, visual-comparison 68, review 157, scorecard 222) sobrepostos, todos prosa/LLM-judge. Metade do conteúdo é estrutura pura (tokens/componentes/layout) — derivável e determinizável. Estado-da-arte (DTCG, Code Connect, drift, spec-anchored) converge em: código=SSOT, contrato testável DERIVADO. Gates determinísticos do oimpresso são globais, não por-tela. Princípio interno derivado>escrito (ADR 0239).
> **Decisão:** Adotar 2 contratos canônicos por-tela: (1) `charter.md` — INTENÇÃO, prosa, LLM-judge; (2) `<Tela>.design-spec.json` — ESTRUTURA, DERIVADO da .tsx (reusa reuse-index + foundation-guard), machine-checkable. Teste por-tela spec-anchored + freshness gate estilo drift (provenance `@git-sha`). visual-comparison e review rebaixados a views derivadas/advisory geradas on-demand; scorecard consome o veredito determinístico nas dims estruturais e mantém LLM-judge só nas subjetivas.
> **Consequências:** (+) 4→2 artefatos; determinismo por-tela; anti-rot por construção; LLM-judge só onde é insubstituível. (−) gerador a manter; migração das 143 via batch (não manual); review.md deixa de ser fonte versionada. Tier-0 multi-tenant: design-spec é estrutura de UI, sem `business_id`, sem PII — zero superfície de vazamento.
> **Status:** PROPOSTA. Supersede nada formalmente até Wagner aprovar; complementa UI-0013 (camadas) e 0239 (git=SSOT).
