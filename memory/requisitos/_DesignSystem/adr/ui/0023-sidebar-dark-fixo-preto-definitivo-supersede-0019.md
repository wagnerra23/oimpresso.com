# ADR UI-0023 · Sidebar DARK-FIXO (preto) DEFINITIVO — supersede UI-0019; erradica o conflito light×black

- **Status**: accepted
- **Data**: 2026-07-16
- **Aprovado em**: 2026-07-16 — Wagner explícito, palavras textuais: *"isso é um erroque ja deveria ter sido resolvido, sidebar é como esta black então. apague os conflitos em definitivo"*
- **Decisores**: Wagner (decisão final), Claude Code (executor)
- **Categoria**: ui · shell · fundações · governança
- **Supersede**: [UI-0019](0019-sidebar-light-definitivo-supersede-0009-0014.md) — "sidebar light DEFINITIVO" (afirmação incorreta; ver Contexto)
- **Confirma superseded**: [UI-0009](0009-cockpit-sidebar-light-padrao.md) · [UI-0014](0014-sidebar-light-mantida-v2-parcial.md) — já históricas, permanecem históricas
- **Refs**:
  - **Fonte da verdade (código):** [`resources/css/cockpit.css:171-190`](../../../../../resources/css/cockpit.css) — bloco `/* Sidebar — DARK FIXO (Wagner 2026-05-05) */`, `.cockpit .sb { --sb-bg: oklch(0.18 0.006 240) }`
  - [proposals/2026-07-08-ds-direcao-design-git-e-sidebar-dark-fixa.md §D-2](../../../../decisions/proposals/2026-07-08-ds-direcao-design-git-e-sidebar-dark-fixa.md) — decisão [W] "sidebar DARK-FIXED", rascunhada e **nunca numerada** (a dívida que esta ADR paga)
  - [UI-0013](0013-constituicao-ui-v2-camadas.md) — Constituição UI v2 (camada Shell)
  - [UI-0020](0020-dark-warm-ds-v6-tokens.md) — dark WARM DS-v6 (não afetado: a sidebar é fixa, não segue o tema)

## Contexto

**A sidebar do oimpresso é preta desde 2026-05-05 e nenhuma ADR registrava isso.** O conflito durou ~2,5 meses e foi codificado como "definitivo" na direção errada.

Linha do tempo verificada em git+código:

| Data | Evento | Estado |
|---|---|---|
| 2026-05-04 | **UI-0009** — "sidebar light padrão" (Wagner: *"branca é a correta muito mais linda"*) | doc = light · código = light |
| **2026-05-05** | **Wagner reverte no código**: *"Menu Fundo Black como no cokpit"* → `cockpit.css` ganha o bloco `Sidebar — DARK FIXO`, que sobrescreve os tokens `--sb-*` **independente do `data-theme`** | doc = light · **código = preto** |
| 2026-05-24 | **UI-0014** — "light mantida (v2 parcial)" | doc segue errado |
| 2026-07-07 | **UI-0019** — "sidebar light **DEFINITIVO**", alegando medição ao vivo (*"sidebar clara"*) | doc carimba o erro como definitivo |
| 2026-07-08 | **Proposta D-2** — "sidebar é **DARK-FIXED**… supersede UI-0009/UI-0014". Fonte concordante citada: README do DS já dizia *"Sidebar is DARK-FIXED in the real cockpit (Wagner: 'menu fundo black')"* | correto, **nunca numerado** |
| 2026-07-16 | Wagner encerra: *"sidebar é como esta black então. apague os conflitos em definitivo"* | esta ADR |

**Por que a UI-0019 errou:** ela afirmou ter medido a produção como "sidebar clara" e tratou isso como canon do shell. O código contradizia a medição desde 05-05 — `.cockpit .sb` define `--sb-bg: oklch(0.18 0.006 240)` (L≈0.18 = preto) **sem qualificador de tema**. A UI-0019 tampouco citou o bloco de CSS que revertia a UI-0009, nem o README do DS. Aplica-se a **REGRA DE PRECEDÊNCIA** de [`memory/proibicoes.md`](../../../../proibicoes.md): *"o charter pode estar ERRADO e ainda é lei — 'lei' significa autoridade de intenção, não garantia de correção. Corrija o doc, não o código."* Aqui o perdedor é a ADR.

