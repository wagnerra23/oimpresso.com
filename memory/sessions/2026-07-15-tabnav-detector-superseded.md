---
date: "2026-07-15"
hour: "13:00 BRT"
topic: "Migração tab-nav PageHeaderTabs — investigação virou refino do detector; PR fechado como superseded por sessões paralelas"
authors: ["C", "W"]
prs: [4296]
outcomes:
  - "Confirmado: papéis barra-de-abas-de-topo e sub-navegacao-contextual = 0 independentes no main (776fee4474)."
  - "PR #4296 fechado como SUPERSEDED — #4293 (consumo transitivo) + #4294 (SubNav papel próprio) já entregaram o mesmo objetivo."
related_adrs: []
---

## TL;DR

A tarefa era migrar os hand-rolls restantes da "barra de abas de topo" pro `PageHeaderTabs`. Investigando o `origin/main` fresco, **não sobrou migração real**: Fiscal já migrado (#4287), Financeiro/Unificado e SubNav eram **falsos-positivos** do detector sintático. Wagner aprovou refinar o detector em vez de migração fake. Enquanto eu implementava (PR #4296), **duas sessões paralelas** ([CC], mesmo prompt replicado) entregaram o mesmo objetivo por caminho diferente e mergearam: **#4293** (consumo transitivo) + **#4294** (SubNav = papel próprio). Fechei #4296 como **superseded** em vez de forçar um duplicado que reverteria trabalho melhor já no main.

## O que foi investigado

Detector `scripts/governance/component-registry-check.mjs --roles` apontava 2 "independentes":
- **`Financeiro/Unificado/Index.tsx`** — falso-positivo: a barra de topo já é `FinanceiroSubNav` → `PageHeaderTabs`. O detector casava a palavra "subnav" em **comentário de prosa** + um `role="tablist"` de OUTRO papel (as abas do drawer detalhes/IA).
- **`Components/shared/SubNav.tsx`** — papel **distinto**: primitivo de sub-navegação in-content (underline/segmented, controlado `value/onChange`). Único uso vivo = toggle `Calls · Custo` no card da Governança. Não é hand-roll do `PageHeaderTabs`.

## Como o main resolveu (sessões paralelas, não eu)

- **#4293** — `scanRoles` reconhece **consumo TRANSITIVO**: uma tela que renderiza um wrapper (que importa o canon) consome o papel. Mata o falso-positivo do Financeiro/Unificado sem precisar mexer em comentário.
- **#4294** — `SubNav` vira papel próprio `sub-navegacao-contextual` no `ROLE_SIGNATURES` (hard-exclude do papel de topo) + reconciliação do `REGISTRY_DS_COMPONENTES.md` (nova §"Sub-navegação contextual") + errata no rascunho `2026-07-15-tab-nav-canonico`.

## Confirmação (evidência — main 776fee4474)

```
--roles:
  ▸ barra-de-abas-de-topo    → independentes: 0 ✅
  ▸ sub-navegacao-contextual → independentes: 0 ✅
  ▸ combobox                 → 3 (onda NOVA, não-tab-nav)
  ▸ status-badge             → 3 (onda NOVA, não-tab-nav)
self-test (gate duro): ✓ todos os checks passaram
#4293 MERGED · #4294 MERGED
```

Os 6 drift restantes são das ondas **novas** `combobox`/`status-badge` (catalogadas na proposta como ondas futuras), sem relação com a barra de abas de topo. Não é regressão.

## Decisão

Fechei **PR #4296** ([link](https://github.com/wagnerra23/oimpresso.com/pull/4296)) como superseded + deletei a branch. Meu commit `3daee1e5a2` fica no histórico do PR. Minha abordagem (`stripComments` + `allCanons` + role-2 com consumer-match) era competitiva, não complementar — mergear reverteria a lógica transitiva do #4293. O main **deliberadamente** manteve o `matches()` do role-2 conservador ("ampliar é decisão de abrir a onda, não unilateral").

## Hardening opcional catalogado (NÃO feito — sem caso vivo)

`stripComments` no teste de markup blindaria um caso que a lógica transitiva não cobre: uma tela com "subnav"/"topnav" só em **comentário** + um tablist de drawer que **não** consome nenhum wrapper transitivamente. Hoje não existe (main = 0). Vira follow-up de ~5 linhas se aparecer.

## Lição

Antes de abrir PR em trabalho de DS/governança "quente", checar `whats-active`/branches paralelas — Wagner replica o mesmo prompt em 2-3 sessões (ver auto-mem `sessoes-paralelas-mesma-branch`). Aqui custou 1 PR redundante, pego cedo pelo aviso de conflito de merge.
