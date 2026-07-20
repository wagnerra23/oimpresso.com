# Hooks Manifest — GERADO (não editar à mão)

> ⚙️ **Auto-gerado** por `scripts/governance/hooks-manifest-generate.mjs` — fontes: `.claude/settings.json` (wiring) + `.claude/hooks/*` (arquivos) + `governance/required-checks-baseline.json` (gates CI).
> Regenerar: `node scripts/governance/hooks-manifest-generate.mjs --write` · drift acusado por `--check`.
>
> **Como ler as colunas computadas** (nada aqui é declarado à mão):
> - **Sinal de bloqueio** = heurística estática sobre o conteúdo do arquivo na geração (`deny` quoted · `exit-2`/`return 2`) — critério da [ADR 0224](../../memory/decisions/0224-hooks-block-vs-advisory-claude-4.8-aware.md) ("mecanismo real, não o nome"). É CAPACIDADE detectada no código (deny condicional/strict conta); ausência de sinal ≠ classificação advisory, e presença ≠ afirmação de runtime.
> - **Ponto-de-corte** = derivado de evento+matcher (hooks) ou da presença no baseline (gates CI → merge).
> - O dono de "o que é required no merge" é `governance/required-checks-baseline.json` (vigiado por `protection-drift.mjs`) — a seção de gates abaixo é CÓPIA GERADA dele, re-derivada a cada `--write` e conferida pelo `--check`.

## Resumo
- **46** wirings em `settings.json` (5 eventos) · **41** arquivos de hook distintos wired
- **43** arquivos de hook no disco (+21 `*.test.*` — testes, fora da conta de órfãos)
- Órfãos (arquivo sem wiring): **2** · Fantasmas (wiring sem arquivo): **0**
- Gates CI no baseline: **31** classic + **1** ruleset → ponto-de-corte merge

## Hooks wired (evento × matcher × arquivo)
| Evento | Matcher | Hook | Runtime | Ponto-de-corte | Sinal de bloqueio (heurística) |
|---|---|---|---|---|---|
| SessionStart | `*` | brief-fetch-curl.ps1 | powershell | sessão (início — injeção de contexto) | — |
| SessionStart | `*` | (inline no settings.json) | powershell | sessão (início — injeção de contexto) | — |
| SessionStart | `*` | check-skills-fresh.ps1 | powershell | sessão (início — injeção de contexto) | — |
| SessionStart | `*` | tier-a-banner.ps1 | powershell | sessão (início — injeção de contexto) | — |
| SessionStart | `*` | loop-fechar-check.ps1 | powershell | sessão (início — injeção de contexto) | — |
| SessionStart | `*` | licoes-code-two-strikes.ps1 | powershell | sessão (início — injeção de contexto) | — |
| SessionStart | `*` | git-base-freshness-guard.mjs | node | sessão (início — injeção de contexto) | — |
| PreToolUse | `Skill/DesignSync/design-login` | diag-pretooluse-trace.mjs | node | ferramenta (pré-uso do matcher) | — |
| PreToolUse | `AskUserQuestion` | block-askq-execution-menu.mjs | node | ferramenta (pré-uso do matcher) | exit-2 |
| PreToolUse | `Read/Glob/Grep` | mcp-first-warning.ps1 | powershell | leitura (pré-Read/Glob/Grep) | — |
| PreToolUse | `Read/Glob/Grep` | block-ancora-no-olho.mjs | node | leitura (pré-Read/Glob/Grep) | exit-2 |
| PreToolUse | `Write/Edit/MultiEdit` | block-automem.mjs | node | geração (pré-Write/Edit) | exit-2 |
| PreToolUse | `Write/Edit/MultiEdit` | block-brl-values-in-memory.mjs | node | geração (pré-Write/Edit) | exit-2 |
| PreToolUse | `Write/Edit/MultiEdit` | block-memory-drift.mjs | node | geração (pré-Write/Edit) | exit-2 |
| PreToolUse | `Write/Edit/MultiEdit` | block-mwart-violation.mjs | node | geração (pré-Write/Edit) | exit-2 |
| PreToolUse | `Write/Edit/MultiEdit` | charter-validate.ps1 | powershell | geração (pré-Write/Edit) | deny |
| PreToolUse | `Write/Edit/MultiEdit` | modulo-preflight-warning.ps1 | powershell | geração (pré-Write/Edit) | — |
| PreToolUse | `Write/Edit/MultiEdit` | preflight-new-capability.ps1 | powershell | geração (pré-Write/Edit) | — |
| PreToolUse | `Write/Edit/MultiEdit` | block-bom-encoding.ps1 | powershell | geração (pré-Write/Edit) | deny |
| PreToolUse | `Write/Edit/MultiEdit` | block-merge-markers.mjs | node | geração (pré-Write/Edit) | exit-2 |
| PreToolUse | `Write/Edit/MultiEdit` | block-routes-string-legacy.mjs | node | geração (pré-Write/Edit) | exit-2 |
| PreToolUse | `Write/Edit/MultiEdit` | nudge-test-contract-anchor.ps1 | powershell | geração (pré-Write/Edit) | — |
| PreToolUse | `Write/Edit/MultiEdit` | warn-red-first.ps1 | powershell | geração (pré-Write/Edit) | — |
| PreToolUse | `Write/Edit/MultiEdit` | block-test-without-red.ps1 | powershell | geração (pré-Write/Edit) | exit-2 |
| PreToolUse | `Bash` | block-destructive.mjs | node | comando (pré-shell — git commit/push trafegam aqui) | exit-2 |
| PreToolUse | `Bash` | pii-redactor.mjs | node | comando (pré-shell — git commit/push trafegam aqui) | exit-2 |
| PreToolUse | `Bash` | commit-discipline-check.ps1 | powershell | comando (pré-shell — git commit/push trafegam aqui) | — |
| PreToolUse | `Bash` | block-claim-without-evidence.mjs | node | comando (pré-shell — git commit/push trafegam aqui) | — |
| PreToolUse | `Bash` | post-merge-ui-smoke-required.mjs | node | comando (pré-shell — git commit/push trafegam aqui) | exit-2 |
| PreToolUse | `Bash` | block-serving-branch-switch.ps1 | powershell | comando (pré-shell — git commit/push trafegam aqui) | exit-2 |
| PreToolUse | `mcp__computer-use__screenshot/mcp__[Cc]laude[-_][Ii]n[-_][C…` | post-merge-ui-smoke-required.mjs | node | ferramenta (pré-uso do matcher) | exit-2 |
| PreToolUse | `mcp__.*figma.*/mcp__.*__(use_figma/get_design_context/get_f…` | block-figma-without-optin.mjs | node | ferramenta (pré-uso do matcher) | exit-2 |
| PreToolUse | `DesignSync` | block-design-sync-without-optin.mjs | node | ferramenta (pré-uso do matcher) | exit-2 |
| PreToolUse | `Skill` | block-skill-design-sync-without-optin.mjs | node | ferramenta (pré-uso do matcher) | exit-2 |
| PreToolUse | `Bash/PowerShell` | block-test-fora-ct100.mjs | node | comando (pré-shell — git commit/push trafegam aqui) | exit-2 |
| PostToolUse | `Bash` | post-merge-ui-smoke-required.mjs | node | pós-ação (observa, não corta) | exit-2 |
| PostToolUse | `Write/Edit` | audit-creates-tasks.mjs | node | pós-ação (observa, não corta) | — |
| Stop | `*` | memory-pending.ps1 | powershell | fim de turno | — |
| Stop | `*` | nudge-recommend-not-menu.ps1 | powershell | fim de turno | — |
| Stop | `*` | nudge-diagnosis-without-evidence.ps1 | powershell | fim de turno | — |
| UserPromptSubmit | `*` | force-r12-closing-signal.mjs | node | prompt (pré-turno) | — |
| UserPromptSubmit | `*` | design-handoff-reprocess.mjs | node | prompt (pré-turno) | — |
| UserPromptSubmit | `*` | block-figma-without-optin.mjs | node | prompt (pré-turno) | exit-2 |
| UserPromptSubmit | `*` | block-design-sync-without-optin.mjs | node | prompt (pré-turno) | exit-2 |
| UserPromptSubmit | `*` | design-compare-protocol.mjs | node | prompt (pré-turno) | — |
| UserPromptSubmit | `*` | design-agente-ativa.mjs | node | prompt (pré-turno) | — |