**Evidência independente (2026-07-16):** as baselines de regressão visual commitadas (`tests/.pest/snapshots/.../IsolatedStatesBaselineTest/`), decodificadas e inspecionadas, mostram sidebar **preta** em **todas** as telas do manifesto (oficina-os, clientes, sells-index, financeiro-unificado, caixa-unificada) — não é quirk de uma tela.

## Decisão

1. **A sidebar do oimpresso é PRETA (DARK-FIXO), em caráter DEFINITIVO** — nos **dois** modos (claro e escuro). Ela **não segue o `data-theme`**: os tokens `--sb-*` são fixos dentro de `.sb`. O comportamento vigente no código desde 2026-05-05 **é** o canon do shell.
2. **Esta ADR é a referência única sobre o tema.** UI-0009, UI-0014 e **UI-0019** ficam superseded (históricas, append-only — **não editar**).
3. **Sidebar dark de protótipo Cowork NÃO é divergência** — deixa de ser rejeitada. Inverte-se o item 3 da UI-0019: handoff com shell/sidebar dark está **de acordo** com o canon.
4. **A fonte da verdade do tema da sidebar é o código** (`cockpit.css` bloco `Sidebar — DARK FIXO`) + os tokens `cockpit.surface.sb-*`. Doc que afirmar "light" está errado por construção.
5. Re-abrir o tema exige **nova ADR com `supersede: [UI-0023]` + aprovação explícita do Wagner** (classe "ideia avaliada e descartada", `memory/proibicoes.md`).

## Consequências

- **13 sites de lei viva corrigidos no mesmo PR** (não são ADRs — ADR não se edita): `CLAUDE.md` §Constituição UI v2 · `.claude/skills/constituicao-ui-aware/SKILL.md` (§"Sidebar permanece light · NÃO mudar pra dark" — **era instrução ativa pra regressão**, dispara em toda edição de UI) · `.claude/skills/cockpit-runbook/{SKILL,TEMPLATE}.md` · `.claude/skills/mwart-quality/SKILL.md` · `prototipo-ui/CLAUDE_COWORK_PRIMER.md` (×2) · `_DesignSystem/README.md` · `Jana/RUNBOOK-{chat,governanca-mcp}.md` · `Jana/RUNBOOK-dashboard.md` (×2) · `Sells/RUNBOOK-create.md`.

- 🔴 **RESÍDUO HONESTO — 2 charters do Suporte seguem dizendo "sidebar light (UI-0009)"**: [`Suporte/Visao.charter.md:40`](../../../../../resources/js/Pages/Suporte/Visao.charter.md) e [`Suporte/Empresas.charter.md:38`](../../../../../resources/js/Pages/Suporte/Empresas.charter.md). **Por que não foram corrigidos:** eles são 2 dos **133 charters legados sem `related_us`** (cobertura 43,9%), protegidos por *grandfather*. Tocá-los — mesmo pra corrigir 1 linha — **acorda** o `charter-us-lint --check` (diff-aware, no-new-lie), que passa a exigir a US que a tela atende. O `memory/requisitos/Suporte/SPEC.md` **não tem nenhuma US** — não há o que declarar, e **inferir uma seria mentira** (`charter-write` é proibida de inferir; só [W] preenche). Reverter foi a saída honesta: a lápide de 2026-07-12 (`memory/proibicoes.md`) proíbe exatamente tocar legado sob gate diff-aware sem pagar a dívida que o toque acorda. **Desbloqueio:** [W] declarar a US das 2 telas de Suporte → aí o fix vira toque oportunístico legítimo.
  - ⚠️ **Lição de método:** rodar `charter-us-lint.mjs` **sem `--check`** (modo report full-tree, exit 0) **não prova nada** sobre o PR — o CI roda `--check` diff-aware. Validar no MESMO modo do CI.
- **Histórico não se toca**: ADRs superseded, handoffs, session logs e a proposta 07-08 permanecem como estão (append-only). Quem ler UI-0019 chega aqui pelo campo `Supersede`.
- **Nenhuma mudança de código** — o código já está certo. Esta ADR alinha a lei à realidade, não o contrário. Zero risco de render.
- **D-1 e D-3 da proposta 07-08 seguem NÃO ratificados** — esta ADR numera **só o D-2** (sidebar). Direção design→git e reconciliação de tokens dark continuam aguardando decisão [W] separada.
- O `--accent`/primary roxo 295 ([ADR 0190](../../../../decisions/0190-primary-button-roxo-universal-295.md)) e o dark WARM ([UI-0020](0020-dark-warm-ds-v6-tokens.md)) **não são afetados** — a sidebar é fixa e independente deles.
