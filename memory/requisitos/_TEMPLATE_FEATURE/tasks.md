<!--
  TEMPLATE — copie pra memory/requisitos/<Mod>/features/<slug>/tasks.md e cure os {{...}}.
  Formato PARSEÁVEL (feature-lint.mjs): header `### T-NN · <título>`, metadados no blockquote,
  DoD em linha `**DoD:**`. blocked_by forma um grafo ACÍCLICO (lint reprova ciclo/ref quebrada).
-->
---
feature: {{slug-kebab}}
module: {{PascalCase}}
---

# Tasks — {{título curto da feature}}

> **Estado de workflow (todo/doing/done) vive no MCP** (`tasks-create ... parent_plan:"{{slug}}"`,
> ADR 0070) — este arquivo é o **plano versionado**: ordem, dependências e DoD. Por isso NÃO há
> `status:` aqui (ADR 0302 — done-ness se lê da âncora da US no SPEC, nunca de checkbox).
> Executar em ordem topológica de `blocked_by:`. Task atômica = 1 sessão consegue fechar.

### T-01 · {{título imperativo — ex: Criar comando skeleton com --dry-run}}
> blocked_by: — · covers: AC-1 · us: US-{{MOD}}-{{NNN}} · estimate: {{2h}}

{{1-3 linhas de contexto: o que fazer, onde (plug-point do plan.md), o que NÃO fazer.}}

**DoD:** {{prova verificável — comando literal + resultado esperado. Ex: `php artisan x:y --dry-run` lista N linhas e NÃO escreve (count antes = count depois).}}

### T-02 · {{título imperativo}}
> blocked_by: T-01 · covers: AC-2, AC-3 · us: US-{{MOD}}-{{NNN}} · estimate: {{...}}

{{...}}

**DoD:** {{...}}

### T-{{NN-final}} · Fechar o loop — âncora da US + smoke real
> blocked_by: T-{{NN-1}} · covers: {{AC final}} · us: US-{{MOD}}-{{NNN}} · estimate: 30min

Última task de TODA feature: smoke real (R1 — evidência literal, não narração) + atualizar
`**Implementado em:**` da US no SPEC pra `` `path` · verificado@<sha7> (<data>) `` (ADR 0273).

**DoD:** âncora da US viva (`node scripts/governance/anchor-lint.mjs memory/requisitos/{{Mod}}/SPEC.md` → `anchored_ok`) + evidência de smoke colada no PR.
