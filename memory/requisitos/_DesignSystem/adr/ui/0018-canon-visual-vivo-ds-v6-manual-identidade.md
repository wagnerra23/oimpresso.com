---
id: requisitos-design-system-adr-ui-0018-canon-visual-vivo-ds-v6-manual-identidade
slug: UI-0018-canon-visual-vivo-ds-v6-manual-identidade
adr_ui: 18
title: "Canon visual VIVO = DS v6 + primitivos + Manual de Identidade; supersede os snapshots zip-cowork (UI-0010, UI-0012)"
status: aceito
lifecycle: ativo
authority: canonical
decided_by: [W]
decided_at: "2026-06-06"
supersedes:
  - "UI-0010-zip-cowork-2026-04-27-canon-visual"
  - "UI-0012-zip-cowork-2026-05-09-canon-visual"
related_adrs:
  - "UI-0013-constituicao-ui-v2-camadas"
  - "0235-ds-v4-accent-roxo-universal"
  - "0249-ds-v6-naming-amends-0235"
  - "0253-primitivos-layout"
  - "0254-design-identity-grade-deterministico"
next_review: "2026-09-06"
pii: false
---

# ADR UI-0018 · Canon visual VIVO = DS v6 + primitivos + Manual de Identidade (supersede os zip-cowork)

> Origem: grande inspeção dos docs de design 2026-06-06 (Wagner: *"adrs conflitantes devem
> morrer e ser trocadas por novas agora"*). Subordinada à Constituição UI v2 ([UI-0013](0013-constituicao-ui-v2-camadas.md)).

## Contexto

[UI-0010](0010-zip-cowork-2026-04-27-canon-visual.md) (zip 2026-04-27) e
[UI-0012](0012-zip-cowork-2026-05-09-canon-visual.md) (zip 2026-05-09) declararam **"o zip Cowork X é
o canon visual"**. Eram corretos no seu tempo, mas **snapshots datados envelhecem por design** — os
próprios docs admitem isso. Hoje:

- A **fonte da verdade visual** é o **DS v6** (tokens semânticos — [ADR 0235](../../../decisions/0235-ds-v4-accent-roxo-universal.md) cor roxo `oklch(0.55 0.15 295)` + [0249](../../../decisions/0249-ds-v6-naming-amends-0235.md) nome).
- O **layout** é **composição de primitivos** (`@/Components/layout` — [ADR 0253](../../../decisions/0253-primitivos-layout.md)), não cópia de HTML do zip.
- A **identidade** ("como deve parecer") vive no **[Manual de Identidade "Clareza Confiante"](../../MANUAL-IDENTIDADE.md)**.
- O **canon visual é MEDIDO** (grade determinístico — [ADR 0254](../../../decisions/0254-design-identity-grade-deterministico.md)), não congelado num arquivo.

Um zip de abril/maio apontado como "canon" **conflita** com essa verdade viva e induz cópia de
padrão obsoleto (cor azul pré-roxo, HTML colado em vez de primitivo).

## Decisão ([W], 2026-06-06)

1. **O canon visual VIVO** = `DS v6 tokens` (cor/tipo/espaço) + `primitivos de layout` (estrutura) +
   `Manual de Identidade` (voz) + `grade determinístico` (medida). **Não há "zip canon".**
2. **UI-0010 e UI-0012 viram `superseded`** por esta ADR (lifecycle `substituido` + `superseded_by: UI-0018`).
   **Permanecem no lugar como histórico** (append-only — referência do que o Cowork propôs naquele momento),
   mas **NÃO são fonte da verdade visual** nem devem ser copiados.
3. Os zips em `ui_kits/` ficam como **arquivo de referência histórica**, não canon.

## Não-decidido aqui (Tier 0 — decisão própria do [W])
- **Link quebrado em UI-0010** (aponta `ui_kits/cowork-2026-04-27/` que foi renomeada pra
  `_BACKUP-NAO-USAR-cowork-2026-04-27/`). Mover/renomear pasta importada por ADR é append-only Tier 0 —
  fica como correção à parte. Esta ADR só remove 0010 do status de "canon" (atenua o impacto do link morto).
- **UI-0007** (topbar) e **UI-0017** (DS "v3") **NÃO morrem** — a inspeção confirmou: 0007 é scoped
  (válida pro AppShell legado) e 0017 é aditiva (só o nome "v3" é velho → DS v6). Tratadas por pointer, não lápide.

## Consequências
- ✅ Mata a única fonte de conflito real entre os snapshots e o DS v6 vivo.
- ✅ Quem for craftar tela não copia mais HTML/cor de zip datado — usa DS v6 + primitivos + Manual.
- ✅ Append-only respeitado (0010/0012 ficam como histórico com ponteiro).
- 🔜 Frontmatter dos demais docs de design normalizado + INDEX regenerado (follow-up da inspeção).

## Refs
- Inspeção 2026-06-06 (5 auditores paralelos) · [INDEX-DESIGN-MEMORIAS](../../INDEX-DESIGN-MEMORIAS.md) · [MANUAL-IDENTIDADE](../../MANUAL-IDENTIDADE.md)
- Supersede: [UI-0010](0010-zip-cowork-2026-04-27-canon-visual.md) · [UI-0012](0012-zip-cowork-2026-05-09-canon-visual.md)
- Canon vivo: [0235](../../../decisions/0235-ds-v4-accent-roxo-universal.md)/[0249](../../../decisions/0249-ds-v6-naming-amends-0235.md) (DS v6) · [0253](../../../decisions/0253-primitivos-layout.md) (primitivos) · [0254](../../../decisions/0254-design-identity-grade-deterministico.md) (grade)
