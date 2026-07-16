---
date: "2026-07-15"
time: "14:35 BRT"
slug: status-badge-onda-tripe
tldr: "Onda 'status-badge' do padrão componente-por-papel: canon já existia (Badge variants + StatusBadge wrapper), então o tripé virou enforcement — regra ds/no-handrolled-status-pill + assinatura no detector --roles + fidelidade. PR #4298 MERGED, #4310 (fila US-038) MERGED. Advisory (DS ≠ Tier-0)."
prs: [4298, 4310]
decided_by: [W]
related_adrs: [0338-ds-lint-eixo-valor-token-fecha-por-forma, 0209-eslint-9-flat-config]
next_steps: ["Migrar os 3 independentes de status-badge (US-_DESIGNSYSTEM-038): VehicleStatusBadge, ServiceOrderStatusBadge, Pills.tsx — 1 PR/tela com gate visual"]
---

# Onda status-badge — tripé componente-por-papel

## TL;DR
Onda `status-badge` (padrão componente-por-papel): o canon **já existia** (`<Badge variant>` tokenizado + `<StatusBadge kind value>`), então o tripé virou **enforcement** — regra `ds/no-handrolled-status-pill` (ratchet 0209, delta 0) + assinatura `status-badge` no detector `--roles` (`canonImport` vira lista) + fidelidade `statusBadgeFidelity.spec.tsx`. **PR #4298 + #4310 MERGED.** Advisory (DS ≠ Tier-0). Fila dos 3 independentes em **US-_DESIGNSYSTEM-038**.

## Estado MCP no momento do fechamento
- **Cycle:** nenhum ativo em COPI (`cycles-active` → vazio).
- **my-work (@wagner):** 30 tasks (9 review, 8 blocked, 13 todo). US-_DESIGNSYSTEM-038 (criada esta sessão) entra via webhook agora que #4310 mergeou.
- **origin/main:** `71ca32d981` (era `618e3846a5` no início — +7 commits durante a sessão, incl. as ondas irmãs #4293/#4294/#4295/#4304).

## O que aconteceu
Abri a **Onda do papel "status-badge"** catalogada na proposta `2026-07-15-tab-nav-canonico-e-componente-por-papel` (§Consequências). O detector `--roles` sinalizava ~11 hand-rolls do pill de status.

**Achado que mudou o tripé:** o papel **já estava no DS**, em 2 camadas — o primitivo `<Badge variant="success|warning|danger|info|neutral">` (tokenizado `-soft/-fg`, dark-aware) + o wrapper de domínio `<StatusBadge kind value>` (`@/Components/shared`, ~16 domínios). Logo a peça (a) do tripé virou **consumir**, não criar. Li os hand-rolls antes de decidir (não inventei canon).

Entregue como **enforcement** (advisory, DS ≠ Tier-0 · ADR 0271/0314):
1. **Regra** `ds/no-handrolled-status-pill` (component-substitute, tipo 2 do ADR 0338): className com `rounded-full` + `px-` + token de status no mesmo literal. Ratchet 0209, baseline delta 0 (9 hits legados absorvidos, 0 falso-positivo — icon-circle/chip-de-categoria excluídos).
2. **Detector** — assinatura `status-badge` em `ROLE_SIGNATURES`; `canonImport` virou **lista** (Badge OU StatusBadge). Cluster: canon + 1 consumidor + **3 independentes** a migrar. Self-test estendido.
3. **Fidelidade** — `tests/statusBadgeFidelity.spec.tsx` (9 testes): `rounded-full` (= `.cli-status-pill` 999px) + par `-soft/-fg` por variante + controle-negativo de cor-crua (ADR 0258). Gate path-scoped advisory.

`FiscalStatusBadge`/`NfceStatusBadge` ficaram **fora** (canon fiscal próprio, R-DS-002/ADR 0235).

## Artefatos gerados
- **PR #4298** (MERGED `1bd0037b1d`): 10 arquivos, tripé completo (eslint.config.js, component-registry-check.mjs + .test.mjs, REGISTRY_DS_COMPONENTES.md, REGRAS_DS_LINT.md, gates-registry.json, status-badge-fidelity-gate.yml, statusBadgeFidelity.spec.tsx, baseline, proposal). CI 83 pass/0 fail.
- **PR #4310** (MERGED `71ca32d981`): US-_DESIGNSYSTEM-038 no SPEC.md (fila de migração dos 3 independentes). Docs-only.

## Persistência
- **git:** ambos PRs em `origin/main`. Handoff + índice neste PR.
- **MCP:** US-038 propaga via webhook (~2min); gates ativos em CI.
- **BRIEFING:** N/A (mudança de governança/DS, não de módulo vertical).

## Próximos passos pra retomar
`tasks-detail US-_DESIGNSYSTEM-038` → puxar 1 PR/tela (começar por `VehicleStatusBadge`, que só duplica `kind:"vehicle"` do StatusBadge). Detector: `node scripts/governance/component-registry-check.mjs --roles`.

## Lições catalogadas
- **Ondas paralelas na mesma linhagem colidem em git, não em conteúdo.** A sessão irmã `combobox` (#4295) tocava os MESMOS arquivos DS (ROLE_SIGNATURES, eslint.config.js, baseline, proposal, REGISTRY, REGRAS). Rebaseei 2× sobre origin/main mantendo AS DUAS ondas lado a lado. Regra prática: quem mergeia por segundo rebaseia + regen baseline; avisar no PR desde cedo.
- **O hook `post-merge-ui-smoke-required` varre o COMANDO por palavras de declaração** (`pronto`/`deployed`/`mergeado`) durante a janela pós-merge-UI de outra PR. Uma PR docs-only tropeçou por "Pronto quando"/"mergeado" incidentais no body + um merge UI concorrente (#4304). Fix honesto: reescrever sem as palavras (não estava declarando smoke de nada) — NÃO usei o override Tier-0.
- **Detector de papel: nome > markup** quando o markup é genérico. Pill (`rounded+px+cor`) casa centenas de usos inline → o eixo inline fica com a regra ds/* + ratchet; o detector rastreia COMPONENTES nomeados (fronteira honesta, report-only).

## Pointers detalhados
- Proposta: `memory/decisions/proposals/2026-07-15-tab-nav-canonico-e-componente-por-papel.md` §Consequências (ondas do detector — estado).
- Canon do papel: `prototipo-ui/REGISTRY_DS_COMPONENTES.md` §"Pílula de status".
- Regra: `prototipo-ui/REGRAS_DS_LINT.md` (`ds/no-handrolled-status-pill`).