## Fantasmas (wiring sem arquivo no disco)
Nenhum.

## Órfãos (arquivo de hook sem wiring em settings.json)
- ⚠️ `charter-validate.sh` — gêmeo cross-platform de charter-validate.ps1 (wired)
- ⚠️ `test-all-hooks-smoke.ps1` — sem wiring em settings.json

## Gates CI (`required-checks-baseline.json` → ponto-de-corte merge)
Contexts `classic_protection` (31):
- ADR (memory/decisions/*.md)
- ADR 0216 PR scan (governance:audit --diff-only)
- ADR frontmatter
- Ancora de design nao-shell (F2/F6 required)
- Append-only canon (ADRs, handoffs, Constituição)
- Casos-coverage · ratchet (trio + rastreabilidade)
- Charter (resources/js/Pages/**/*.charter.md)
- DS gate
- Dominio-dict · ratchet (enum ⇔ dicionário)
- ESLint · ratchet vs baseline
- Frontend / Vite build
- Layout primitives · ratchet
- No hardcode business_id (Tier 0)
- No-mock-in-prod · ratchet
- PHP / Pest (Financeiro · MySQL)
- PHP / Pest (NfeBrasil · MySQL)
- PHP / Pest (Unit)
- PHPStan / Larastan · ratchet vs baseline
- PII scan (CPF/CNPJ literal)
- SDD scorecard ratchet (métrica armada não regride · GT-G3)
- SPEC (memory/requisitos/*/SPEC.md)
- Secret scan (gitleaks · só linhas novas do PR)
- Stylelint · ratchet vs baseline
- Tier-0 guards (WithoutGlobalScopes + BusinessId)
- anchor entry/covers gate
- anchor-lint ADR 0273
- charter status:live precisa de sinal de prod
- doneness-lint ADR 0302
- gate selftest (as catracas mordem · GT-G6)
- screen-coverage-gate
- visual-regression

Contexts `rulesets` (1):
- Governance Gate (índice + memory-health + meta-teste)
