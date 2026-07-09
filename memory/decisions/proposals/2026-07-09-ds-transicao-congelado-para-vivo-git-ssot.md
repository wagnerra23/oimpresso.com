# PROPOSTA · Transição DS v6 congelado → projeto vivo no claude.ai/design (git permanece SSOT)

> **Status:** PROPOSTA. **NÃO é lei, NÃO é ADR numerado.** [CL] rascunha; **[W] decide, numera e aceita** (append-only — Constituição v2 [ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md); soberania [ADR 0238](../0238-soberania-constituicao-wagner.md)).
> **Irmã de:** [`2026-07-08-profissionalizar-ds-sync-git-espelho.md`](2026-07-08-profissionalizar-ds-sync-git-espelho.md) (FASE 1, canvas) + [`2026-07-08-ds-direcao-design-git-e-sidebar-dark-fixa.md`](2026-07-08-ds-direcao-design-git-e-sidebar-dark-fixa.md) (FASE 2, D-1 direção).
> **Build sobre / emenda:** [ADR 0239](../0239-governanca-design-system-git-ssot-regressao-ia.md) (git SSOT) · [ADR 0249](../0249-ds-v6-naming-amends-0235.md) (DS v6 naming) · [ADR 0300](../0300-errata-0239-nome-real-fonte-design-system.md) (errata fonte) · [ADR 0315](../0315-design-sync-claude-design-vs-cowork-charter.md) (espelho ≠ fonte — **emenda: pull design→git controlado**) · [ADR 0325](../0325-import-prototipo-designsync-pull-direto.md) (import DesignSync) · [ADR 0281](../0281-dark-mode-bridge-data-theme-tokens.md) (bridge dark).

---

## Contexto

O Design System nasceu como **DS v6** — um snapshot **congelado** de tokens/componentes (`prototipo-ui/cowork/ds-v6`, jun/2026, [ADR 0249](../0249-ds-v6-naming-amends-0235.md)). A [ADR 0239](../0239-governanca-design-system-git-ssot-regressao-ia.md) fixou **git = SSOT**: tokens DTCG JSON (`resources/css/tokens/{base,semantic}.tokens.json`) compilados por Style Dictionary em `_generated-*.css`.

Em paralelo, o projeto **"Office Impresso — Design System"** no claude.ai/design (`019dd02f-…`) deixou de ser export estático e virou a **superfície viva de autoria** — onde o Wagner desenha. A [ADR 0315](../0315-design-sync-claude-design-vs-cowork-charter.md) o classificou como **espelho derivado** (não fonte); a [ADR 0325](../0325-import-prototipo-designsync-pull-direto.md) abriu o import direto via `DesignSync`.

**O problema que forçou esta proposta** (investigação 2026-07-08): o canvas dark do cockpit divergiu em **3 cópias** (git `0.165/hue 282`, snapshot jun `0.205/282`, espelho `0.26/hue 240`). Causa-raiz não foi "cor errada" — foi **ausência de loop de sincronização** entre git (SSOT) e espelho vivo. Alguém editou o canvas no claude.ai/design e o git seguiu no valor dele: **drift silencioso**, o vetor que a 0239 existe pra prevenir. A FASE 1 (reconciliar por imagem) foi resolvida em #3981/#3982/#3983; falta **institucionalizar o loop**.

## Decisão proposta

1. **O projeto no claude.ai/design é um ESPELHO VIVO, não um snapshot congelado nem fonte concorrente.** O DS v6 "congelado" (`prototipo-ui/cowork/ds-v6`) permanece como **referência histórica** (o retrato de jun/2026), não o sistema vigente. O sistema vigente é o par **git (SSOT) ↔ espelho vivo (vitrine/autoria)**.

2. **git permanece o SSOT** ([ADR 0239](../0239-governanca-design-system-git-ssot-regressao-ia.md), reafirmada). O valor canônico de qualquer token **nasce no git** (`semantic.tokens.json` → `npm run tokens:build` → `_generated-*.css`), é validado pelo CI e usado no deploy. O espelho é atualizado **depois**.

3. **Loop bidirecional determinístico e incremental**, em dois runbooks companheiros:
   - **PULL (design → git)** — [`design-sync-pull.md`](../../../.claude/runbooks/design-sync-pull.md): traz o que o Wagner desenhou, **com diff (`ds-token-diff.mjs`) + triagem obrigatória** (o espelho não está uniformemente à frente — em 2026-07-08, 19 de 28 divergências eram design VELHO; pull cego teria regredido o git). **Emenda a [ADR 0315](../0315-design-sync-claude-design-vs-cowork-charter.md)** (que antes só previa git→design). *Consolida a D-1 da proposta-irmã 2026-07-08-ds-direcao — um único ADR pra a emenda 0315.*
   - **PUSH (git → design)** — [`design-sync-push.md`](../../../.claude/runbooks/design-sync-push.md): re-espelha o token já canonizado no git, incremental (`finalize_plan`/`write_files`, nunca replace atacado), fechando o loop.

4. **Sentinela de drift** ([`ds-mirror-drift.mjs`](../../../scripts/governance/ds-mirror-drift.mjs), sobre o motor `ds-token-diff.mjs`) compara git × espelho e **alerta** quando separam. **Advisory primeiro**; promoção a `required` só se/quando a política [ADR 0314](0314-poda-gates-onda-2-lei-fusoes.md) permitir (required = só Tier-0).

5. **Fundações continuam Tier 0.** Mudança de token de canvas/sidebar/primary/status (camada Fundações — Constituição UI v2 · [UI-0013](../../requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)) exige **[W] aprovando o SCREENSHOT buildado** antes de merge/deploy (R1/R2/R10). O loop **não** afrouxa esse gate — só garante que git e espelho não divirjam no escuro.

## Consequências

**Positivas**
- Acaba o drift silencioso: git e espelho têm caminho determinístico de reconciliação nos dois sentidos.
- Autoria fluida (Wagner desenha no claude.ai/design) **sem** perder o SSOT (git valida e deploya).
- Espelho ganha carimbo de proveniência (commit-fonte no `README.md`), auditável pelo sentinela.

**Negativas / custo**
- O espelho **não** se atualiza sozinho: exige rodar os runbooks (opt-in `/design-sync` [W]). Trabalho humano deliberado — por design (Fundações Tier 0).
- O sentinela no CI do GH Actions **não** chama `DesignSync` (sem login claude.ai lá); compara contra um **snapshot commitado** + baseline; o diff contra o espelho **vivo** roda local/cron.

**Neutras**
- O DS v6 congelado (`prototipo-ui/cowork/ds-v6`) segue no repo como referência histórica; não é apagado nem promovido.

## Escopo — o que esta proposta NÃO decide

- **D-2 (sidebar preto-fixa, supersede UI-0009/UI-0014)** e **D-3 (valores dark reconciliados)** da proposta-irmã `2026-07-08-ds-direcao` são **UI-ADR/PR à parte** — não entram aqui.

## Delta no espelho (parte 2 do P4 — aguarda opt-in `/design-sync` de [W])
Atualizar o header do `README.md`/`colors_and_type.css` do espelho pra refletir "projeto vivo, git SSOT" + o commit-fonte atual (hoje stale `5390c5a2cd8f`). Escrita no espelho via `DesignSync` (incremental) — **só depois do opt-in explícito de [W]** (hooks `block-(skill-)design-sync-without-optin`).

---

**Rodapé de evolução**
- 2026-07-09 — [CL] rascunho da transição (P4). Consolida a emenda 0315 (D-1) num ADR único de loop. Aguardando [W] numerar/aceitar + opt-in pro delta do espelho.
