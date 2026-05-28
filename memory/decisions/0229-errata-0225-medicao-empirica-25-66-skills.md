---
adr: 0229
title: Errata 0225 — medição empírica 25/66 skills eager (corrige diagnóstico "8→5" estimate-based)
status: accepted
date: 2026-05-28
deciders: [Wagner]
amends: [0225-skills-tier-a-recalibracao-claude-4.8.md]
references:
  - 0095-skills-tiers-convencao-interna.md
  - 0168-protocolo-wagner-sempre-tier-A-irrevogavel.md
  - 0224-hooks-block-vs-advisory-claude-4.8-aware.md
  - 0225-skills-tier-a-recalibracao-claude-4.8.md
lifecycle: active
---

## Contexto

A [ADR 0225](0225-skills-tier-a-recalibracao-claude-4.8.md) (accepted, canon) enquadrou a recalibração como **"8 always-on → 5 núcleo + 7 auto-trigger"**. Esse "8" era **estimate-based** — usei o banner `tier-a-banner.ps1` (que declarava 8 skills) como proxy do drift real.

Uma sessão paralela de 2026-05-28 (branch `frosty-greider-83ab2f`, PR #1891) abriu uma proposta concorrente da 0225 com um diagnóstico **measurement-based** mais rigoroso. O #1891 foi fechado (a 0225 do #1892 venceu como canon), mas **a medição empírica que ele continha não foi capturada em lugar nenhum canon** — vivia só no corpo do PR fechado e no handoff de sessão.

Esta errata existe pra **preservar o número real** (recomendação (b) do handoff `2026-05-28-handoff-governance-framework-PR2-reavaliacao-4.8.md`): mantém a 0225 como canon e emenda-a com a medição empírica, porque **o número importa pro time MCP** (Felipe/Maiara/Eliana/Luiz) dimensionar o drift de atenção que afeta TODA sessão de TODO dev.

## Decisão (errata)

A 0225 **continua canon**. Acrescenta-se a ela a seguinte correção de diagnóstico:

### O drift era 3× pior que o estimado

```
grep "tier: A" | "always-on" | "BLOQUEADOR" em .claude/skills/*/SKILL.md (2026-05-28)
→ 25 de 66 skills (38%) auto-marcadas como crítica/eager
```

| Métrica | 0225 canon (estimate) | Errata (measurement) |
|---|---|---|
| Skills eager/BLOQUEADOR | 8 (banner) | **25 de 66 (38%)** |
| Drift vs baseline saudável | "alguns a mais" | **~3× o alvo (~2-3 Tier 0)** |
| Base do número | banner como proxy | grep empírico no FS |

**Quando 25 skills gritam "BLOQUEADOR/always-on", a atenção do Claude 4.8 não prioriza nenhuma** — é o anti-padrão de atenção: muito eager dilui. Prova decisiva da mesma data: o R12 (fechamento de sessão) era Tier A always-on e **mesmo assim falhou** numa sessão longa (200+ turnos) — carregou no boot, diluiu no meio, perdeu no fim. Só voltou a funcionar migrado pra **hook UserPromptSubmit (ativação lazy no momento exato)**. Ver [ADR 0224](0224-hooks-block-vs-advisory-claude-4.8-aware.md).

### O que NÃO muda

- **O veredito final da 0225 permanece:** núcleo Tier A = 5 skills (`multi-tenant-patterns`, `commit-discipline`, `incident-done-checklist`, `memory-first-secret-search`, `hostinger-dns-autonomy`) + 7 rebaixadas pra auto-trigger. Os 3 extras sobre o "~2-3" do #1891 são **Wagner-instituídos por falha concreta** (memory-first-secret e hostinger-dns em 2026-05-28; incident-done = DoD smoke real) — não muletas de modelo, logo ficam.
- O princípio canônico da 0225 segue valendo: *"se cabe em hook determinístico OU auto-trigger por description/path, NÃO é Tier A always-on"*.

A errata só **corrige o tamanho do problema** (25, não 8) e ancora o princípio em evidência medida, não estimada.

## Consequências

- **Time MCP enxerga o drift real (38%)** ao ler 0225+0229 juntas — dimensiona o esforço de manutenção das 3 fontes (CLAUDE.md + banner + skills-audit) com o número certo.
- `review_triggers` da 0225 ("skills Tier A voltam a passar de ~5") ganha baseline empírico de comparação.
- PR #1891 fica formalmente **superseded por #1892 (canon) + harvest nesta errata** — nada de valor perdido.
- Append-only respeitado: 0225 intacta; esta emenda referencia via `amends`.

## Referências

- [ADR 0225](0225-skills-tier-a-recalibracao-claude-4.8.md) — recalibração skills Tier A (canon que esta errata emenda)
- [ADR 0224](0224-hooks-block-vs-advisory-claude-4.8-aware.md) — hooks block vs advisory (evidência R12)
- Handoff `memory/sessions/2026-05-28-handoff-governance-framework-PR2-reavaliacao-4.8.md` §"2 pendências" item 1 — recomendação (b)
- PR #1891 (closed) — proposta measurement-based concorrente, origem da medição 25/66
- Inventário empírico 2026-05-28: 25/66 skills Tier A/BLOQUEADOR (sessão `frosty-greider-83ab2f`)
